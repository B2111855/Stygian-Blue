<?php
// Public: Đặt thuê gói trang phục (dedicated page)
if (!isset($conn)) { require_once '../../../database/config.php'; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../helpers/assets.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pkgId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pkgId <= 0) {
  http_response_code(400);
  echo '<p>Thiếu mã gói.</p>';
  exit;
}

$package = null; $items=[]; $branchName='';
if ($stmt = $conn->prepare("SELECT ID_GOI, TEN_GOI, MO_TA, ID_CN_OWNER, DISCOUNT_PERCENT FROM goi_trang_phuc_master WHERE ID_GOI=? LIMIT 1")) {
  $stmt->bind_param('i', $pkgId); $stmt->execute(); $res=$stmt->get_result(); $package=$res?$res->fetch_assoc():null; $stmt->close();
}
if (!$package) { echo '<p>Gói không tồn tại.</p>'; exit; }

if ($br = $conn->prepare("SELECT TEN_CN FROM chi_nhanh WHERE ID_CN=?")) { $br->bind_param('i', $package['ID_CN_OWNER']); $br->execute(); $r=$br->get_result()->fetch_assoc(); $branchName = $r['TEN_CN'] ?? ('CN#'.$package['ID_CN_OWNER']); $br->close(); }

$sql_items = "SELECT c.ID_TRANG_PHUC, c.SO_LUONG,
                      tp.TEN, tp.GIA_THUE,
                      (SELECT ha.URL FROM trang_phuc_hinh_anh ha WHERE ha.ID_TP=c.ID_TRANG_PHUC AND ha.IS_ACTIVE=1 ORDER BY ha.IS_COVER DESC, ha.THU_TU ASC LIMIT 1) AS IMAGE_URL
               FROM goi_trang_phuc_chi_tiet c
               JOIN trang_phuc tp ON tp.ID_TRANG_PHUC=c.ID_TRANG_PHUC
               WHERE c.ID_GOI=? ORDER BY c.THU_TU ASC, tp.TEN ASC";
if ($stmt = $conn->prepare($sql_items)) {
  $stmt->bind_param('i', $pkgId); $stmt->execute(); $res=$stmt->get_result(); while($row=$res->fetch_assoc()) $items[]=$row; $stmt->close();
}

$totalOriginal=0; foreach($items as $it){ $totalOriginal += ((int)$it['GIA_THUE']) * ((int)$it['SO_LUONG']); }
$discountPercent = (int)($package['DISCOUNT_PERCENT'] ?? 0);
$discountAmount = $discountPercent>0 ? (int)round($totalOriginal*$discountPercent/100) : 0;
$totalFinal = $totalOriginal - $discountAmount; // per day

$defaultDays = 1; $defaultQty = 1;
$now = new DateTime('now'); $now->setTime(10,0);
$defaultFromDisplay = $now->format('Y-m-d H:i');
$defaultFrom = $now->format('Y-m-d\TH:i');
$to = clone $now; $to->modify('+'.$defaultDays.' day');
$defaultTo = $to->format('Y-m-d\TH:i');
$debugEnabled = (isset($_SESSION['ID_QUYEN']) && (string)$_SESSION['ID_QUYEN'] === '1') || (isset($_GET['debug']) && ($_GET['debug'] === '1' || strcasecmp($_GET['debug'], 'true') === 0));

// Consume PRG flash (message + form repopulate)
$flashType = '';
$flashMessage = null;
if (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) {
  $flashType = (string)($_SESSION['flash']['type'] ?? '');
  $flashMessage = (string)($_SESSION['flash']['message'] ?? '');
  $old = is_array($_SESSION['flash']['form_old'] ?? null) ? $_SESSION['flash']['form_old'] : [];
  // Repopulate form fields if present
  if (!empty($old['rent_from'])) {
    $defaultFrom = (string)$old['rent_from'];
    // Convert to display format
    $df = DateTime::createFromFormat('Y-m-d\TH:i', $defaultFrom);
    if ($df) { $defaultFromDisplay = $df->format('Y-m-d H:i'); }
  }
  if (!empty($old['rent_days'])) {
    $defaultDays = max(1, (int)$old['rent_days']);
  }
  if (!empty($old['quantity'])) {
    $defaultQty = max(1, (int)$old['quantity']);
  }
  // Recompute return time from from + days
  $tmpFrom = DateTime::createFromFormat('Y-m-d\TH:i', $defaultFrom);
  if ($tmpFrom) { $tmpTo = clone $tmpFrom; $tmpTo->modify('+' . $defaultDays . ' day'); $defaultTo = $tmpTo->format('Y-m-d\TH:i'); }
  unset($_SESSION['flash']);
} else {
  // Backward compatibility with old message keys
  $flashMessage = $_SESSION['message'] ?? null;
  $flashType = (string)($_SESSION['message_type'] ?? '');
  if (isset($_SESSION['message'])) { unset($_SESSION['message']); }
  if (isset($_SESSION['message_type'])) { unset($_SESSION['message_type']); }
}

