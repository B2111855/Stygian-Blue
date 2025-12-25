<?php
// pages/trangphuc/index.php
// Hiển thị danh sách trang phục cho thuê
include '../../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function selected($a,$b){ return $a===$b ? 'selected' : ''; }

if (!function_exists('tp_app_base_uri')) {
  function tp_app_base_uri(){
    static $base = null;
    if ($base !== null) {
      return $base;
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $script = str_replace('\\', '/', (string)$script);
    $pos = strpos($script, '/app/');
    if ($pos !== false) {
      $base = substr($script, 0, $pos);
    } else {
      $base = '';
    }
    $base = rtrim($base, '/');
    return $base;
  }
}

if (!function_exists('tp_asset_url')) {
  function tp_asset_url($path, $fallback = ''){
    $path = trim((string)$path);
    if ($path === '' && $fallback !== '') {
      $path = trim($fallback);
    }
    if ($path === '') {
      return '';
    }
    $path = str_replace('\\', '/', $path);
    if (preg_match('#^(?:https?:)?//#i', $path) || strpos($path, 'data:') === 0) {
      return $path;
    }
    if (strpos($path, '../') === 0 || strpos($path, './') === 0) {
      return $path;
    }
    $normalized = '/' . ltrim($path, '/');
    $base = tp_app_base_uri();
    if ($base !== '' && strpos($normalized, $base . '/') === 0) {
      return $normalized;
    }
    return ($base === '' ? '' : $base) . $normalized;
  }
}

if (!function_exists('tp_app_root_path')) {
  function tp_app_root_path(){
    static $root = null;
    if ($root !== null) {
      return $root;
    }
    $root = dirname(__DIR__, 3);
    return $root;
  }
}

if (!function_exists('tp_normalize_asset_input')) {
  function tp_normalize_asset_input($path){
    $path = trim((string)$path);
    if ($path === '') {
      return '';
    }
    if (preg_match('#^(?:https?:)?//#i', $path) || strpos($path, 'data:') === 0) {
      return $path;
    }
    $path = str_replace('\\', '/', $path);
    while (strpos($path, '../') === 0) {
      $path = substr($path, 3);
    }
    $path = preg_replace('#^\./+#', '', $path);
    return ltrim($path, '/');
  }
}

if (!function_exists('tp_asset_exists')) {
  function tp_asset_exists($path){
    $normalized = tp_normalize_asset_input($path);
    if ($normalized === '') {
      return false;
    }
    if (preg_match('#^(?:https?:)?//#i', $normalized) || strpos($normalized, 'data:') === 0) {
      return true;
    }
    $full = rtrim(tp_app_root_path(), '/\\') . '/' . $normalized;
    if (is_file($full)) {
      return true;
    }
    $fallbackFull = realpath(__DIR__ . '/' . $normalized);
    return $fallbackFull !== false && is_file($fallbackFull);
  }
}

if (!function_exists('tp_find_asset_variant')) {
  function tp_find_asset_variant($path){
    $normalized = tp_normalize_asset_input($path);
    if ($normalized === '' || preg_match('#^(?:https?:)?//#i', $normalized) || strpos($normalized, 'data:') === 0) {
      return '';
    }
    $directory = pathinfo($normalized, PATHINFO_DIRNAME);
    $directory = $directory === '.' ? '' : $directory;
    $filename = pathinfo($normalized, PATHINFO_FILENAME);
    $extension = pathinfo($normalized, PATHINFO_EXTENSION);
    if ($filename === '') {
      return '';
    }
    if (!preg_match('/^(.+)_\d+$/', $filename, $matches)) {
      return '';
    }
    $baseName = $matches[1];
    $searchDir = rtrim(tp_app_root_path(), '/\\') . ($directory === '' ? '' : '/' . $directory);
    if (!is_dir($searchDir)) {
      return '';
    }
    $pattern = $searchDir . '/' . $baseName . '_*' . ($extension !== '' ? '.' . $extension : '');
    $candidates = glob($pattern);
    if (!$candidates) {
      return '';
    }
    sort($candidates);
    $picked = end($candidates);
    $relative = $directory === '' ? basename($picked) : $directory . '/' . basename($picked);
    return $relative;
  }
}

if (!function_exists('tp_pick_image_path')) {
  function tp_pick_image_path($path, $fallback = ''){
    $normalized = tp_normalize_asset_input($path);
    if ($normalized !== '' && tp_asset_exists($normalized)) {
      return $normalized;
    }
    if ($normalized !== '') {
      $variant = tp_find_asset_variant($normalized);
      if ($variant !== '' && tp_asset_exists($variant)) {
        return $variant;
      }
    }
    return tp_normalize_asset_input($fallback);
  }
}

$defaultImagePath = 'public/images/bg01.png';

// ==== Input (GET) ====
$kw        = trim($_GET['q'] ?? '');
$branch    = $_GET['cn'] ?? '';
$category  = $_GET['loai'] ?? '';
$minPrice  = $_GET['min'] ?? '';
$maxPrice  = $_GET['max'] ?? '';
$sort      = $_GET['sort'] ?? 'price_desc';

// Debug: log giá trị để kiểm tra
// error_log("DEBUG trangphuc.php - URL: " . $_SERVER['REQUEST_URI']);
// error_log("DEBUG trangphuc.php - minPrice: " . var_export($minPrice, true));
// error_log("DEBUG trangphuc.php - maxPrice: " . var_export($maxPrice, true));
// error_log("DEBUG trangphuc.php - GET: " . print_r($_GET, true));

// ==== Lấy danh sách chi nhánh, loại cho bộ lọc ====
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

// ==== New schema: GIA_THUE trực tiếp trong bảng trang_phuc ====
$priceExpr = 'COALESCE(tp.GIA_THUE, 0)';

$imageJoin = "LEFT JOIN (\n  SELECT ID_TP, SUBSTRING_INDEX(GROUP_CONCAT(URL ORDER BY IS_COVER DESC, THU_TU ASC, ID_HA ASC SEPARATOR '||'), '||', 1) AS URL\n  FROM trang_phuc_hinh_anh\n  WHERE IS_ACTIVE = 1\n  GROUP BY ID_TP\n) ha ON ha.ID_TP = tp.ID_TRANG_PHUC";

$sql = "SELECT\n  tp.ID_TRANG_PHUC AS ID_TP,\n  tp.TEN AS TEN_TP,\n  tp.SIZE,\n  tp.MAU_SAC AS MAU,\n  tp.TRANG_THAI AS TINH_TRANG,\n  tp.GHI_CHU,\n  tp.ID_CN,\n  tp.SCOPE_TYPE,\n  tp.ID_CN_OWNER,\n  cn.TEN_CN,\n  COALESCE(tp.GIA_THUE, 0) AS DON_GIA,\n  ha.URL AS HINH_ANH,\n  loai.TEN_LOAI,\n  loai.ID_LOAI\nFROM trang_phuc tp\nLEFT JOIN chi_nhanh cn ON cn.ID_CN = tp.ID_CN\nLEFT JOIN trang_phuc_loai loai ON loai.ID_LOAI = tp.ID_LOAI\n$imageJoin";

$params = [];
$types  = '';

if ($kw !== '') {
  $sql .= " AND tp.TEN LIKE CONCAT('%', ?, '%')";
  $types .= 's';
  $params[] = $kw;
}
if ($branch !== '' && ctype_digit($branch)) {
  // Lọc theo chi nhánh: bao gồm cả trang phục global
  $sql .= " AND (tp.ID_CN = ? OR tp.SCOPE_TYPE = 'global')";
  $types .= 'i';
  $params[] = (int)$branch;
}
if ($category !== '' && ctype_digit($category)) {
  $sql .= " AND tp.ID_LOAI = ?";
  $types .= 'i';
  $params[] = (int)$category;
}
$sql .= " AND tp.TRANG_THAI = 'available'";
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
    $sql .= " ORDER BY DON_GIA ASC, tp.TEN ASC";
    break;
  case 'name_asc':
    $sql .= " ORDER BY tp.TEN ASC";
    break;
  case 'name_desc':
    $sql .= " ORDER BY tp.TEN DESC";
    break;
  default:
    $sql .= " ORDER BY DON_GIA DESC, tp.TEN ASC";
}

