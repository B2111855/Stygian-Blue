<?php
/**
 * FILE: lienhe.php
 * MỤC ĐÍCH: Trang đặt lịch hẹn dịch vụ/gói - View chính cho booking flow
 * 
 * CHỨC NĂNG CHÍNH:
 * - Hiển thị form đặt lịch (dịch vụ lẻ hoặc gói)
 * - Tích hợp bản đồ (Google Maps/Leaflet) để chọn địa điểm
 * - Kiểm tra khung giờ trống và tính khả dụng
 * - Tính toán báo giá dự kiến
 * - Validate và submit booking
 * 
 * DEPENDENCIES:
 * - check_availability.php: Kiểm tra slot trống, nhân viên rảnh
 * - get_booked_slots.php: Lấy danh sách giờ đã đặt
 * - get_package_detail.php: Chi tiết gói dịch vụ
 * - get_package_costumes.php: Danh sách trang phục
 */

include '../../../database/config.php';
// Load dotenv so GOOGLE_MAPS_API_KEY / GOOGLE_API_KEY from .env is available here
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
  require_once __DIR__ . '/../../../vendor/autoload.php';
  if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../..');
    $dotenv->safeLoad();
  }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * LOAD THÔNG TIN USER TỪ SESSION
 * Nếu đã đăng nhập, lấy ID_TK từ session để pre-fill form
 */
$idTk = null;
if (isset($_SESSION['user']['ID_TK'])) {
    $idTk = $_SESSION['user']['ID_TK'];
} elseif (isset($_SESSION['ID_TK'])) {
    $idTk = $_SESSION['ID_TK'];
}

// Thông tin cá nhân để pre-fill vào form
$hoTen = '';
$email = '';
$sdt   = '';

if ($idTk) {
    $stmt = $conn->prepare('SELECT HO_TEN, EMAIL, SDT FROM TAI_KHOAN WHERE ID_TK = ?');
    if ($stmt) {
        $stmt->bind_param('s', $idTk);
        if ($stmt->execute()) {
            $stmt->bind_result($hoTen, $email, $sdt);
            $stmt->fetch();
        }
        $stmt->close();
    }
}

/**
 * LOAD DANH SÁCH CHI NHÁNH
 * Lấy tất cả chi nhánh kèm tọa độ GPS để hiển thị trên bản đồ
 */
$branches = [];
$stmtBranches = $conn->prepare('SELECT ID_CN, TEN_CN, LATITUDE, LONGITUDE FROM CHI_NHANH ORDER BY TEN_CN ASC');
if ($stmtBranches) {
    $stmtBranches->execute();
    $resultBranches = $stmtBranches->get_result();
    while ($row = $resultBranches->fetch_assoc()) {
        $branches[] = $row;
    }
    $stmtBranches->close();
}

$defaultBranchLat = null;
$defaultBranchLng = null;
foreach ($branches as $branch) {
  if (!empty($branch['LATITUDE']) && !empty($branch['LONGITUDE'])) {
    $defaultBranchLat = $branch['LATITUDE'];
    $defaultBranchLng = $branch['LONGITUDE'];
    break;
  }
}
if ($defaultBranchLat === null) {
  $defaultBranchLat = 10.7768890;
}
if ($defaultBranchLng === null) {
  $defaultBranchLng = 106.7008060;
}

$services = [];
$stmtServices = $conn->prepare('SELECT ID_DV, TEN_DV FROM DICH_VU ORDER BY TEN_DV ASC');
if ($stmtServices) {
$stmtServices->execute();
    $resultServices = $stmtServices->get_result();
    while ($row = $resultServices->fetch_assoc()) {
        $services[] = $row;
    }
    $stmtServices->close();
}

$packages = [];
/**
 * LOAD GÓI DỊCH VỤ VỚI GIÁ KHUYẾN MÃI
 * - Lấy giá gốc từ view v_goi_dich_vu_tong_tien
 * - Lấy giá sau giảm từ view v_goi_dich_vu_gia_khuyen_mai
 * - Chỉ lấy gói có TRANG_THAI = 'ban'
 */
$stmtPackages = $conn->prepare(
  "SELECT 
    g.ID_GOI,
    g.TEN_GOI,
    g.MO_TA,
    vt.TONG_GIA_GOI AS GIA_GOC,
    km.GIA_SAU_GIAM,
    km.SO_TIEN_GIAM,
    km.TEN_CHUONG_TRINH,
    (SELECT SUM(d.THOI_GIAN) 
     FROM goi_dich_vu_chi_tiet gdt 
     JOIN dich_vu d ON d.ID_DV = gdt.ID_DV 
     WHERE gdt.ID_GOI = g.ID_GOI) AS THOI_LUONG_PHUT
   FROM goi_dich_vu g
   LEFT JOIN v_goi_dich_vu_tong_tien vt ON vt.ID_GOI = g.ID_GOI
   LEFT JOIN v_goi_dich_vu_gia_khuyen_mai km ON km.ID_GOI = g.ID_GOI
   WHERE g.TRANG_THAI = 'ban'
   ORDER BY g.TEN_GOI ASC"
);
if ($stmtPackages) {
  $stmtPackages->execute();
  $resultPackages = $stmtPackages->get_result();
  while ($row = $resultPackages->fetch_assoc()) {
    $base = isset($row['GIA_GOC']) ? (int)$row['GIA_GOC'] : 0;
    $priceAfter = isset($row['GIA_SAU_GIAM']) && $row['GIA_SAU_GIAM'] !== null ? (int)$row['GIA_SAU_GIAM'] : $base;
    $discount = max(0, $base - $priceAfter);
    $row['GIA_HIEN_THI'] = $priceAfter;
    $row['SO_TIEN_GIAM_TINH'] = $discount;
    $packages[] = $row;
  }
  $stmtPackages->close();
}

/**
 * XỬ LÝ URL PARAMETERS ĐỂ PRE-FILL FORM
 * Hỗ trợ deep linking từ các trang khác:
 * - ?id_dv=X hoặc ?dv=X: Pre-select dịch vụ lẻ
 * - ?id_goi=X: Pre-select gói dịch vụ
 * - ?branch_id=X: Pre-select chi nhánh
 * - ?booking_type=service|package: Chọn loại đặt lịch
 */
$preSelectedBookingType = 'service'; // mặc định
$preSelectedServiceId = null;
$preSelectedPackageId = null;
$preSelectedBranchId = null;

// Nếu truyền id_dv (ưu tiên) hoặc dv (alias cũ) từ các trang dịch vụ
if (isset($_GET['id_dv'])) {
  $preSelectedServiceId = intval($_GET['id_dv']);
  $preSelectedBookingType = 'service';
} elseif (isset($_GET['dv'])) {
  $preSelectedServiceId = intval($_GET['dv']);
  $preSelectedBookingType = 'service';
}

// Nếu truyền id_goi từ goi_chitiet.php
if (isset($_GET['id_goi'])) {
    $preSelectedPackageId = intval($_GET['id_goi']);
    $preSelectedBookingType = 'package';
}

// Nếu truyền branch_id
if (isset($_GET['branch_id'])) {
    $preSelectedBranchId = intval($_GET['branch_id']);
}

