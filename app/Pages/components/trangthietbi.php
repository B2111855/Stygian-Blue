<?php
// pages/thietbi/index.php
// Yêu cầu: PHP >= 8.0, MySQLi. Đường dẫn include giữ nguyên như dự án của bạn.
include '../../../database/config.php';

// ==== Helper ====
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function selected($a,$b){ return $a===$b ? 'selected' : ''; }
function checked($a,$b){ return $a===$b ? 'checked' : ''; }

// ==== Input (GET) ====
$kw        = trim($_GET['q'] ?? '');
$branch    = $_GET['cn'] ?? '';
$status    = $_GET['st'] ?? '';      // '', 'tot', 'hong', 'dang_sua'
$minPrice  = $_GET['min'] ?? '';
$maxPrice  = $_GET['max'] ?? '';
$sort      = $_GET['sort'] ?? 'price_desc'; // price_desc|price_asc|name_asc|name_desc

// Ước tính (client sẽ tính, nhưng giữ để đẩy xuống form từng item)
$rentFrom  = $_GET['from'] ?? '';
$rentTo    = $_GET['to'] ?? '';
$qty       = (int)($_GET['qty'] ?? 1);
if ($qty < 1) $qty = 1;

// ==== Lấy danh sách chi nhánh cho bộ lọc ====
$branches = [];
$brs = $conn->query("SELECT ID_CN, TEN_CN FROM CHI_NHANH ORDER BY TEN_CN ASC");
if ($brs) {
  while ($r = $brs->fetch_assoc()) $branches[] = $r;
}

// ==== Build SQL (thiết bị + đơn giá mới nhất + trạng thái bảo trì hiện tại) ====
// Chú ý: LEFT JOIN subquery để lấy đơn giá mới nhất
$sql = "
SELECT 
  ttb.ID_TB,
  ttb.TEN_TB,
  ttb.TINH_TRANG,
  ttb.NGAY_BAO_TRI,
  ttb.IMAGE,
  ttb.ID_CN,
  cn.TEN_CN,
  COALESCE(dg.DON_GIA, 0) AS DON_GIA,
  CASE 
    WHEN lbt.ID_TB IS NOT NULL THEN 1 
    ELSE 0 
  END AS DANG_BAO_TRI
FROM TRANG_THIET_BI ttb
JOIN CHI_NHANH cn ON cn.ID_CN = ttb.ID_CN
LEFT JOIN (
  SELECT x.ID_TB, x.DON_GIA
  FROM don_gia_trang_thiet_bi x
  JOIN (
    SELECT ID_TB, MAX(NGAY_GIO) AS MG
    FROM don_gia_trang_thiet_bi
    GROUP BY ID_TB
  ) m ON m.ID_TB = x.ID_TB AND m.MG = x.NGAY_GIO
) dg ON dg.ID_TB = ttb.ID_TB
LEFT JOIN (
  SELECT DISTINCT ID_TB
  FROM lich_bao_tri_thiet_bi
  WHERE NOW() BETWEEN START_AT AND END_AT
) lbt ON lbt.ID_TB = ttb.ID_TB
WHERE 1=1
";

// ==== Áp điều kiện lọc bằng prepared statement ====
$params = [];
$types  = '';

if ($kw !== '') {
  $sql .= " AND ttb.TEN_TB LIKE CONCAT('%', ?, '%')";
  $types .= 's'; $params[] = $kw;
}
if ($branch !== '' && ctype_digit($branch)) {
  $sql .= " AND ttb.ID_CN = ?";
  $types .= 'i'; $params[] = (int)$branch;
}
if ($status !== '') {
  if ($status === 'dang_sua') {
    // Đang bảo trì hoặc tình trạng 'đang sửa'
    $sql .= " AND (ttb.TINH_TRANG = 'đang sửa' OR lbt.ID_TB IS NOT NULL)";
  } elseif ($status === 'hong') {
    $sql .= " AND ttb.TINH_TRANG = 'hỏng'";
  } elseif ($status === 'tot') {
    $sql .= " AND (ttb.TINH_TRANG NOT IN ('hỏng','đang sửa') AND lbt.ID_TB IS NULL)";
  }
}
if ($minPrice !== '' && is_numeric($minPrice)) {
  $sql .= " AND COALESCE(dg.DON_GIA,0) >= ?";
  $types .= 'i'; $params[] = (int)$minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
  $sql .= " AND COALESCE(dg.DON_GIA,0) <= ?";
  $types .= 'i'; $params[] = (int)$maxPrice;
}

