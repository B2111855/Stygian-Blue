<?php
// pages/trangphuc/index.php
// Hiển thị danh sách trang phục cho thuê
include '../../../database/config.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function selected($a,$b){ return $a===$b ? 'selected' : ''; }

// ==== Input (GET) ====
$kw        = trim($_GET['q'] ?? '');
$branch    = $_GET['cn'] ?? '';
$category  = $_GET['loai'] ?? '';
$size      = $_GET['size'] ?? '';
$color     = $_GET['mau'] ?? '';
$minPrice  = $_GET['min'] ?? '';
$maxPrice  = $_GET['max'] ?? '';
$sort      = $_GET['sort'] ?? 'price_desc';

$rentFrom  = $_GET['from'] ?? '';
$rentTo    = $_GET['to'] ?? '';
$qty       = (int)($_GET['qty'] ?? 1);
if ($qty < 1) $qty = 1;

// ==== Lấy danh sách chi nhánh, loại, size, màu cho bộ lọc ====
$branches = [];
if ($brs = $conn->query("SELECT ID_CN, TEN_CN FROM CHI_NHANH ORDER BY TEN_CN ASC")) {
  while ($r = $brs->fetch_assoc()) $branches[] = $r;
  $brs->free();
}

$categories = [];
if ($cats = $conn->query("SELECT ID_LOAI, TEN_LOAI FROM trang_phuc_loai ORDER BY TEN_LOAI ASC")) {
  while ($r = $cats->fetch_assoc()) $categories[] = $r;
  $cats->free();
}

$sizes = [];
if ($sz = $conn->query("SELECT DISTINCT SIZE FROM trang_phuc WHERE IS_ACTIVE = 1 AND SIZE IS NOT NULL AND SIZE <> '' ORDER BY SIZE")) {
  while ($r = $sz->fetch_row()) $sizes[] = $r[0];
  $sz->free();
}

$colors = [];
if ($cl = $conn->query("SELECT DISTINCT MAU FROM trang_phuc WHERE IS_ACTIVE = 1 AND MAU IS NOT NULL AND MAU <> '' ORDER BY MAU")) {
  while ($r = $cl->fetch_row()) $colors[] = $r[0];
  $cl->free();
}

// ==== Cấu hình lấy đơn giá mới nhất ====
$priceSelect = "0 AS DON_GIA, NULL AS HIEU_LUC_TU";
$priceExpr   = '0';
$priceJoin   = '';

$hasPriceView = false;
if ($check = $conn->query("SHOW FULL TABLES LIKE 'v_trang_phuc_don_gia_moinhat'")) {
  $hasPriceView = $check->num_rows > 0;
  $check->free();
}

if ($hasPriceView) {
  $priceSelect = "COALESCE(gia.DON_GIA, 0) AS DON_GIA, gia.HIEU_LUC_TU";
  $priceExpr   = 'COALESCE(gia.DON_GIA, 0)';
  $priceJoin   = "LEFT JOIN v_trang_phuc_don_gia_moinhat gia ON gia.ID_TP = tp.ID_TP";
} else {
  $hasPriceTable = false;
  if ($check = $conn->query("SHOW TABLES LIKE 'don_gia_trang_phuc'")) {
    $hasPriceTable = $check->num_rows > 0;
    $check->free();
  }
  if ($hasPriceTable) {
    $priceSelect = "COALESCE(gia.DON_GIA, 0) AS DON_GIA, gia.HIEU_LUC_TU";
    $priceExpr   = 'COALESCE(gia.DON_GIA, 0)';
    $priceJoin   = "LEFT JOIN (\n      SELECT x.ID_TP, x.DON_GIA, x.NGAY_GIO AS HIEU_LUC_TU\n      FROM don_gia_trang_phuc x\n      JOIN (\n        SELECT ID_TP, MAX(NGAY_GIO) AS MG\n        FROM don_gia_trang_phuc\n        GROUP BY ID_TP\n      ) m ON m.ID_TP = x.ID_TP AND m.MG = x.NGAY_GIO\n    ) gia ON gia.ID_TP = tp.ID_TP";
  }
}

