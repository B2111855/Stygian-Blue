<?php
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

$idTk = null;
if (isset($_SESSION['user']['ID_TK'])) {
    $idTk = $_SESSION['user']['ID_TK'];
} elseif (isset($_SESSION['ID_TK'])) {
    $idTk = $_SESSION['ID_TK'];
}

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
$stmtPackages = $conn->prepare(
    "SELECT ID_GOI, TEN_GOI, TONG_GIA_GOI FROM v_goi_dich_vu_tong_tien \n     WHERE TRANG_THAI = 'ban' \n       AND (HIEU_LUC_TU IS NULL OR HIEU_LUC_TU <= NOW()) \n       AND (HIEU_LUC_DEN IS NULL OR HIEU_LUC_DEN >= NOW()) \n     ORDER BY TEN_GOI ASC"
);
if ($stmtPackages) {
    $stmtPackages->execute();
    $resultPackages = $stmtPackages->get_result();
    while ($row = $resultPackages->fetch_assoc()) {
        $packages[] = $row;
    }
    $stmtPackages->close();
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
// mapMode: google nếu có key; nếu local và không có key thì tắt bản đồ; nếu không thì dùng leaflet.
$hasGoogleKey = !empty($googleMapsApiKey);
$mapMode = 'leaflet';
$branchMapNoteText = $hasGoogleKey
  ? 'Đang dùng OpenStreetMap; sẽ tự chuyển sang Google Maps khi API sẵn sàng.'
  : 'Đang dùng OpenStreetMap (Leaflet).';
?>
<section data-booking data-app-env="<?= htmlspecialchars($appEnv) ?>" class="space-y-6">
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
                ><?= htmlspecialchars($branch['TEN_CN']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-500">Lịch trống sẽ hiển thị theo chi nhánh đã chọn.</p>
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
                <input type="radio" name="booking_type" value="service" checked class="h-4 w-4 border-slate-300 text-sky-500 focus:ring-sky-400">
                <span>Dịch vụ lẻ</span>
              </label>
              <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                <input type="radio" name="booking_type" value="package" class="h-4 w-4 border-slate-300 text-sky-500 focus:ring-sky-400">
                <span>Gói dịch vụ</span>
              </label>
            </div>
            <p class="text-xs text-slate-500">Tuỳ nhu cầu: chọn dịch vụ lẻ hoặc gói trọn bộ.</p>
          </fieldset>

          <div class="space-y-2" data-service-field>
            <label for="service" class="text-sm font-semibold text-slate-800">Dịch vụ</label>
            <select id="service" name="service_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
              <option value="">Chọn dịch vụ...</option>
              <?php foreach ($services as $service): ?>
                <option value="<?= $service['ID_DV'] ?>"><?= htmlspecialchars($service['TEN_DV']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-500">Có thể chọn thêm thiết bị sau khi chọn dịch vụ phù hợp.</p>
          </div>

          <div class="space-y-2 hidden" data-package-field>
            <label for="package" class="text-sm font-semibold text-slate-800">Gói dịch vụ</label>
            <select id="package" name="package_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200">
              <option value="">Chọn gói dịch vụ...</option>
              <?php foreach ($packages as $package): ?>
                <option value="<?= $package['ID_GOI'] ?>" data-price="<?= (int) $package['TONG_GIA_GOI'] ?>">
                  <?= htmlspecialchars($package['TEN_GOI']) ?> (<?= number_format((int) $package['TONG_GIA_GOI'], 0, ',', '.') ?>đ)
                </option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-500">Giá hiển thị đã bao gồm toàn bộ dịch vụ trong gói.</p>
          </div>
          <div class="space-y-2 hidden" data-package-costume-field>
            <!-- Sẽ được render động danh sách trang phục của gói -->
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
          </div>

          <div id="validationSummary" class="hidden rounded-2xl border border-rose-200 bg-rose-50/90 p-4 text-sm text-rose-700" aria-live="polite"></div>

          <button id="submitBtn" type="submit" class="inline-flex w-full justify-center rounded-2xl bg-gradient-to-r from-sky-500 via-indigo-500 to-violet-500 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 disabled:cursor-not-allowed disabled:opacity-70">Xác nhận đặt lịch</button>
          <p id="formMsg" class="text-sm text-amber-600"></p>
        </form>
      </div>
    </div>
  </div>
</section>

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
  if (!root) return;

  const slotTimes = ['08:00:00', '09:00:00', '10:00:00', '13:00:00', '14:00:00', '15:00:00'];

  const slotContainer = root.querySelector('#khungGioContainer');
  const slotField = root.querySelector('#gioHen');
  const dateField = root.querySelector('#ngayHen');
  const branchField = root.querySelector('#branch');
  const slotMessage = root.querySelector('#slotMsg');
  const bookingTypeRadios = root.querySelectorAll("input[name='booking_type']");
  const locationTypeRadios = root.querySelectorAll("input[name='location_type']");
  const serviceFieldWrap = root.querySelector('[data-service-field]');
  const packageFieldWrap = root.querySelector('[data-package-field]');
  const serviceSelect = root.querySelector('#service');
  const packageSelect = root.querySelector('#package');
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

  const defaultQuoteNote = quoteNote ? quoteNote.textContent : '';
  const slotBaseClasses = 'flex w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-sm font-semibold text-slate-700 transition hover:border-sky-300 hover:text-sky-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300';
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
  const slotDisabledClasses = ['cursor-not-allowed', 'bg-rose-50', 'border-rose-200', 'text-rose-500', 'opacity-70'];
  const defaultBranchMapNote = branchMapNote ? branchMapNote.textContent : '';
  const GEO_ACCURACY_ACCEPTABLE = 3000;
  const GEO_ACCURACY_CAUTION = 8000;

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
    const type = currentBookingType();
    const serviceValue = serviceSelect ? serviceSelect.value : '';
    const serviceId = serviceValue ? parseInt(serviceValue, 10) : 0;
    const packageId = parseInt(packageSelect ? packageSelect.value : '0', 10);
    if (type === 'service' && !serviceId) {
      errors.push('Chọn dịch vụ lẻ.');
    }
    if (type === 'package' && !packageId) {
      errors.push('Chọn gói dịch vụ.');
    }
    if (currentLocationType() === 'external') {
      const hasAddress = !!(addressField && addressField.value.trim());
      const hasCoords = !!(extLatField && extLatField.value && extLngField && extLngField.value);
      if (!hasAddress && !hasCoords) {
        errors.push('Nhập địa chỉ hoặc chọn vị trí trên bản đồ cho địa điểm khác.');
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
      if (serviceSelect) serviceSelect.value = '';
    } else {
      serviceFieldWrap.classList.remove('hidden');
      packageFieldWrap.classList.add('hidden');
      if (packageSelect) packageSelect.value = '';
    }
    updateQuotePreview();
    refreshValidationSummary();
  }

  function updateAddressDefault() {
    if (!addressField || !branchField) return;
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
    if (type === 'external') {
      externalLocationWrap.classList.remove('hidden');
      // marker remains / will be created when user clicks or locates
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

  async function loadBookedSlots() {
    const date = dateField.value;
    const branchId = parseInt(branchField.value, 10);
    slotField.value = '';
    slotContainer.innerHTML = '';
    setSlotMessage('');
    refreshValidationSummary();

    if (!date || Number.isNaN(branchId)) {
      setSlotMessage('Vui lòng chọn chi nhánh và ngày.', 'accent');
      refreshValidationSummary();
      return;
    }

    slotContainer.innerHTML = '<p class="col-span-full text-sm text-slate-500">Đang tải khung giờ...</p>';

    try {
      const response = await fetch(`../controller/get_booked_slots.php?date=${encodeURIComponent(date)}&branch_id=${branchId}`);
      const bookedSlots = await response.json();
      const hasSlots = renderSlots(Array.isArray(bookedSlots) ? bookedSlots : []);
      if (hasSlots) {
        setSlotMessage('Chọn một khung giờ phù hợp.', 'muted');
      }
    } catch (error) {
      slotContainer.innerHTML = '';
      setSlotMessage('Không thể tải khung giờ, vui lòng thử lại.', 'error');
    }
    refreshValidationSummary();
  }

  function renderSlots(booked) {
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
      button.className = slotBaseClasses;

      const disabled = booked.includes(time) || (isToday && time <= currentTime);
      if (disabled) {
        button.disabled = true;
        slotDisabledClasses.forEach((cls) => button.classList.add(cls));
      } else {
        hasAvailableSlot = true;
        button.addEventListener('click', () => {
          if (button.disabled) return;
          slotContainer.querySelectorAll('button[data-slot-button]').forEach((btn) => {
            btn.setAttribute('aria-checked', 'false');
            slotSelectedClasses.forEach((cls) => btn.classList.remove(cls));
          });
          button.setAttribute('aria-checked', 'true');
          slotSelectedClasses.forEach((cls) => button.classList.add(cls));
          slotField.value = time;
          updateQuotePreview();
          refreshValidationSummary();
          setSlotMessage(`Đã chọn khung giờ ${time.slice(0, 5)}`, 'accent');
        });
      }

      slotContainer.appendChild(button);
    });

    if (!hasAvailableSlot) {
      slotContainer.innerHTML = '<p class="col-span-full rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-600">Không còn khung giờ trống trong ngày này. Vui lòng chọn ngày khác.</p>';
      setSlotMessage('Không còn khung giờ trống trong ngày này. Vui lòng chọn ngày khác.', 'error');
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
    loadBookedSlots();
    refreshValidationSummary();
  });
  if (branchField) {
    branchField.addEventListener('change', () => {
      updateAddressDefault();
      loadBookedSlots();
      const current = selectedBranchCoordinates();
      if (current && current.position) panToBranch(current.position, current.label);
      refreshValidationSummary();
    });
  }
  if (addressField) {
    addressField.addEventListener('input', () => {
      addressField.dataset.autofill = 'false';
      refreshValidationSummary();
    });
  }
  bookingTypeRadios.forEach((radio) => {
    radio.addEventListener('change', toggleBookingFields);
  });
  locationTypeRadios.forEach((radio) => {
    radio.addEventListener('change', toggleLocationFields);
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

  if (serviceSelect) {
    serviceSelect.addEventListener('change', async function onServiceChange() {
      if (currentBookingType() !== 'service') {
        deviceWrapper.classList.add('hidden');
        deviceList.innerHTML = '';
        updateQuotePreview();
        return;
      }

      const selectedOption = this.options[this.selectedIndex];
      const selectedLabel = selectedOption ? selectedOption.textContent.trim() : '';

      if (selectedLabel === 'Thuê trang thiết bị') {
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

      updateQuotePreview();
      refreshValidationSummary();
    });
  }

  if (packageSelect) {
    packageSelect.addEventListener('change', () => {
      updateQuotePreview();
      refreshValidationSummary();
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

  async function updateQuotePreview() {
    const branchId = parseInt(branchField.value, 10);
    const date = dateField.value;
    const time = slotField.value;
    const type = currentBookingType();
    const serviceValue = serviceSelect ? serviceSelect.value : '';
    const serviceId = serviceValue ? parseInt(serviceValue, 10) : 0;
    const packageId = parseInt(packageSelect ? packageSelect.value : '0', 10);
    const costumeIds = selectedCostumeIds();
    const locationType = currentLocationType();
    const extLat = extLatField ? parseFloat(extLatField.value) : NaN;
    const extLng = extLngField ? parseFloat(extLngField.value) : NaN;

    if (!branchId || !date || !time) {
      quoteBox.classList.add('hidden');
      return;
    }

    let packagePrice = 0;
    if (type === 'package') {
      if (!packageId) {
        quoteBox.classList.add('hidden');
        return;
      }
      const selectedOption = packageSelect.options[packageSelect.selectedIndex];
      packagePrice = selectedOption ? parseInt(selectedOption.getAttribute('data-price') || '0', 10) : 0;
      if (quoteNote) {
        quoteNote.textContent = 'Giá gói đã bao gồm toàn bộ dịch vụ trong gói.';
      }
      quoteBox.classList.remove('hidden');
    }

    if (type === 'service' && !serviceId) {
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
        service_id: type === 'service' ? serviceId : null,
        device_ids: type === 'service' ? selectedDeviceIds() : [],
        location_type: locationType,
        ext_lat: Number.isFinite(extLat) ? extLat : null,
        ext_lng: Number.isFinite(extLng) ? extLng : null
      };
      if (type === 'package') {
        payload.package_id = packageId;
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
      const displayTotal = total;
      quoteTotal.textContent = displayTotal.toLocaleString('vi-VN') + '₫';
      // Show travel fee if present
      const feeVal = data && typeof data.travel_fee === 'number' ? data.travel_fee : 0;
      if (travelValueEl) {
        travelValueEl.textContent = Number.isFinite(feeVal)
          ? feeVal.toLocaleString('vi-VN') + '₫'
          : '—';
      }
    } catch (error) {
      quoteTotal.textContent = '—';
      if (travelValueEl) {
        travelValueEl.textContent = '—';
      }
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

  if (packageSelect) {
    packageSelect.addEventListener('change', () => {
      const pkgId = parseInt(packageSelect.value || '0', 10);
      renderPackageCostumes(pkgId);
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
  const initialBranch = selectedBranchCoordinates();
  if (initialBranch && initialBranch.position) panToBranch(initialBranch.position, initialBranch.label);
  refreshValidationSummary();
  registerLeafletReadyCallback(() => {
    ensureUnifiedMap();
    const cur = selectedBranchCoordinates();
    if (cur && cur.position) panToBranch(cur.position, cur.label);
  });

  if (canUseGoogle) {
    registerMapsReadyCallback(() => {
      promoteToGoogleMaps();
    });
  }

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

    submitBtn.disabled = true;
    submitBtn.textContent = 'Đang gửi...';
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

<?php if (!empty($googleMapsApiKey)): ?>
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