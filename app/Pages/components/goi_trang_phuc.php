<?php
// Public: Danh sách gói trang phục (tab giống dichvu)
if (!isset($conn)) { require_once '../../../database/config.php'; }
require_once __DIR__ . '/../../helpers/assets.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$search = trim($_GET['q'] ?? '');
$branch = isset($_GET['cn']) ? (int)$_GET['cn'] : 0;
$status = ''; // status filter removed from UX
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Branches for filter
$branches = [];
if ($brs = $conn->query("SELECT ID_CN, TEN_CN FROM chi_nhanh ORDER BY TEN_CN")) { while($r=$brs->fetch_assoc()) $branches[]=$r; }

// Count total (only active packages for public view)
$countSql = "SELECT COUNT(*) AS total FROM goi_trang_phuc_master m WHERE m.TRANG_THAI = 'active'";
$listSql  = "SELECT m.ID_GOI, m.TEN_GOI, m.MO_TA, m.ID_CN_OWNER, m.TRANG_THAI, m.DISCOUNT_PERCENT,
                    (SELECT COUNT(*) FROM goi_trang_phuc_chi_tiet c WHERE c.ID_GOI = m.ID_GOI) AS TOTAL_ITEMS,
                    (
                      SELECT GROUP_CONCAT(DISTINCT ha.URL ORDER BY ha.IS_COVER DESC, ha.THU_TU ASC, ha.ID_HA ASC SEPARATOR '||')
                      FROM goi_trang_phuc_chi_tiet c2
                      STRAIGHT_JOIN trang_phuc_hinh_anh ha ON ha.ID_TP = c2.ID_TRANG_PHUC
                      WHERE c2.ID_GOI = m.ID_GOI AND ha.IS_ACTIVE = 1
                    ) AS COVER_URLS
             FROM goi_trang_phuc_master m WHERE m.TRANG_THAI = 'active'";

// Build params separately for count and list to avoid mismatch
$countParams = []; $countTypes = '';
$listParams  = []; $listTypes  = '';

// Filters
if ($branch > 0) {
  $countSql .= " AND m.ID_CN_OWNER = ?"; $countParams[] = $branch; $countTypes .= 'i';
  $listSql  .= " AND m.ID_CN_OWNER = ?"; $listParams[]  = $branch; $listTypes  .= 'i';
}
if ($search !== '') {
  $countSql .= " AND m.TEN_GOI LIKE CONCAT('%', ?, '%')"; $countParams[] = $search; $countTypes .= 's';
  $listSql  .= " AND m.TEN_GOI LIKE CONCAT('%', ?, '%')"; $listParams[]  = $search; $listTypes  .= 's';
}

// Ordering + pagination for list only
$listSql .= " ORDER BY m.TEN_GOI ASC LIMIT ? OFFSET ?";
$listParams[] = $limit; $listParams[] = $offset; $listTypes .= 'ii';

$totalRows = 0; $packages=[];
if ($stmt = $conn->prepare($countSql)) {
  if ($countTypes !== '') { $stmt->bind_param($countTypes, ...$countParams); }
  if ($stmt->execute()) { $res=$stmt->get_result(); $row=$res->fetch_assoc(); $totalRows = (int)($row['total'] ?? 0); }
  $stmt->close();
}
if ($stmt = $conn->prepare($listSql)) {
  if ($listTypes !== '') { $stmt->bind_param($listTypes, ...$listParams); }
  if ($stmt->execute()) { $r=$stmt->get_result(); while($row=$r->fetch_assoc()) $packages[]=$row; }
  $stmt->close();
}
$totalPages = max(1, (int)ceil($totalRows / $limit));

// Map branches
$branchNames = [];
foreach ($branches as $b) { $branchNames[(int)$b['ID_CN']] = $b['TEN_CN']; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gói trang phục</title>
  <?= sb_tailwind_link_tag(); ?>
  <style>
    .package-card {
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .package-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 40px -15px rgba(99, 102, 241, 0.3);
    }
    .package-cover {
      position: relative;
      overflow: hidden;
    }
    .package-cover::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to bottom, transparent 60%, rgba(0,0,0,0.7));
      pointer-events: none;
    }
    .package-images-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 2px;
    }
    .package-images-grid img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  </style>