$errorMessage = null;
$result = null;
$rawRows = [];
$catalogItems = [];

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

if ($result) {
  while ($row = $result->fetch_assoc()) {
    $rawRows[] = $row;
  }
  $result->free();
  $result = null;
}

$statusMap = [
  'available' => ['Sẵn sàng', 'bg-emerald-100/80 text-emerald-700 border border-emerald-200/70'],
  'rented' => ['Đang thuê', 'bg-indigo-100/80 text-indigo-700 border border-indigo-200/70'],
  'maintenance' => ['Bảo trì', 'bg-amber-100/80 text-amber-700 border border-amber-200/70'],
  // Legacy mappings for backward compatibility
  'san_sang' => ['Sẵn sàng', 'bg-emerald-100/80 text-emerald-700 border border-emerald-200/70'],
  'dang_thue' => ['Đang thuê', 'bg-indigo-100/80 text-indigo-700 border border-indigo-200/70'],
  'bao_tri' => ['Bảo trì', 'bg-amber-100/80 text-amber-700 border border-amber-200/70'],
  'ngung' => ['Ngưng cho thuê', 'bg-rose-100/80 text-rose-700 border border-rose-200/70'],
];

// Chỉ hiển thị theo LOẠI trang phục (group by ID_LOAI)
$typeMap = [];
foreach ($rawRows as $row) {
  $idLoai = $row['ID_LOAI'] ?? null;
  if ($idLoai === null || $idLoai === '') {
    // Bỏ qua item chưa được gán loại theo yêu cầu chỉ hiển thị loại
    continue;
  }
  if (!isset($typeMap[$idLoai])) {
    $typeMap[$idLoai] = [
      'ID_LOAI' => $idLoai,
      'TEN_LOAI' => $row['TEN_LOAI'] ?? 'Chưa rõ',
      'TOTAL_ITEMS' => 0,
      'AVAILABLE_ITEMS' => 0,
      'MIN_PRICE_AVAILABLE' => null,
      'MAX_PRICE_AVAILABLE' => null,
      'MIN_PRICE_OVERALL' => null,
      'MAX_PRICE_OVERALL' => null,
      'REP_IMAGE' => $row['HINH_ANH'] ?? '',
      'REP_ITEM_ID' => $row['ID_TP'] ?? null,
    ];
  }
  $typeMap[$idLoai]['TOTAL_ITEMS']++;
  $priceInt = (int)$row['DON_GIA'];
  if ($typeMap[$idLoai]['MIN_PRICE_OVERALL'] === null || $priceInt < $typeMap[$idLoai]['MIN_PRICE_OVERALL']) {
    $typeMap[$idLoai]['MIN_PRICE_OVERALL'] = $priceInt;
    // Cập nhật representative item ngay cả khi chưa khả dụng để có ID chi tiết
    $typeMap[$idLoai]['REP_ITEM_ID'] = $row['ID_TP'] ?? $typeMap[$idLoai]['REP_ITEM_ID'];
  }
  if ($typeMap[$idLoai]['MAX_PRICE_OVERALL'] === null || $priceInt > $typeMap[$idLoai]['MAX_PRICE_OVERALL']) {
    $typeMap[$idLoai]['MAX_PRICE_OVERALL'] = $priceInt;
  }
  $status = $row['TINH_TRANG'] ?? '';
  $isAvail = in_array($status, ['available','san_sang']);
  if ($isAvail) {
    $typeMap[$idLoai]['AVAILABLE_ITEMS']++;
    if ($typeMap[$idLoai]['MIN_PRICE_AVAILABLE'] === null || $priceInt < $typeMap[$idLoai]['MIN_PRICE_AVAILABLE']) {
      $typeMap[$idLoai]['MIN_PRICE_AVAILABLE'] = $priceInt;
      // cập nhật hình đại diện ưu tiên item khả dụng
      if (!empty($row['HINH_ANH'])) {
        $typeMap[$idLoai]['REP_IMAGE'] = $row['HINH_ANH'];
      }
      // Luôn cập nhật representative item id sang item khả dụng rẻ nhất
      $typeMap[$idLoai]['REP_ITEM_ID'] = $row['ID_TP'] ?? $typeMap[$idLoai]['REP_ITEM_ID'];
    }
    if ($typeMap[$idLoai]['MAX_PRICE_AVAILABLE'] === null || $priceInt > $typeMap[$idLoai]['MAX_PRICE_AVAILABLE']) {
      $typeMap[$idLoai]['MAX_PRICE_AVAILABLE'] = $priceInt;
    }
  }
}

