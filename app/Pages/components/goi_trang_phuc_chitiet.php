<?php
// Public: Chi tiết gói trang phục
if (!isset($conn)) { require_once '../../../database/config.php'; }
require_once __DIR__ . '/../../helpers/assets.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pkgId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$package = null; $items=[]; $branchName=''; $packageImages=[];
if ($pkgId > 0) {
  if ($stmt = $conn->prepare("SELECT ID_GOI, TEN_GOI, MO_TA, ID_CN_OWNER, TRANG_THAI, DISCOUNT_PERCENT FROM goi_trang_phuc_master WHERE ID_GOI=? LIMIT 1")) {
    $stmt->bind_param('i', $pkgId); $stmt->execute(); $res=$stmt->get_result(); $package=$res?$res->fetch_assoc():null; $stmt->close();
  }
  if ($package) {
    $bId = (int)($package['ID_CN_OWNER'] ?? 0);
    if ($br = $conn->prepare("SELECT TEN_CN FROM chi_nhanh WHERE ID_CN=?")) { $br->bind_param('i',$bId); $br->execute(); $r=$br->get_result()->fetch_assoc(); $branchName = $r['TEN_CN'] ?? ('CN#'.$bId); $br->close(); }
    
    // Get items with images
    $sql_items = "SELECT c.ID_TRANG_PHUC, c.SO_LUONG,
                         tp.TEN, tp.GIA_THUE, tp.SIZE, tp.MAU_SAC, tp.TRANG_THAI,
                         (SELECT ha.URL FROM trang_phuc_hinh_anh ha WHERE ha.ID_TP=c.ID_TRANG_PHUC AND ha.IS_ACTIVE=1 ORDER BY ha.IS_COVER DESC, ha.THU_TU ASC LIMIT 1) AS IMAGE_URL
                  FROM goi_trang_phuc_chi_tiet c
                  JOIN trang_phuc tp ON tp.ID_TRANG_PHUC=c.ID_TRANG_PHUC
                  WHERE c.ID_GOI=? ORDER BY c.THU_TU ASC, tp.TEN ASC";
    if ($stmt = $conn->prepare($sql_items)) {
      $stmt->bind_param('i', $pkgId); 
      $stmt->execute(); 
      $res=$stmt->get_result(); 
      while($row=$res->fetch_assoc()) $items[]=$row; 
      $stmt->close();
    } else {
      error_log("Package detail query error: " . $conn->error);
    }
    
    // DEBUG: Log items count
    error_log("Package $pkgId loaded " . count($items) . " items");
    if (count($items) > 0) {
      error_log("First item: " . json_encode($items[0]));
    }
    
    
    // Get package cover images
    if ($stmt = $conn->prepare("SELECT GROUP_CONCAT(DISTINCT ha.URL ORDER BY ha.IS_COVER DESC, ha.THU_TU ASC, ha.ID_HA ASC SEPARATOR '||')
                                 FROM goi_trang_phuc_chi_tiet c2
                                 STRAIGHT_JOIN trang_phuc_hinh_anh ha ON ha.ID_TP = c2.ID_TRANG_PHUC
                                 WHERE c2.ID_GOI = ? AND ha.IS_ACTIVE = 1")) {
      $stmt->bind_param('i', $pkgId); $stmt->execute(); $res=$stmt->get_result(); 
      if ($row = $res->fetch_assoc()) {
        $urls = trim((string)($row['GROUP_CONCAT(DISTINCT ha.URL ORDER BY ha.IS_COVER DESC, ha.THU_TU ASC, ha.ID_HA ASC SEPARATOR \'||\')'] ?? ''));
        if ($urls !== '') { $packageImages = array_slice(array_filter(explode('||', $urls)), 0, 6); }
      }
      $stmt->close();
    }
  }
}

// Calculate pricing
$totalOriginal = 0;
foreach ($items as $it) {
  $price = (int)($it['GIA_THUE'] ?? 0);
  $qty = (int)($it['SO_LUONG'] ?? 1);
  $itemTotal = $price * $qty;
  $totalOriginal += $itemTotal;
}
$discountPercent = (int)($package['DISCOUNT_PERCENT'] ?? 0);
$discountAmount = ($discountPercent > 0) ? (int)round($totalOriginal * $discountPercent / 100) : 0;
$totalFinal = $totalOriginal - $discountAmount;

$firstItemId = !empty($items) ? (int)$items[0]['ID_TRANG_PHUC'] : null;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chi tiết gói trang phục<?= $package? ' – '.h($package['TEN_GOI']) : '' ?></title>
  <?= sb_tailwind_link_tag(); ?>
  <style>
    /* Hero image layouts - 5:7 Portrait ratio */
    .hero-1, .hero-2, .hero-3, .hero-5plus { width: 100%; }
    .hero-1 img, .hero-2 img, .hero-3 img, .hero-5plus img { 
      aspect-ratio: 5/7; 
      width: 100%; 
      object-fit: cover; 
      border-radius: 16px; 
    }
    
    .hero-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    
    .hero-3 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .hero-3 img:first-child { grid-row: span 2; aspect-ratio: 5/14; }
    .hero-3 .right-col { display: flex; flex-direction: column; gap: 12px; }
    
    .hero-5plus { display: grid; grid-template-columns: 1fr 1fr 1fr; grid-template-rows: repeat(2, 1fr); gap: 12px; }
    .hero-5plus img:first-child { grid-column: 1; grid-row: span 2; aspect-ratio: 5/14; }
    
    @media (max-width: 768px) { 
      .hero-2, .hero-3, .hero-5plus { grid-template-columns: 1fr; grid-template-rows: auto; }
      .hero-3 img:first-child, .hero-5plus img:first-child { grid-column: 1; grid-row: 1; aspect-ratio: 5/7; }
      .hero-3 .right-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    }
    
    .item-card { transition: all 0.2s ease; }
    .item-card:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0,0,0,0.1); }
    .price-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
  <div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
    <!-- Header -->
    <header class="flex items-center justify-between gap-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-600">Stygian Blue Studio</p>
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900">Chi tiết gói trang phục</h1>
        <?php if ($package): ?>
          <p class="text-sm text-slate-600">Chi nhánh: <span class="font-medium text-indigo-600"><?= h($branchName) ?></span></p>
        <?php endif; ?>
      </div>
      <a href="trangphuc.php?tab=packages" class="rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50 transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Trở lại
      </a>
    </header>

    <?php if (!$package): ?>
      <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
        <svg class="w-20 h-20 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <p class="text-lg font-medium text-slate-600">Gói không tồn tại hoặc đã bị xóa.</p>
        <a href="trangphuc.php?tab=packages" class="mt-4 inline-block text-indigo-600 hover:text-indigo-700">Xem các gói khác</a>
      </div>
    <?php else: ?>
      


      <!-- Package Info & Pricing -->
      <div class="grid lg:grid-cols-3 gap-6">
        <!-- Package Details -->
        <section class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <h2 class="text-2xl font-bold text-slate-900"><?= h($package['TEN_GOI']) ?></h2>
              <p class="text-slate-600 mt-2"><?= h($package['MO_TA'] ?? 'Gói trang phục được tuyển chọn kỹ lưỡng theo concept, phù hợp cho mọi dịp.') ?></p>
            </div>
            <?php if ($discountPercent > 0): ?>
              <span class="px-4 py-2 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-sm font-bold shadow-md whitespace-nowrap">-<?= $discountPercent ?>%</span>
            <?php endif; ?>
          </div>
          
          <div class="flex items-center gap-4 text-sm text-slate-600 border-t border-slate-100 pt-4">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              <?= h($branchName) ?>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
              <?= count($items) ?> trang phục
            </div>
          </div>
        </section>

        <!-- Pricing Card -->
        <section class="price-card rounded-2xl p-6 text-white shadow-lg space-y-4">
          <h3 class="text-lg font-bold">Tổng quan giá</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between items-center pb-2 border-b border-white/20">
              <span class="opacity-90">Tổng gốc:</span>
              <span class="font-semibold"><?= number_format($totalOriginal, 0, ',', '.') ?>₫</span>
            </div>
            <?php if ($discountAmount > 0): ?>
              <div class="flex justify-between items-center text-emerald-200">
                <span>Giảm giá (-<?= $discountPercent ?>%):</span>
                <span class="font-semibold">-<?= number_format($discountAmount, 0, ',', '.') ?>₫</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="pt-3 border-t border-white/30">
            <div class="flex justify-between items-baseline">
              <span class="text-sm opacity-90">Thành tiền:</span>
              <span class="text-3xl font-bold"><?= number_format($totalFinal, 0, ',', '.') ?>₫</span>
            </div>
          </div>
          <?php if ($package): ?>
            <a href="goi_datthue.php?id=<?= $pkgId ?>" class="mt-4 block w-full text-center rounded-lg bg-white text-indigo-600 font-semibold px-4 py-3 hover:bg-indigo-50 transition shadow-md">
              Đặt thuê gói này
            </a>
              <p class="text-xs opacity-80 mt-2">Chọn thời gian thuê cho cả gói; giá gói và giảm giá sẽ được áp dụng khi xác nhận.</p>
          <?php else: ?>
            <button disabled class="mt-4 block w-full text-center rounded-lg bg-white/20 text-white/50 font-semibold px-4 py-3 cursor-not-allowed">
              Gói chưa có trang phục
            </button>
          <?php endif; ?>
        </section>
      </div>

      <!-- Items List -->
      <section class="space-y-6">
        <h3 class="text-xl font-bold text-slate-900">Danh sách trang phục trong gói</h3>
        
        <?php if (empty($items)): ?>
          <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center">
            <svg class="w-16 h-16 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <p class="text-slate-500">Gói này chưa có trang phục nào.</p>
          </div>
        <?php else: ?>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach($items as $it): ?>
              <?php
                $itemPrice = (int)($it['GIA_THUE'] ?? 0);
                $itemQty = (int)($it['SO_LUONG'] ?? 1);
                $itemTotal = $itemPrice * $itemQty;
                $itemImg = trim((string)($it['IMAGE_URL'] ?? ''));
              ?>
              <article class="item-card rounded-xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                <?php if ($itemImg !== ''): ?>
                  <div class="aspect-[5/7] bg-slate-100">
                    <img src="<?= h(sb_asset_href($itemImg)) ?>" alt="<?= h($it['TEN']) ?>" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">
                  </div>
                <?php else: ?>
                  <div class="aspect-[5/7] bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center">
                    <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  </div>
                <?php endif; ?>
                
                <div class="p-4 space-y-3">
                  <div>
                    <div class="flex items-start justify-between gap-2 mb-1">
                      <h4 class="font-semibold text-slate-900 text-sm leading-tight"><?= h($it['TEN']) ?></h4>
                      <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-xs font-medium whitespace-nowrap border border-indigo-200">x<?= $itemQty ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                      <?php if (!empty($it['SIZE'])): ?>
                        <span class="flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                          <?= h($it['SIZE']) ?>
                        </span>
                      <?php endif; ?>
                      <?php if (!empty($it['MAU_SAC'])): ?>
                        <span class="flex items-center gap-1">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                          <?= h($it['MAU_SAC']) ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                  
                  <div class="flex items-baseline justify-between pt-2 border-t border-slate-100">
                    <div>
                      <p class="text-xs text-slate-500">Đơn giá × <?= $itemQty ?></p>
                      <p class="text-lg font-bold text-indigo-600"><?= number_format($itemTotal, 0, ',', '.') ?>₫</p>
                    </div>
                    <a href="trangphuc.php?id=<?= (int)$it['ID_TRANG_PHUC'] ?>" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Chi tiết →</a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</body>
</html>