</head>
<body class="bg-white text-slate-800">
  <div class="max-w-7xl mx-auto p-6 space-y-8">
    <nav class="mb-6 flex items-center gap-2">
      <a href="../Views/trangphuc.php?tab=items" class="inline-flex items-center rounded-full px-5 py-2 text-sm font-semibold border bg-white text-slate-800 border-slate-300 shadow-sm">Trang phục</a>
      <a href="../Views/trangphuc.php?tab=packages" class="inline-flex items-center rounded-full px-5 py-2 text-sm font-semibold border bg-indigo-600 text-white border-indigo-600 shadow-sm">Gói trang phục</a>
    </nav>
    <header class="space-y-3">
      <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-600">Stygian Blue Studio</p>
      <h1 class="text-3xl md:text-4xl font-bold text-slate-900">Gói trang phục</h1>
      <p class="text-slate-600">Chọn gói đã được kurate để phù hợp concept, đặt nhanh với một bước.</p>
    </header>

    <section class="rounded-2xl border bg-white p-5 shadow-sm">
      <form class="grid gap-4 md:grid-cols-3">
        <div class="md:col-span-2">
          <label class="text-sm font-semibold">Từ khóa</label>
          <input name="q" value="<?=h($search)?>" class="w-full rounded-lg border px-3 py-2" placeholder="Tên gói..." />
        </div>
        <div>
          <label class="text-sm font-semibold">Chi nhánh</label>
          <select name="cn" class="w-full rounded-lg border px-3 py-2">
            <option value="0">Tất cả</option>
            <?php foreach($branches as $b): ?>
              <option value="<?= (int)$b['ID_CN'] ?>" <?= $branch===(int)$b['ID_CN']?'selected':'' ?>><?= h($b['TEN_CN']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-3 flex gap-3 pt-1">
          <button class="rounded-lg bg-slate-900 text-white px-4 py-2">Lọc</button>
          <a href="?" class="rounded-lg border px-4 py-2 text-slate-700">Làm mới</a>
        </div>
      </form>
    </section>

    <?php if (empty($packages)): ?>
      <div class="rounded-2xl border bg-white p-10 text-center text-slate-600">Không có gói phù hợp với bộ lọc hiện tại.</div>
    <?php else: ?>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach($packages as $pkg): ?>
          <?php 
            $owner = (int)($pkg['ID_CN_OWNER'] ?? 0); 
            $ownerName = $branchNames[$owner] ?? ('CN#'.$owner); 
            $coverUrls = trim((string)($pkg['COVER_URLS'] ?? ''));
            $images = $coverUrls !== '' ? array_filter(explode('||', $coverUrls)) : [];
            // Limit to first 4 images
            $images = array_slice($images, 0, 4);
          ?>
          <article class="package-card rounded-2xl bg-white shadow-md flex flex-col overflow-hidden border border-slate-200/50">
            <?php if (!empty($images)): ?>
              <div class="package-cover aspect-[4/3] bg-slate-100">
                <?php if (count($images) === 1): ?>
                  <img src="<?= h(sb_asset_href($images[0])) ?>" alt="Ảnh gói <?= h($pkg['TEN_GOI']) ?>" class="w-full h-full object-cover" loading="lazy" onerror="this.onerror=null;this.classList.add('hidden')">
                <?php else: ?>
                  <div class="package-images-grid h-full">
                    <?php foreach(array_slice($images, 0, 4) as $img): ?>
                      <img src="<?= h(sb_asset_href($img)) ?>" alt="Item trong gói" class="" loading="lazy" onerror="this.onerror=null;this.classList.add('hidden')">
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="aspect-[4/3] bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 flex items-center justify-center text-slate-400">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l4-4a3 3 0 014 0l6 6"/><path d="M13 13l3-3a3 3 0 014 0l1 1"/></svg>
              </div>
            <?php endif; ?>
            <div class="p-6 flex flex-col gap-4">
              <div class="space-y-2">
                <div class="flex items-start justify-between gap-3">
                  <h2 class="text-xl font-bold text-slate-900 leading-tight flex-1"><?= h($pkg['TEN_GOI']) ?></h2>
                  <?php if (!empty($pkg['DISCOUNT_PERCENT']) && (int)$pkg['DISCOUNT_PERCENT']>0): ?>
                    <span class="px-3 py-1 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-bold shadow-sm">-<?= (int)$pkg['DISCOUNT_PERCENT'] ?>%</span>
                  <?php endif; ?>
                </div>
                <p class="text-xs font-medium text-indigo-600">
                  <svg class="inline w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                  <?= h($ownerName) ?>
                </p>
              </div>
              
              <p class="text-sm text-slate-600 line-clamp-2"><?= h($pkg['MO_TA'] ?? 'Gói trang phục được tuyển chọn kỹ lưỡng theo concept, phù hợp cho mọi dịp.') ?></p>
              
              <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                <div class="flex items-center gap-1.5 text-slate-700">
                  <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                  <span class="text-sm font-semibold"><?= (int)($pkg['TOTAL_ITEMS'] ?? 0) ?></span>
                  <span class="text-xs text-slate-500">trang phục</span>
                </div>
              </div>
              
              <div class="mt-auto flex items-center gap-2">
                <a href="goi_trang_phuc_chitiet.php?id=<?= (int)$pkg['ID_GOI'] ?>" class="flex-1 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 px-4 py-2.5 text-sm font-semibold text-white text-center transition-all shadow-md hover:shadow-lg">Xem chi tiết gói</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-3">
          <?php if ($page>1): ?><a href="?p=<?= $page-1 ?>" class="rounded-lg border px-3 py-1 text-sm bg-white">« Trước</a><?php endif; ?>
          <span class="text-xs text-slate-600">Trang <?= $page ?> / <?= $totalPages ?></span>
          <?php if ($page<$totalPages): ?><a href="?p=<?= $page+1 ?>" class="rounded-lg border px-3 py-1 text-sm bg-white">Sau »</a><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
