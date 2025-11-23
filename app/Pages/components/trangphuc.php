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
if ($sz = $conn->query("SELECT DISTINCT SIZE FROM trang_phuc WHERE SIZE IS NOT NULL AND SIZE <> '' ORDER BY SIZE")) {
  while ($r = $sz->fetch_row()) $sizes[] = $r[0];
  $sz->free();
}

$colors = [];
if ($cl = $conn->query("SELECT DISTINCT MAU_SAC FROM trang_phuc WHERE MAU_SAC IS NOT NULL AND MAU_SAC <> '' ORDER BY MAU_SAC")) {
  while ($r = $cl->fetch_row()) $colors[] = $r[0];
  $cl->free();
}

// ==== New schema: GIA_THUE trực tiếp trong bảng trang_phuc ====
$priceExpr = 'COALESCE(tp.GIA_THUE, 0)';

$imageJoin = "LEFT JOIN (\n  SELECT ID_TP, SUBSTRING_INDEX(GROUP_CONCAT(URL ORDER BY IS_COVER DESC, THU_TU ASC, ID_HA ASC SEPARATOR '||'), '||', 1) AS URL\n  FROM trang_phuc_hinh_anh\n  WHERE IS_ACTIVE = 1\n  GROUP BY ID_TP\n) ha ON ha.ID_TP = tp.ID_TRANG_PHUC";

$sql = "SELECT\n  tp.ID_TRANG_PHUC AS ID_TP,\n  tp.TEN AS TEN_TP,\n  tp.SIZE,\n  tp.MAU_SAC AS MAU,\n  tp.TRANG_THAI AS TINH_TRANG,\n  tp.GHI_CHU,\n  tp.ID_CN,\n  cn.TEN_CN,\n  COALESCE(tp.GIA_THUE, 0) AS DON_GIA,\n  ha.URL AS HINH_ANH,\n  loai.TEN_LOAI,\n  loai.ID_LOAI\nFROM trang_phuc tp\nJOIN chi_nhanh cn ON cn.ID_CN = tp.ID_CN\nLEFT JOIN trang_phuc_loai loai ON loai.ID_LOAI = tp.ID_LOAI\n$imageJoin";

$params = [];
$types  = '';

if ($kw !== '') {
  $sql .= " AND tp.TEN LIKE CONCAT('%', ?, '%')";
  $types .= 's';
  $params[] = $kw;
}
if ($branch !== '' && ctype_digit($branch)) {
  $sql .= " AND tp.ID_CN = ?";
  $types .= 'i';
  $params[] = (int)$branch;
}
$sql .= " AND tp.TRANG_THAI = 'available'";
if ($size !== '') {
  $sql .= " AND tp.SIZE = ?";
  $types .= 's';
  $params[] = $size;
}
if ($color !== '') {
  $sql .= " AND tp.MAU_SAC = ?";
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
      'MIN_PRICE_OVERALL' => null,
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
  }
}