// Nếu truyền booking_type trực tiếp
if (isset($_GET['booking_type']) && in_array($_GET['booking_type'], ['service', 'package'])) {
    $preSelectedBookingType = $_GET['booking_type'];
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Unified key resolution order for Maps: prefer specific, then unified
$googleMapsApiKey = getenv('GOOGLE_MAPS_API_KEY') ?: getenv('GOOGLE_API_KEY');
if (!$googleMapsApiKey && isset($_ENV['GOOGLE_MAPS_API_KEY'])) {
  $googleMapsApiKey = $_ENV['GOOGLE_MAPS_API_KEY'];
}
if (!$googleMapsApiKey && isset($_ENV['GOOGLE_API_KEY'])) {
  $googleMapsApiKey = $_ENV['GOOGLE_API_KEY'];
}
if (!$googleMapsApiKey && defined('GOOGLE_MAPS_API_KEY')) {
  $googleMapsApiKey = constant('GOOGLE_MAPS_API_KEY');
}
if (!$googleMapsApiKey && defined('GOOGLE_API_KEY')) {
  $googleMapsApiKey = constant('GOOGLE_API_KEY');
}
// Env name
$appEnv = getenv('APP_ENV') ?: (isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'production');

/**
 * CẤU HÌNH MAP PROVIDER (Google Maps hoặc Leaflet)
 * Logic ưu tiên:
 * 1. Nếu có GOOGLE_MAPS_API_KEY hợp lệ + MAP_PROVIDER=google → dùng Google Maps
 * 2. Nếu MAP_FORCE_LEAFLET=true → bắt buộc dùng Leaflet
 * 3. Mặc định: Leaflet (tránh watermark "For development purposes only" của Google)
 */
$hasGoogleKey = !empty($googleMapsApiKey);
$mapProviderEnv = getenv('MAP_PROVIDER') ?: (isset($_ENV['MAP_PROVIDER']) ? $_ENV['MAP_PROVIDER'] : '');
$forceLeafletEnv = getenv('MAP_FORCE_LEAFLET') ?: (isset($_ENV['MAP_FORCE_LEAFLET']) ? $_ENV['MAP_FORCE_LEAFLET'] : '');
$preferGoogle = ($hasGoogleKey && (strtolower($mapProviderEnv) === 'google') && strtolower((string)$forceLeafletEnv) !== 'true');
$mapMode = $preferGoogle ? 'google' : 'leaflet';
$branchMapNoteText = $preferGoogle
  ? 'Đang dùng Google Maps.'
  : 'Đang dùng OpenStreetMap (Leaflet).';
?>
<section data-booking data-app-env="<?= htmlspecialchars($appEnv) ?>" data-prefer-google="<?= $preferGoogle ? 'true' : 'false' ?>" class="space-y-6">
  <div class="grid gap-6 xl:grid-cols-5">
    <div class="xl:col-span-2" id="mapColumn">
      <div
        class="flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl"
        data-map-mode="<?= htmlspecialchars($mapMode) ?>"
        data-google-enabled="<?= $hasGoogleKey ? 'true' : 'false' ?>"
        data-default-lat="<?= htmlspecialchars($defaultBranchLat ?? 10.776889) ?>"
        data-default-lng="<?= htmlspecialchars($defaultBranchLng ?? 106.700806) ?>"
      >
        <div class="border-b border-slate-100 bg-white/80 p-4 backdrop-blur">
          <label for="mapSearchInput" class="text-sm font-semibold text-slate-800">Tìm kiếm địa điểm</label>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <div class="flex-1">
              <input
                type="text"
                id="mapSearchInput"
                placeholder="Nhập địa điểm, địa chỉ hoặc tên chi nhánh..."
                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200"
              >
            </div>
            <button
              type="button"
              id="mapSearchBtn"
              class="inline-flex items-center justify-center rounded-2xl bg-sky-500 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sky-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300"
            >Tìm</button>
          </div>
          <p id="mapSearchStatus" class="mt-2 text-xs text-slate-500" aria-live="polite"></p>
        </div>
        <div
          id="branchMap"
          class="min-h-[256px] w-full flex-1"
          aria-label="Bản đồ chi nhánh và chọn địa điểm hẹn"
        ></div>
        <p id="branchMapNote" class="px-4 pb-4 text-center text-xs text-slate-500"><?= htmlspecialchars($branchMapNoteText) ?></p>
      </div>
    </div>

    <div class="xl:col-span-3">
      <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-xl backdrop-blur sm:p-8">
        <header class="text-center">
          <p class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-600">Điền thông tin của bạn</p>
          <h1 class="mt-2 text-2xl font-bold text-slate-900">Đặt lịch hẹn</h1>
          <p class="mt-3 text-sm text-slate-600">Chọn chi nhánh, khung giờ và loại dịch vụ để chúng tôi chuẩn bị tốt nhất cho buổi làm việc.</p>
        </header>

        <form action="../controller/process_schedule.php" method="POST" id="scheduleForm" class="mt-6 space-y-6" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

          <div class="space-y-2">
            <label for="branch" class="text-sm font-semibold text-slate-800">Chi nhánh</label>
            <select id="branch" name="branch_id" required class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
              <option value="">Chọn chi nhánh...</option>
              <?php foreach ($branches as $branch): ?>
                <option
                  value="<?= $branch['ID_CN'] ?>"
                  data-lat="<?= isset($branch['LATITUDE']) ? htmlspecialchars($branch['LATITUDE']) : '' ?>"
                  data-lng="<?= isset($branch['LONGITUDE']) ? htmlspecialchars($branch['LONGITUDE']) : '' ?>"
                  <?php echo ($preSelectedBranchId === (int)$branch['ID_CN']) ? 'selected' : ''; ?>
                ><?= htmlspecialchars($branch['TEN_CN']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-500">Lịch trống sẽ hiển thị theo chi nhánh đã chọn.</p>
          </div>

          <fieldset class="space-y-3">
            <legend class="text-sm font-semibold text-slate-800">Địa điểm hẹn</legend>
            <div class="flex flex-wrap gap-3" role="radiogroup" aria-label="Chọn địa điểm hẹn">
              <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                <input type="radio" name="location_type" value="branch" checked class="h-4 w-4 border-slate-300 text-sky-500 focus:ring-sky-400">
                <span>Tại chi nhánh</span>
              </label>
              <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                <input type="radio" name="location_type" value="external" class="h-4 w-4 border-slate-300 text-sky-500 focus:ring-sky-400">
                <span>Địa điểm khác ( Có phụ phí ) </span>
              </label>
            </div>

            <div id="externalLocationWrap" class="hidden space-y-3">
              <label for="address" class="text-sm font-semibold text-slate-800">Địa chỉ thực hiện</label>
              <input type="text" id="address" name="address" placeholder="Nhập địa chỉ hoặc chọn trên bản đồ" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">

              <div class="rounded-2xl border border-slate-200 bg-white p-3">
                <p class="text-xs text-slate-500">Sử dụng bản đồ bên trái để chọn vị trí (click) hoặc dùng nút bên dưới.</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                  <button type="button" id="locateBtn" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:border-sky-300 hover:text-sky-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-200">
                    <span aria-hidden="true" class="inline-block h-2 w-2 rounded-full bg-sky-500"></span>
                    Dùng vị trí hiện tại
                  </button>
                  <p id="locateStatus" class="text-xs text-slate-500" aria-live="polite"></p>
                </div>
              </div>

              <input type="hidden" id="ext_lat" name="ext_lat">
              <input type="hidden" id="ext_lng" name="ext_lng">
            </div>
          </fieldset>

          <fieldset class="space-y-3">
            <legend class="text-sm font-semibold text-slate-800">Hình thức đặt lịch</legend>
            <div class="flex flex-wrap gap-3" role="radiogroup" aria-label="Chọn hình thức đặt lịch">
              <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                <input type="radio" name="booking_type" value="service" <?php echo $preSelectedBookingType === 'service' ? 'checked' : ''; ?> class="h-4 w-4 border-slate-300 text-sky-500 focus:ring-sky-400">
                <span>Dịch vụ lẻ</span>
              </label>
              <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                <input type="radio" name="booking_type" value="package" <?php echo $preSelectedBookingType === 'package' ? 'checked' : ''; ?> class="h-4 w-4 border-slate-300 text-sky-500 focus:ring-sky-400">
                <span>Gói dịch vụ</span>
              </label>
            </div>
            <p class="text-xs text-slate-500">Tuỳ nhu cầu: chọn dịch vụ lẻ hoặc gói trọn bộ.</p>
          </fieldset>

          <?php $isPackageMode = ($preSelectedBookingType === 'package'); ?>
          <div class="space-y-2 <?php echo $isPackageMode ? 'hidden' : ''; ?>" data-service-field>
            <label class="text-sm font-semibold text-slate-800">Dịch vụ</label>
            <div class="flex items-center gap-2">
              <input type="text" id="serviceSearch" placeholder="Tìm dịch vụ..." class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
            </div>
            <div id="serviceList" class="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
              <?php foreach ($services as $service): ?>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700" data-service-row data-name="<?= htmlspecialchars($service['TEN_DV']) ?>">
                  <input type="checkbox" name="service_ids[]" value="<?= $service['ID_DV'] ?>" class="h-4 w-4 rounded border-slate-300 text-sky-500 focus:ring-sky-400" <?php echo ($preSelectedServiceId === (int)$service['ID_DV']) ? 'checked' : ''; ?> />
                  <span class="line-clamp-1" title="<?= htmlspecialchars($service['TEN_DV']) ?>"><?= htmlspecialchars($service['TEN_DV']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <p class="text-xs text-slate-500">Chọn một hoặc nhiều dịch vụ. Bạn có thể dùng ô tìm kiếm để lọc.</p>
          </div>

          <div class="space-y-2 <?php echo $isPackageMode ? '' : 'hidden'; ?>" data-package-field>
            <label class="text-sm font-semibold text-slate-800">Gói dịch vụ</label>
            <input type="text" id="packageSearch" placeholder="Tìm gói..." class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
            <div id="packageList" class="grid gap-2 md:grid-cols-2">
              <?php foreach ($packages as $package):
                $id   = (int)$package['ID_GOI'];
                $base = (int)$package['GIA_GOC'];
                $final = (int)$package['GIA_HIEN_THI'];
                $discount = (int)$package['SO_TIEN_GIAM_TINH'];
                $hasDiscount = $discount > 0 && $final < $base;
                $duration = isset($package['THOI_LUONG_PHUT']) ? (int)$package['THOI_LUONG_PHUT'] : 0;
              ?>
              <label class="flex flex-col gap-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm" data-package-row data-name="<?= htmlspecialchars($package['TEN_GOI']) ?>" data-price-base="<?= $base ?>" data-price-final="<?= $final ?>" data-duration="<?= $duration ?>">
                <div class="flex items-start gap-3">
                  <input type="checkbox" name="package_ids[]" value="<?= $id ?>" class="mt-1 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-400" <?php echo ($preSelectedPackageId === $id) ? 'checked' : ''; ?>>
                  <div class="flex-1">
                    <div class="flex items-center justify-between gap-2">
                      <span class="font-semibold text-slate-800 line-clamp-2" title="<?= htmlspecialchars($package['TEN_GOI']) ?>"><?= htmlspecialchars($package['TEN_GOI']) ?></span>
                      <?php if ($hasDiscount): ?>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200">-<?= number_format($discount,0,',','.') ?>đ</span>
                      <?php endif; ?>
                    </div>
                    <div class="text-xs text-slate-500 line-clamp-2" title="<?= htmlspecialchars($package['MO_TA'] ?? '') ?>"><?= htmlspecialchars($package['MO_TA'] ?? '') ?></div>
                    <?php if ($duration > 0): 
                      $hours = floor($duration / 60);
                      $mins = $duration % 60;
                      $durationText = $hours > 0 ? "{$hours}h" . ($mins > 0 ? " {$mins}p" : "") : "{$mins}p";
                    ?>
                    <div class="mt-0.5 text-xs text-sky-600">⏱️ <?= $durationText ?></div>
                    <?php endif; ?>
                    <div class="mt-1 flex items-center gap-2 text-sm">
                      <span class="font-semibold text-sky-600"><?= number_format($final,0,',','.') ?>đ</span>
                      <?php if ($hasDiscount): ?>
                        <span class="text-xs text-slate-400 line-through"><?= number_format($base,0,',','.') ?>đ</span>
                      <?php else: ?>
                        <span class="text-xs text-slate-500">Giá trọn gói</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="package_id" id="primaryPackageId" value="<?= $preSelectedPackageId ? (int)$preSelectedPackageId : '' ?>">
            <p class="text-xs text-slate-500">Có thể chọn nhiều gói cùng lúc. Giá đã bao gồm dịch vụ trong gói và áp dụng khuyến mãi nếu có.</p>
          </div>
          <div class="space-y-2 hidden" data-package-costume-field>
            <!-- Sẽ được render động danh sách trang phục của gói -->
          </div>

          <div class="space-y-2">
            <label for="ngayHen" class="text-sm font-semibold text-slate-800">Ngày</label>
            <input type="text" id="ngayHen" name="ngayHen" required placeholder="YYYY-MM-DD" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
            <p class="text-xs text-slate-500">Chỉ được chọn ngày từ hiện tại trở đi.</p>
          </div>

          <div class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <label class="text-sm font-semibold text-slate-800">Khung giờ</label>
              <span class="text-xs font-medium text-slate-500">Chọn một khung giờ phù hợp</span>
            </div>
            <div id="khungGioContainer" class="grid grid-cols-2 gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Chọn khung giờ"></div>
            <input type="hidden" id="gioHen" name="gioHen" required>
            <p id="slotMsg" class="text-xs text-slate-500"></p>
          </div>

          <div id="chon_thiet_bi_div" class="space-y-3 hidden">
            <label class="text-sm font-semibold text-slate-800">Chọn thiết bị</label>
            <div id="thiet_bi_checkbox_list" class="space-y-2"></div>
          </div>

          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="space-y-2">
              <label class="text-sm font-semibold text-slate-800" for="full_name">Họ tên</label>
              <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($hoTen) ?>" readonly class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            </div>
            <div class="space-y-2">
              <label class="text-sm font-semibold text-slate-800" for="email">Email</label>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" readonly class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            </div>
            <div class="space-y-2">
              <label class="text-sm font-semibold text-slate-800" for="phone">Số điện thoại</label>
              <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($sdt) ?>" readonly class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
              <?php if (empty($sdt)): ?>
                <p id="phoneNotice" class="text-xs text-amber-600">Bạn chưa cập nhật số điện thoại để tiện xác nhận lịch. Truy cập <a href="thongtin.php" class="font-semibold text-sky-600 underline-offset-2 hover:underline">Thông tin cá nhân</a> để bổ sung.</p>
              <?php else: ?>
                <p id="phoneNotice" class="text-xs text-slate-500">Số điện thoại sẽ được dùng khi nhân viên liên hệ xác nhận lịch.</p>
              <?php endif; ?>
            </div>
          </div>

          <div id="quoteBox" class="hidden rounded-2xl border border-sky-200 bg-sky-50/80 p-4">
            <div class="flex items-center justify-between text-sm font-semibold text-slate-800">
              <span>Tạm tính</span>
              <strong id="quoteTotal" class="text-lg text-slate-900">—</strong>
            </div>
            <p id="quoteNote" class="mt-2 text-xs text-slate-500">Chưa bao gồm phụ phí đặc biệt (nếu có).</p>
            <div id="quoteItems" class="mt-3 space-y-1"></div>
          </div>

          <div id="validationSummary" class="hidden rounded-2xl border border-rose-200 bg-rose-50/90 p-4 text-sm text-rose-700" aria-live="polite"></div>

          <button id="submitBtn" type="submit" class="inline-flex w-full justify-center rounded-2xl bg-gradient-to-r from-sky-500 via-indigo-500 to-violet-500 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 disabled:cursor-not-allowed disabled:opacity-70">
            <span class="submit-text">Xác nhận đặt lịch</span>
            <span class="submit-loading hidden">
              <svg class="inline-block h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Đang xử lý...
            </span>
          </button>
          <p id="formMsg" class="text-sm text-amber-600"></p>
        </form>
      </div>
    </div>
  </div>
</section>

<style>
  /* Loading animation styles for schedule form */
  .submit-loading svg {
    display: inline-block;
    margin-right: 6px;
    vertical-align: middle;
  }

  .submit-loading.hidden {
    display: none;
  }

  .submit-text.hidden {
    display: none;
  }

  /* Loading overlay for schedule form */
  #scheduleLoadingOverlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9998;
    justify-content: center;
    align-items: center;
  }

  #scheduleLoadingOverlay.active {
    display: flex;
  }

  .schedule-loading-modal {
    background: white;
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    z-index: 9999;
    animation: slideUp 0.3s ease-out;
  }

  .schedule-loading-modal .spinner {
    width: 60px;
    height: 60px;
    margin: 0 auto 20px;
    border: 4px solid #f0f0f0;
    border-top: 4px solid #4f46e5;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }

  .schedule-loading-modal p {
    color: #6b7280;
    font-size: 18px;
    font-weight: 600;
    margin: 0;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  @keyframes slideUp {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>

<script>
  // Hide loading overlay when page loads (form submission completed)
  window.addEventListener('load', function() {
    const overlay = document.getElementById('scheduleLoadingOverlay');
    if (overlay) {
      overlay.classList.remove('active');
    }
  });
  
  // Hide loading overlay on page unload (user navigated away)
  window.addEventListener('beforeunload', function() {
    const overlay = document.getElementById('scheduleLoadingOverlay');
    if (overlay) {
      overlay.classList.remove('active');
    }
  });
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
(function () {
  const shouldAutoLoadLeaflet = true;
  window._leafletReadyCallbacks = window._leafletReadyCallbacks || [];
  window._onLeafletLoaded = function () {
    window._leafletLoaded = true;
    var queue = window._leafletReadyCallbacks ? window._leafletReadyCallbacks.splice(0) : [];
    queue.forEach(function (cb) {
      try {
        cb();
      } catch (error) {
        console.error('[Leaflet] callback error', error);
      }
    });
  };

  window._leafletGeocoderReadyCallbacks = window._leafletGeocoderReadyCallbacks || [];
  window._onLeafletGeocoderLoaded = function () {
    window._leafletGeocoderReady = true;
    var queue = window._leafletGeocoderReadyCallbacks ? window._leafletGeocoderReadyCallbacks.splice(0) : [];
    queue.forEach(function (cb) {
      try {
        cb();
      } catch (error) {
        console.error('[Leaflet Geocoder] callback error', error);
      }
    });
  };

  function injectStylesheet(id, href) {
    if (document.getElementById(id)) return;
    var link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  }

  function injectScript(id, src, onload) {
    var existing = document.getElementById(id);
    if (existing) {
      if (existing.dataset.loaded === 'true') {
        if (typeof onload === 'function') onload();
      } else if (typeof onload === 'function') {
        existing.addEventListener('load', onload, { once: true });
      }
      return;
    }
    var script = document.createElement('script');
    script.id = id;
    script.src = src;
    script.async = true;
    script.addEventListener('load', function () {
      script.dataset.loaded = 'true';
      if (typeof onload === 'function') onload();
    }, { once: true });
    script.addEventListener('error', function (event) {
      console.error('[Leaflet] failed to load script', src, event);
    });
    document.head.appendChild(script);
  }

  window.loadLeafletFallbackAssets = window.loadLeafletFallbackAssets || function () {
    if (window._leafletAssetsRequested) return;
    window._leafletAssetsRequested = true;
    injectStylesheet('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    injectStylesheet('leaflet-geocoder-css', 'https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css');
    injectScript('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', function () {
      window._onLeafletLoaded && window._onLeafletLoaded();
      injectScript('leaflet-geocoder-js', 'https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js', function () {
        window._onLeafletGeocoderLoaded && window._onLeafletGeocoderLoaded();
      });
    });
  };

  if (shouldAutoLoadLeaflet) {
    window.loadLeafletFallbackAssets();
  }
})();
</script>

<script>
(function () {
  const root = document.querySelector('[data-booking]');
  if (!root) {
    console.error('[lienhe.php] ERROR: [data-booking] element not found');
    return;
  }
  
  console.log('[lienhe.php] INIT: Form found, initializing...');

  const slotTimes = ['08:00:00', '09:00:00', '10:00:00', '13:00:00', '14:00:00', '15:00:00'];

  const slotContainer = root.querySelector('#khungGioContainer');
  const slotField = root.querySelector('#gioHen');
  const dateField = root.querySelector('#ngayHen');
  const branchField = root.querySelector('#branch');
  
  // Store selected slot end time info for validation
  let selectedSlotEndTime = null;
  const slotMessage = root.querySelector('#slotMsg');
  const bookingTypeRadios = root.querySelectorAll("input[name='booking_type']");
  const locationTypeRadios = root.querySelectorAll("input[name='location_type']");
  const serviceFieldWrap = root.querySelector('[data-service-field]');
  const packageFieldWrap = root.querySelector('[data-package-field]');
  const serviceSelect = null; // replaced by checkbox list
  const serviceSearch = root.querySelector('#serviceSearch');
  const serviceList = root.querySelector('#serviceList');
  const packageList = root.querySelector('#packageList');
  const packageSearch = root.querySelector('#packageSearch');
  const primaryPackageInput = root.querySelector('#primaryPackageId');
  const deviceWrapper = root.querySelector('#chon_thiet_bi_div');
  const deviceList = root.querySelector('#thiet_bi_checkbox_list');
  const addressField = root.querySelector('#address');
  const externalLocationWrap = root.querySelector('#externalLocationWrap');
  const extLatField = root.querySelector('#ext_lat');
  const extLngField = root.querySelector('#ext_lng');
  const locateBtn = root.querySelector('#locateBtn');
  const locateStatus = root.querySelector('#locateStatus');
  const quoteBox = root.querySelector('#quoteBox');
  const quoteTotal = root.querySelector('#quoteTotal');
  const quoteNote = root.querySelector('#quoteNote');
  const mapWrapper = document.querySelector('[data-map-mode]');
  let mapMode = mapWrapper ? mapWrapper.dataset.mapMode : 'dynamic';
  const canUseGoogle = !!(mapWrapper && mapWrapper.dataset.googleEnabled === 'true');
  if (mapWrapper) {
    mapWrapper.dataset.mapMode = mapMode;
  }
  const appEnv = root.dataset.appEnv || 'production';
  const branchMapEl = document.getElementById('branchMap');
  const branchMapNote = document.getElementById('branchMapNote');
  const mapSearchInput = root.querySelector('#mapSearchInput');
  const mapSearchBtn = root.querySelector('#mapSearchBtn');
  const mapSearchStatus = root.querySelector('#mapSearchStatus');
    // If map is disabled in dev mode, collapse the map column and expand form
    if (mapMode === 'none') {
      const mapCol = document.getElementById('mapColumn');
      if (mapCol) mapCol.classList.add('hidden');
      const formCol = root.closest('section')?.querySelector('.xl\\:col-span-3');
      if (formCol) {
        formCol.classList.remove('xl:col-span-3');
        formCol.classList.add('xl:col-span-5');
      }
    }
  // Unified map instances (google or leaflet)
  let unifiedMap = null;            // Google Map or Leaflet Map object
  let branchMarker = null;          // Marker for selected branch
  let externalMarker = null;        // Marker for external location
  let googleLocateControl = null;   // Custom control (Google only)
  let leafletGeocoderControl = null;// Geocoder control (Leaflet only)
  let pendingBranchTarget = null;   // Branch coordinates waiting for map init
  let geoWatchId = null;
  let leafletFallbackActivated = false;
  let googleDevWatermarkTimer = null;
  let googleDevWatermarkObserver = null;
  let googleAutocomplete = null;
  const submitBtn = root.querySelector('#submitBtn');
  const formMsg = root.querySelector('#formMsg');
  const validationSummary = root.querySelector('#validationSummary');
  const packageDetailCache = new Map();

  const defaultQuoteNote = quoteNote ? quoteNote.textContent : '';
  const slotBaseClasses = 'flex w-full items-center justify-center rounded-xl border-2 border-sky-200 bg-white px-4 py-3 text-center text-sm font-semibold text-slate-800 transition hover:border-sky-400 hover:bg-sky-50 hover:text-sky-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 shadow-sm';
  const slotSelectedClasses = [
    'border-transparent',
    'bg-gradient-to-r',
    'from-sky-500',
    'to-indigo-500',
    'text-white',
    'shadow-xl',
    'ring-2',
    'ring-sky-200'
  ];
  const slotDisabledClasses = 'flex w-full items-center justify-center rounded-xl border-2 border-slate-400 bg-slate-300 px-4 py-3 text-center text-sm font-semibold text-slate-600 cursor-not-allowed opacity-50 line-through';
  const defaultBranchMapNote = branchMapNote ? branchMapNote.textContent : '';
  const GEO_ACCURACY_ACCEPTABLE = 3000;
  const GEO_ACCURACY_CAUTION = 8000;

  /**
   * ĐĂNG KÝ CALLBACK KHI LEAFLET READY
   * Vì Leaflet load async, cần queue callbacks và execute khi library đã sẵn sàng
   * @param {Function} callback - Hàm sẽ chạy khi Leaflet đã load xong
   */
  function registerLeafletReadyCallback(callback) {
    if (mapMode !== 'leaflet' || typeof callback !== 'function') return;
    if (window._leafletLoaded && typeof window.L !== 'undefined') {
      callback();
      return;
    }
    window._leafletReadyCallbacks = window._leafletReadyCallbacks || [];
    window._leafletReadyCallbacks.push(callback);
  }

  function registerLeafletGeocoderReady(callback) {
    if (mapMode !== 'leaflet' || typeof callback !== 'function') return;
    var geocoderReady = window._leafletGeocoderReady && window.L && window.L.Control && typeof window.L.Control.geocoder === 'function';
    if (geocoderReady) {
      callback();
      return;
    }
    window._leafletGeocoderReadyCallbacks = window._leafletGeocoderReadyCallbacks || [];
    window._leafletGeocoderReadyCallbacks.push(callback);
  }

  function registerMapsReadyCallback(callback) {
    if (!canUseGoogle || typeof callback !== 'function') return;
    window._afterMapsReadyCallbacks = window._afterMapsReadyCallbacks || [];
    window._afterMapsReadyCallbacks.push(callback);
    if (window._mapsApiLoaded) {
      callback();
    }
  }

  function setBranchMapNote(message, tone = 'muted') {
    if (!branchMapNote) return;
    branchMapNote.textContent = message || defaultBranchMapNote;
    branchMapNote.classList.remove('text-slate-500', 'text-rose-600', 'text-sky-600');
    const toneClass = tone === 'error' ? 'text-rose-600' : tone === 'accent' ? 'text-sky-600' : 'text-slate-500';
    branchMapNote.classList.add(toneClass);
  }

  function setMapSearchStatus(message, tone = 'muted') {
    if (!mapSearchStatus) return;
    mapSearchStatus.textContent = message || '';
    mapSearchStatus.classList.remove('text-slate-500', 'text-rose-600', 'text-sky-600');
    const toneClass = tone === 'error' ? 'text-rose-600' : tone === 'accent' ? 'text-sky-600' : 'text-slate-500';
    mapSearchStatus.classList.add(toneClass);
  }

  function activateLeafletFallback(reason) {
    stopGoogleDevWatermarkMonitor();
    if (mapMode === 'leaflet' && leafletFallbackActivated) {
      if (reason) setBranchMapNote(reason, 'error');
      return;
    }
    const switchingFromGoogle = mapMode !== 'leaflet';
    mapMode = 'leaflet';
    leafletFallbackActivated = true;
    if (mapWrapper) {
      mapWrapper.dataset.mapMode = 'leaflet';
    }
    if (switchingFromGoogle) {
      unifiedMap = null;
      googleAutocomplete = null;
      if (branchMapEl) {
        branchMapEl.innerHTML = '';
      }
    }
    if (typeof window.loadLeafletFallbackAssets === 'function') {
      window.loadLeafletFallbackAssets();
    }
    setBranchMapNote(reason || 'Google Maps không khả dụng, đang dùng Leaflet.', 'error');
    registerLeafletReadyCallback(() => {
      ensureUnifiedMap();
      const current = selectedBranchCoordinates();
      if (current && current.position) {
        panToBranch(current.position, current.label);
      }
    });
  }

  window._handleMapsFailure = function (reason) {
    activateLeafletFallback(reason);
  };

  function getDefaultBranchCenter() {
    const source = mapWrapper || branchMapEl;
    if (!source) {
      return { lat: 10.776889, lng: 106.700806 };
    }
    const lat = parseFloat(source.dataset.defaultLat || '10.776889');
    const lng = parseFloat(source.dataset.defaultLng || '106.700806');
    return {
      lat: Number.isFinite(lat) ? lat : 10.776889,
      lng: Number.isFinite(lng) ? lng : 106.700806
    };
  }

  function destroyLeafletMap() {
    if (mapMode !== 'leaflet') return;
    if (unifiedMap) {
      try {
        if (typeof unifiedMap.off === 'function') {
          unifiedMap.off();
        }
      } catch (error) {
        console.warn('[Leaflet] off() error', error);
      }
      try {
        if (typeof unifiedMap.remove === 'function') {
          unifiedMap.remove();
        }
      } catch (error) {
        console.warn('[Leaflet] remove() error', error);
      }
    }
    unifiedMap = null;
    branchMarker = null;
    externalMarker = null;
    if (branchMapEl) {
      branchMapEl.innerHTML = '';
    }
  }

  function promoteToGoogleMaps() {
    if (!canUseGoogle || mapMode === 'google') return;
    if (!(window.google && window.google.maps)) return;
    destroyLeafletMap();
    mapMode = 'google';
    leafletFallbackActivated = false;
    if (mapWrapper) {
      mapWrapper.dataset.mapMode = 'google';
    }
    unifiedMap = null;
    branchMarker = null;
    externalMarker = null;
    ensureUnifiedMap();
    const current = selectedBranchCoordinates();
    if (current && current.position) {
      panToBranch(current.position, current.label);
    } else {
      setBranchMapNote('Đang dùng Google Maps.', 'accent');
    }
    if (
      currentLocationType() === 'external' &&
      extLatField && extLatField.value &&
      extLngField && extLngField.value
    ) {
      const lat = parseFloat(extLatField.value);
      const lng = parseFloat(extLngField.value);
      if (Number.isFinite(lat) && Number.isFinite(lng)) {
        applyExternalCoords(lat, lng, {
          label: addressField && addressField.value ? addressField.value : 'Địa điểm đã chọn',
          zoom: 16,
          reverseLookup: false
        });
      }
    }
  }

  function ensureUnifiedMap() {
    if (!branchMapEl || unifiedMap) return;
    if (mapMode === 'none') return; // dev mode without map
    const center = getDefaultBranchCenter();
    if (mapMode === 'google') {
      if (!(window.google && window.google.maps)) return;
      unifiedMap = new window.google.maps.Map(branchMapEl, {
        center,
        zoom: 13,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false
      });
      branchMarker = new window.google.maps.Marker({ map: unifiedMap, position: center });
      unifiedMap.addListener('click', (e) => {
        if (currentLocationType() !== 'external') return; // only set external when mode selected
        const lat = e.latLng.lat();
        const lng = e.latLng.lng();
        applyExternalCoords(lat, lng, {
          reverseLookup: true,
          label: 'Đang xác định địa chỉ từ bản đồ...'
        });
      });
      installGoogleLocateControl();
      setupGoogleAutocomplete();
      startGoogleDevWatermarkMonitor();
    } else { // leaflet fallback
      if (typeof window.L === 'undefined') {
        registerLeafletReadyCallback(ensureUnifiedMap);
        return;
      }
      unifiedMap = window.L.map(branchMapEl).setView([center.lat, center.lng], 13);
      window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(unifiedMap);
      branchMarker = window.L.marker([center.lat, center.lng]).addTo(unifiedMap);
      unifiedMap.on('click', (event) => {
        if (currentLocationType() !== 'external') return;
        const { lat, lng } = event.latlng;
        applyExternalCoords(lat, lng, {
          reverseLookup: true,
          label: 'Đang xác định địa chỉ từ bản đồ...'
        });
      });
      registerLeafletGeocoderReady(attachLeafletGeocoder);
      setTimeout(() => {
        if (unifiedMap && typeof unifiedMap.invalidateSize === 'function') unifiedMap.invalidateSize();
      }, 0);
    }
    if (pendingBranchTarget && pendingBranchTarget.position) {
      panToBranch(pendingBranchTarget.position, pendingBranchTarget.label);
      pendingBranchTarget = null;
    }
  }

  function hasGoogleDevWatermark() {
    if (!branchMapEl) return false;
    const text = (branchMapEl.textContent || branchMapEl.innerText || '').toLowerCase();
    return text.includes('for development purposes only');
  }

  function stopGoogleDevWatermarkMonitor() {
    if (googleDevWatermarkTimer) {
      clearTimeout(googleDevWatermarkTimer);
      googleDevWatermarkTimer = null;
    }
    if (googleDevWatermarkObserver) {
      try {
        googleDevWatermarkObserver.disconnect();
      } catch (error) {
        console.warn('[Maps] watermark observer disconnect error', error);
      }
      googleDevWatermarkObserver = null;
    }
  }

  function startGoogleDevWatermarkMonitor() {
    if (mapMode !== 'google' || !branchMapEl) return;
    if (googleDevWatermarkTimer || googleDevWatermarkObserver) return;

    const fallbackToLeaflet = () => {
      stopGoogleDevWatermarkMonitor();
      console.warn('[Maps] Development watermark detected, falling back to Leaflet');
      if (typeof window._handleMapsFailure === 'function') {
        window._handleMapsFailure('Google Maps đang ở chế độ phát triển. Đang chuyển sang Leaflet.');
      }
    };

    const checkWatermark = () => {
      if (mapMode !== 'google') {
        stopGoogleDevWatermarkMonitor();
        return;
      }
      if (hasGoogleDevWatermark()) {
        fallbackToLeaflet();
        return;
      }
      googleDevWatermarkTimer = window.setTimeout(checkWatermark, 1500);
    };

    if (window.MutationObserver) {
      googleDevWatermarkObserver = new window.MutationObserver(() => {
        if (hasGoogleDevWatermark()) {
          fallbackToLeaflet();
        }
      });
      googleDevWatermarkObserver.observe(branchMapEl, {
        subtree: true,
        childList: true,
        characterData: true
      });
    }

    googleDevWatermarkTimer = window.setTimeout(checkWatermark, 1000);
  }

  function attachLeafletGeocoder() {
    if (mapMode !== 'leaflet' || !unifiedMap || leafletGeocoderControl) return;
    if (!(window.L && window.L.Control && typeof window.L.Control.geocoder === 'function')) return;
    leafletGeocoderControl = window.L.Control.geocoder({
      defaultMarkGeocode: false,
      placeholder: 'Tìm kiếm địa điểm...',
      errorMessage: 'Không tìm thấy địa điểm phù hợp.',
      collapsed: false
    })
      .on('markgeocode', (event) => {
        const geo = event && event.geocode;
        if (!geo || !geo.center) return;
        const { lat, lng } = geo.center;
        applyExternalCoords(lat, lng, { label: geo.name, zoom: 16 });
      })
      .addTo(unifiedMap);
  }

  function selectedBranchCoordinates() {
    if (!branchField) return null;
    const option = branchField.options[branchField.selectedIndex];
    if (!option || !option.value) return null;
    const lat = parseFloat(option.dataset.lat || '');
    const lng = parseFloat(option.dataset.lng || '');
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
      return { label: option.textContent.trim() };
    }
    return {
      label: option.textContent.trim(),
      position: { lat, lng }
    };
  }


  function panToBranch(position, label) {
    if (!position) return;
    if (!unifiedMap) {
      pendingBranchTarget = { position, label };
      setBranchMapNote('Đang tải bản đồ, vui lòng đợi...', 'accent');
      ensureUnifiedMap();
      return;
    }
    if (mapMode === 'google') {
      unifiedMap.panTo(position);
      unifiedMap.setZoom(Math.max(unifiedMap.getZoom() || 13, 14));
      if (branchMarker) branchMarker.setPosition(position);
    } else {
      if (branchMarker && typeof branchMarker.setLatLng === 'function') {
        branchMarker.setLatLng([position.lat, position.lng]);
      }
      if (typeof unifiedMap.setView === 'function') unifiedMap.setView([position.lat, position.lng], Math.max(unifiedMap.getZoom() || 13, 14));
    }
    if (label) setBranchMapNote(`Đang hiển thị ${label}.`, 'accent');
  }

  function setSlotMessage(text, tone = 'muted') {
    if (!slotMessage) return;
    slotMessage.textContent = text;
    slotMessage.classList.remove('text-slate-500', 'text-rose-600', 'text-sky-600');
    if (!text) {
      slotMessage.classList.add('text-slate-500');
      return;
    }
    const toneClass = tone === 'error' ? 'text-rose-600' : tone === 'accent' ? 'text-sky-600' : 'text-slate-500';
    slotMessage.classList.add(toneClass);
  }

  function setLocateStatus(message, tone = 'muted') {
    if (!locateStatus) return;
    locateStatus.textContent = message || '';
    locateStatus.classList.remove('text-slate-500', 'text-rose-600', 'text-sky-600');
    if (!message) {
      locateStatus.classList.add('text-slate-500');
      return;
    }
    const toneClass = tone === 'error' ? 'text-rose-600' : tone === 'accent' ? 'text-sky-600' : 'text-slate-500';
    locateStatus.classList.add(toneClass);
  }

  function ensureExternalLocationSelected() {
    const externalRadio = root.querySelector("input[name='location_type'][value='external']");
    if (externalRadio && !externalRadio.checked) {
      externalRadio.checked = true;
      toggleLocationFields();
    }
  }

  /**
   * ÁP DỤNG TỌA ĐỘ CHO ĐỊA ĐIỂM NGOÀI CHI NHÁNH
   * 
   * @param {number} lat - Vĩ độ
   * @param {number} lng - Kinh độ
   * @param {Object} options - Tùy chọn
   * @param {string} options.label - Nhãn địa điểm (pre-fill vào address field)
   * @param {number} options.zoom - Mức zoom bản đồ
   * @param {boolean} options.reverseLookup - Có gọi reverse geocoding không
   * @param {string} options.autofillToken - Token để track autofill
   * 
   * WORKFLOW:
   * 1. Chuyển sang radio "Địa điểm khác"
   * 2. Fill tọa độ vào hidden fields (ext_lat, ext_lng)
   * 3. Pre-fill địa chỉ (nếu có label)
   * 4. Đặt marker trên bản đồ (Google/Leaflet)
   * 5. Gọi reverse geocoding nếu cần (chuyển tọa độ → địa chỉ)
   */
  function applyExternalCoords(lat, lng, options = {}) {
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    ensureExternalLocationSelected();

    const latNum = Number(lat);
    const lngNum = Number(lng);
    if (extLatField) {
      extLatField.value = latNum.toFixed(6);
    }
    if (extLngField) {
      extLngField.value = lngNum.toFixed(6);
    }
    const shouldPrefillAddress = !!(addressField && (options.label || options.reverseLookup));
    if (shouldPrefillAddress) {
      const fallbackLabel = options.label || `Đang xác định địa chỉ tại (${latNum.toFixed(5)}, ${lngNum.toFixed(5)})`;
      addressField.value = fallbackLabel;
      addressField.dataset.autofill = 'true';
      if (options.autofillToken) {
        addressField.dataset.autofillToken = String(options.autofillToken);
      }
    }

    const positionObj = { lat: latNum, lng: lngNum };
    const doPlace = () => {
      if (!unifiedMap) {
        ensureUnifiedMap();
        if (!unifiedMap) return; // will try again via pending callbacks
      }
      if (mapMode === 'google') {
        unifiedMap.panTo(positionObj);
        const targetZoom = Math.max(unifiedMap.getZoom() || 12, options.zoom || 15);
        unifiedMap.setZoom(targetZoom);
        if (!externalMarker) {
          externalMarker = new window.google.maps.Marker({ map: unifiedMap, position: positionObj, icon: null });
        } else {
          externalMarker.setPosition(positionObj);
        }
      } else if (mapMode === 'leaflet') {
        if (typeof unifiedMap.setView === 'function') {
          unifiedMap.setView([positionObj.lat, positionObj.lng], Math.max(unifiedMap.getZoom() || 12, options.zoom || 15));
        }
        if (!externalMarker) {
          externalMarker = window.L.marker([positionObj.lat, positionObj.lng]).addTo(unifiedMap);
        } else if (typeof externalMarker.setLatLng === 'function') {
          externalMarker.setLatLng([positionObj.lat, positionObj.lng]);
        }
      }
    };
    if (mapMode === 'google') {
      if (unifiedMap && window.google && window.google.maps) doPlace(); else registerMapsReadyCallback(doPlace);
    } else {
      if (unifiedMap && window.L) doPlace(); else registerLeafletReadyCallback(doPlace);
    }

    updateQuotePreview();
    refreshValidationSummary();

    if (options.reverseLookup) {
      const token = Date.now();
      if (addressField) {
        addressField.dataset.autofillToken = String(token);
      }
      reverseGeocodeLatLng(latNum, lngNum)
        .then((humanAddress) => {
          if (!humanAddress || !addressField) return;
          const currentToken = addressField.dataset.autofillToken;
          if (String(currentToken) !== String(token)) return;
          addressField.value = humanAddress;
          addressField.dataset.autofill = 'true';
        })
        .catch(() => {
          /* noop */
        });
    }
  }

  function geolocationErrorMessage(error) {
    if (!error) return 'Không thể lấy vị trí hiện tại.';
    switch (error.code) {
      case 1:
        return 'Bạn đã từ chối cấp quyền truy cập vị trí.';
      case 2:
        return 'Không thể xác định vị trí từ thiết bị.';
      case 3:
        return 'Quá thời gian chờ khi lấy vị trí. Vui lòng thử lại.';
      default:
        return 'Không thể lấy vị trí hiện tại.';
    }
  }

  /**
   * REVERSE GEOCODING: CHUYỂN TỌA ĐỘ THÀNH ĐỊA CHỈ
   * 
   * Sử dụng Nominatim API (OpenStreetMap) để lấy địa chỉ từ tọa độ GPS
   * 
   * @param {number} lat - Vĩ độ
   * @param {number} lng - Kinh độ
   * @returns {Promise<string|null>} Địa chỉ hoặc null nếu không tìm thấy
   * 
   * API: https://nominatim.openstreetmap.org/reverse
   * Response: { display_name, address: { road, suburb, city, state } }
   */
  async function reverseGeocodeLatLng(lat, lng) {
    const params = new URLSearchParams({
      format: 'jsonv2',
      lat: String(lat),
      lon: String(lng),
      'accept-language': 'vi'
    });
    const endpoint = `https://nominatim.openstreetmap.org/reverse?${params.toString()}`;
    const response = await fetch(endpoint, {
      headers: {
        'Accept': 'application/json',
        'User-Agent': 'StygianBlueBooking/1.0 (contact@stygianblue.local)'
      }
    });
    if (!response.ok) return null;
    const data = await response.json();
    if (data && data.display_name) {
      return data.display_name;
    }
    if (data && data.address) {
      const { road, suburb, city, state } = data.address;
      return [road, suburb, city, state].filter(Boolean).join(', ');
    }
    return null;
  }

  function stopGeolocationWatch(info) {
    if (geoWatchId !== null && navigator.geolocation && typeof navigator.geolocation.clearWatch === 'function') {
      try {
        navigator.geolocation.clearWatch(geoWatchId);
      } catch (error) {
        console.warn('[Geolocation] clearWatch error', error);
      }
      geoWatchId = null;
    }
    if (locateBtn) {
      locateBtn.disabled = false;
    }
    setGoogleLocateBusy(false);
    if (info && info.message) {
      setLocateStatus(info.message, info.tone || 'muted');
    }
  }

  function handleGeolocationSuccess(position, options = {}) {
    const { allowRetry = false, fromWatch = false } = options;
    if (!position || !position.coords) {
      setLocateStatus('Không thể lấy vị trí hiện tại.', 'error');
      if (!fromWatch) {
        stopGeolocationWatch();
      }
      return;
    }

    const { latitude, longitude, accuracy } = position.coords;
    const hasAccuracy = Number.isFinite(accuracy);
    const roundedAccuracy = hasAccuracy ? Math.round(accuracy) : null;
    const isAcceptable = hasAccuracy && accuracy <= GEO_ACCURACY_ACCEPTABLE;
    const isCaution = hasAccuracy && accuracy <= GEO_ACCURACY_CAUTION;
    const needsReverse = !fromWatch || isAcceptable;

    applyExternalCoords(latitude, longitude, {
      label: 'Vị trí hiện tại của tôi',
      zoom: 16,
      reverseLookup: needsReverse
    });

    if (!fromWatch && locateBtn) {
      locateBtn.disabled = false;
    }
    if (!fromWatch) {
      setGoogleLocateBusy(false);
    }

    if (!hasAccuracy) {
      setLocateStatus('Đã lấy vị trí hiện tại. Kiểm tra lại điểm đánh dấu trước khi xác nhận.', 'accent');
      if (allowRetry) {
        startGeolocationWatch(10000);
      }
      return;
    }

    if (isAcceptable) {
      setLocateStatus(`Đã lấy vị trí hiện tại (sai số ±${roundedAccuracy}m).`, 'accent');
      stopGeolocationWatch();
      return;
    }

    if (isCaution) {
      setLocateStatus(`Vị trí hiện tại có sai số ±${roundedAccuracy}m. Bạn có thể kéo điểm đánh dấu để tinh chỉnh thêm.`, 'accent');
      stopGeolocationWatch();
      return;
    }

    setLocateStatus(`Vị trí hệ thống trả về sai số ±${roundedAccuracy}m (có thể chỉ là tọa độ trung tâm khu vực). Kéo điểm đánh dấu hoặc nhập địa chỉ chính xác hơn.`, 'error');
    if (allowRetry) {
      startGeolocationWatch(roundedAccuracy);
    }
  }

  function startGeolocationWatch(initialAccuracy) {
    if (!(navigator.geolocation && typeof navigator.geolocation.watchPosition === 'function')) {
      setLocateStatus('Thiết bị không hỗ trợ đo chính xác hơn. Vui lòng chỉnh tay trên bản đồ.', 'error');
      return;
    }
    stopGeolocationWatch();
    if (locateBtn) {
      locateBtn.disabled = true;
    }
    const rounded = Number.isFinite(initialAccuracy) ? Math.round(initialAccuracy) : 'lớn';
    setLocateStatus(`Sai số hiện tại khoảng ±${rounded}m. Đang cố gắng đo chính xác hơn...`, 'error');
    const deadline = Date.now() + 20000;
    geoWatchId = navigator.geolocation.watchPosition(
      (position) => {
        const currentAccuracy = position.coords && position.coords.accuracy;
        handleGeolocationSuccess(position, { allowRetry: false, fromWatch: true });
        if (Number.isFinite(currentAccuracy) && currentAccuracy <= GEO_ACCURACY_ACCEPTABLE) {
          stopGeolocationWatch({ message: `Đã cải thiện vị trí (sai số ±${Math.round(currentAccuracy)}m).`, tone: 'accent' });
        } else if (Date.now() > deadline) {
          stopGeolocationWatch({ message: 'Không thể đo chính xác hơn, vui lòng kéo điểm đánh dấu hoặc nhập địa chỉ cụ thể.', tone: 'error' });
        }
      },
      (error) => {
        stopGeolocationWatch({ message: geolocationErrorMessage(error), tone: 'error' });
      },
      { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 }
    );
  }

  /**
   * LẤY VỊ TRÍ HIỆN TẠI TỪ GPS/BROWSER
   * 
   * Sử dụng Geolocation API của browser để lấy tọa độ GPS
   * 
   * @param {string} triggerSource - 'button' hoặc 'google-control'
   * 
   * WORKFLOW:
   * 1. Kiểm tra permission và browser support
   * 2. Gọi navigator.geolocation.getCurrentPosition()
   * 3. Xử lý kết quả:
   *    - Nếu accuracy ≤ 3000m: Chấp nhận ngay
   *    - Nếu accuracy ≤ 8000m: Cảnh báo và cho phép điều chỉnh
   *    - Nếu accuracy > 8000m: Yêu cầu người dùng chỉnh tay
   * 4. Có thể bật watchPosition để cải thiện độ chính xác
   * 5. Gọi reverseGeocodeLatLng() để lấy địa chỉ
   */
  function requestCurrentLocation(triggerSource = 'button') {
    ensureExternalLocationSelected();
    if (currentLocationType() !== 'external') {
      setLocateStatus('Chọn "Địa điểm khác" trước khi dùng vị trí hiện tại.', 'error');
      setGoogleLocateBusy(false);
      return;
    }
    if (!navigator.geolocation) {
      setLocateStatus('Trình duyệt không hỗ trợ định vị.', 'error');
      setGoogleLocateBusy(false);
      return;
    }

    stopGeolocationWatch();
    if (triggerSource === 'button' && locateBtn) {
      locateBtn.disabled = true;
    }
    if (triggerSource === 'google-control') {
      setGoogleLocateBusy(true);
    }

    setLocateStatus(
      triggerSource === 'google-control'
        ? 'Google Maps đang xác định vị trí của bạn...'
        : 'Đang xác định vị trí của bạn...',
      'accent'
    );

    navigator.geolocation.getCurrentPosition(
      (position) => {
        handleGeolocationSuccess(position, { allowRetry: true, fromWatch: false });
      },
      (error) => {
        stopGeolocationWatch({ message: geolocationErrorMessage(error), tone: 'error' });
      },
      { enableHighAccuracy: true, timeout: triggerSource === 'google-control' ? 20000 : 15000, maximumAge: 0 }
    );
  }

  function getSelectedServiceIds() {
    const result = Array.from(root.querySelectorAll("input[name='service_ids[]']:checked"))
      .map((cb) => parseInt(cb.value, 10))
      .filter((v) => !Number.isNaN(v));
    if (result.length > 0) {
      console.log('[Multi-Service Debug] getSelectedServiceIds returning:', result);
    }
    return result;
  }

  function getSelectedPackageIds() {
    return Array.from(root.querySelectorAll("input[name='package_ids[]']:checked"))
      .map((cb) => parseInt(cb.value, 10))
      .filter((v) => !Number.isNaN(v));
  }

  function updatePrimaryPackage() {
    const ids = getSelectedPackageIds();
    if (primaryPackageInput) {
      primaryPackageInput.value = ids[0] ? String(ids[0]) : '';
    }
  }

  /**
   * KIỂM TRA VALIDATION FORM
   * 
   * Kiểm tra các trường bắt buộc:
   * 1. Chi nhánh đã chọn
   * 2. Ngày hẹn đã chọn
   * 3. Khung giờ đã chọn
   * 4. Khung giờ không vượt quá 21:00 (giờ đóng cửa)
   * 5. Dịch vụ/gói đã chọn (tùy booking_type)
   * 6. Tọa độ địa điểm (nếu location_type=external)
   * 
   * @returns {Array<string>} Mảng các lỗi validation
   */
  function buildValidationErrors() {
    const errors = [];
    if (!branchField || !branchField.value) {
      errors.push('Chọn chi nhánh làm việc.');
    }
    if (!dateField || !dateField.value) {
      errors.push('Chọn ngày hẹn.');
    }
    if (!slotField || !slotField.value) {
      errors.push('Chọn khung giờ trống.');
    }
    
    // Check if selected time slot exceeds closing time (21:00)
    if (selectedSlotEndTime) {
      const endTimeHour = parseInt(selectedSlotEndTime.split(':')[0], 10);
      const endTimeMinute = parseInt(selectedSlotEndTime.split(':')[1], 10);
      const endTimeInMinutes = endTimeHour * 60 + endTimeMinute;
      const closingTimeInMinutes = 21 * 60; // 21:00
      
      if (endTimeInMinutes > closingTimeInMinutes) {
        errors.push('Khung giờ đã chọn kết thúc sau 21:00 (giờ đóng cửa). Vui lòng chọn khung giờ sớm hơn hoặc giảm số lượng dịch vụ.');
      }
    }
    
    const type = currentBookingType();
    const selectedServiceIds = getSelectedServiceIds();
    const selectedPackageIds = getSelectedPackageIds();
    if (type === 'service' && selectedServiceIds.length === 0) {
      errors.push('Chọn ít nhất một dịch vụ lẻ.');
    }
    if (type === 'package' && selectedPackageIds.length === 0) {
      errors.push('Chọn ít nhất một gói dịch vụ.');
    }
    if (currentLocationType() === 'external') {
      if (!hasExternalCoordinates()) {
        errors.push('Chọn vị trí trên bản đồ hoặc dùng vị trí hiện tại cho địa điểm khác.');
      }
    }
    return errors;
  }

  function refreshValidationSummary() {
    const errors = buildValidationErrors();
    const isComplete = errors.length === 0;
    if (submitBtn) {
      submitBtn.disabled = !isComplete;
    }
    if (!validationSummary) {
      return isComplete;
    }
    if (!isComplete) {
      validationSummary.classList.remove('hidden');
      validationSummary.innerHTML = [
        '<p class="font-semibold">Cần bổ sung:</p>',
        '<ul class="mt-2 list-disc list-inside space-y-1">',
        errors.map((msg) => `<li>${msg}</li>`).join(''),
        '</ul>'
      ].join('');
    } else {
      validationSummary.classList.add('hidden');
      validationSummary.innerHTML = '';
    }
    return isComplete;
  }

  function currentBookingType() {
    const checked = root.querySelector("input[name='booking_type']:checked");
    if (!checked) return 'service';
    if (checked.value === 'package') return 'package';
    return 'service';
  }

  function toggleBookingFields() {
    const type = currentBookingType();
    if (type === 'package') {
      serviceFieldWrap.classList.add('hidden');
      packageFieldWrap.classList.remove('hidden');
      deviceWrapper.classList.add('hidden');
      deviceList.innerHTML = '';
      // clear checkbox selections when switching to package
      root.querySelectorAll("input[name='service_ids[]']").forEach((cb) => { cb.checked = false; });
    } else {
      serviceFieldWrap.classList.remove('hidden');
      packageFieldWrap.classList.add('hidden');
      root.querySelectorAll("input[name='package_ids[]']").forEach((cb) => { cb.checked = false; });
      if (primaryPackageInput) primaryPackageInput.value = '';
    }
    updateQuotePreview();
    refreshValidationSummary();
  }

  function updateAddressDefault() {
    if (!addressField || !branchField) return;
    if (currentLocationType() !== 'external') {
      addressField.value = '';
      addressField.dataset.autofill = 'false';
      return;
    }
    const selected = branchField.options[branchField.selectedIndex];
    const branchName = selected && selected.value ? selected.textContent.trim() : '';
    if (!branchName) return;
    const defaultValue = `Chi nhánh ${branchName}`;
    const isAuto = addressField.dataset.autofill === 'true';
    if (addressField.value.trim() === '' || isAuto) {
      addressField.value = defaultValue;
      addressField.dataset.autofill = 'true';
    }
  }

  function currentLocationType() {
    const checked = root.querySelector("input[name='location_type']:checked");
    return checked && checked.value === 'external' ? 'external' : 'branch';
  }

  function hasExternalCoordinates() {
    if (!extLatField || !extLngField) return false;
    const lat = parseFloat(extLatField.value);
    const lng = parseFloat(extLngField.value);
    return Number.isFinite(lat) && Number.isFinite(lng);
  }

  // legacy external map removed (unified)

  function installGoogleLocateControl() {
    if (mapMode !== 'google' || !unifiedMap || googleLocateControl || !(window.google && window.google.maps)) {
      return;
    }
    const controlDiv = document.createElement('div');
    controlDiv.style.background = '#ffffff';
    controlDiv.style.border = '1px solid rgba(148, 163, 184, 0.7)';
    controlDiv.style.borderRadius = '9999px';
    controlDiv.style.padding = '8px 12px';
    controlDiv.style.margin = '12px';
    controlDiv.style.fontSize = '12px';
    controlDiv.style.fontWeight = '600';
    controlDiv.style.color = '#0ea5e9';
    controlDiv.style.cursor = 'pointer';
    controlDiv.style.boxShadow = '0 1px 3px rgba(15, 23, 42, 0.15)';
    controlDiv.style.transition = 'transform 120ms ease, opacity 120ms ease';
    controlDiv.style.display = 'flex';
    controlDiv.style.alignItems = 'center';
    controlDiv.style.gap = '6px';
    controlDiv.style.userSelect = 'none';
    controlDiv.setAttribute('role', 'button');
    controlDiv.setAttribute('tabindex', '0');
    controlDiv.title = 'Dùng vị trí hiện tại (Google Maps)';

    const dot = document.createElement('span');
    dot.style.display = 'inline-block';
    dot.style.width = '6px';
    dot.style.height = '6px';
    dot.style.borderRadius = '9999px';
    dot.style.background = '#0ea5e9';
    controlDiv.appendChild(dot);

    const label = document.createElement('span');
    label.textContent = 'Vị trí của tôi';
    controlDiv.appendChild(label);

    controlDiv.addEventListener('click', () => {
      setGoogleLocateBusy(true);
      requestCurrentLocation('google-control');
    });
    controlDiv.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        setGoogleLocateBusy(true);
        requestCurrentLocation('google-control');
      }
    });

    unifiedMap.controls[window.google.maps.ControlPosition.RIGHT_BOTTOM].push(controlDiv);
    googleLocateControl = controlDiv;
    setGoogleLocateBusy(false);
  }

  function setGoogleLocateBusy(isBusy) {
    if (!googleLocateControl) return;
    googleLocateControl.dataset.busy = isBusy ? 'true' : 'false';
    googleLocateControl.style.transform = isBusy ? 'scale(0.97)' : 'scale(1)';
    googleLocateControl.style.opacity = isBusy ? '0.75' : '1';
  }

  function setupGoogleAutocomplete() {
    if (!mapSearchInput || mapMode !== 'google') return;
    if (!(window.google && window.google.maps && window.google.maps.places)) return;
    if (googleAutocomplete) return;
    googleAutocomplete = new window.google.maps.places.Autocomplete(mapSearchInput, {
      fields: ['geometry', 'formatted_address', 'name'],
      types: ['geocode']
    });
    googleAutocomplete.addListener('place_changed', () => {
      const place = googleAutocomplete.getPlace();
      if (!place || !place.geometry || !place.geometry.location) {
        setMapSearchStatus('Không tìm thấy địa điểm phù hợp.', 'error');
        return;
      }
      const loc = place.geometry.location;
      const label = place.formatted_address || place.name || mapSearchInput.value.trim();
      handleMapSearchResult(loc.lat(), loc.lng(), label);
    });
  }

  function handleMapSearchResult(lat, lng, label) {
    applyExternalCoords(lat, lng, {
      label: label || 'Vị trí vừa tìm',
      zoom: 16,
      reverseLookup: !label
    });
    setMapSearchStatus(label ? `Đã di chuyển đến ${label}.` : 'Đã tìm thấy vị trí.', 'accent');
  }

  function handleMapSearchQuery(rawQuery) {
    const query = (rawQuery || '').trim();
    if (!query) {
      setMapSearchStatus('Nhập địa điểm cần tìm.', 'error');
      return;
    }
    if (mapMode === 'none') {
      setMapSearchStatus('Bản đồ đang tắt trong môi trường phát triển.', 'error');
      return;
    }
    if (mapMode === 'google' && window.google && window.google.maps) {
      performGoogleTextSearch(query);
    } else {
      performLeafletSearch(query);
    }
  }

  function performGoogleTextSearch(query) {
    if (!(window.google && window.google.maps)) {
      setMapSearchStatus('Google Maps chưa sẵn sàng.', 'error');
      return;
    }
    setMapSearchStatus('Đang tìm kiếm trên Google Maps...', 'accent');
    const geocoder = new window.google.maps.Geocoder();
    geocoder.geocode({ address: query }, (results, status) => {
      if (status !== 'OK' || !Array.isArray(results) || !results.length) {
        setMapSearchStatus('Không tìm thấy địa điểm phù hợp.', 'error');
        return;
      }
      const best = results[0];
      if (!(best.geometry && best.geometry.location)) {
        setMapSearchStatus('Không thể lấy tọa độ cho địa điểm đã chọn.', 'error');
        return;
      }
      handleMapSearchResult(best.geometry.location.lat(), best.geometry.location.lng(), best.formatted_address || query);
    });
  }

  async function performLeafletSearch(query) {
    setMapSearchStatus('Đang tìm kiếm địa điểm...', 'accent');
    try {
      const params = new URLSearchParams({
        format: 'jsonv2',
        q: query,
        limit: '1',
        addressdetails: '1',
        'accept-language': 'vi'
      });
      const response = await fetch(`https://nominatim.openstreetmap.org/search?${params.toString()}`, {
        headers: {
          'Accept': 'application/json',
          'User-Agent': 'StygianBlueBooking/1.0 (contact@stygianblue.local)'
        }
      });
      if (!response.ok) {
        throw new Error('bad_response');
      }
      const data = await response.json();
      if (!Array.isArray(data) || !data.length) {
        setMapSearchStatus('Không tìm thấy địa điểm phù hợp.', 'error');
        return;
      }
      const best = data[0];
      const lat = parseFloat(best.lat);
      const lng = parseFloat(best.lon);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        setMapSearchStatus('Không thể lấy tọa độ cho địa điểm đã chọn.', 'error');
        return;
      }
      const label = best.display_name || query;
      handleMapSearchResult(lat, lng, label);
    } catch (error) {
      setMapSearchStatus('Không thể tìm kiếm, vui lòng thử lại.', 'error');
    }
  }

  // legacy separate leaflet external map removed (unified)

  function toggleLocationFields() {
    const type = currentLocationType();
    const showExternal = type === 'external';

    if (addressField) {
      addressField.disabled = !showExternal;
      if (!showExternal) {
        addressField.value = '';
        addressField.dataset.autofill = 'false';
      }
    }

    if (showExternal) {
      externalLocationWrap.classList.remove('hidden');
      // marker remains / sẽ được tạo khi người dùng chọn trên bản đồ
    } else {
      externalLocationWrap.classList.add('hidden');
      extLatField.value = '';
      extLngField.value = '';
      if (externalMarker) {
        if (mapMode === 'google' && externalMarker.setMap) externalMarker.setMap(null);
        if (mapMode === 'leaflet' && unifiedMap && unifiedMap.removeLayer && externalMarker) unifiedMap.removeLayer(externalMarker);
        externalMarker = null;
      }
      stopGeolocationWatch();
      setLocateStatus('', 'muted');
    }
    updateQuotePreview();
    refreshValidationSummary();
  }

  /**
   * KIỂM TRA KHẢ DỤNG KHUNG GIỜ (Core Availability Check)
   * 
   * GỌI API: /app/Pages/Controller/check_availability.php
   * 
   * INPUT:
   * - branch_id: ID chi nhánh
   * - start: Thời gian bắt đầu (YYYY-MM-DD HH:MM:SS)
   * - booking_type: 'service' hoặc 'package'
   * - service_ids[]: Mảng ID dịch vụ (nếu booking_type=service)
   * - package_ids[]: Mảng ID gói (nếu booking_type=package)
   * - location_type: 'branch' hoặc 'external'
   * - ext_lat, ext_lng, distance_km: Tọa độ địa điểm ngoài (nếu location_type=external)
   * 
   * OUTPUT:
   * {
   *   available: true/false,
   *   reason: 'Lý do nếu không khả dụng',
   *   end_time: 'HH:MM:SS' (thời gian kết thúc dự kiến),
   *   duration_min: số phút
   * }
   * 
   * LOGIC KIỂM TRA:
   * 1. Tính tổng thời lượng dịch vụ/gói
   * 2. Kiểm tra slot không vượt quá 21:00 (giờ đóng cửa)
   * 3. Kiểm tra độc quyền tại chi nhánh (không có lịch nào trùng)
   * 4. Kiểm tra nhân viên rảnh (cho lịch ngoài chi nhánh)
   */
  async function checkAvailability(slotTime) {
    const selectedDate = dateField.value; // YYYY-MM-DD
    const startTime = `${selectedDate} ${slotTime}`;
    const selectedBranch = branchField.value;
    const locationType = root.querySelector("input[name='location_type']:checked").value;
    
    // [DEBUG] Validate required fields
    if (!selectedDate) {
      console.warn('[checkAvailability] ERROR: dateField.value is empty');
      return { available: false, reason: 'Chưa chọn ngày' };
    }
    if (!selectedBranch) {
      console.warn('[checkAvailability] ERROR: branchField.value is empty');
      return { available: false, reason: 'Chưa chọn chi nhánh' };
    }
    if (!slotTime) {
      console.warn('[checkAvailability] ERROR: slotTime is empty');
      return { available: false, reason: 'Khoảng giờ không hợp lệ' };
    }
    
    // Lấy dịch vụ/gói được chọn
    const bookingType = root.querySelector("input[name='booking_type']:checked").value;
    let serviceIds = [];
    let packageIds = [];
    
    if (bookingType === 'service') {
      const checkedServices = root.querySelectorAll("input[name='service_ids[]']:checked");
      serviceIds = Array.from(checkedServices).map(el => parseInt(el.value));
      if (!serviceIds.length && root.querySelector("#service")) {
        const singleService = root.querySelector("#service");
        if (singleService && singleService.value) {
          serviceIds = [parseInt(singleService.value)];
        }
      }
      if (!serviceIds.length) {
        console.warn('[checkAvailability] ERROR: No services selected, serviceIds:', serviceIds);
        return { available: false, reason: 'Chưa chọn dịch vụ' };
      }
    } else if (bookingType === 'package') {
      packageIds = getSelectedPackageIds();
      if (!packageIds.length) {
        console.warn('[checkAvailability] ERROR: No package selected');
        return { available: false, reason: 'Chưa chọn gói dịch vụ' };
      }
    }
    
    // Lấy tọa độ nếu external
    let extLat = null, extLng = null, distanceKm = null;
    if (locationType === 'external') {
      extLat = extLatField ? parseFloat(extLatField.value) : null;
      extLng = extLngField ? parseFloat(extLngField.value) : null;
      distanceKm = extLatField?.dataset.distanceKm ? parseFloat(extLatField.dataset.distanceKm) : null;
    }
    
    const payload = {
      branch_id: selectedBranch,
      start: startTime,
      booking_type: bookingType,
      service_ids: serviceIds,
      package_ids: packageIds,
      package_id: packageIds[0] || null,
      location_type: locationType,
      ext_lat: extLat,
      ext_lng: extLng,
      distance_km: distanceKm
    };
    
    console.log('[checkAvailability] Payload:', payload);
    
    try {
      const response = await fetch('/StygianBlue/app/Pages/Controller/check_availability.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      
      console.log('[checkAvailability] Response status:', response.status);
      const data = await response.json();
      console.log('[checkAvailability] FULL Response data:', JSON.stringify(data, null, 2));
      console.log('[checkAvailability] end_time value:', data.end_time);
      console.log('[checkAvailability] end_time type:', typeof data.end_time);
      console.log('[checkAvailability] duration_min value:', data.duration_min);
      console.log('[checkAvailability] duration_min type:', typeof data.duration_min);
      
      // Extract HH:MM from end_time
      if (data.end_time) {
        const extracted = data.end_time.substring(11, 16);
        console.log('[checkAvailability] Extracted HH:MM from end_time:', extracted);
      }
      
      return data;
    } catch (error) {
      console.warn('[checkAvailability] Error:', error);
      // Fallback: cho phép nếu không thể kiểm tra
      return { available: true, reason: 'Không thể kiểm tra' };
    }
  }
  
  /**
   * [DEBUG] Test checkAvailability directly
   */
  window.testCheckAvailability = async function() {
    const date = '2025-12-09'; // Change to actual date
    const time = '14:00:00';
    const branchId = 1; // Change to actual branch
    
    const payload = {
      branch_id: branchId,
      start: `${date} ${time}`,
      booking_type: 'service',
      service_ids: [1],
      location_type: 'branch'
    };
    
    console.log('[testCheckAvailability] Payload:', payload);
    
    try {
      const response = await fetch('/StygianBlue/app/Pages/Controller/check_availability.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      
      console.log('[testCheckAvailability] Status:', response.status);
      const data = await response.json();
      console.log('[testCheckAvailability] Response:', data);
      return data;
    } catch (error) {
      console.error('[testCheckAvailability] Error:', error);
    }
  }
  
  // Auto-test on page load (remove this later)
  if (window.location.hash === '#test') {
    window.addEventListener('load', () => {
      setTimeout(() => {
        console.log('=== AUTO TEST START ===');
        testCheckAvailability().then(r => {
          console.log('=== AUTO TEST RESULT ===', r);
        });
      }, 1000);
    });
  }

  /**
   * LOAD KHUNG GIỜ ĐÃ ĐẶT VÀ KIỂM TRA KHẢ DỤNG
   * 
   * WORKFLOW:
   * 1. Gọi get_booked_slots.php để lấy danh sách giờ đã được đặt từ DB
   * 2. Gọi checkAvailability() cho từng slot để kiểm tra:
   *    - Nhân viên có rảnh không
   *    - Slot có vượt quá 21:00 không
   *    - Có xung đột lịch độc quyền không
   * 3. Render UI slots với trạng thái:
   *    - Đã đặt (booked)
   *    - Quá giờ (past time)
   *    - Không khả dụng (not available)
   *    - Có thể chọn (available)
   * 
   * GỌI API:
   * - GET get_booked_slots.php?date=YYYY-MM-DD&branch_id=X
   * - POST check_availability.php (multiple times)
   */
  async function loadBookedSlots() {
    const date = dateField.value;
    const branchId = parseInt(branchField.value, 10);
    const loadId = Math.random().toString(36).substring(7); // Generate unique ID for this load
    
    console.log(`[loadBookedSlots load#${loadId}] Starting: date=${date}, branch=${branchId}`);
    
    slotField.value = '';
    slotContainer.innerHTML = '';
    setSlotMessage('');
    selectedSlotEndTime = null; // Reset end time validation
    refreshValidationSummary();

    if (!date || Number.isNaN(branchId)) {
      setSlotMessage('Vui lòng chọn chi nhánh và ngày.', 'accent');
      refreshValidationSummary();
      return;
    }

    slotContainer.innerHTML = '<p class="col-span-full text-sm text-slate-500">Đang tải khung giờ...</p>';

    try {
      // Step 1: Get booked slots (already taken times from DB)
      const response = await fetch(`../controller/get_booked_slots.php?date=${encodeURIComponent(date)}&branch_id=${branchId}`);
      const bookedSlots = await response.json();
      
      // Step 2: Check availability for all slots in batch
      const slotAvailability = {};
      let limitAvailability = null;
      for (const time of slotTimes) {
        if (limitAvailability) {
          slotAvailability[time] = limitAvailability;
          continue;
        }
        const availability = await checkAvailability(time);
        slotAvailability[time] = availability;
        if (availability?.limit_reason === 'duration_exceeds_daily_limit') {
          console.warn(`[loadBookedSlots load#${loadId}] Duration limit hit at ${time}`, availability);
          limitAvailability = availability;
        }
      }

      if (limitAvailability) {
        slotTimes.forEach((time) => {
          slotAvailability[time] = limitAvailability;
        });
        setSlotMessage(limitAvailability.reason || 'Tổng thời lượng vượt giới hạn cho phép. Vui lòng bỏ bớt dịch vụ.', 'error');
      }
      
      console.log(`[loadBookedSlots load#${loadId}] Slot availability:`, slotAvailability);
      
      // Step 3: Render slots with availability info
      const hasSlots = renderSlots(
        Array.isArray(bookedSlots) ? bookedSlots : [],
        slotAvailability,
        { suppressEmptyMessage: Boolean(limitAvailability) }
      );
      if (!limitAvailability && hasSlots) {
        setSlotMessage('Chọn một khung giờ phù hợp.', 'muted');
      }
    } catch (error) {
      console.error(`[loadBookedSlots load#${loadId}] Error:`, error);
      slotContainer.innerHTML = '';
      setSlotMessage('Không thể tải khung giờ, vui lòng thử lại.', 'error');
    }
    refreshValidationSummary();
  }

  function renderSlots(booked, slotAvailability = {}, options = {}) {
    slotContainer.innerHTML = '';
    const today = new Date();
    const currentDate = dateField.value;
    const isToday = currentDate === today.toISOString().slice(0, 10);
    const currentTime = `${String(today.getHours()).padStart(2, '0')}:${String(today.getMinutes()).padStart(2, '0')}:00`;

    let hasAvailableSlot = false;

    slotTimes.forEach((time) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = time.slice(0, 5);
      button.setAttribute('role', 'radio');
      button.setAttribute('aria-checked', 'false');
      button.dataset.slotButton = 'true';
      button.dataset.slotTime = time;

      // Check 3 reasons for disable: booked, past time, or not available per backend check
      const isBooked = booked.includes(time);
      const isPastTime = isToday && time <= currentTime;
      const backendAvailability = slotAvailability[time];
      const isNotAvailable = backendAvailability && !backendAvailability.available;
      
      const disabled = isBooked || isPastTime || isNotAvailable;
      
      if (disabled) {
        button.disabled = true;
        button.className = slotDisabledClasses;
        
        // Add title tooltip with reason
        let reason = '';
        if (isBooked) reason = 'Khung giờ đã được đặt';
        else if (isPastTime) reason = 'Thời gian đã qua';
        else if (isNotAvailable) reason = backendAvailability.reason || 'Không khả dụng';
        button.title = reason;
      } else {
        hasAvailableSlot = true;
        button.className = slotBaseClasses;
        button.title = 'Nhấp để chọn khung giờ này';
        
        // Store availability data in button's dataset to avoid closure issues
        console.log('[renderSlots] Processing available slot:', time);
        console.log('[renderSlots] backendAvailability:', backendAvailability);
        
        if (backendAvailability) {
          const jsonStr = JSON.stringify(backendAvailability);
          console.log('[renderSlots] Storing JSON for slot', time, ':', jsonStr);
          button.dataset.availableJson = jsonStr;
          console.log('[renderSlots] Stored in dataset:', button.dataset.availableJson);
        }
        
        button.addEventListener('click', () => {
          if (button.disabled) return;
          
          // Simply select the slot (already checked availability)
          slotContainer.querySelectorAll('button[data-slot-button]').forEach((btn) => {
            btn.setAttribute('aria-checked', 'false');
            slotSelectedClasses.forEach((cls) => btn.classList.remove(cls));
          });
          button.setAttribute('aria-checked', 'true');
          slotSelectedClasses.forEach((cls) => button.classList.add(cls));
          slotField.value = time;
          updateQuotePreview();
          refreshValidationSummary();
          
          // Show end time if available - use stored availability data
          const availability = button.dataset.availableJson 
            ? JSON.parse(button.dataset.availableJson) 
            : null;
          
          console.log('[renderSlots click] Slot:', time);
          console.log('[renderSlots click] button.dataset.availableJson:', button.dataset.availableJson);
          console.log('[renderSlots click] Parsed availability:', availability);
            
          if (availability) {
            const endTimeMsg = availability.end_time 
              ? `${availability.end_time.substring(11, 16)}` 
              : time.slice(0, 5);
            const durationMsg = availability.duration_min 
              ? ` (${availability.duration_min} phút)` 
              : '';
            
            console.log('[renderSlots click] endTime value:', availability.end_time);
            console.log('[renderSlots click] extracted endTimeMsg:', endTimeMsg);
            console.log('[renderSlots click] duration_min:', availability.duration_min);
            
            // Check if end time exceeds studio closing time (21:00)
            const endTimeStr = availability.end_time ? availability.end_time.substring(11, 16) : null;
            if (endTimeStr) {
              // Store for validation
              selectedSlotEndTime = endTimeStr;
              
              const endTimeHour = parseInt(endTimeStr.split(':')[0], 10);
              const endTimeMinute = parseInt(endTimeStr.split(':')[1], 10);
              const endTimeInMinutes = endTimeHour * 60 + endTimeMinute;
              const closingTimeInMinutes = 21 * 60; // 21:00
              
              if (endTimeInMinutes > closingTimeInMinutes) {
                setSlotMessage(`⚠️ Cảnh báo: Khung giờ ${time.slice(0, 5)} - ${endTimeMsg}${durationMsg} vượt quá giờ đóng cửa của studio (21:00). Vui lòng chọn khung giờ sớm hơn hoặc giảm dịch vụ.`, 'error');
              } else {
                setSlotMessage(`Đã chọn khung giờ ${time.slice(0, 5)} - ${endTimeMsg}${durationMsg}`, 'accent');
              }
            } else {
              selectedSlotEndTime = null;
              setSlotMessage(`Đã chọn khung giờ ${time.slice(0, 5)} - ${endTimeMsg}${durationMsg}`, 'accent');
            }
          } else {
            setSlotMessage(`Đã chọn khung giờ ${time.slice(0, 5)}`, 'accent');
          }
        });
      }

      slotContainer.appendChild(button);
    });

    if (!hasAvailableSlot) {
      slotContainer.innerHTML = '<p class="col-span-full rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-600">Không còn khung giờ trống trong ngày này. Vui lòng chọn ngày khác.</p>';
      if (!options?.suppressEmptyMessage) {
        setSlotMessage('Không còn khung giờ trống trong ngày này. Vui lòng chọn ngày khác.', 'error');
      }
      refreshValidationSummary();
      return false;
    }

    refreshValidationSummary();
    return true;
  }

  if (window.flatpickr) {
    window.flatpickr(dateField, {
      dateFormat: 'Y-m-d',
      minDate: 'today',
      disableMobile: true,
      onChange: () => {
        loadBookedSlots();
        refreshValidationSummary();
      }
    });
  }

  dateField.addEventListener('change', () => {
    console.log('[lienhe.php] Date changed to:', dateField.value);
    loadBookedSlots();
    refreshValidationSummary();
  });
  if (branchField) {
    branchField.addEventListener('change', () => {
      console.log('[lienhe.php] Branch changed to:', branchField.value);
      updateAddressDefault();
      loadBookedSlots();
      const current = selectedBranchCoordinates();
      if (current && current.position) panToBranch(current.position, current.label);
      refreshValidationSummary();
    });
  }

  // [NEW] Reload slots khi thay đổi service/package/location (affects availability check)
  bookingTypeRadios.forEach((radio) => {
    radio.addEventListener('change', () => {
      if (dateField.value && branchField.value) {
        loadBookedSlots();
      }
      refreshValidationSummary();
    });
  });

  locationTypeRadios.forEach((radio) => {
    radio.addEventListener('change', () => {
      if (dateField.value && branchField.value) {
        loadBookedSlots();
      }
      refreshValidationSummary();
    });
  });

  if (serviceSearch) {
    serviceSearch.addEventListener('input', () => {
      // Reload slots khi có service được chọn/bỏ chọn
      const hasServiceSelected = root.querySelectorAll("input[name='service_ids']:checked").length > 0;
      if (hasServiceSelected && dateField.value && branchField.value) {
        // Dùng debounce để không reload quá nhiều
        clearTimeout(window._serviceChangeTimeout);
        window._serviceChangeTimeout = setTimeout(() => {
          loadBookedSlots();
        }, 300);
      }
    });
  }

  if (packageList) {
    packageList.addEventListener('change', (e) => {
      if (e.target && e.target.name === 'package_ids[]') {
        updatePrimaryPackage();
        const ids = getSelectedPackageIds();
        renderPackageCostumes(ids[0] || 0);
        if (dateField.value && branchField.value && getSelectedPackageIds().length) {
          loadBookedSlots();
        }
        updateServiceSummary();
        updateQuotePreview();
        refreshValidationSummary();
      }
    });
  }

  if (packageSearch && packageList) {
    packageSearch.addEventListener('input', () => {
      const q = packageSearch.value.toLowerCase().trim();
      packageList.querySelectorAll('[data-package-row]').forEach((row) => {
        const name = (row.getAttribute('data-name') || '').toLowerCase();
        row.classList.toggle('hidden', q && !name.includes(q));
      });
    });
  }
  
  // [NEW] Add event listeners to service checkboxes for summary + slot reload
  root.querySelectorAll("input[name='service_ids[]']").forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
      updateServiceSummary();
      const checked = root.querySelectorAll("input[name='service_ids[]']:checked");
      if (dateField.value && branchField.value && checked.length > 0) {
        loadBookedSlots();
      }
      refreshValidationSummary();
    });
  });
  
  if (addressField) {
    addressField.addEventListener('input', () => {
      addressField.dataset.autofill = 'false';
      refreshValidationSummary();
    });
  }
  bookingTypeRadios.forEach((radio) => {
    radio.addEventListener('change', () => {
      toggleBookingFields();
      updateServiceSummary();
    });
  });
  locationTypeRadios.forEach((radio) => {
    radio.addEventListener('change', () => {
      toggleLocationFields();
      updateServiceSummary();
    });
  });

  if (locateBtn) {
    locateBtn.addEventListener('click', () => {
      requestCurrentLocation('button');
    });
  }

  if (mapSearchBtn && mapSearchInput) {
    mapSearchBtn.addEventListener('click', () => {
      handleMapSearchQuery(mapSearchInput.value);
    });
  }

  if (mapSearchInput) {
    mapSearchInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        handleMapSearchQuery(mapSearchInput.value);
      }
    });
  }

  // Device toggle based on whether a checkbox with text 'Thuê trang thiết bị' is selected
  async function updateDeviceSectionFromServices() {
    if (currentBookingType() !== 'service') {
      deviceWrapper.classList.add('hidden');
      deviceList.innerHTML = '';
      return;
    }
    const selectedLabels = Array.from(root.querySelectorAll("input[name='service_ids[]']:checked"))
      .map((cb) => (cb.closest('[data-service-row]')?.querySelector('span')?.textContent || '').trim());
    const hasDeviceService = selectedLabels.includes('Thuê trang thiết bị');
    if (hasDeviceService) {
        deviceWrapper.classList.remove('hidden');
        deviceList.innerHTML = '<p class="text-sm text-slate-500">Đang tải danh sách thiết bị...</p>';
         try {
          const response = await fetch('../controller/get_trang_thiet_bi.php');
          const devices = await response.json();
          deviceList.innerHTML = '';
          if (Array.isArray(devices) && devices.length > 0) {
            devices.forEach((item) => {
              const label = document.createElement('label');
              const checkbox = document.createElement('input');
              const span = document.createElement('span');

              label.className = 'flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700';
              checkbox.type = 'checkbox';
              checkbox.name = 'thiet_bi_id[]';
              checkbox.value = item.ID_TB;
              checkbox.className = 'h-4 w-4 rounded border-slate-300 text-sky-500 focus:ring-sky-400';
              checkbox.addEventListener('change', updateQuotePreview);
              span.textContent = item.TEN_TB;

              label.appendChild(checkbox);
              label.appendChild(span);
              deviceList.appendChild(label);
            });
          } else {
            deviceList.innerHTML = '<p class="text-sm text-slate-500">Không có thiết bị khả dụng.</p>';
          }
        } catch (error) {
          deviceList.innerHTML = '<p class="text-sm text-rose-600">Không thể tải thiết bị.</p>';
        }
      } else {
        deviceWrapper.classList.add('hidden');
        deviceList.innerHTML = '';
      }
  }

  // Listen to changes on any service checkbox
  root.querySelectorAll("input[name='service_ids[]']").forEach((cb) => {
    cb.addEventListener('change', async () => {
      const checked = getSelectedServiceIds();
      console.log('[Multi-Service Debug] Checkbox changed, now checked:', checked);
      await updateDeviceSectionFromServices();
      updateQuotePreview();
      refreshValidationSummary();
      // [NEW] Reload slots khi service checkbox thay đổi
      if (dateField.value && branchField.value && checked.length > 0) {
        loadBookedSlots();
      }
    });
  });

  // Client-side search filter for services
  if (serviceSearch && serviceList) {
    serviceSearch.addEventListener('input', () => {
      const q = serviceSearch.value.trim().toLowerCase();
      serviceList.querySelectorAll('[data-service-row]').forEach((row) => {
        const name = (row.dataset.name || '').toLowerCase();
        const show = !q || name.includes(q);
        row.classList.toggle('hidden', !show);
      });
    });
  }

  if (deviceList) {
    deviceList.addEventListener('change', (e) => {
      if (e.target && e.target.matches("input[name='thiet_bi_id[]']")) {
        updateQuotePreview();
        refreshValidationSummary();
      }
    });
  }

  function selectedDeviceIds() {
    return Array.from(root.querySelectorAll("input[name='thiet_bi_id[]']:checked"))
      .map((checkbox) => parseInt(checkbox.value, 10))
      .filter((value) => !Number.isNaN(value));
  }

  /**
   * LẤY CHI TIẾT GÓI DỊCH VỤ
   * 
   * GỌI API: GET ../Controller/get_package_detail.php
   * 
   * INPUT:
   * - package_id: ID gói dịch vụ
   * - branch_id: ID chi nhánh (optional, để tính khuyến mãi)
   * 
   * OUTPUT:
   * {
   *   package: { ID_GOI, TEN_GOI, MO_TA, ... },
   *   services: [ { ID_DV, TEN_DV, THOI_LUONG } ],
   *   costume_requirements: [ { SO_LUONG, GHI_CHU } ],
   *   promotion: { GIA_GOC, GIA_SAU_GIAM, SO_TIEN_GIAM, TEN_CHUONG_TRINH },
   *   total_duration: số phút
   * }
   * 
   * Kết quả được cache trong packageDetailCache để tránh gọi lại
   */
  async function fetchPackageDetail(packageId) {
    const url = new URL('../Controller/get_package_detail.php', window.location.href);
    url.searchParams.set('package_id', packageId);
    if (branchField && branchField.value) {
      url.searchParams.set('branch_id', branchField.value);
    }
    const res = await fetch(url.toString());
    return res.json();
  }

  function formatCurrency(value) {
    return Number.isFinite(value) ? value.toLocaleString('vi-VN') + '₫' : '—';
  }

  /**
   * HIỂN THỊ TÓM TẮT DỊCH VỤ/GÓI ĐÃ CHỌN
   * 
   * Tính toán và hiển thị:
   * - Tổng thời lượng dự kiến (phút)
   * - Thời gian kết thúc dự kiến
   * - Cảnh báo nếu vượt quá 21:00 (giờ đóng cửa)
   * - Cảnh báo nếu thiếu nhân viên (cho lịch ngoài chi nhánh)
   * 
   * GỌI API:
   * - GET get_service_detail.php?service_id=X để lấy thời lượng dịch vụ lẻ
   * - Sử dụng cache packageDetailCache cho gói dịch vụ
   * 
   * Hiển thị trong #serviceSummaryBox
   */
  async function updateServiceSummary() {
    const bookingType = currentBookingType();
    const branchId = parseInt(branchField.value, 10);
    const locationType = root.querySelector("input[name='location_type']:checked")?.value || 'branch';
    
    // Clear previous summary
    const summaryBox = root.querySelector('#serviceSummaryBox');
    if (!summaryBox) {
      // Create summary box if doesn't exist
      const box = document.createElement('div');
      box.id = 'serviceSummaryBox';
      box.className = 'rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-2 text-sm';
      const parent = root.querySelector('[data-service-field]');
      if (parent) parent.parentElement.insertBefore(box, parent.nextElementSibling);
    }
    
    const summary = root.querySelector('#serviceSummaryBox');
    if (!summary) return;
    
    summary.innerHTML = '';
    
    if (bookingType === 'service') {
      const checkedServices = root.querySelectorAll("input[name='service_ids[]']:checked");
      const serviceIds = Array.from(checkedServices).map(el => parseInt(el.value));
      
      if (serviceIds.length === 0) {
        summary.innerHTML = '<p class="text-slate-500">Chưa chọn dịch vụ nào.</p>';
        return;
      }
      
      // Query backend for actual durations
      let totalDuration = 0;
      try {
        const response = await fetch('/StygianBlue/app/Pages/Controller/calculate_duration.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            booking_type: 'service',
            service_ids: serviceIds,
            package_id: null
          })
        });
        const data = await response.json();
        totalDuration = data.duration_min || (serviceIds.length * 60);
      } catch (error) {
        console.warn('[updateServiceSummary] Duration query failed, using default:', error);
        totalDuration = serviceIds.length * 60; // fallback
      }
      
      const maxDailyMinutes = <?= (int)getenv('MAX_DAILY_SERVICE_MIN') ?: 600 ?>; // Configurable daily cap
      
      // Get service names for display
      const serviceNames = Array.from(checkedServices)
        .map(el => el.closest('label')?.querySelector('span')?.textContent?.trim() || 'Dịch vụ')
        .join(', ');
      
      let html = `
        <div>
          <p class="font-semibold text-slate-700">Dịch vụ đã chọn:</p>
          <p class="text-slate-600 ml-2">${serviceNames}</p>
        </div>
        <div>
          <p class="font-semibold text-slate-700">Tổng thời lượng: <span class="text-sky-600">${totalDuration} phút</span></p>
          <p class="text-slate-500 text-xs">= ${Math.floor(totalDuration / 60)} giờ ${totalDuration % 60} phút</p>
        </div>
      `;
      
      // Warnings
      if (totalDuration > maxDailyMinutes) {
        html += `
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-2">
            <p class="text-amber-700 font-semibold">⚠️ Cảnh báo:</p>
            <p class="text-amber-600 text-xs">Tổng thời lượng (${totalDuration} phút) vượt quá khả năng trong một ngày (${maxDailyMinutes} phút). Vui lòng bỏ chọn một số dịch vụ.</p>
          </div>
        `;
      }
      
      // Staff warning for external
      if (locationType === 'external' && branchId) {
        const requiredStaff = serviceIds.length; // 1 staff per service
        // We'd need to fetch available staff count from backend
        html += `
          <div class="rounded-lg border border-blue-200 bg-blue-50 p-2">
            <p class="text-blue-700 font-semibold">👥 Nhân viên cần:</p>
            <p class="text-blue-600 text-xs">${requiredStaff} nhân viên cho ${serviceIds.length} dịch vụ (mỗi dịch vụ cần 1 nhân viên).</p>
          </div>
        `;
      }
      
      summary.innerHTML = html;
    } else if (bookingType === 'package') {
      const packageIds = getSelectedPackageIds();
      if (!packageIds.length) {
        summary.innerHTML = '<p class="text-slate-500">Chưa chọn gói dịch vụ nào.</p>';
        return;
      }

      summary.innerHTML = '<p class="text-sm text-slate-500">Đang tải thông tin gói...</p>';
      try {
        const detailPromises = packageIds.map(async (pid) => {
          if (packageDetailCache.has(pid)) return packageDetailCache.get(pid);
          const data = await fetchPackageDetail(pid);
          if (data && data.ok) {
            packageDetailCache.set(pid, data);
          }
          return data;
        });

        const details = await Promise.all(detailPromises);
        const validDetails = details.filter((d) => d && d.ok);
        if (!validDetails.length) {
          summary.innerHTML = '<p class="text-sm text-rose-600">Không tải được thông tin gói.</p>';
          return;
        }

        const parts = [];
        let totalDuration = 0;

        validDetails.forEach((data, idx) => {
          const pkg = data.package || {};
          const promo = data.promotion || null;
          const services = Array.isArray(data.services) ? data.services : [];
          const slots = Array.isArray(data.costume_slots) ? data.costume_slots : [];

          const basePrice = formatCurrency(pkg.base_price);
          const finalPriceRaw = (promo && promo.price_after !== null && promo.price_after !== undefined)
            ? promo.price_after
            : pkg.final_price;
          const finalPrice = formatCurrency(finalPriceRaw);
          const discountAmount = promo && promo.computed_discount ? promo.computed_discount : Math.max(0, (pkg.base_price || 0) - (finalPriceRaw || 0));
          const hasDiscount = promo && finalPriceRaw !== null && finalPriceRaw !== undefined && finalPriceRaw !== pkg.base_price;

          if (idx > 0) {
            parts.push('<div class="h-px bg-slate-200 my-4"></div>');
          }

          parts.push('<div class="space-y-1">');
          parts.push('<p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-600">Gói dịch vụ</p>');
          parts.push(`<p class="text-lg font-bold text-slate-900">${pkg.name || 'Gói #' + packageIds[idx]}</p>`);
          if (pkg.description) {
            parts.push(`<p class="text-sm text-slate-600">${pkg.description}</p>`);
          }
          
          // Hiển thị thời lượng gói
          if (pkg.duration_min && pkg.duration_min > 0) {
            const hours = Math.floor(pkg.duration_min / 60);
            const mins = pkg.duration_min % 60;
            const durationText = hours > 0 
              ? `${hours} giờ${mins > 0 ? ` ${mins} phút` : ''}`
              : `${mins} phút`;
            parts.push(`<p class="text-sm text-sky-600">⏱️ Thời lượng: <strong>${durationText}</strong></p>`);
          }
          
          parts.push('</div>');

          parts.push('<div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1">');
          parts.push(`<div class="flex items-center justify-between text-sm text-slate-700"><span>Giá gốc</span><strong>${basePrice}</strong></div>`);
          const promoName = promo?.name ? ` (${promo.name})` : '';
          const discountLabel = discountAmount > 0 ? ` -${formatCurrency(discountAmount)}` : '';
          const promoText = hasDiscount
            ? `Giá sau KM${promoName}${discountLabel ? ' ' + discountLabel : ''}`
            : `Giá sau KM${promoName || ''} (chưa áp dụng khuyến mãi)`;
          parts.push(`<div class="flex items-center justify-between text-sm ${hasDiscount ? 'text-emerald-700' : 'text-slate-500'}"><span>${promoText}</span><strong>${finalPrice}</strong></div>`);
          parts.push('</div>');

          if (services.length > 0) {
            parts.push('<div class="space-y-2">');
            parts.push('<p class="text-sm font-semibold text-slate-800">Dịch vụ trong gói</p>');
            services.forEach((svc) => {
              const d = svc.duration ? (svc.duration * (svc.quantity || 1)) : 0;
              totalDuration += d;
            });
            parts.push('<ul class="space-y-1">');
            services.forEach((svc) => {
              const qty = svc.quantity && svc.quantity > 1 ? ` × ${svc.quantity}` : '';
              const price = formatCurrency(svc.unit_price);
              const duration = svc.duration ? `${svc.duration}p` : '';
              parts.push(`<li class="flex justify-between text-sm text-slate-700"><span>${svc.name}${qty}${duration ? ' • ' + duration : ''}</span><span>${price}</span></li>`);
            });
            parts.push('</ul></div>');
          }

          if (slots.length > 0) {
            parts.push('<div class="space-y-2">');
            parts.push('<p class="text-sm font-semibold text-slate-800">Yêu cầu trang phục</p>');
            parts.push('<ul class="space-y-1">');
            slots.forEach((slot) => {
              const qty = slot.quantity ? ` (${slot.quantity})` : '';
              const required = slot.required ? '• Bắt buộc' : '';
              const type = slot.type ? ` • ${slot.type}` : '';
              const group = slot.group ? ` • ${slot.group}` : '';
              const size = slot.size ? ` • Size ${slot.size}` : '';
              parts.push(`<li class="text-sm text-slate-700">${slot.name || 'Trang phục'}${qty}${group}${type}${size} ${required}</li>`);
            });
            parts.push('</ul></div>');
          }
        });

        if (totalDuration > 0) {
          parts.unshift(`<p class="text-xs text-slate-500">Tổng thời lượng ước tính (tất cả gói): ${totalDuration} phút (~${Math.floor(totalDuration/60)}h ${totalDuration%60}p)</p>`);
        }

        summary.innerHTML = parts.join('');
      } catch (err) {
        summary.innerHTML = '<p class="text-sm text-rose-600">Không tải được thông tin gói.</p>';
      }
    }
  }

  /**
   * CẬP NHẬT BÁO GIÁ DỰ KIẾN (QUOTE PREVIEW)
   * 
   * Tính toán tổng chi phí dựa trên:
   * - Dịch vụ lẻ hoặc gói đã chọn
   * - Khuyến mãi (nếu có)
   * - Phí di chuyển (nếu làm ngoài chi nhánh)
   * - Trang phục thuê (nếu có)
   * 
   * GỌI API:
   * - POST get_travel_fee.php để tính phí di chuyển
   *   Input: { branch_id, ext_lat, ext_lng }
   *   Output: { fee, distance_km }
   * 
   * Hiển thị breakdown:
   * - Giá gói/dịch vụ
   * - Giảm giá (nếu có)
   * - Phí di chuyển
   * - Tổng cộng
   */
  async function updateQuotePreview() {
    const branchId = parseInt(branchField.value, 10);
    const date = dateField.value;
    const time = slotField.value;
    const type = currentBookingType();
    const serviceIds = getSelectedServiceIds();
    const packageIds = getSelectedPackageIds();
    const costumeIds = selectedCostumeIds();
    const locationType = currentLocationType();
    const hasCoords = hasExternalCoordinates();
    const extLat = hasCoords && extLatField ? parseFloat(extLatField.value) : NaN;
    const extLng = hasCoords && extLngField ? parseFloat(extLngField.value) : NaN;

    if (!branchId || !date || !time) {
      quoteBox.classList.add('hidden');
      return;
    }

    if (locationType === 'external' && !hasCoords) {
      quoteBox.classList.add('hidden');
      setLocateStatus('Chọn vị trí trên bản đồ hoặc dùng vị trí hiện tại để tính phụ phí.', 'error');
      return;
    }

    let packagePrice = 0;
    if (type === 'package') {
      if (!packageIds.length) {
        quoteBox.classList.add('hidden');
        return;
      }
      packageIds.forEach((pid) => {
        if (packageDetailCache.has(pid)) {
          const data = packageDetailCache.get(pid);
          const price = data?.package?.final_price ?? data?.package?.base_price ?? 0;
          packagePrice += Number.isFinite(price) ? price : 0;
          return;
        }
        const row = packageList ? packageList.querySelector(`input[name='package_ids[]'][value='${pid}']`)?.closest('[data-package-row]') : null;
        if (row) {
          const finalAttr = parseInt(row.getAttribute('data-price-final') || '0', 10);
          const baseAttr = parseInt(row.getAttribute('data-price-base') || '0', 10);
          packagePrice += finalAttr || baseAttr || 0;
        }
      });
      if (quoteNote) {
        quoteNote.textContent = 'Giá gói đã bao gồm toàn bộ dịch vụ trong gói (đã áp dụng khuyến mãi nếu có).';
      }
      quoteBox.classList.remove('hidden');
    }

    if (type === 'service' && serviceIds.length === 0) {
      quoteBox.classList.add('hidden');
      return;
    }

    quoteBox.classList.remove('hidden');
    quoteTotal.textContent = 'Đang tính...';
    if (quoteNote) {
      if (type === 'package') {
        quoteNote.textContent = 'Giá gói đã bao gồm toàn bộ dịch vụ trong gói.';
      } else {
        quoteNote.textContent = defaultQuoteNote;
      }
    }

    let travelRow = document.getElementById('travelFeeRow');
    let travelValueEl = document.getElementById('travelFeeValue');
    const quoteItems = document.getElementById('quoteItems');
    if (!travelRow) {
      travelRow = document.createElement('div');
      travelRow.id = 'travelFeeRow';
      travelRow.className = 'mt-2 flex items-center justify-between text-sm text-slate-700';
      const label = document.createElement('span');
      label.textContent = 'Phụ phí di chuyển';
      travelValueEl = document.createElement('span');
      travelValueEl.id = 'travelFeeValue';
      travelRow.appendChild(label);
      travelRow.appendChild(travelValueEl);
      quoteBox.appendChild(travelRow);
    }
    if (!travelValueEl) {
      travelValueEl = document.getElementById('travelFeeValue');
    }
    if (travelValueEl) {
      travelValueEl.textContent = 'Đang tính...';
    }
    if (travelRow) {
      travelRow.classList.toggle('hidden', locationType !== 'external');
    }

    try {
      const payload = {
        branch_id: branchId,
        date,
        time,
        booking_type: type,
        service_id: type === 'service' ? (serviceIds[0] || null) : null,
        service_ids: type === 'service' ? serviceIds : [],
        device_ids: type === 'service' ? selectedDeviceIds() : [],
        location_type: locationType,
        ext_lat: Number.isFinite(extLat) ? extLat : null,
        ext_lng: Number.isFinite(extLng) ? extLng : null
      };
      if (type === 'package') {
        payload.package_id = packageIds[0] || null;
        payload.package_ids = packageIds;
        payload.costume_ids = costumeIds;
      }

      const response = await fetch('../controller/quote_preview.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await response.json();
      const total = data && typeof data.total === 'number' ? data.total : 0;
      const fee = data && typeof data.travel_fee === 'number' ? data.travel_fee : 0;
      const displayTotal = type === 'package' ? packagePrice + (Number.isFinite(fee) ? fee : 0) : total;
      quoteTotal.textContent = displayTotal.toLocaleString('vi-VN') + '₫';
      // Show travel fee if present
      const feeVal = data && typeof data.travel_fee === 'number' ? data.travel_fee : 0;
      if (travelValueEl) {
        travelValueEl.textContent = Number.isFinite(feeVal)
          ? feeVal.toLocaleString('vi-VN') + '₫'
          : '—';
      }

      // Render line items
      if (quoteItems) {
        let items = [];
        if (type === 'package') {
          items = packageIds.map((pid) => {
            let name = `Gói #${pid}`;
            let price = 0;
            if (packageDetailCache.has(pid)) {
              const d = packageDetailCache.get(pid);
              name = d?.package?.name || name;
              price = d?.package?.final_price ?? d?.package?.base_price ?? 0;
            } else {
              const row = packageList ? packageList.querySelector(`input[name='package_ids[]'][value='${pid}']`)?.closest('[data-package-row]') : null;
              if (row) {
                name = row.getAttribute('data-name') || name;
                const finalAttr = parseInt(row.getAttribute('data-price-final') || '0', 10);
                const baseAttr = parseInt(row.getAttribute('data-price-base') || '0', 10);
                price = finalAttr || baseAttr || 0;
              }
            }
            return { label: name, price };
          });
        } else {
          items = Array.isArray(data?.items) ? data.items : [];
        }

        // Deduplicate: remove any travel-fee line from items; shown via travelFeeRow
        items = items.filter((it) => {
          const label = typeof it.label === 'string' ? it.label : '';
          return !/phụ\s*phí\s*di\s*chuyển/i.test(label);
        });

        if (items.length === 0) {
          quoteItems.innerHTML = '';
        } else {
          const parts = [];
          items.forEach((it) => {
            const label = typeof it.label === 'string' ? it.label : 'Mục';
            const price = typeof it.price === 'number' ? it.price : 0;
            parts.push(
              `<div class="flex items-center justify-between text-sm text-slate-700"><span>${label}</span><span>${price.toLocaleString('vi-VN')}₫</span></div>`
            );
          });
          quoteItems.innerHTML = parts.join('');
        }
      }
    } catch (error) {
      quoteTotal.textContent = '—';
      if (travelValueEl) {
        travelValueEl.textContent = '—';
      }
      const quoteItems = document.getElementById('quoteItems');
      if (quoteItems) quoteItems.innerHTML = '';
    }
  }

  function selectedCostumeIds() {
    return Array.from(root.querySelectorAll('[data-package-costume-field] input[name="costume_ids[]"]:checked'))
      .map((cb) => parseInt(cb.value, 10))
      .filter((id) => !Number.isNaN(id));
  }

  function renderPackageCostumes(packageId) {
    const wrap = root.querySelector('[data-package-costume-field]');
    if (!wrap) return;
    if (!packageId) {
      wrap.classList.add('hidden');
      wrap.innerHTML = '';
      updateQuotePreview();
      return;
    }
    wrap.classList.remove('hidden');
    wrap.innerHTML = '<p class="text-sm text-slate-500">Đang tải trang phục gói...</p>';
    fetch(`../controller/get_package_costumes.php?package_id=${encodeURIComponent(packageId)}`)
      .then(r => r.json())
      .then(data => {
        if (!data.ok) {
          wrap.innerHTML = '<p class="text-sm text-rose-600">Không tải được danh sách trang phục.</p>';
          return;
        }
        if (!Array.isArray(data.costumes) || data.costumes.length === 0) {
          wrap.innerHTML = '<p class="text-sm text-slate-500">Gói này chưa cấu hình trang phục.</p>';
          updateQuotePreview();
          return;
        }
        const parts = [];
        parts.push('<div class="space-y-2">');
        parts.push('<p class="text-sm font-semibold text-slate-800">Trang phục trong gói</p>');
        parts.push('<div class="grid gap-2 md:grid-cols-2">');
        data.costumes.forEach(c => {
          const id = c.ID_TRANG_PHUC;
          const mandatory = parseInt(c.BAT_BUOC, 10) === 1;
          const discount = parseInt(c.DISCOUNT_PERCENT, 10);
          const basePrice = parseInt(c.GIA_THUE, 10);
          let finalPrice = basePrice;
          if (mandatory) finalPrice = 0; else if (discount > 0) finalPrice = Math.round(basePrice * (1 - discount/100));
          const priceLabel = mandatory ? 'Bao gồm' : (discount > 0 ? `${finalPrice.toLocaleString('vi-VN')}₫ (giảm ${discount}%)` : `${finalPrice.toLocaleString('vi-VN')}₫`);
          parts.push('<label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3 text-sm shadow-sm" data-costume-row="'+id+'">');
          if (mandatory) {
            parts.push('<input type="checkbox" checked disabled class="mt-1 h-4 w-4 rounded border-slate-300 text-sky-600" />');
          } else {
            parts.push(`<input name="costume_ids[]" value="${id}" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-sky-600" />`);
          }
          parts.push('<span class="flex flex-col">');
          parts.push(`<span class="font-medium text-slate-700">${c.TEN}</span>`);
          parts.push(`<span class="text-xs text-slate-500" data-costume-price="${id}">${priceLabel}</span>`);
          parts.push('</span>');
          parts.push('</label>');
        });
        parts.push('</div></div>');
        wrap.innerHTML = parts.join('');
        wrap.querySelectorAll("input[name='costume_ids[]']").forEach(cb => {
          cb.addEventListener('change', () => updateQuotePreview());
        });
        updateQuotePreview();
        updateCostumeAvailability();
      })
      .catch(() => {
        wrap.innerHTML = '<p class="text-sm text-rose-600">Lỗi tải trang phục.</p>';
      });
  }

  function updateCostumeAvailability() {
    const date = dateField.value;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
    const branchId = branchField ? parseInt(branchField.value || '0', 10) : 0;
    if (!branchId) return;
    const checkboxes = root.querySelectorAll('[data-package-costume-field] input[name="costume_ids[]"]');
    const seen = new Set();
    checkboxes.forEach((cb) => {
      const id = parseInt(cb.value, 10);
      if (Number.isNaN(id) || seen.has(id)) return;
      seen.add(id);
      const url = new URL('../controller/get_costume_availability.php', window.location.href);
      url.searchParams.set('costume_id', id);
      url.searchParams.set('date', date);
      url.searchParams.set('branch_id', branchId);
      fetch(url)
        .then((r) => r.json())
        .then((data) => {
          if (!data.ok) return;
          if (!data.available) {
            const relatedCheckboxes = root.querySelectorAll(`input[name="costume_ids[]"][value="${id}"]`);
            relatedCheckboxes.forEach((input) => {
              input.checked = false;
              input.disabled = true;
            });
            const spans = root.querySelectorAll(`[data-costume-price="${id}"]`);
            spans.forEach((span) => {
              if (span.dataset.unavailable !== 'true') {
                span.textContent = span.textContent + ' • Hết ngày này';
                span.classList.remove('text-slate-500');
                span.classList.add('text-rose-600');
                span.dataset.unavailable = 'true';
              }
            });
          }
        })
        .catch(() => {});
    });
  }

  dateField.addEventListener('change', () => {
    updateCostumeAvailability();
  });

  toggleBookingFields();
  toggleLocationFields();
  updateAddressDefault();
  // Initial hydrate for package details/costumes if pre-selected
  updatePrimaryPackage();
  const initialPackageIds = getSelectedPackageIds();
  if (initialPackageIds.length > 0) {
    renderPackageCostumes(initialPackageIds[0]);
    updateServiceSummary();
  } else {
    renderPackageCostumes(0);
  }
  const initialBranch = selectedBranchCoordinates();
  if (initialBranch && initialBranch.position) panToBranch(initialBranch.position, initialBranch.label);
  refreshValidationSummary();
  updateServiceSummary();
  registerLeafletReadyCallback(() => {
    ensureUnifiedMap();
    const cur = selectedBranchCoordinates();
    if (cur && cur.position) panToBranch(cur.position, cur.label);
  });

  if (canUseGoogle && root.dataset.preferGoogle === 'true') {
    registerMapsReadyCallback(() => {
      promoteToGoogleMaps();
    });
  }

  /**
   * XỬ LÝ SUBMIT FORM ĐẶT LỊCH
   * 
   * WORKFLOW:
   * 1. Validate form (gọi buildValidationErrors())
   * 2. Hiển thị loading overlay
   * 3. Submit form đến controller xử lý
   * 4. Controller sẽ:
   *    - Tạo record LICH_HEN trong DB
   *    - Gán nhân viên (nếu có)
   *    - Tạo các record liên quan (dịch vụ, gói, trang phục)
   *    - Redirect về trang xác nhận hoặc danh sách lịch hẹn
   * 
   * Note: Backward compatible với form cũ bằng cách set hidden field service_id
   * từ service_ids[] đầu tiên
   */
  const form = root.querySelector('#scheduleForm');
  form.addEventListener('submit', (event) => {
    formMsg.textContent = '';
    const errors = buildValidationErrors();
    if (errors.length > 0) {
      event.preventDefault();
      formMsg.textContent = errors[0];
      refreshValidationSummary();
      return;
    }

    // Backward-compat: set hidden primary service_id from the first selected option
    if (currentBookingType() === 'service') {
      const ids = getSelectedServiceIds();
      console.log('[Multi-Service Debug] Selected services:', ids);
      let hidden = form.querySelector('input[name="service_id"]');
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'service_id';
        form.appendChild(hidden);
      }
      hidden.value = ids.length > 0 ? String(ids[0]) : '';
      console.log('[Multi-Service Debug] Form will submit with service_ids:', ids, 'primary:', hidden.value);
      
      // Verify checkboxes are checked right before submit
      const verifyChecks = Array.from(root.querySelectorAll("input[name='service_ids[]']:checked")).map(c => c.value);
      console.log('[Multi-Service Debug] Verify checked at submit:', verifyChecks);
    }

    submitBtn.disabled = true;
    
    // Show loading animation
    const submitText = submitBtn.querySelector('.submit-text');
    const submitLoading = submitBtn.querySelector('.submit-loading');
    if (submitText) submitText.classList.add('hidden');
    if (submitLoading) submitLoading.classList.remove('hidden');
    
    // Show loading overlay
    let overlay = document.getElementById('scheduleLoadingOverlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'scheduleLoadingOverlay';
      overlay.className = 'active';
      overlay.innerHTML = `
        <div class="schedule-loading-modal">
          <div class="spinner"></div>
          <p>Đang xử lý đơn đặt lịch của bạn...</p>
        </div>
      `;
      document.body.appendChild(overlay);
    } else {
      overlay.classList.add('active');
    }
  });
})();