// Lấy thông tin user từ session (theo mẫu lienhe.php)
$idTk = null;
if (isset($_SESSION['user']['ID_TK'])) {
  $idTk = $_SESSION['user']['ID_TK'];
} elseif (isset($_SESSION['ID_TK'])) {
  $idTk = $_SESSION['ID_TK'];
}
$userName = '';
$userEmail = '';
$userPhone = '';
if ($idTk) {
  if ($st = $conn->prepare('SELECT HO_TEN, EMAIL, SDT FROM TAI_KHOAN WHERE ID_TK = ?')) {
    $st->bind_param('s', $idTk);
    if ($st->execute()) { $st->bind_result($userName, $userEmail, $userPhone); $st->fetch(); }
    $st->close();
  }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đặt thuê gói – <?= h($package['TEN_GOI']) ?></title>
  <?= sb_tailwind_link_tag(); ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
</head>
<body class="bg-slate-50 text-slate-800">
  <style>
    /* Prevent images from overflowing in the sidebar list */
    .pkg-thumb {
      width: 32px;
      height: 32px;
      border-radius: 0.375rem; /* rounded-md */
      overflow: hidden;
      background: #f1f5f9; /* slate-100 */
      flex-shrink: 0;
    }
    .pkg-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    /* Tinh chỉnh Flatpickr gọn hơn nếu thiếu CSS mặc định */
    .flatpickr-calendar { font-size: 13px; }
    .flatpickr-time { font-size: 12px; }
    .flatpickr-calendar.inline, .flatpickr-calendar.open { max-width: 320px; }
  </style>
  <div class="max-w-6xl mx-auto p-4 md:p-6 space-y-6">
    <header class="flex items-center justify-between gap-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-600">Stygian Blue Studio</p>
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900">Đặt thuê gói</h1>
        <p class="text-sm text-slate-600">Chi nhánh: <span class="font-medium text-indigo-600"><?= h($branchName) ?></span></p>
      </div>
      <a href="goi_trang_phuc_chitiet.php?id=<?= $pkgId ?>" class="rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50 transition flex items-center gap-2">
        ← Trở lại gói
      </a>
    </header>

    <?php if ($flashMessage) { ?>
      <div id="flashMessage" class="rounded-2xl px-5 py-4 border-2 <?php
        echo ($flashType==='success')
          ? 'border-emerald-400/60 bg-emerald-500/15 text-emerald-900'
          : 'border-red-400/60 bg-red-500/15 text-red-900';
      ?>">
        <p class="text-sm font-semibold">
          <?= h($flashMessage) ?>
        </p>
      </div>
    <?php } ?>

    <form id="pkgBookingForm" action="../Controller/process_package_booking.php" method="post" class="grid lg:grid-cols-3 gap-6">
      <input type="hidden" name="package_id" value="<?= (int)$pkgId ?>">
      <input type="hidden" name="return_to" value="xemLichhen.php">
      <?php if ($debugEnabled) { ?><input type="hidden" name="debug" value="1"><?php } ?>

      <section class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <h2 class="text-lg font-semibold text-slate-900">Thời gian & thông tin</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <label class="block">
            <span class="text-xs font-bold uppercase text-slate-600">Thời gian nhận</span>
            <input type="text" id="startDisplay" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2" value="<?= h($defaultFromDisplay) ?>">
            <input type="hidden" name="rent_from" id="rentFrom" value="<?= h($defaultFrom) ?>">
          </label>
          <label class="block">
            <span class="text-xs font-bold uppercase text-slate-600">Số ngày thuê</span>
            <div class="mt-2 flex items-center gap-2">
              <button type="button" class="px-3 py-2 rounded border" onclick="adjustDays(-1)">-</button>
              <input type="number" id="rentDaysInput" name="rent_days" min="1" max="30" value="<?= (int)$defaultDays ?>" class="w-24 rounded-lg border border-slate-300 px-3 py-2">
              <button type="button" class="px-3 py-2 rounded border" onclick="adjustDays(1)">+</button>
            </div>
          </label>
          <label class="block">
            <span class="text-xs font-bold uppercase text-slate-600">Số lượng (hệ số gói)</span>
            <input type="number" id="quantity" name="quantity" min="1" max="10" value="<?= (int)$defaultQty ?>" class="mt-2 w-24 rounded-lg border border-slate-300 px-3 py-2">
          </label>
          <label class="block">
            <span class="text-xs font-bold uppercase text-slate-600">Thời gian trả (tự tính)</span>
            <input type="text" id="returnDisplay" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2" value="<?= h(str_replace('T',' ',$defaultTo)) ?>" readonly>
            <input type="hidden" name="rent_to" id="rentTo" value="<?= h($defaultTo) ?>">
          </label>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 pt-4">
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
            <div class="text-xs font-bold uppercase text-slate-600 mb-2">Thông tin khách hàng (từ tài khoản)</div>
            <div class="text-sm text-slate-700 grid sm:grid-cols-3 gap-3">
              <div><span class="text-slate-500">Họ và tên:</span> <span class="font-medium"><?= h($userName) ?></span></div>
              <div><span class="text-slate-500">SĐT:</span> <span class="font-medium"><?= h($userPhone) ?></span></div>
              <div><span class="text-slate-500">Email:</span> <span class="font-medium break-all"><?= h($userEmail) ?></span></div>
            </div>
          </div>
        </div>

        <div class="pt-4">
          <button type="submit" id="pkgSubmitBtn" class="rounded-lg bg-indigo-600 text-white px-5 py-3 font-semibold">Xác nhận đặt thuê gói</button>
        </div>
      </section>

      <aside class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <h3 class="text-lg font-semibold text-slate-900">Tổng quan giá gói</h3>
        <div class="text-sm space-y-2">
          <div class="flex justify-between items-center">
            <span class="text-slate-600">Tổng gốc:</span>
            <span class="font-semibold" id="pkgTotalOriginal"><?= number_format($totalOriginal, 0, ',', '.') ?>₫</span>
          </div>
          <?php if ($discountAmount > 0) { ?>
          <div class="flex justify-between items-center text-emerald-700">
            <span>Giảm giá (-<?= (int)$discountPercent ?>%):</span>
            <span class="font-semibold" id="pkgDiscountAmount">-<?= number_format($discountAmount, 0, ',', '.') ?>₫</span>
          </div>
          <?php } ?>
          <div class="flex justify-between items-center">
            <span class="text-slate-600">Thành tiền/ngày:</span>
            <span class="font-semibold" id="pkgTotalFinalPerDay"><?= number_format($totalFinal, 0, ',', '.') ?>₫</span>
          </div>
        </div>
        <?php if (!empty($items)) { ?>
        <details class="rounded-xl bg-slate-50 border border-slate-200 p-3" open>
          <summary class="cursor-pointer text-xs text-slate-600">Thành phần gói (<?= count($items) ?>)</summary>
          <ul class="mt-2 space-y-1">
            <?php foreach ($items as $pi) { $thumb = trim((string)($pi['IMAGE_URL'] ?? '')); ?>
            <li class="flex items-center justify-between text-xs text-slate-700">
              <div class="flex items-center gap-2 flex-1 min-w-0 mr-2">
                <div class="pkg-thumb">
                  <?php if ($thumb !== '') { ?>
                    <img src="<?= h(sb_asset_href($thumb)) ?>" alt="<?= h($pi['TEN']) ?>" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">
                  <?php } else { ?>
                    <svg class="w-full h-full text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l5-5 4 4 3-3 6 6"/></svg>
                  <?php } ?>
                </div>
                <span class="truncate"><?= h($pi['TEN']) ?></span>
              </div>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 border border-indigo-200">x<?= (int)$pi['SO_LUONG'] ?></span>
              <span class="ml-2 font-semibold whitespace-nowrap"><?= number_format((int)$pi['GIA_THUE'], 0, ',', '.') ?>₫</span>
            </li>
            <?php } ?>
          </ul>
        </details>
        <?php } ?>

        <div class="mt-2 text-sm text-slate-600">
          <div class="flex justify-between items-center border-t pt-2">
            <span>Tổng dự kiến:</span>
            <span class="text-lg font-bold" id="pkgEstimatedTotal"><?= number_format($totalFinal * (int)$defaultDays * (int)$defaultQty, 0, ',', '.') ?>₫</span>
          </div>
        </div>
      </aside>
    </form>
  </div>

  <script>
    (function(){
      const startInput = document.getElementById('startDisplay');
      const rentFrom = document.getElementById('rentFrom');
      const returnDisplay = document.getElementById('returnDisplay');
      const rentTo = document.getElementById('rentTo');
      const daysInput = document.getElementById('rentDaysInput');
      const qtyInput = document.getElementById('quantity');
      const perDay = <?= (int)$totalFinal ?>;

      function formatDateTimeLocal(d){
        const pad=n=> (n<10?'0':'')+n;
        return d.getFullYear()+"-"+pad(d.getMonth()+1)+"-"+pad(d.getDate())+"T"+pad(d.getHours())+":"+pad(d.getMinutes());
      }
      function formatDisplay(d){ return formatDateTimeLocal(d).replace('T',' '); }
      function clampDays(v){ let n=parseInt(v||'1',10)||1; return Math.max(1, Math.min(30, n)); }

      function syncPeriod(){
        const fromStr = rentFrom.value;
        const from = new Date(fromStr);
        const days = clampDays(daysInput.value);
        if (!isNaN(from.getTime())){
          const end = new Date(from.getTime());
          end.setDate(end.getDate()+days);
          rentTo.value = formatDateTimeLocal(end);
          returnDisplay.value = formatDisplay(end);
        }
        updateTotal();
      }
      function updateTotal(){
        const days = clampDays(daysInput.value);
        const qty = Math.max(1, parseInt(qtyInput.value||'1',10));
        const est = perDay * days * qty;
        document.getElementById('pkgEstimatedTotal').textContent = new Intl.NumberFormat('vi-VN').format(est)+"₫";
      }
      window.adjustDays = function(delta){ daysInput.value = clampDays((parseInt(daysInput.value||'1',10)||1)+delta); syncPeriod(); };

      function initPicker(){
        if (typeof flatpickr === 'undefined') return;
        window.flatpickr(startInput, {
          enableTime:true,
          time_24hr:true,
          dateFormat:'Y-m-d H:i',
          defaultDate:'<?= h($defaultFromDisplay) ?>',
          minDate:'today',
          minTime:'08:00',
          maxTime:'21:00',
          onChange: function(selected){
            if (!selected[0]) return;
            const iso = formatDateTimeLocal(selected[0]);
            rentFrom.value = iso;
            syncPeriod();
          }
        });
      }
      (function loadFlatpickr(){
        if (!window.flatpickr){
          const s=document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js'; s.onload=initPicker; document.head.appendChild(s);
        } else { initPicker(); }
      })();
      // Nếu có flash message, cuộn tới đó
      const fm = document.getElementById('flashMessage');
      if (fm) { fm.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      syncPeriod();

      // Submit form via AJAX to keep user on page when errors
      const form = document.getElementById('pkgBookingForm');
      const submitBtn = document.getElementById('pkgSubmitBtn');
      function showBanner(type, message){
        const existing = document.getElementById('flashMessage');
        const cls = type === 'success'
          ? 'border-emerald-400/60 bg-emerald-500/15 text-emerald-900'
          : 'border-red-400/60 bg-red-500/15 text-red-900';
        if (existing){
          existing.className = 'rounded-2xl px-5 py-4 border-2 ' + cls;
          existing.innerHTML = '<p class="text-sm font-semibold"></p>';
          existing.querySelector('p').textContent = message || '';
          existing.scrollIntoView({ behavior: 'smooth', block: 'start' });
          return;
        }
        const div = document.createElement('div');
        div.id = 'flashMessage';
        div.className = 'rounded-2xl px-5 py-4 mb-2 border-2 ' + cls;
        div.innerHTML = '<p class="text-sm font-semibold"></p>';
        div.querySelector('p').textContent = message || '';
        form.parentElement.insertBefore(div, form);
        div.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      if (form){
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          if (submitBtn){ submitBtn.disabled = true; submitBtn.textContent = 'Đang gửi…'; }
          try {
            const fd = new FormData(form);
            const res = await fetch(form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
            const data = await res.json().catch(()=>({ ok:false, message:'Phản hồi không hợp lệ.' }));
            if (data && data.ok){
              window.location.href = data.redirect || '../Views/xemLichhen.php';
              return;
            }
            showBanner('error', (data && data.message) ? data.message : 'Không thể gửi yêu cầu. Vui lòng thử lại.');
          } catch (err){
            showBanner('error', 'Lỗi kết nối. Vui lòng kiểm tra mạng và thử lại.');
          } finally {
            if (submitBtn){ submitBtn.disabled = false; submitBtn.textContent = 'Xác nhận đặt thuê gói'; }
          }
        });
      }
    })();
  </script>
</body>
</html>