// Chuyển sang mảng để render
$catalogItems = [];
foreach ($typeMap as $g) {
  // Giá hiển thị: ưu tiên giá thấp nhất của item khả dụng, nếu không dùng giá thấp nhất chung
  $displayPrice = $g['MIN_PRICE_AVAILABLE'] !== null ? $g['MIN_PRICE_AVAILABLE'] : ($g['MIN_PRICE_OVERALL'] ?? 0);
  $catalogItems[] = [
    'ID_LOAI' => $g['ID_LOAI'],
    'TEN_LOAI' => $g['TEN_LOAI'],
    'TOTAL_ITEMS' => $g['TOTAL_ITEMS'],
    'AVAILABLE_ITEMS' => $g['AVAILABLE_ITEMS'],
    'DON_GIA' => $displayPrice,
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
    .square-img {
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
    .status-badge {
      position: absolute;
      top: 0.65rem;
      left: 0.65rem;
      border-radius: 999px;
      padding: 0.3rem 0.75rem;
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      box-shadow: 0 10px 25px -18px rgba(17, 24, 39, 0.5);
    }
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
    <header class="hero-grid mb-12">
      <div class="space-y-6">
        <div class="space-y-3">
          <p class="text-sm font-medium uppercase tracking-[0.3em] text-indigo-500">Stygian Blue Studio</p>
          <h1 class="hero-title text-4xl md:text-5xl font-semibold text-slate-900">Khám phá tủ đồ phù hợp cho từng khoảnh khắc</h1>
          <p class="text-base text-slate-600 max-w-2xl">
            Lọc nhanh, xem thông tin rõ ràng và đặt lịch thuê chỉ trong một bước để bạn luôn sẵn sàng cho mọi sự kiện.
          </p>
        </div>
        <div class="hero-quick-actions">
          <div class="hero-pill">
            <span>Nhập từ khóa yêu thích</span>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-lg hover:bg-slate-800"
              data-scroll-to="filters"
            >
              Bắt đầu lọc
              <svg width="16" height="16" fill="none" stroke="currentColor" class="opacity-80">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m11 11 4 4m-2.5-7a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0Z" />
              </svg>
            </button>
          </div>
          <div class="hero-pill">
            <span>Mở bộ lọc nâng cao</span>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 bg-white hover:border-slate-300"
              data-scroll-to="filters"
            >
              Xem bộ lọc
            </button>
          </div>
        </div>
        <div class="hero-metrics">
          <div class="hero-metric">
            <p class="text-sm font-medium text-slate-500">Trang phục sẵn sàng</p>
            <p class="text-3xl font-semibold text-slate-900"><?= number_format($readyCount, 0, ',', '.') ?></p>
          </div>
          <div class="hero-metric">
            <p class="text-sm font-medium text-slate-500">Chi nhánh phục vụ</p>
            <p class="text-3xl font-semibold text-slate-900"><?= number_format(count($branches), 0, ',', '.') ?></p>
          </div>
          <div class="hero-metric">
            <p class="text-sm font-medium text-slate-500">Nhóm trang phục</p>
            <p class="text-3xl font-semibold text-slate-900"><?= number_format(count($categories), 0, ',', '.') ?></p>
          </div>
        </div>
      </div>
      <div class="hero-visual text-white">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/40 via-transparent to-slate-900/80"></div>
        <div class="absolute bottom-6 left-6 right-6 rounded-2xl border border-white/20 bg-white/15 p-5 backdrop-blur-md">
          <p class="text-xs uppercase tracking-[0.3em] text-white/70">Lookbook</p>
          <p class="text-2xl font-semibold leading-tight">98% khách hàng chọn được trang phục trong <span class="text-indigo-200">dưới 3 phút</span></p>
          <p class="text-sm text-white/80 mt-2">Bộ lọc thông minh giúp đề xuất đúng size, đúng chi nhánh còn hàng.</p>
        </div>
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

    <div class="grid gap-8 lg:grid-cols-[280px_minmax(0,1fr)] xl:grid-cols-[340px_minmax(0,1fr)]">
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
        <?php elseif (!empty($catalogItems)): ?>
          <div class="results-grid grid gap-4 lg:gap-5">
            <?php foreach ($catalogItems as $type):
              $price = (int)$type['DON_GIA'];
              $image = $type['HINH_ANH'] ?: $defaultImageUrl;
              $avail = (int)$type['AVAILABLE_ITEMS'];
              $total = (int)$type['TOTAL_ITEMS'];
              $utilPercent = $total > 0 ? round(($total - $avail) * 100 / $total) : 0; // phần trăm đang dùng
              $statusKey = $avail > 0 ? 'available' : 'rented';
              $badge = $statusMap[$statusKey] ?? ['Loại', 'bg-slate-100/90 text-slate-600 border border-slate-200/80'];
              $typeBookingQuery = http_build_query([
                'mode' => 'type',
                'loai' => $type['ID_LOAI'],
                'from' => $rentFrom,
                'to'   => $rentTo,
                'qty'  => $qty,
              ]);
              $detailId = $type['REP_ITEM_ID'] ?? null;
              $detailHref = $detailId ? ('trangphuc_chitiet.php?id=' . urlencode($detailId)) : '#';
            ?>
              <article class="result-card flex h-full flex-col gap-3 cursor-pointer group" data-detail-id="<?=h($detailId)?>">
                <div class="relative">
                  <img src="<?=h($image)?>" alt="<?=h($type['TEN_LOAI'])?>" class="square-img shadow-sm" loading="lazy" onerror="this.onerror=null;this.src='<?=h($defaultImageUrl)?>';">
                  <div class="status-badge <?=h($badge[1])?>">
                    <?=h($badge[0])?>
                  </div>
                  <?php if ($detailId): ?>
                  <a href="<?=h($detailHref)?>" class="absolute inset-0" aria-label="Xem chi tiết loại trang phục"></a>
                  <?php endif; ?>
                </div>
                <div class="space-y-2">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                      <h2 class="text-base font-semibold leading-snug text-slate-900 line-clamp-2"><?=h($type['TEN_LOAI'])?></h2>
                      <p class="text-xs text-slate-500">Còn <span class="font-semibold text-emerald-600"><?=$avail?></span> / <?=$total?> bộ</p>
                    </div>
                    <div class="shrink-0 text-right">
                      <p class="text-xl font-bold text-indigo-700" data-price="<?= (int)$price ?>"><?= number_format($price, 0, ',', '.') ?></p>
                      <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">VND/ngày từ</p>
                      <p class="text-[11px] text-slate-400">Đang dùng: <?=$utilPercent?>%</p>
                    </div>
                  </div>
                </div>
                <div class="text-xs text-slate-500 space-y-1">
                  <p>Thuê theo loại: <span class="estimate-text font-semibold text-emerald-600" data-estimate-for-type="<?= (int)$type['ID_LOAI'] ?>">Chọn ngày để tính</span></p>
                  <p>Giá hiển thị là mức thấp nhất hiện khả dụng.</p>
                </div>
                <div class="card-actions mt-auto">
                  <?php if ($detailId): ?>
                    <a href="<?=h($detailHref)?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-700 bg-white transition hover:border-indigo-300 hover:text-indigo-600">Chi tiết</a>
                  <?php endif; ?>
                  <?php if ($avail > 0): ?>
                    <a
                      href="trangphuc_datthue.php?<?= h($typeBookingQuery) ?>"
                      class="inline-flex flex-1 items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-slate-800"
                      data-book-btn="true"
                    >
                      Thuê theo loại
                    </a>
                  <?php else: ?>
                    <span class="inline-flex flex-1 items-center justify-center rounded-lg bg-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Tạm hết</span>
                  <?php endif; ?>
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

      document.querySelectorAll('[data-scroll-to="filters"]').forEach(btn => {
        btn.addEventListener('click', () => {
          const form = document.getElementById('filterForm');
          if (form && form.scrollIntoView) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
          const keyword = document.getElementById('q');
          if (keyword) {
            keyword.focus();
          }
        });
      });

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

      // Estimate cho từng loại (dựa trên giá thấp nhất khả dụng hiện có)
      document.querySelectorAll('[data-estimate-for-type]').forEach(span => {
        if (days <= 0) {
          span.textContent = 'Chọn ngày để tính';
          return;
        }
        const card = span.closest('article');
        if (!card) return;
        const priceText = card.querySelector('[data-price]');
        if (!priceText) return;
        const raw = priceText.dataset.price || priceText.textContent.replace(/[^\d]/g, '');
        const price = parseInt(raw || '0', 10);
        const estimate = price * days * qty;
        span.textContent = new Intl.NumberFormat('vi-VN').format(estimate) + ' VND (' + days + ' ngày x ' + qty + ')';
      });

      // Click toàn bộ card mở chi tiết (trừ khi nhấn nút đặt thuê hoặc nút chi tiết riêng)
      document.querySelectorAll('article.result-card[data-detail-id]').forEach(card => {
        card.addEventListener('click', function(e){
          const target = e.target;
          if (target.closest('a')) { return; } // để anchor hoạt động bình thường
          const detailId = card.getAttribute('data-detail-id');
            if (detailId) {
              window.location.href = 'trangphuc_chitiet.php?id=' + encodeURIComponent(detailId);
            }
        });
      });
    })();
  </script>
</body>
</html>