// Chuyển sang mảng để render
$catalogItems = [];
foreach ($typeMap as $g) {
  // Giá hiển thị: ưu tiên giá của item khả dụng, nếu không dùng giá chung
  $minPrice = $g['MIN_PRICE_AVAILABLE'] !== null ? $g['MIN_PRICE_AVAILABLE'] : ($g['MIN_PRICE_OVERALL'] ?? 0);
  $maxPrice = $g['MAX_PRICE_AVAILABLE'] !== null ? $g['MAX_PRICE_AVAILABLE'] : ($g['MAX_PRICE_OVERALL'] ?? 0);
  $catalogItems[] = [
    'ID_LOAI' => $g['ID_LOAI'],
    'TEN_LOAI' => $g['TEN_LOAI'],
    'TOTAL_ITEMS' => $g['TOTAL_ITEMS'],
    'AVAILABLE_ITEMS' => $g['AVAILABLE_ITEMS'],
    'MIN_PRICE' => $minPrice,
    'MAX_PRICE' => $maxPrice,
    'HINH_ANH' => $g['REP_IMAGE'],
    'REP_ITEM_ID' => $g['REP_ITEM_ID'],
  ];
}
$totalFound = count($catalogItems);

$defaultImageResolved = tp_pick_image_path($defaultImagePath, $defaultImagePath);
$defaultImageUrl = tp_asset_url($defaultImageResolved === '' ? $defaultImagePath : $defaultImageResolved, $defaultImagePath);
foreach ($catalogItems as &$item) {
  $resolvedPath = tp_pick_image_path($item['HINH_ANH'] ?? '', $defaultImagePath);
  $item['HINH_ANH'] = tp_asset_url($resolvedPath === '' ? $defaultImagePath : $resolvedPath, $defaultImagePath);
}
unset($item);

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
if ($minPrice !== '' && (int)$minPrice > 0) {
  $activeFilters[] = 'Giá từ: ' . number_format((int)$minPrice, 0, ',', '.') . '₫';
}
if ($maxPrice !== '' && (int)$maxPrice > 0) {
  $activeFilters[] = 'Giá đến: ' . number_format((int)$maxPrice, 0, ',', '.') . '₫';
}