// Fallback: nếu Google Maps JS không tải sau 3s khi chọn 'Địa điểm khác', báo cho người dùng cấu hình API key.
setTimeout(() => {
  const wrapper = document.querySelector('[data-map-mode]');
  const mode = wrapper ? wrapper.dataset.mapMode : 'leaflet';
  const branchMapDiv = document.getElementById('branchMap');
  if (!branchMapDiv) return;
  if (mode === 'none') {
    // Hide container completely in dev mode without map
    const mapCol = document.getElementById('mapColumn');
    if (mapCol) mapCol.classList.add('hidden');
    const formCol = document.querySelector('.xl\\:col-span-3');
    if (formCol) {
      formCol.classList.remove('xl:col-span-3');
      formCol.classList.add('xl:col-span-5');
    }
    return;
  }
  if (mode === 'google') {
    const hasGoogle = typeof window.google !== 'undefined' && window.google.maps;
    if (!hasGoogle && !branchMapDiv.dataset.fallbackShown) {
      branchMapDiv.dataset.fallbackShown = 'true';
      branchMapDiv.innerHTML = '<div class="flex h-full w-full items-center justify-center p-4 text-center text-sm text-rose-600">Không tải được Google Maps. Đang chuyển sang Leaflet dự phòng...</div>';
      if (typeof window._handleMapsFailure === 'function') {
        window._handleMapsFailure('Không tải được Google Maps, đang chuyển sang Leaflet.');
      }
    }
  } else { // leaflet
    const hasLeaflet = typeof window.L !== 'undefined';
    if (!hasLeaflet && !branchMapDiv.dataset.fallbackShown) {
      branchMapDiv.dataset.fallbackShown = 'true';
      branchMapDiv.innerHTML = '<div class="flex h-full w-full items-center justify-center p-4 text-center text-sm text-rose-600">Không tải được Leaflet. Kiểm tra CDN hoặc kết nối mạng.</div>';
    }
  }
}, 3000);
</script>

