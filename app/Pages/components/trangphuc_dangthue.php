<?php
// Trang hiển thị các loại trang phục đang được thuê (giao diện khách hàng)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../../database/config.php';
require_once __DIR__ . '/../../helpers/assets.php';

function tp_escape($v){return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');}

// Lấy ngày hiện tại (có thể cho phép truyền ngày sau này)
$today = date('Y-m-d');

// Truy vấn tổng hợp theo loại: đếm tổng số instance, số đang thuê (overlap ngày hiện tại), số còn rảnh.
$sql = "SELECT 
  loai.ID_LOAI,
  loai.TEN_LOAI,
  COUNT(tp.ID_TRANG_PHUC) AS total_instances,
  SUM(CASE WHEN active.ID_TRANG_PHUC IS NOT NULL THEN 1 ELSE 0 END) AS rented_instances,
  COUNT(tp.ID_TRANG_PHUC) - SUM(CASE WHEN active.ID_TRANG_PHUC IS NOT NULL THEN 1 ELSE 0 END) AS available_instances
FROM trang_phuc_loai loai
LEFT JOIN trang_phuc tp ON tp.ID_LOAI = loai.ID_LOAI
LEFT JOIN (
  SELECT ct.ID_TRANG_PHUC
  FROM don_thue_trang_phuc_ct ct
  JOIN don_thue_trang_phuc d ON d.ID_DON = ct.ID_DON
  WHERE d.TRANG_THAI IN ('cho_duyet','da_duyet','dang_thue')
    AND NOT (d.NGAY_TRA_DK < CURDATE() OR d.NGAY_NHAN > CURDATE())
  GROUP BY ct.ID_TRANG_PHUC
) active ON active.ID_TRANG_PHUC = tp.ID_TRANG_PHUC
GROUP BY loai.ID_LOAI, loai.TEN_LOAI
ORDER BY rented_instances DESC, loai.TEN_LOAI ASC";

$result = $conn->query($sql);
$rows = [];
if ($result) {
    while ($r = $result->fetch_assoc()) {
        $r['total_instances'] = (int)$r['total_instances'];
        $r['rented_instances'] = (int)$r['rented_instances'];
        $r['available_instances'] = (int)$r['available_instances'];
        $r['utilization'] = $r['total_instances'] > 0 ? round($r['rented_instances'] * 100 / $r['total_instances']) : 0;
        $rows[] = $r;
    }
    $result->free();
}

$totalTypes = count($rows);
$totalItems = array_sum(array_column($rows, 'total_instances'));
$totalRented = array_sum(array_column($rows, 'rented_instances'));
$totalAvailable = array_sum(array_column($rows, 'available_instances'));
$avgUtil = ($totalItems > 0 ? round($totalRented * 100 / $totalItems) : 0);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nhóm trang phục đang thuê hôm nay</title>
  <?= sb_tailwind_link_tag(); ?>
  <style>
    body { font-family: 'Inter', system-ui, sans-serif; background: linear-gradient(180deg,#f8fafc 0%,#ffffff 55%,#f1f5f9 100%); }
    .shell { max-width: 1320px; margin:0 auto; padding:2.5rem 1.5rem 4rem; }
    .metric-card { background: #ffffff; border:1px solid rgba(148,163,184,.3); border-radius:1.25rem; padding:1rem 1.25rem; box-shadow:0 12px 30px -20px rgba(30,41,59,.25); }
    .grid-metrics { display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); }
    .type-card { background: linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(248,250,252,.92) 100%); border:1px solid rgba(148,163,184,.25); border-radius:1rem; padding:1rem 1.1rem 1.15rem; display:flex; flex-direction:column; gap:.75rem; position:relative; }
    .badge { display:inline-flex; align-items:center; gap:.4rem; font-size:.65rem; font-weight:700; letter-spacing:.09em; text-transform:uppercase; padding:.35rem .6rem; border-radius:999px; background:#eef2ff; color:#4338ca; }
    .util-bar { height:6px; border-radius:4px; background:#e2e8f0; overflow:hidden; }
    .util-bar span { display:block; height:100%; background:#6366f1; }
    .status-chip { font-size:.65rem; font-weight:600; padding:.25rem .55rem; border-radius:999px; letter-spacing:.05em; }
    .status-busy { background:rgba(220,38,38,.12); color:#dc2626; }
    .status-ok { background:rgba(16,185,129,.15); color:#0f766e; }
    .table-shell { margin-top:2rem; }
    .cards-grid { display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); }
    .header-actions a { display:inline-flex; align-items:center; gap:.5rem; padding:.55rem .9rem; border-radius:.9rem; font-size:.8rem; font-weight:600; background:#1e293b; color:#fff; text-decoration:none; }
    .header-actions a.secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
  </style>
</head>
<body>
  <div class="shell">
    <header class="mb-8 space-y-3">
      <p class="text-xs font-semibold tracking-[0.35em] text-indigo-600 uppercase">Stygian Blue Studio</p>
      <h1 class="text-3xl md:text-4xl font-semibold text-slate-900">Tình trạng thuê hôm nay (<?= tp_escape($today) ?>)</h1>
      <p class="text-slate-600 max-w-2xl">Thống kê nhanh số bộ mỗi nhóm đang được giữ hoặc thuê trong ngày hiện tại. Dữ liệu cập nhật theo thời gian thực khi trạng thái đơn đổi.</p>
      <div class="header-actions flex gap-3 pt-2">
        <a href="trangphuc.php">Quay lại trang chọn trang phục</a>
        <a class="secondary" href="trangphuc_dangthue.php">Làm mới</a>
      </div>
    </header>

    <section class="grid-metrics mb-10">
      <div class="metric-card">
        <p class="text-xs text-slate-500">Nhóm trang phục</p>
        <p class="text-2xl font-semibold text-slate-900"><?= number_format($totalTypes) ?></p>
      </div>
      <div class="metric-card">
        <p class="text-xs text-slate-500">Tổng số bộ</p>
        <p class="text-2xl font-semibold text-slate-900"><?= number_format($totalItems) ?></p>
      </div>
      <div class="metric-card">
        <p class="text-xs text-slate-500">Đang thuê / giữ</p>
        <p class="text-2xl font-semibold text-indigo-600"><?= number_format($totalRented) ?></p>
      </div>
      <div class="metric-card">
        <p class="text-xs text-slate-500">Còn rảnh</p>
        <p class="text-2xl font-semibold text-emerald-600"><?= number_format($totalAvailable) ?></p>
      </div>
      <div class="metric-card">
        <p class="text-xs text-slate-500">Tỷ lệ sử dụng</p>
        <p class="text-2xl font-semibold text-slate-900"><?= $avgUtil ?>%</p>
      </div>
    </section>

    <?php if (!$rows): ?>
      <div class="type-card text-center">
        <p class="text-sm text-slate-600">Chưa có lượt thuê nào trùng với ngày hôm nay.</p>
      </div>
    <?php else: ?>
      <div class="cards-grid">
        <?php foreach ($rows as $r): 
          $busyRatio = $r['total_instances'] > 0 ? ($r['rented_instances'] / $r['total_instances']) : 0;
          $chipClass = $busyRatio >= 0.7 ? 'status-busy' : 'status-ok';
        ?>
        <article class="type-card">
          <div class="flex items-center justify-between">
            <span class="badge">LOẠI</span>
            <span class="status-chip <?= $chipClass ?>">
              <?= $busyRatio >= 0.7 ? 'Cao' : 'Ổn' ?>
            </span>
          </div>
          <h2 class="text-lg font-semibold leading-tight text-slate-900"><?= tp_escape($r['TEN_LOAI']) ?></h2>
          <p class="text-xs text-slate-500">Mã loại: <?= (int)$r['ID_LOAI'] ?></p>

          <div class="space-y-1 text-sm">
            <p><strong><?= $r['rented_instances'] ?></strong> đang thuê / giữ</p>
            <p><strong><?= $r['available_instances'] ?></strong> còn rảnh</p>
            <p>Tổng <strong><?= $r['total_instances'] ?></strong> bộ</p>
          </div>

          <div class="util-bar" aria-label="Tỷ lệ sử dụng <?= $r['utilization'] ?>%">
            <span style="width: <?= $r['utilization'] ?>%;"></span>
          </div>
          <p class="text-xs text-slate-500">Sử dụng: <?= $r['utilization'] ?>%</p>

          <div class="pt-1">
            <a href="trangphuc.php?loai=<?= (int)$r['ID_LOAI'] ?>" class="inline-flex items-center justify-center w-full rounded-lg bg-slate-900 text-white text-xs font-semibold tracking-wide px-3 py-2 shadow hover:bg-slate-700">Xem trong danh sách</a>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