// ==== Sắp xếp ====
switch ($sort) {
  case 'price_asc':  $sql .= " ORDER BY DON_GIA ASC, ttb.TEN_TB ASC"; break;
  case 'name_asc':   $sql .= " ORDER BY ttb.TEN_TB ASC"; break;
  case 'name_desc':  $sql .= " ORDER BY ttb.TEN_TB DESC"; break;
  default:           $sql .= " ORDER BY DON_GIA DESC, ttb.TEN_TB ASC"; // price_desc
}

$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Lấy ID_KH nếu có phiên đăng nhập (đã start ở header chung của bạn)
$currentCustomerId = $_SESSION['user']['ID_TK'] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Thuê trang thiết bị</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .square-img{width:100%;aspect-ratio:1/1;object-fit:cover}
    .sb-badge{display:inline-flex;align-items:center;gap:.375rem}
  </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-blue-100 to-blue-200 min-h-screen text-gray-800">
  <div class="max-w-7xl mx-auto px-4 py-8">
    <header class="mb-8">
      <h1 class="text-4xl font-extrabold text-center text-blue-900 drop-shadow-sm">Thuê trang thiết bị</h1>
      <p class="text-center text-gray-700 mt-2">Tìm kiếm, lọc theo chi nhánh/tình trạng/giá · Ước tính chi phí theo ngày thuê</p>
    </header>

    <!-- Bộ lọc -->
    <form class="bg-white/70 backdrop-blur rounded-2xl shadow p-4 mb-8">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tìm theo tên</label>
          <input type="text" name="q" value="<?=h($kw)?>" placeholder="Máy ảnh, đèn, tripod..."
                 class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Chi nhánh</label>
          <select name="cn" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
            <option value="">Tất cả</option>
            <?php foreach($branches as $b): ?>
              <option value="<?=$b['ID_CN']?>" <?=selected($branch,(string)$b['ID_CN'])?>><?=h($b['TEN_CN'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tình trạng</label>
          <select name="st" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
            <option value="">Tất cả</option>
            <option value="tot" <?=selected($status,'tot')?>>Sẵn sàng</option>
            <option value="dang_sua" <?=selected($status,'dang_sua')?>>Đang bảo trì / sửa</option>
            <option value="hong" <?=selected($status,'hong')?>>Hỏng</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Giá từ (₫/ngày)</label>
          <input type="number" name="min" value="<?=h($minPrice)?>" min="0"
                 class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Đến (₫/ngày)</label>
          <input type="number" name="max" value="<?=h($maxPrice)?>" min="0"
                 class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mt-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Sắp xếp</label>
          <select name="sort" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
            <option value="price_desc" <?=selected($sort,'price_desc')?>>Giá ↓</option>
            <option value="price_asc"  <?=selected($sort,'price_asc')?>>Giá ↑</option>
            <option value="name_asc"   <?=selected($sort,'name_asc')?>>Tên A→Z</option>
            <option value="name_desc"  <?=selected($sort,'name_desc')?>>Tên Z→A</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Thuê từ</label>
          <input type="date" name="from" value="<?=h($rentFrom)?>" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Đến</label>
          <input type="date" name="to" value="<?=h($rentTo)?>" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
          <input type="number" name="qty" value="<?=h($qty)?>" min="1" class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex items-end">
          <button class="w-full bg-blue-700 hover:bg-blue-600 text-white font-medium py-2 rounded-lg transition">Áp dụng</button>
        </div>
      </div>
    </form>

    <!-- Danh sách thiết bị -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): 
          $price    = (int)$row['DON_GIA'];
          $statusTx = mb_strtolower((string)$row['TINH_TRANG']);
          $maint    = (int)$row['DANG_BAO_TRI'] === 1;
          $badge    = ['text'=>'Sẵn sàng','class'=>'bg-emerald-50 text-emerald-700 ring-emerald-200'];
          if ($statusTx === 'hỏng') {
            $badge = ['text'=>'Hỏng','class'=>'bg-rose-50 text-rose-700 ring-rose-200'];
          } elseif ($statusTx === 'đang sửa' || $maint) {
            $badge = ['text'=>'Bảo trì','class'=>'bg-amber-50 text-amber-700 ring-amber-200'];
          }
        ?>
          <div class="bg-white rounded-2xl shadow hover:shadow-xl transition p-4 flex flex-col">
            <div class="relative mb-3">
              <img src="<?=h($row['IMAGE'])?>" alt="<?=h($row['TEN_TB'])?>" class="square-img rounded-xl shadow-sm">
              <div class="absolute top-2 left-2 rounded-full px-3 py-1 text-xs font-semibold ring-1 <?=h($badge['class'])?> sb-badge">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 12l2 2 4-4"/></svg>
                <span><?=h($badge['text'])?></span>
              </div>
            </div>

            <h2 class="text-xl font-semibold text-blue-900"><?=h($row['TEN_TB'])?></h2>
            <div class="mt-1 text-sm text-gray-600">Chi nhánh: <span class="font-medium text-gray-800"><?=h($row['TEN_CN'])?></span></div>
            <div class="mt-1 text-sm text-gray-600">Tình trạng: <span class="font-medium text-gray-800"><?=h($row['TINH_TRANG'])?></span></div>
            <?php if (!empty($row['NGAY_BAO_TRI'])): ?>
              <div class="mt-1 text-sm text-gray-600">Bảo trì gần nhất: <span class="font-medium text-gray-800"><?=h($row['NGAY_BAO_TRI'])?></span></div>
            <?php endif; ?>

            <div class="mt-3">
              <div class="text-2xl font-bold text-blue-700 tracking-tight"><?= number_format($price, 0, ',', '.') ?> <span class="text-sm font-medium text-gray-500">₫/ngày</span></div>
              <div class="text-xs text-gray-500 mt-1">Ước tính chi phí dựa trên khoảng ngày & số lượng bạn chọn ở bộ lọc.</div>
              <div class="mt-1 text-sm">
                <span class="text-gray-600">Ước tính:</span>
                <span class="font-semibold text-emerald-700" data-estimate-for="<?= (int)$row['ID_TB'] ?>">—</span>
              </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100">
              <form action="lienhe.php" method="POST" class="flex flex-col gap-2">
                <input type="hidden" name="id_tb" value="<?=h($row['ID_TB'])?>">
                <input type="hidden" name="service_id" value="thue_trang_thiet_bi">
                <input type="hidden" name="ID_KH" value="<?=h($currentCustomerId ?? '')?>">
                <input type="hidden" name="from" value="<?=h($rentFrom)?>">
                <input type="hidden" name="to" value="<?=h($rentTo)?>">
                <input type="hidden" name="qty" value="<?=h($qty)?>">
                <button type="submit"
                  class="w-full bg-blue-800 hover:bg-blue-600 text-white font-medium py-2.5 rounded-lg flex items-center justify-center gap-2 transition disabled:opacity-60"
                  <?= ($statusTx==='hỏng' || $maint) ? 'disabled' : '' ?>
                >
                  <!-- tool icon -->
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M21 2l-2 2-3-3-2 2 3 3-2 2L9 4 4 9l7 7 10-10-3-4zM2 20l6-1-5-5-1 6z"/></svg>
                  Thuê thiết bị
                </button>
              </form>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-center text-red-600 col-span-full">Không có trang thiết bị nào phù hợp!</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Tính ước tính chi phí client-side
    (function(){
      const from = document.querySelector('input[name="from"]').value;
      const to   = document.querySelector('input[name="to"]').value;
      const qty  = Math.max(1, parseInt(document.querySelector('input[name="qty"]').value || '1', 10));

      function daysBetween(a, b){
        const d1 = new Date(a), d2 = new Date(b);
        if (isNaN(d1) || isNaN(d2)) return 0;
        const ms = d2.setHours(12,0,0,0) - d1.setHours(12,0,0,0);
        return Math.max(0, Math.ceil(ms / 86400000)); // mỗi ngày
      }
      const days = daysBetween(from, to);

      if (days > 0) {
        // Lặp qua mọi card để tính = DON_GIA * days * qty (DON_GIA render sẵn)
        document.querySelectorAll('[data-estimate-for]').forEach(span => {
          // lấy giá ngay block gần nhất (số hiển thị có . và ,)
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
