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
$status    = $_GET['st'] ?? '';
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
if ($status !== '') {
  $allowed = ['san_sang','dang_thue','bao_tri','ngung'];
  if (in_array($status, $allowed, true)) {
    $sql .= " AND tp.TINH_TRANG = ?";
    $types .= 's';
    $params[] = $status;
  }
}
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
  'san_sang' => ['Sẵn sàng', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
  'dang_thue' => ['Đang thuê', 'bg-indigo-50 text-indigo-700 ring-indigo-200'],
  'bao_tri' => ['Bảo trì', 'bg-amber-50 text-amber-700 ring-amber-200'],
  'ngung' => ['Ngưng cho thuê', 'bg-rose-50 text-rose-700 ring-rose-200'],
];

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
    .square-img{width:100%;aspect-ratio:3/4;object-fit:cover}
  </style>
</head>
<body class="bg-gradient-to-br from-pink-50 via-purple-100 to-indigo-100 min-h-screen text-gray-800">
  <div class="max-w-7xl mx-auto px-4 py-8">
    <header class="mb-8 text-center">
      <h1 class="text-4xl font-extrabold text-indigo-900">Bộ sưu tập trang phục</h1>
      <p class="text-gray-700 mt-2">Tìm và chọn trang phục phù hợp cho buổi chụp hoặc sự kiện của bạn.</p>
    </header>

    <form class="bg-white/80 backdrop-blur rounded-2xl shadow p-4 mb-8">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tìm theo tên</label>
          <input type="text" name="q" value="<?=h($kw)?>" placeholder="Áo cưới, cosplay, vest..."
                 class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Chi nhánh</label>
          <select name="cn" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
            <option value="">Tất cả</option>
            <?php foreach($branches as $b): ?>
              <option value="<?=$b['ID_CN']?>" <?=selected($branch,(string)$b['ID_CN'])?>><?=h($b['TEN_CN'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Loại trang phục</label>
          <select name="loai" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
            <option value="">Tất cả</option>
            <?php foreach($categories as $c): ?>
              <option value="<?=$c['ID_LOAI']?>" <?=selected($category,(string)$c['ID_LOAI'])?>><?=h($c['TEN_LOAI'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tình trạng</label>
          <select name="st" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
            <option value="">Tất cả</option>
            <option value="san_sang" <?=selected($status,'san_sang')?>>Sẵn sàng</option>
            <option value="dang_thue" <?=selected($status,'dang_thue')?>>Đang thuê</option>
            <option value="bao_tri" <?=selected($status,'bao_tri')?>>Bảo trì</option>
            <option value="ngung" <?=selected($status,'ngung')?>>Ngưng cho thuê</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Sắp xếp</label>
          <select name="sort" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
            <option value="price_desc" <?=selected($sort,'price_desc')?>>Giá ↓</option>
            <option value="price_asc"  <?=selected($sort,'price_asc')?>>Giá ↑</option>
            <option value="name_asc"   <?=selected($sort,'name_asc')?>>Tên A→Z</option>
            <option value="name_desc"  <?=selected($sort,'name_desc')?>>Tên Z→A</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mt-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Size</label>
          <select name="size" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
            <option value="">Tất cả</option>
            <?php foreach($sizes as $s): ?>
              <option value="<?=h($s)?>" <?=selected($size,$s)?>><?=h($s)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Màu sắc</label>
          <select name="mau" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
            <option value="">Tất cả</option>
            <?php foreach($colors as $c): ?>
              <option value="<?=h($c)?>" <?=selected($color,$c)?>><?=h($c)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Giá từ (₫/ngày)</label>
          <input type="number" name="min" value="<?=h($minPrice)?>" min="0"
                 class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Đến (₫/ngày)</label>
          <input type="number" name="max" value="<?=h($maxPrice)?>" min="0"
                 class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex items-end">
          <button class="w-full bg-indigo-700 hover:bg-indigo-600 text-white font-medium py-2 rounded-lg transition">Áp dụng</button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Thuê từ</label>
          <input type="date" name="from" value="<?=h($rentFrom)?>" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Đến</label>
          <input type="date" name="to" value="<?=h($rentTo)?>" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
          <input type="number" name="qty" value="<?=h($qty)?>" min="1" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex items-end">
          <a href="?" class="w-full text-center border border-indigo-200 text-indigo-700 font-medium py-2 rounded-lg transition hover:border-indigo-400">Làm mới</a>
        </div>
      </div>
    </form>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php if ($errorMessage): ?>
        <p class="text-center text-red-600 col-span-full"><?=h($errorMessage)?></p>
      <?php elseif ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()):
          $price = (int)$row['DON_GIA'];
          $statusKey = $row['TINH_TRANG'] ?? '';
          $badge = $statusMap[$statusKey] ?? ['Chưa rõ', 'bg-gray-50 text-gray-700 ring-gray-200'];
          $image = $row['HINH_ANH'] ?: '../../../public/images/bg01.png';
        ?>
          <div class="bg-white rounded-2xl shadow hover:shadow-xl transition p-4 flex flex-col">
            <div class="relative mb-3">
              <img src="<?=h($image)?>" alt="<?=h($row['TEN_TP'])?>" class="square-img rounded-xl shadow-sm">
              <div class="absolute top-2 left-2 rounded-full px-3 py-1 text-xs font-semibold ring-1 <?=h($badge[1])?>">
                <?=h($badge[0])?>
              </div>
            </div>

            <h2 class="text-xl font-semibold text-indigo-900"><?=h($row['TEN_TP'])?></h2>
            <?php if (!empty($row['TEN_LOAI'])): ?>
              <div class="mt-1 text-sm text-gray-600">Loại: <span class="font-medium text-gray-800"><?=h($row['TEN_LOAI'])?></span></div>
            <?php endif; ?>
            <div class="mt-1 text-sm text-gray-600">Chi nhánh: <span class="font-medium text-gray-800"><?=h($row['TEN_CN'])?></span></div>
            <?php if (!empty($row['SIZE'])): ?>
              <div class="mt-1 text-sm text-gray-600">Size: <span class="font-medium text-gray-800"><?=h($row['SIZE'])?></span></div>
            <?php endif; ?>
            <?php if (!empty($row['MAU'])): ?>
              <div class="mt-1 text-sm text-gray-600">Màu sắc: <span class="font-medium text-gray-800"><?=h($row['MAU'])?></span></div>
            <?php endif; ?>
            <?php if (!empty($row['NGAY_GIAT_CUOI']) && $row['NGAY_GIAT_CUOI'] !== '0000-00-00'): ?>
              <div class="mt-1 text-sm text-gray-600">Giặt gần nhất: <span class="font-medium text-gray-800"><?=h($row['NGAY_GIAT_CUOI'])?></span></div>
            <?php endif; ?>
            <?php if (!empty($row['GHI_CHU'])): ?>
              <div class="mt-1 text-sm text-gray-600">Ghi chú: <span class="font-medium text-gray-800"><?=h($row['GHI_CHU'])?></span></div>
            <?php endif; ?>

            <div class="mt-3">
              <div class="text-2xl font-bold text-indigo-700 tracking-tight"><?= number_format($price, 0, ',', '.') ?> <span class="text-sm font-medium text-gray-500">₫/ngày</span></div>
              <?php if (!empty($row['HIEU_LUC_TU'])): ?>
                <div class="text-xs text-gray-500 mt-1">Giá áp dụng từ: <?=h($row['HIEU_LUC_TU'])?></div>
              <?php endif; ?>
              <div class="text-xs text-gray-500 mt-1">Ước tính chi phí dựa trên khoảng ngày & số lượng bạn chọn.</div>
              <div class="mt-1 text-sm">
                <span class="text-gray-600">Ước tính:</span>
                <span class="font-semibold text-emerald-700" data-estimate-for="<?= (int)$row['ID_TP'] ?>">—</span>
              </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100">
              <form action="lienhe.php" method="POST" class="flex flex-col gap-2">
                <input type="hidden" name="id_tp" value="<?=h($row['ID_TP'])?>">
                <input type="hidden" name="service_id" value="thue_trang_phuc">
                <input type="hidden" name="ID_KH" value="<?=h($currentCustomerId ?? '')?>">
                <input type="hidden" name="from" value="<?=h($rentFrom)?>">
                <input type="hidden" name="to" value="<?=h($rentTo)?>">
                <input type="hidden" name="qty" value="<?=h($qty)?>">
                <button type="submit"
                  class="w-full bg-indigo-800 hover:bg-indigo-600 text-white font-medium py-2.5 rounded-lg transition disabled:opacity-60"
                  <?= ($statusKey !== 'san_sang') ? 'disabled' : '' ?>
                >
                  Đặt lịch thuê
                </button>
              </form>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-center text-red-600 col-span-full">Không có trang phục nào phù hợp!</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    (function(){
      const from = document.querySelector('input[name="from"]').value;
      const to   = document.querySelector('input[name="to"]').value;
      const qty  = Math.max(1, parseInt(document.querySelector('input[name="qty"]').value || '1', 10));

      function daysBetween(a, b){
        const d1 = new Date(a), d2 = new Date(b);
        if (isNaN(d1) || isNaN(d2)) return 0;
        const ms = d2.setHours(12,0,0,0) - d1.setHours(12,0,0,0);
        return Math.max(0, Math.ceil(ms / 86400000));
      }
      const days = daysBetween(from, to);

      if (days > 0) {
        document.querySelectorAll('[data-estimate-for]').forEach(span => {
          const priceEl = span.closest('div').previousElementSibling.querySelector('.text-2xl');
          if (!priceEl) return;
          const raw = priceEl.textContent.replace(/[^\d]/g, '');
          const price = parseInt(raw || '0', 10);
          const est = price * days * qty;
          span.textContent = new Intl.NumberFormat('vi-VN').format(est) + ' ₫ ('+ days +' ngày × ' + qty + ')';
        });
      } else {
        document.querySelectorAll('[data-estimate-for]').forEach(span => { span.textContent = 'Chọn ngày để ước tính'; });
      }
    })();
  </script>
</body>
</html>