$advancedOpen = ($minPrice !== '' && (int)$minPrice > 0) || ($maxPrice !== '' && (int)$maxPrice > 0);

$currentCustomerId = $_SESSION['user']['ID_TK'] ?? null;

// Số loại có ít nhất một item khả dụng
$readyCount = 0;
foreach ($catalogItems as $item) {
  if ((int)$item['AVAILABLE_ITEMS'] > 0) {
    $readyCount++;
  }
}

$heroBackgroundUrl = $defaultImageUrl;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Thuê trang phục</title>
  <?= sb_tailwind_link_tag(); ?>
  <style>
    :root {
      color-scheme: light;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    body {
      background: linear-gradient(180deg, #f5f6fa 0%, #ffffff 45%, #f2f5ff 100%);
    }
    .page-shell {
      max-width: 1440px;
      margin: 0 auto;
      padding: 3rem 1.75rem 4.5rem;
    }
    .hero-grid {
      display: grid;
      gap: 2.5rem;
      align-items: center;
    }
    @media (min-width: 1024px) {
      .hero-grid {
        grid-template-columns: minmax(0, 1fr) 460px;
      }
    }
    .hero-title {
      letter-spacing: -0.02em;
    }
    .hero-visual {
      position: relative;
      border-radius: 1.75rem;
      overflow: hidden;
      min-height: 320px;
      background: radial-gradient(circle at top, rgba(79, 70, 229, 0.3), rgba(15, 23, 42, 0.85)),
        url('<?=h($heroBackgroundUrl)?>') center/cover;
      box-shadow: 0 35px 80px -40px rgba(15, 23, 42, 0.7);
    }
    .hero-visual::after {
      content: '';
      position: absolute;
      inset: 0;
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: inherit;
    }
    .hero-quick-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
    }
    .hero-pill {
      flex: 1 1 220px;
      display: inline-flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.9rem 1.1rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.85);
      border: 1px solid rgba(99, 102, 241, 0.2);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
    }
    .hero-pill span {
      font-size: 0.9rem;
      font-weight: 600;
      color: #4338ca;
    }
    .hero-metrics {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .hero-metric {
      flex: 1 1 160px;
      padding: 1rem 1.2rem;
      border-radius: 1.25rem;
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid rgba(148, 163, 184, 0.35);
      box-shadow: 0 18px 40px -30px rgba(30, 41, 59, 0.4);
    }
    .hero-metric p {
      margin: 0;
    }
    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
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
    @media (min-width: 1024px) {
      .filter-shell {
        position: sticky;
        top: 2rem;
        align-self: flex-start;
      }
    }
    .portrait-img {
      width: 100%;
      aspect-ratio: 3 / 4;
      object-fit: cover;
      border-radius: 1rem;
    }
    .meta-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.35rem 0.75rem;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
      color: #334155;
      background: rgba(226, 232, 240, 0.75);
      border: 1px solid rgba(148, 163, 184, 0.35);
    }
    .branch-chip {
      color: #1d4ed8;
      background: rgba(59, 130, 246, 0.12);
      border-color: rgba(59, 130, 246, 0.4);
    }
    .result-card {
      background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 255, 0.92) 100%);
      border-radius: 1.1rem;
      border: 1px solid rgba(148, 163, 184, 0.18);
      box-shadow: 0 18px 40px -30px rgba(30, 64, 175, 0.35);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      padding: 1.25rem;
    }
    .results-grid {
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      align-items: stretch;
    }
    .result-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 28px 60px -28px rgba(46, 64, 161, 0.35);
    }
    /* status-badge removed per UX request */
    .card-attributes {
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
      font-size: 0.75rem;
    }
    .card-actions {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding-top: 0.75rem;
    }
    .card-actions a {
      flex: 1;
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
    <nav class="mb-6 flex items-center gap-2">
      <?php $tabActive = ($_GET['tab'] ?? 'items') === 'items'; ?>
      <a href="?tab=items" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold border <?php echo $tabActive ? 'bg-white text-indigo-700 border-indigo-200' : 'bg-white text-slate-700 border-slate-200'; ?>">Trang phục</a>
      <a href="?tab=packages" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold border <?php echo !$tabActive ? 'bg-white text-indigo-700 border-indigo-200' : 'bg-white text-slate-700 border-slate-200'; ?>">Gói trang phục</a>
    </nav>
    <?php $currentTab = ($_GET['tab'] ?? 'items'); ?>
    <?php if ($currentTab === 'items'): ?>
    <header class="hero-grid mb-12">
      <div class="space-y-6">
        <div class="space-y-3">
          <p class="text-sm font-medium uppercase tracking-[0.3em] text-indigo-500">Stygian Blue Studio</p>
          <h1 class="hero-title text-4xl md:text-5xl font-semibold text-slate-900">Khám phá các loại trang phục phù hợp</h1>
          <p class="text-base text-slate-600 max-w-2xl">
            Duyệt theo loại trang phục, xem khoảng giá và số lượng khả dụng. Sau đó chọn item cụ thể với size và màu bạn thích.
          </p>
        </div>
        <div class="hero-metrics">
          <div class="hero-metric">
            <p class="text-sm font-medium text-slate-500">Loại có sẵn</p>
            <p class="text-3xl font-semibold text-slate-900"><?= number_format($readyCount, 0, ',', '.') ?></p>
          </div>
          <div class="hero-metric">
            <p class="text-sm font-medium text-slate-500">Chi nhánh</p>
            <p class="text-3xl font-semibold text-slate-900"><?= number_format(count($branches), 0, ',', '.') ?></p>
          </div>
          <div class="hero-metric">
            <p class="text-sm font-medium text-slate-500">Tổng loại</p>
            <p class="text-3xl font-semibold text-slate-900"><?= number_format(count($categories), 0, ',', '.') ?></p>
          </div>
        </div>
      </div>
      <div class="hero-visual text-white">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/40 via-transparent to-slate-900/80"></div>
        <div class="absolute bottom-6 left-6 right-6 rounded-2xl border border-white/20 bg-white/15 p-5 backdrop-blur-md">
          <p class="text-xs uppercase tracking-[0.3em] text-white/70">Lookbook</p>
          <p class="text-2xl font-semibold leading-tight">Tìm loại trang phục yêu thích trong <span class="text-indigo-200">dưới 1 phút</span></p>
          <p class="text-sm text-white/80 mt-2">Duyệt theo loại, xem khoảng giá và số lượng còn. Sau đó chọn item với size & màu phù hợp.</p>
        </div>
      </div>
    </header>
    <?php else: ?>
    <header class="mb-8">
      <p class="text-sm font-medium uppercase tracking-[0.3em] text-indigo-600">Stygian Blue Studio</p>
      <h1 class="text-3xl md:text-4xl font-bold text-slate-900">Gói trang phục</h1>
      <p class="text-slate-600">Chọn gói đã được kurate để phù hợp concept, đặt nhanh với một bước.</p>
    </header>
    <?php endif; ?>

    <div class="space-y-6">

      <section class="space-y-6">
        <?php if ($errorMessage): ?>
          <div class="info-card px-6 py-6 text-center text-rose-600 font-semibold"><?=h($errorMessage)?></div>
        <?php elseif (!empty($catalogItems)): ?>
          <div class="results-grid grid gap-4 lg:gap-5">
            <?php foreach ($catalogItems as $type):
              $minPrice = (int)$type['MIN_PRICE'];
              $maxPrice = (int)$type['MAX_PRICE'];
              $image = $type['HINH_ANH'] ?: $defaultImageUrl;
              $avail = (int)$type['AVAILABLE_ITEMS'];
              $total = (int)$type['TOTAL_ITEMS'];
              $utilPercent = $total > 0 ? round(($total - $avail) * 100 / $total) : 0; // phần trăm đang dùng
              $statusKey = $avail > 0 ? 'available' : 'rented';
              $badge = $statusMap[$statusKey] ?? ['Loại', 'bg-slate-100/90 text-slate-600 border border-slate-200/80'];
              $detailId = $type['REP_ITEM_ID'] ?? null;
              $detailHref = $detailId ? ('trangphuc_chitiet.php?id=' . urlencode($detailId)) : '#';
              
              // Hiển thị giá dạng khoảng nếu min != max
              $priceDisplay = $minPrice === $maxPrice 
                ? number_format($minPrice, 0, ',', '.') 
                : number_format($minPrice, 0, ',', '.') . ' - ' . number_format($maxPrice, 0, ',', '.');
            ?>
              <article class="result-card flex h-full flex-col gap-3" data-type-id="<?=(int)$type['ID_LOAI']?>">
                <div class="relative">
                  <img src="<?=h($image)?>" alt="<?=h($type['TEN_LOAI'])?>" class="portrait-img shadow-sm" loading="lazy" onerror="this.onerror=null;this.src='<?=h($defaultImageUrl)?>';">
                </div>
                <div class="space-y-2">
                  <div class="space-y-1">
                    <h2 class="text-base font-semibold leading-snug text-slate-900 line-clamp-2"><?=h($type['TEN_LOAI'])?></h2>
                    <p class="text-xs text-slate-500">Còn <span class="font-semibold text-emerald-600"><?=$avail?></span> / <?=$total?> bộ</p>
                  </div>
                  <div class="flex items-baseline justify-between gap-2 pt-1">
                    <div class="text-left">
                      <p class="text-xl font-bold text-indigo-700 leading-tight" data-min-price="<?= $minPrice ?>" data-max-price="<?= $maxPrice ?>"><?= $priceDisplay ?></p>
                      <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">VND/NGÀY</p>
                    </div>
                    <div class="text-right text-[11px] text-slate-400">
                      Đang dùng: <?=$utilPercent?>%
                    </div>
                  </div>
                </div>
                <div class="text-xs text-slate-500 space-y-1">
                  <p class="estimate-text font-semibold text-indigo-600" data-estimate-for-type="<?= (int)$type['ID_LOAI'] ?>">Click "Xem trang phục" để chọn item</p>
                  <p>Giá hiển thị là khoảng giá thuê/ngày của các item trong loại này.</p>
                </div>
                <div class="card-actions mt-auto">
                  <button type="button" class="btn-view-items inline-flex flex-1 items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-slate-800" data-type-id="<?=(int)$type['ID_LOAI']?>" data-type-name="<?=h($type['TEN_LOAI'])?>">
                    Xem trang phục
                  </button>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="info-card px-6 py-6 text-center text-slate-600 font-medium">Không có trang phục nào phù hợp với bộ lọc hiện tại.</div>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <!-- Modal Xem Trang Phục Theo Loại -->
  <div id="itemsModal" class="fixed inset-0 hidden" style="position: fixed !important; z-index: 99999 !important;">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" style="z-index: 99999;" onclick="document.getElementById('closeModal').click()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4 md:p-6 lg:p-8" style="z-index: 100000; pointer-events: none;">
      <div class="relative bg-white rounded-3xl shadow-2xl w-full my-4" style="max-width: 95vw; max-height: 92vh; pointer-events: auto;">
        <div class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between rounded-t-3xl">
          <div>
            <h2 id="modalTypeName" class="text-2xl font-bold text-slate-900"></h2>
            <p class="text-sm text-slate-600">Chọn trang phục bạn muốn thuê</p>
          </div>
          <button type="button" id="closeModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        
        <div id="modalContent" class="p-6 overflow-y-auto" style="max-height: calc(92vh - 100px);">
          <div class="flex items-center justify-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-4 border-indigo-200 border-t-indigo-600"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      // Chuyển hướng người dùng chọn item để xem chi tiết giá
      document.querySelectorAll('[data-estimate-for-type]').forEach(span => {
        span.textContent = 'Chọn item để xem chi tiết giá';
      });

      // Modal functionality
      const modal = document.getElementById('itemsModal');
      const modalContent = document.getElementById('modalContent');
      const modalTypeName = document.getElementById('modalTypeName');
      const closeModalBtn = document.getElementById('closeModal');

      function openModal(typeId, typeName) {
        modalTypeName.textContent = typeName;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
        
        // Show loading
        modalContent.innerHTML = '<div class="flex items-center justify-center py-12"><div class="animate-spin rounded-full h-12 w-12 border-4 border-indigo-200 border-t-indigo-600"></div></div>';
        
        // Fetch items - use absolute path from root
        const apiUrl = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '') + '/../api/get_items_by_type.php?loai=' + typeId;
        console.log('Fetching from:', apiUrl); // Debug log
        
        fetch(apiUrl)
          .then(res => {
            console.log('Response status:', res.status); // Debug log
            return res.json();
          })
          .then(data => {
            console.log('Response data:', data); // Debug log
            if (!data.success) {
              modalContent.innerHTML = '<div class="text-center py-12 text-rose-600 font-semibold">' + (data.message || 'Có lỗi xảy ra') + '</div>';
              return;
            }
            
            if (!data.items || data.items.length === 0) {
              modalContent.innerHTML = '<div class="text-center py-12 text-slate-600">Không có trang phục nào trong loại này. (Tìm thấy: ' + (data.count || 0) + ')</div>';
              return;
            }
            
            // Render items
            let html = '<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">';
            data.items.forEach(item => {
              const isAvailable = item.TRANG_THAI === 'available';
              const statusClass = isAvailable ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500';
              const statusText = isAvailable ? 'Sẵn sàng' : 'Đang thuê';
              
              html += `
                <article class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm hover:shadow-md transition">
                  <div class="relative">
                    <img src="${item.HINH_ANH || data.defaultImage}" alt="${item.TEN}" class="w-full aspect-[3/4] object-cover" onerror="this.onerror=null;this.src='${data.defaultImage}'">
                    <span class="absolute top-3 right-3 px-3 py-1 rounded-full text-xs font-semibold ${statusClass}">
                      ${statusText}
                    </span>
                  </div>
                  <div class="p-4 space-y-3">
                    <h3 class="text-base font-semibold text-slate-900 line-clamp-2">${item.TEN}</h3>
                    <div class="flex items-center gap-3 text-sm text-slate-600">
                      ${item.SIZE ? '<span class="px-2 py-1 bg-slate-100 rounded-lg">Size: ' + item.SIZE + '</span>' : ''}
                      ${item.MAU_SAC ? '<span class="px-2 py-1 bg-slate-100 rounded-lg">' + item.MAU_SAC + '</span>' : ''}
                    </div>
                    <p class="text-sm text-slate-500">${item.TEN_CN}</p>
                    <div class="flex items-baseline gap-2">
                      <p class="text-2xl font-bold text-indigo-600">${new Intl.NumberFormat('vi-VN').format(item.GIA_THUE)}</p>
                      <p class="text-xs text-slate-500">₫/ngày</p>
                    </div>
                    ${isAvailable 
                      ? `<div class="flex gap-2">
                          <a href="trangphuc_chitiet.php?id=${item.ID_TRANG_PHUC}" class="flex-1 text-center rounded-lg border-2 border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-indigo-500 hover:text-indigo-600 hover:bg-indigo-50">Xem chi tiết</a>
                          <a href="trangphuc_datthue.php?id=${item.ID_TRANG_PHUC}" class="flex-1 text-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">Đặt thuê</a>
                        </div>`
                      : `<button disabled class="block w-full text-center rounded-lg bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-400 cursor-not-allowed">Đang được thuê</button>`
                    }
                  </div>
                </article>
              `;
            });
            html += '</div>';
            modalContent.innerHTML = html;
          })
          .catch(err => {
            modalContent.innerHTML = '<div class="text-center py-12 text-rose-600 font-semibold">Không thể tải dữ liệu. Vui lòng thử lại.</div>';
            console.error('Error fetching items:', err);
          });
      }

      function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = ''; // Restore scrolling
      }

      // Event listeners for modal buttons
      document.querySelectorAll('.btn-view-items').forEach(btn => {
        btn.addEventListener('click', () => {
          const typeId = btn.dataset.typeId;
          const typeName = btn.dataset.typeName;
          openModal(typeId, typeName);
        });
      });

      closeModalBtn.addEventListener('click', closeModal);
      
      // Close modal with Escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
          closeModal();
        }
      });

      // Remove old card click handler
      // (commented out the old one above)
    })();
  </script>
</body>
</html>