<?php if (!empty($googleMapsApiKey) && ($preferGoogle ?? false)): ?>
  <script>
    // Callback khi Google Maps JS tải xong
    function initGoogleMaps() {
      console.log('[Maps] API loaded');
      window._mapsApiLoaded = true;
      if (Array.isArray(window._afterMapsReadyCallbacks)) {
        window._afterMapsReadyCallbacks.forEach(function (cb) {
          try {
            cb();
          } catch (error) {
            console.error('[Maps] callback error', error);
          }
        });
      }
    }
    // Nếu Google Maps fail, đặt cờ để có thể quyết định fallback (hiện chỉ hiển thị thông báo có sẵn)
    function onGoogleMapsError() {
      console.error('[Maps] failed to load Google Maps script');
      window._mapsApiFailed = true;
      if (typeof window._handleMapsFailure === 'function') {
        window._handleMapsFailure('Không tải được Google Maps (lỗi script). Đang chuyển sang Leaflet.');
      }
    }
    // Được Google Maps gọi khi API key không hợp lệ / chưa bật thanh toán
    window.gm_authFailure = function () {
      console.error('[Maps] gm_authFailure triggered');
      window._mapsApiFailed = true;
      if (typeof window._handleMapsFailure === 'function') {
        window._handleMapsFailure('Google Maps API key không hợp lệ hoặc chưa bật thanh toán. Đang chuyển sang Leaflet.');
      }
    };
  </script>
  <script async src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleMapsApiKey) ?>&callback=initGoogleMaps&loading=async&libraries=places" onerror="onGoogleMapsError();"></script>
<?php else: ?>
  <!-- Chưa có GOOGLE_MAPS_API_KEY: thiết lập biến môi trường hoặc define('GOOGLE_MAPS_API_KEY','your_key') -->
<?php endif; ?>