$imageJoin = "LEFT JOIN (\n  SELECT ID_TP, SUBSTRING_INDEX(GROUP_CONCAT(URL ORDER BY IS_COVER DESC, THU_TU ASC, ID_HA ASC SEPARATOR '||'), '||', 1) AS URL\n  FROM trang_phuc_hinh_anh\n  WHERE IS_ACTIVE = 1\n  GROUP BY ID_TP\n) ha ON ha.ID_TP = tp.ID_TP";

$sql = "SELECT\n  tp.ID_TP,\n  tp.TEN_TP,\n  tp.SIZE,\n  tp.MAU,\n  tp.TINH_TRANG,\n  tp.NGAY_GIAT_CUOI,\n  tp.GHI_CHU,\n  tp.ID_CN,\n  cn.TEN_CN,\n  loai.TEN_LOAI,\n  $priceSelect,\n  ha.URL AS HINH_ANH\nFROM trang_phuc tp\nJOIN CHI_NHANH cn ON cn.ID_CN = tp.ID_CN\nLEFT JOIN trang_phuc_loai loai ON loai.ID_LOAI = tp.ID_LOAI\n$priceJoin\n$imageJoin\nWHERE tp.IS_ACTIVE = 1";

$params = [];
$types  = '';

if ($kw !== '') {
  $sql .= " AND tp.TEN_TP LIKE CONCAT('%', ?, '%')";
  $types .= 's';
  $params[] = $kw;
}
if ($branch !== '' && ctype_digit($branch)) {
  $sql .= " AND tp.ID_CN = ?";
  $types .= 'i';
  $params[] = (int)$branch;
}
if ($category !== '' && ctype_digit($category)) {
  $sql .= " AND tp.ID_LOAI = ?";
  $types .= 'i';
  $params[] = (int)$category;
}
$sql .= " AND tp.TINH_TRANG = 'san_sang'";
if ($size !== '') {
  $sql .= " AND tp.SIZE = ?";
  $types .= 's';
  $params[] = $size;
}
if ($color !== '') {
  $sql .= " AND tp.MAU = ?";
  $types .= 's';
  $params[] = $color;
}
if ($minPrice !== '' && is_numeric($minPrice)) {
  $sql .= " AND $priceExpr >= ?";
  $types .= 'i';
  $params[] = (int)$minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
  $sql .= " AND $priceExpr <= ?";
  $types .= 'i';
  $params[] = (int)$maxPrice;
}

switch ($sort) {
  case 'price_asc':
    $sql .= " ORDER BY DON_GIA ASC, tp.TEN_TP ASC";
    break;
  case 'name_asc':
    $sql .= " ORDER BY tp.TEN_TP ASC";
    break;
  case 'name_desc':
    $sql .= " ORDER BY tp.TEN_TP DESC";
    break;
  default:
    $sql .= " ORDER BY DON_GIA DESC, tp.TEN_TP ASC";
}

$errorMessage = null;
$result = null;

$stmt = $conn->prepare($sql);
if (!$stmt) {
  $errorMessage = 'Không thể tải danh sách trang phục: ' . $conn->error;
} else {
  if ($types !== '') {
    $stmt->bind_param($types, ...$params);
  }
  if ($stmt->execute()) {
    $result = $stmt->get_result();
  } else {
    $errorMessage = 'Không thể tải danh sách trang phục: ' . $stmt->error;
  }
}

$statusMap = [
  'san_sang' => ['Sẵn sàng', 'bg-emerald-100/80 text-emerald-700 border border-emerald-200/70'],
  'dang_thue' => ['Đang thuê', 'bg-indigo-100/80 text-indigo-700 border border-indigo-200/70'],
  'bao_tri' => ['Bảo trì', 'bg-amber-100/80 text-amber-700 border border-amber-200/70'],
  'ngung' => ['Ngưng cho thuê', 'bg-rose-100/80 text-rose-700 border border-rose-200/70'],
];

$totalFound = $result ? $result->num_rows : 0;

$activeFilters = [];
if ($kw !== '') {
  $activeFilters[] = 'Từ khóa: “' . h($kw) . '”';
}
if ($branch !== '' && ctype_digit($branch)) {
  foreach ($branches as $b) {
    if ((string)$b['ID_CN'] === (string)$branch) {
      $activeFilters[] = 'Chi nhánh: ' . h($b['TEN_CN']);
      break;
    }
  }
}
if ($category !== '' && ctype_digit($category)) {
  foreach ($categories as $c) {
    if ((string)$c['ID_LOAI'] === (string)$category) {
      $activeFilters[] = 'Loại: ' . h($c['TEN_LOAI']);
      break;
    }
  }
}
if ($size !== '') {
  $activeFilters[] = 'Size: ' . h($size);
}
if ($color !== '') {
  $activeFilters[] = 'Màu: ' . h($color);
}
if ($minPrice !== '') {
  $activeFilters[] = 'Giá từ: ' . number_format((int)$minPrice, 0, ',', '.') . '₫';
}
if ($maxPrice !== '') {
  $activeFilters[] = 'Giá đến: ' . number_format((int)$maxPrice, 0, ',', '.') . '₫';
}
if ($rentFrom !== '') {
  $activeFilters[] = 'Thuê từ: ' . h($rentFrom);
}
if ($rentTo !== '') {
  $activeFilters[] = 'Đến: ' . h($rentTo);
}
if ($qty > 1) {
  $activeFilters[] = 'Số lượng: ' . h($qty);
}

$advancedOpen = $size !== '' || $color !== '' || $minPrice !== '' || $maxPrice !== '' || $rentFrom !== '' || $rentTo !== '' || $qty > 1;

$currentCustomerId = $_SESSION['user']['ID_TK'] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Thuê trang phục</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root {
      color-scheme: light;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    body {
      background: linear-gradient(180deg, #f5f6fa 0%, #ffffff 45%, #f2f5ff 100%);
    }
    .page-shell {
      max-width: 1200px;
      margin: 0 auto;
      padding: 3rem 1.5rem 4rem;
    }
    .hero-title {
      letter-spacing: -0.02em;
    }
    .info-card {
      background: rgba(255, 255, 255, 0.85);
      border-radius: 1.25rem;
      border: 1px solid rgba(119, 127, 252, 0.12);
      box-shadow: 0 24px 60px -35px rgba(46, 64, 161, 0.35);
      backdrop-filter: blur(8px);
    }
    .filter-chip {
      display: inline-flex;
      align-items: center;
      padding: 0.35rem 0.9rem;
      border-radius: 999px;
      font-size: 0.85rem;
      font-weight: 600;
      color: #3b3b9a;
      background: rgba(87, 97, 255, 0.14);
      border: 1px solid rgba(94, 103, 255, 0.15);
      margin: 0.25rem 0.4rem 0 0;
    }
    .filter-shell {
      position: relative;
      background: rgba(255, 255, 255, 0.92);
      border-radius: 1.1rem;
      border: 1px solid rgba(203, 213, 225, 0.55);
      box-shadow: 0 18px 50px -30px rgba(30, 64, 175, 0.28);
    }
    .filter-shell::after {
      content: '';
      position: absolute;
      inset: 0;
      border-radius: inherit;
      pointer-events: none;
      border: 1px solid rgba(147, 197, 253, 0.2);
      opacity: 0;
      transition: opacity 0.25s ease;
    }
    .filter-shell:focus-within::after {
      opacity: 1;
    }
    .square-img {
      width: 100%;
      aspect-ratio: 3 / 4;
      object-fit: cover;
      border-radius: 1rem;
    }
    .result-card {
      background: linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(248, 250, 255, 0.92) 100%);
      border-radius: 1.25rem;
      border: 1px solid rgba(148, 163, 184, 0.22);
      box-shadow: 0 18px 40px -28px rgba(30, 64, 175, 0.35);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .result-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 28px 60px -28px rgba(46, 64, 161, 0.35);
    }
    .status-badge {
      position: absolute;
      top: 0.75rem;
      left: 0.75rem;
      border-radius: 999px;
      padding: 0.35rem 0.9rem;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      box-shadow: 0 10px 25px -18px rgba(17, 24, 39, 0.5);
    }
    .estimate-text {
      color: #0f766e;
      font-weight: 600;
    }
    .advanced-panel {
      transition: max-height 0.35s ease;
      overflow: hidden;
    }
    .advanced-panel.hidden {
      max-height: 0;
      padding-top: 0 !important;
      padding-bottom: 0 !important;
    }
    .advanced-panel.visible {
      max-height: 800px;
    }
  </style>
</head>
<body class="min-h-screen text-slate-800">
  <div class="page-shell">
    <header class="text-center space-y-3 mb-10">
      <p class="text-sm font-medium uppercase tracking-[0.3em] text-indigo-500">Stygian Blue Studio</p>
      <h1 class="hero-title text-4xl md:text-5xl font-semibold text-slate-900">Khám phá tủ đồ phù hợp cho từng khoảnh khắc</h1>
      <p class="max-w-2xl mx-auto text-base text-slate-600">
        Lọc nhanh, xem thông tin rõ ràng và đặt lịch thuê chỉ trong một bước để bạn luôn sẵn sàng cho mọi sự kiện.
      </p>
      <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/80 border border-indigo-100 text-indigo-700 font-semibold shadow-sm">
        <?= number_format($totalFound, 0, ',', '.') ?> trang phục sẵn sàng phù hợp với tiêu chí của bạn
      </div>
    </header>

    <?php if ($activeFilters): ?>
      <section class="info-card px-6 py-5 mb-8">
        <p class="text-sm font-semibold text-indigo-900 mb-2">Bộ lọc đang áp dụng</p>
        <div>
          <?php foreach ($activeFilters as $label): ?>
            <span class="filter-chip"><?= $label ?></span>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <div class="grid gap-8 lg:grid-cols-[320px_minmax(0,1fr)]">
      <aside class="filter-shell p-6 space-y-6">
        <div class="space-y-1">
          <h2 class="text-lg font-semibold text-slate-900">Tìm kiếm nhanh</h2>
          <p class="text-sm text-slate-500">Điền các tiêu chí chính, sau đó mở rộng bộ lọc nâng cao nếu cần chi tiết hơn.</p>
        </div>
        <form id="filterForm" class="space-y-4">
          <div class="space-y-1">
            <label class="text-sm font-semibold text-slate-700" for="q">Từ khóa</label>
            <input id="q" type="text" name="q" value="<?=h($kw)?>" placeholder="Áo cưới, cosplay, vest..." class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 text-sm shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
          </div>

          <div class="grid gap-4">
            <label class="flex flex-col gap-1 text-sm">
              <span class="font-semibold text-slate-700">Chi nhánh</span>
              <select name="cn" class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
                <option value="">Tất cả</option>
                <?php foreach($branches as $b): ?>
                  <option value="<?=$b['ID_CN']?>" <?=selected($branch,(string)$b['ID_CN'])?>><?=h($b['TEN_CN'])?></option>
                <?php endforeach; ?>
              </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
              <span class="font-semibold text-slate-700">Loại trang phục</span>
              <select name="loai" class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
                <option value="">Tất cả</option>
                <?php foreach($categories as $c): ?>
                  <option value="<?=$c['ID_LOAI']?>" <?=selected($category,(string)$c['ID_LOAI'])?>><?=h($c['TEN_LOAI'])?></option>
                <?php endforeach; ?>
              </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
              <span class="font-semibold text-slate-700">Sắp xếp</span>
              <select name="sort" class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
                <option value="price_desc" <?=selected($sort,'price_desc')?>>Giá giảm dần</option>
                <option value="price_asc"  <?=selected($sort,'price_asc')?>>Giá tăng dần</option>
                <option value="name_asc"   <?=selected($sort,'name_asc')?>>Tên A → Z</option>
                <option value="name_desc"  <?=selected($sort,'name_desc')?>>Tên Z → A</option>
              </select>
            </label>
          </div>

          <div>
            <button type="button" id="toggleAdvanced" class="w-full rounded-xl border border-indigo-200 bg-indigo-50/80 px-4 py-2.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100" data-open="<?= $advancedOpen ? 'true' : 'false' ?>" aria-expanded="<?= $advancedOpen ? 'true' : 'false' ?>">
              <?= $advancedOpen ? 'Ẩn bộ lọc nâng cao' : 'Hiện bộ lọc nâng cao' ?>
            </button>
            <div id="advancedFilters" class="advanced-panel <?= $advancedOpen ? 'visible' : 'hidden' ?> mt-4 space-y-4">
              <div class="grid gap-3">
                <label class="flex flex-col gap-1 text-sm">
                  <span class="font-semibold text-slate-700">Size</span>
                  <select name="size" class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
                    <option value="">Tất cả</option>
                    <?php foreach($sizes as $s): ?>
                      <option value="<?=h($s)?>" <?=selected($size,$s)?>><?=h($s)?></option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <label class="flex flex-col gap-1 text-sm">
                  <span class="font-semibold text-slate-700">Màu sắc</span>
                  <select name="mau" class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
                    <option value="">Tất cả</option>
                    <?php foreach($colors as $c): ?>
                      <option value="<?=h($c)?>" <?=selected($color,$c)?>><?=h($c)?></option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <div class="grid grid-cols-2 gap-3">
                  <label class="flex flex-col gap-1 text-sm">
                    <span class="font-semibold text-slate-700">Giá từ (₫/ngày)</span>
                    <input type="number" name="min" value="<?=h($minPrice)?>" min="0" class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
                  </label>
                  <label class="flex flex-col gap-1 text-sm">
                    <span class="font-semibold text-slate-700">Đến (₫/ngày)</span>
                    <input type="number" name="max" value="<?=h($maxPrice)?>" min="0" class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
                  </label>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <label class="flex flex-col gap-1 text-sm">
                    <span class="font-semibold text-slate-700">Thuê từ</span>
                    <input type="date" name="from" value="<?=h($rentFrom)?>" class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
                  </label>
                  <label class="flex flex-col gap-1 text-sm">
                    <span class="font-semibold text-slate-700">Đến</span>
                    <input type="date" name="to" value="<?=h($rentTo)?>" class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
                  </label>
                </div>

                <label class="flex flex-col gap-1 text-sm">
                  <span class="font-semibold text-slate-700">Số lượng</span>
                  <input type="number" name="qty" value="<?=h($qty)?>" min="1" class="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200">
                </label>
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-3 pt-2">
            <button type="submit" class="w-full rounded-xl bg-gradient-to-r from-indigo-600 via-sky-600 to-cyan-500 px-4 py-3 text-sm font-semibold text-white shadow-md transition hover:shadow-lg">Áp dụng bộ lọc</button>
            <a href="?" class="w-full text-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600">Làm mới lựa chọn</a>
          </div>
        </form>
      </aside>

      <section class="space-y-6">
        <?php if ($errorMessage): ?>
          <div class="info-card px-6 py-6 text-center text-rose-600 font-semibold"><?=h($errorMessage)?></div>
        <?php elseif ($result && $result->num_rows > 0): ?>
          <div class="grid gap-6 sm:grid-cols-2">
            <?php while ($row = $result->fetch_assoc()):
              $price = (int)$row['DON_GIA'];
              $statusKey = $row['TINH_TRANG'] ?? '';
              $badge = $statusMap[$statusKey] ?? ['Chưa rõ', 'bg-slate-100/90 text-slate-600 border border-slate-200/80'];
              $image = $row['HINH_ANH'] ?: '../../../public/images/bg01.png';
            ?>
              <article class="result-card p-5 flex flex-col">
                <div class="relative mb-4">
                  <img src="<?=h($image)?>" alt="<?=h($row['TEN_TP'])?>" class="square-img shadow-sm">
                  <div class="status-badge <?=h($badge[1])?>">
                    <?=h($badge[0])?>
                  </div>
                </div>

                <h2 class="text-xl font-semibold text-slate-900 mb-2"><?=h($row['TEN_TP'])?></h2>
                <ul class="space-y-1 text-sm text-slate-600">
                  <?php if (!empty($row['TEN_LOAI'])): ?>
                    <li>Loại: <span class="font-medium text-slate-800"><?=h($row['TEN_LOAI'])?></span></li>
                  <?php endif; ?>
                  <li>Chi nhánh: <span class="font-medium text-slate-800"><?=h($row['TEN_CN'])?></span></li>
                  <?php if (!empty($row['SIZE'])): ?>
                    <li>Size: <span class="font-medium text-slate-800"><?=h($row['SIZE'])?></span></li>
                  <?php endif; ?>
                  <?php if (!empty($row['MAU'])): ?>
                    <li>Màu sắc: <span class="font-medium text-slate-800"><?=h($row['MAU'])?></span></li>
                  <?php endif; ?>
                  <?php if (!empty($row['NGAY_GIAT_CUOI']) && $row['NGAY_GIAT_CUOI'] !== '0000-00-00'): ?>
                    <li>Giặt gần nhất: <span class="font-medium text-slate-800"><?=h($row['NGAY_GIAT_CUOI'])?></span></li>
                  <?php endif; ?>
                  <?php if (!empty($row['GHI_CHU'])): ?>
                    <li>Ghi chú: <span class="font-medium text-slate-800"><?=h($row['GHI_CHU'])?></span></li>
                  <?php endif; ?>
                </ul>

                <div class="mt-4 space-y-1">
                  <p class="text-2xl font-bold text-indigo-700"><?= number_format($price, 0, ',', '.') ?> <span class="text-sm font-medium text-slate-500">₫/ngày</span></p>
                  <?php if (!empty($row['HIEU_LUC_TU'])): ?>
                    <p class="text-xs text-slate-500">Giá áp dụng từ: <?=h($row['HIEU_LUC_TU'])?></p>
                  <?php endif; ?>
                  <p class="text-xs text-slate-500">Ước tính tự động cập nhật khi bạn chọn ngày và số lượng.</p>
                  <p class="text-sm"><span class="text-slate-600">Ước tính:</span> <span class="estimate-text" data-estimate-for="<?= (int)$row['ID_TP'] ?>">—</span></p>
                </div>

                <form action="lienhe.php" method="POST" class="mt-5 pt-5 border-t border-slate-200 space-y-3">
                  <input type="hidden" name="id_tp" value="<?=h($row['ID_TP'])?>">
                  <input type="hidden" name="service_id" value="thue_trang_phuc">
                  <input type="hidden" name="ID_KH" value="<?=h($currentCustomerId ?? '')?>">
                  <input type="hidden" name="from" value="<?=h($rentFrom)?>">
                  <input type="hidden" name="to" value="<?=h($rentTo)?>">
                  <input type="hidden" name="qty" value="<?=h($qty)?>">
                  <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:bg-slate-400" <?= ($statusKey !== 'san_sang') ? 'disabled' : '' ?>>Đặt lịch thuê</button>
                </form>
              </article>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <div class="info-card px-6 py-6 text-center text-slate-600 font-medium">Không có trang phục nào phù hợp với bộ lọc hiện tại.</div>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <script>
    (function(){
      const advanced = document.getElementById('advancedFilters');
      const toggleBtn = document.getElementById('toggleAdvanced');

      if (advanced && toggleBtn) {
        const setState = (open) => {
          advanced.classList.toggle('hidden', !open);
          advanced.classList.toggle('visible', open);
          toggleBtn.dataset.open = open ? 'true' : 'false';
          toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
          toggleBtn.textContent = open ? 'Ẩn bộ lọc nâng cao' : 'Hiện bộ lọc nâng cao';
        };

        if (toggleBtn.dataset.open === 'true') {
          setState(true);
        }

        toggleBtn.addEventListener('click', () => {
          const next = toggleBtn.dataset.open !== 'true';
          setState(next);
        });
      }

      const fromInput = document.querySelector('input[name="from"]');
      const toInput = document.querySelector('input[name="to"]');
      const qtyInput = document.querySelector('input[name="qty"]');

      const from = fromInput ? fromInput.value : '';
      const to = toInput ? toInput.value : '';
      const qty = Math.max(1, qtyInput ? parseInt(qtyInput.value || '1', 10) : 1);

      function daysBetween(a, b){
        const start = new Date(a);
        const end = new Date(b);
        if (isNaN(start) || isNaN(end)) return 0;
        const ms = end.setHours(12,0,0,0) - start.setHours(12,0,0,0);
        return Math.max(0, Math.ceil(ms / 86400000));
      }

      const days = daysBetween(from, to);

      document.querySelectorAll('[data-estimate-for]').forEach(span => {
        if (days <= 0) {
          span.textContent = 'Chọn ngày để ước tính';
          return;
        }

        const priceContainer = span.closest('article');
        if (!priceContainer) return;
        const priceText = priceContainer.querySelector('.text-2xl');
        if (!priceText) return;
        const numeric = priceText.textContent.replace(/[^\d]/g, '');
        const price = parseInt(numeric || '0', 10);
        const estimate = price * days * qty;
        span.textContent = new Intl.NumberFormat('vi-VN').format(estimate) + ' ₫ (' + days + ' ngày × ' + qty + ')';
      });
    })();
  </script>
</body>
</html>
