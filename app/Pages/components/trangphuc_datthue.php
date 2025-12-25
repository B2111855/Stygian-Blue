
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
  require_once '../../../database/config.php';
}

require_once __DIR__ . '/../../helpers/assets.php';

if (!function_exists('tp_escape')) {
  function tp_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

// Chỉ hỗ trợ trang phục lẻ; không còn mode/type
$mode = 'costume';
$defaultFromRaw = $defaultFromRaw ?? ($_GET['from'] ?? '');
$defaultToRaw = $defaultToRaw ?? ($_GET['to'] ?? '');
$defaultDaysParam = $defaultDaysParam ?? (isset($_GET['days']) ? (int)$_GET['days'] : null);
$costume = $costume ?? [];
$costumeId = $costumeId ?? (isset($_GET['id']) ? (int)$_GET['id'] : ($costume['ID_TRANG_PHUC'] ?? 0));
$branchId = isset($branchId) ? (int)$branchId : 0;
$defaultQty = isset($defaultQty) ? max(1, (int)$defaultQty) : 1;
$errorText = $errorText ?? null;
$isLoggedIn = $isLoggedIn ?? (!empty($_SESSION['ID_TK']) || !empty($_SESSION['user']['ID_TK']));
$userInfo = $userInfo ?? [];
// Tham khảo trang đặt thuê gói: nạp thông tin KH từ phiên
if ($isLoggedIn && (!$userInfo || empty($userInfo['HO_TEN']))) {
  $idTk = $_SESSION['user']['ID_TK'] ?? $_SESSION['ID_TK'] ?? null;
  if ($idTk) {
    if ($st = $conn->prepare('SELECT HO_TEN, EMAIL, SDT FROM TAI_KHOAN WHERE ID_TK = ?')) {
      $st->bind_param('s', $idTk);
      if ($st->execute()) {
        $st->bind_result($name, $email, $phone);
        if ($st->fetch()) {
          $userInfo = ['HO_TEN' => (string)$name, 'EMAIL' => (string)$email, 'SDT' => (string)$phone];
        }
      }
      $st->close();
    }
  }
}

// Fallback: luôn tải trang phục theo id (lẻ)
if ($costumeId > 0 && empty($costume) && isset($conn)) {
  if ($stmt = $conn->prepare("SELECT tp.ID_TRANG_PHUC, tp.TEN, tp.SIZE, tp.MAU_SAC, COALESCE(tp.GIA_THUE,0) AS GIA_THUE, tp.ID_CN, cn.TEN_CN 
                              FROM trang_phuc tp LEFT JOIN chi_nhanh cn ON cn.ID_CN=tp.ID_CN 
                              WHERE tp.ID_TRANG_PHUC=? LIMIT 1")) {
    $stmt->bind_param('i', $costumeId);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      if ($res) { $costume = $res->fetch_assoc() ?: []; }
    }
    $stmt->close();
    if (empty($costume)) {
      $errorText = 'Không tìm thấy trang phục hoặc đã bị ẩn.';
    }
  }
}

$branchId = $branchId ?: (isset($costume['ID_CN']) ? (int)$costume['ID_CN'] : (isset($_SESSION['selected_branch_id']) ? (int)$_SESSION['selected_branch_id'] : 0));
$pricePerDay = isset($costume['GIA_THUE']) ? (int)$costume['GIA_THUE'] : 0;

if (!$errorText && $costumeId <= 0) {
  $errorText = 'Thiếu mã trang phục cần đặt thuê.';
}
if (!$errorText && $branchId <= 0) {
  $errorText = 'Trang phục chưa gắn chi nhánh, không thể đặt thuê.';
}
if (!$errorText && $pricePerDay <= 0) {
  $errorText = 'Trang phục chưa có đơn giá, vui lòng liên hệ hỗ trợ.';
}

try {
  $defaultStart = $defaultFromRaw !== '' ? new DateTimeImmutable($defaultFromRaw) : new DateTimeImmutable('+1 day 10:00');
} catch (Exception $e) {
  $defaultStart = new DateTimeImmutable('+1 day 10:00');
}

$defaultDays = 2;
if ($defaultDaysParam !== null) {
  $defaultDays = max(1, min(30, $defaultDaysParam));
} elseif ($defaultToRaw !== '') {
  try {
    $userEnd = new DateTimeImmutable($defaultToRaw);
    $diffDays = (int) $defaultStart->diff($userEnd)->days;
    $defaultDays = max(1, min(30, $diffDays ?: 1));
  } catch (Exception $e) {
    $defaultDays = 2;
  }
}

$defaultEnd = $defaultStart->modify("+{$defaultDays} days");
$defaultFrom = $defaultStart->format('Y-m-d\\TH:i');
$defaultTo   = $defaultEnd->format('Y-m-d\\TH:i');
$defaultFromDisplay = str_replace('T',' ',$defaultFrom);
$defaultToDisplay   = str_replace('T',' ',$defaultTo);

$minDate = date('Y-m-d\TH:i');
$flashMessage = $_SESSION['message'] ?? null;
$flashType    = $_SESSION['message_type'] ?? null;
if ($flashMessage !== null) {
  unset($_SESSION['message'], $_SESSION['message_type']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đặt thuê trang phục<?= $costume ? ' – ' . tp_escape($costume['TEN'] ?? '') : '' ?></title>
  <?= sb_tailwind_link_tag(); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Flatpickr (range date-time picker) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
  <style>
    /* Override flatpickr to match dark glass theme */
    .flatpickr-calendar {background:#0f172a;border:1px solid rgba(148,163,184,0.3);box-shadow:0 10px 25px -5px rgba(0,0,0,.4);} 
    .flatpickr-day {color:#e2e8f0;font-weight:500;} 
    .flatpickr-day.today {border-color:#38bdf8;} 
    .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange {background:linear-gradient(135deg,#3b82f6,#0ea5e9);color:#fff;} 
    .flatpickr-months, .flatpickr-weekdays {background:#0f172a;color:#bae6fd;} 
    .flatpickr-time input {color:#fff;background:#1e293b;border:1px solid #334155;} 
    .flatpickr-time .numInputWrapper span {color:#38bdf8;} 
    .range-helper-badge {font-size:10px;letter-spacing:.08em;background:rgba(255,255,255,.08);padding:2px 6px;border-radius:6px;border:1px solid rgba(255,255,255,.12);} 
  </style>
  <style>
    body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .hero-bg { 
      background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #22d3ee 100%); 
      min-height: 100vh;
      position: relative;
    }
    .hero-bg::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.15) 0%, transparent 50%), 
                        radial-gradient(circle at 80% 80%, rgba(34, 211, 238, 0.15) 0%, transparent 50%);
      pointer-events: none;
    }
    .glass-card { 
      backdrop-filter: blur(16px); 
      background: rgba(15,23,42,0.85); 
      border: 1px solid rgba(148,163,184,0.4); 
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-card:hover {
      border-color: rgba(148,163,184,0.6);
      box-shadow: 0 25px 30px -5px rgba(0, 0, 0, 0.4), 0 15px 15px -5px rgba(0, 0, 0, 0.3);
    }
    .input-field {
      transition: all 0.2s ease;
      background: rgba(255, 255, 255, 0.08);
    }
    .input-field:focus {
      background: rgba(255, 255, 255, 0.12);
      transform: translateY(-1px);
    }
    .input-field.error {
      border-color: #ef4444;
      background: rgba(239, 68, 68, 0.1);
    }
    .btn-primary {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.4);
      transition: all 0.3s ease;
    }
    .btn-primary:hover:not(:disabled) {
      box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.5);
      transform: translateY(-2px);
    }
    .btn-primary:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    .qty-btn {
      transition: all 0.2s ease;
    }
    .qty-btn:hover:not(:disabled) {
      background: rgba(255, 255, 255, 0.2);
      transform: scale(1.05);
    }
    .fade-in {
      animation: fadeIn 0.5s ease-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .pulse-dot {
      animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
    .skeleton {
      background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%);
      background-size: 200% 100%;
      animation: shimmer 1.5s infinite;
    }
    @keyframes shimmer {
      0% { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }
  </style>
</head>
<body class="hero-bg text-slate-100">
  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Header -->
    <header class="fade-in flex flex-wrap items-start justify-between gap-6 mb-8">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-3 mb-2">
          <div class="h-1 w-12 bg-gradient-to-r from-sky-400 to-blue-500 rounded-full"></div>
          <p class="text-xs font-bold uppercase tracking-[0.3em] text-sky-300">Stygian Blue Studio</p>
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-white to-sky-200 bg-clip-text text-transparent mb-3">
          Đặt thuê trang phục
        </h1>
        <?php if ($costume): ?>
          <div class="flex flex-wrap items-center gap-2 text-sm text-sky-100/90">
            <span class="font-semibold"><?= tp_escape($costume['TEN']) ?></span>
            <span class="text-sky-300">•</span>
            <span><?= tp_escape($costume['SIZE']) ?></span>
            <span class="text-sky-300">•</span>
            <span><?= tp_escape($costume['MAU_SAC']) ?></span>
            <span class="text-sky-300">•</span>
            <span class="inline-flex items-center gap-1">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
              <?= tp_escape($costume['TEN_CN']) ?>
            </span>
          </div>
        <?php endif; ?>
      </div>
      <a href="<?= 'trangphuc_chitiet.php?id='.tp_escape($costumeId) ?>" 
         class="inline-flex items-center gap-2 rounded-xl border-2 border-sky-300/70 px-5 py-2.5 text-sm font-semibold text-sky-100 transition hover:bg-sky-500/20 hover:border-sky-300">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Quay lại
      </a>
    </header>

    <?php if ($flashMessage) { ?>
      <?php
        $flashSuccess  = $flashType === 'success';
        $flashContainer = $flashSuccess
          ? 'border-emerald-400/60 bg-emerald-500/20'
          : 'border-red-400/60 bg-red-500/20';
        $flashIconColor = $flashSuccess ? 'text-emerald-300' : 'text-red-400';
        $flashTitle     = $flashSuccess ? 'Thành công' : 'Không thể hoàn tất';
        $flashTextColor = $flashSuccess ? 'text-emerald-100' : 'text-red-100';
        $flashSubColor  = $flashSuccess ? 'text-emerald-200/90' : 'text-red-200/90';
      ?>
      <div class="fade-in flex items-start gap-3 rounded-2xl border-2 px-5 py-4 mb-6 <?= $flashContainer ?>">
        <svg class="w-5 h-5 <?= $flashIconColor ?> flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <?php if ($flashSuccess): ?>
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          <?php else: ?>
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          <?php endif; ?>
        </svg>
        <div>
          <p class="text-sm font-semibold <?= $flashTextColor ?>"><?= tp_escape($flashTitle) ?></p>
          <p class="text-sm <?= $flashSubColor ?> mt-1"><?= tp_escape($flashMessage) ?></p>
        </div>
      </div>
    <?php } ?>

    <?php if ($errorText) { ?>
      <section class="fade-in glass-card rounded-3xl px-8 py-20 text-center max-w-2xl mx-auto">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-500/20 border-2 border-red-400/50 mb-6">
          <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <h2 class="text-3xl font-bold mb-4 text-white"><?= tp_escape($errorText) ?></h2>
        <p class="text-slate-300 text-lg mb-8 max-w-md mx-auto">
          Vui lòng kiểm tra lại hoặc chọn một trang phục khác để tiếp tục đặt thuê.
        </p>
        <a href="trangphuc.php" 
           class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 px-8 py-4 text-base font-semibold text-white transition hover:from-sky-600 hover:to-blue-700 shadow-lg hover:shadow-xl">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
          </svg>
          Xem danh sách trang phục
        </a>
      </section>
    <?php } else { ?>
      <section class="fade-in grid gap-6 lg:grid-cols-3 items-start">
        <!-- Main Form -->
        <form method="POST" action="../Controller/process_costume_booking.php" id="bookingForm" class="glass-card rounded-3xl p-6 sm:p-8 space-y-8 lg:col-span-2">
          <input type="hidden" name="costume_id" value="<?= tp_escape($costumeId) ?>">
          <input type="hidden" name="branch_id" value="<?= tp_escape($branchId) ?>">
          <input type="hidden" name="price_per_day" value="<?= $pricePerDay ?>">
          <input type="hidden" name="return_to" value="<?= 'trangphuc_chitiet.php?id='.tp_escape($costumeId) ?>">
          <input type="hidden" name="rent_from" id="rentFrom" value="<?= tp_escape($defaultFrom) ?>">
          <input type="hidden" name="rent_to" id="rentTo" value="<?= tp_escape($defaultTo) ?>">
          <input type="hidden" id="rentDays" value="<?= (int)$defaultDays ?>">

          <!-- Rental Period Section -->
          <div class="space-y-5">
            <div class="flex items-center gap-3">
              <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-sky-500/20 border border-sky-400/30">
                <svg class="w-5 h-5 text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-bold text-white">Thời gian thuê</h2>
                <p class="text-sm text-sky-200/70">Chọn thời gian nhận và trả trang phục</p>
              </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
              <label class="group lg:col-span-2 block">
                <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-200/90 mb-2">
                  Thời gian nhận
                </span>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                  <input type="text" id="rentStart" name="rent_start_display" autocomplete="off"
                         value="<?= tp_escape($defaultFromDisplay) ?>"
                         placeholder="Chọn thời gian nhận"
                         class="input-field w-full rounded-xl border border-white/30 px-4 py-3.5 text-base text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/50 focus:outline-none">
                  <div class="text-sm text-sky-200/80 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white/10 border border-white/20">→</span>
                    <div>
                      <p class="text-xs text-sky-200/60">Trả dự kiến</p>
                      <p class="font-semibold text-white" id="returnPreview"><?= tp_escape($defaultToDisplay) ?></p>
                    </div>
                  </div>
                </div>
                <p class="text-xs text-sky-200/70 mt-2">Giờ làm việc 08:00 - 21:00 (tự động cộng ngày thuê vào thời gian trả).</p>
              </label>

              <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                <label class="group">
                  <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-200/90 mb-2">Số ngày thuê</span>
                  <div class="flex items-center gap-2">
                    <button type="button" class="qty-btn w-10 h-10 rounded-lg border border-white/30" onclick="adjustDays(-1)">-</button>
                    <input type="number" id="rentDaysInput" min="1" max="30" value="<?= (int)$defaultDays ?>"
                           class="input-field w-full text-center rounded-lg border border-white/30 px-3 py-2.5 text-base text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/50 focus:outline-none">
                    <button type="button" class="qty-btn w-10 h-10 rounded-lg border border-white/30" onclick="adjustDays(1)">+</button>
                  </div>
                  <p class="text-xs text-sky-200/60 mt-2">Tối đa 30 ngày.</p>
                </label>

                <label class="group">
                  <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-200/90 mb-2">Số lượng</span>
                  <div class="flex items-center gap-2">
                    <button type="button" class="qty-btn w-10 h-10 rounded-lg border border-white/30" onclick="changeQty(-1)">-</button>
                    <input type="number" name="quantity" id="quantity" min="1" max="10" value="<?= (int)$defaultQty ?>"
                           class="input-field w-full text-center rounded-lg border border-white/30 px-3 py-2.5 text-base text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/50 focus:outline-none">
                    <button type="button" class="qty-btn w-10 h-10 rounded-lg border border-white/30" onclick="changeQty(1)">+</button>
                  </div>
                  <p class="text-xs text-sky-200/60 mt-2">Giữ lại 1 bộ để đảm bảo khả dụng.</p>
                </label>
              </div>
            </div>

            

            <label class="group block">
              <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-200/90 mb-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                Ghi chú phối đồ
              </span>
              <input type="text" name="style_note" maxlength="120" placeholder="Concept, phụ kiện..." 
                     class="input-field w-full rounded-xl border border-white/30 px-4 py-3.5 text-base text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/50 focus:outline-none">
            </label>
          </div>

          <!-- Contact Information Section -->
          <div class="space-y-5">
            <div class="flex items-center gap-3">
              <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-400/30">
                <svg class="w-5 h-5 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-bold text-white">Thông tin liên hệ</h2>
                <p class="text-sm text-sky-200/70">Để chúng tôi có thể liên hệ xác nhận</p>
              </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
              <label class="group sm:col-span-2">
                <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-200/90 mb-2">
                  Họ và tên
                </span>
                <input type="text" name="contact_name" id="contactName"
                       value="<?= tp_escape($userInfo['HO_TEN'] ?? '') ?>" 
                       <?= $isLoggedIn ? 'required' : '' ?> 
                       placeholder="Nguyễn Văn A" 
                       class="input-field w-full rounded-xl border border-white/30 px-4 py-3.5 text-base text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/50 focus:outline-none">
                <span class="error-message hidden text-xs text-red-400 mt-1.5 ml-1"></span>
              </label>

              <label class="group">
                <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-200/90 mb-2">
                  Số điện thoại
                </span>
                <input type="tel" name="contact_phone" id="contactPhone"
                       value="<?= tp_escape($userInfo['SDT'] ?? '') ?>" 
                       <?= $isLoggedIn ? 'required' : '' ?> 
                       placeholder="0912 345 678" 
                       pattern="[0-9]{10,11}"
                       class="input-field w-full rounded-xl border border-white/30 px-4 py-3.5 text-base text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/50 focus:outline-none">
                <span class="error-message hidden text-xs text-red-400 mt-1.5 ml-1"></span>
              </label>

              <label class="group">
                <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-200/90 mb-2">
                  Email
                </span>
                <input type="email" name="contact_email" id="contactEmail"
                       value="<?= tp_escape($userInfo['EMAIL'] ?? '') ?>" 
                       <?= $isLoggedIn ? 'required' : '' ?> 
                       placeholder="ban@example.com" 
                       class="input-field w-full rounded-xl border border-white/30 px-4 py-3.5 text-base text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/50 focus:outline-none">
                <span class="error-message hidden text-xs text-red-400 mt-1.5 ml-1"></span>
              </label>

              <label class="group sm:col-span-2">
                <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-200/90 mb-2">
                  Ghi chú thêm
                </span>
                <textarea name="note" rows="3" placeholder="Mong muốn về bối cảnh, giờ lấy đồ, người nhận hộ..." 
                          class="input-field w-full rounded-xl border border-white/30 px-4 py-3.5 text-base text-white placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/50 focus:outline-none resize-none"></textarea>
              </label>
            </div>
          </div>

          <?php if (!$isLoggedIn) { ?>
            <div class="flex items-start gap-3 rounded-2xl border-2 border-amber-400/60 bg-gradient-to-r from-amber-500/20 to-orange-500/20 px-5 py-4">
              <svg class="w-6 h-6 text-amber-300 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
              </svg>
              <div class="flex-1">
                <p class="text-sm font-semibold text-amber-100 mb-1">Yêu cầu đăng nhập</p>
                <p class="text-sm text-amber-200/90">
                  Bạn cần đăng nhập để hoàn tất đặt thuê. 
                  <a href="../../../login.php?redirect=<?= urlencode('app/Pages/Views/trangphuc_datthue.php?id=' . $costumeId) ?>" 
                     class="underline font-bold hover:text-white transition">Đăng nhập ngay</a>
                </p>
              </div>
            </div>
          <?php } ?>

          <!-- Availability Status -->
          <div id="availabilityStatus" class="hidden rounded-2xl border px-5 py-4"></div>

          <!-- Submit Button -->
          <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-4 pt-4 border-t border-white/20">
            <p class="text-xs text-sky-200/70 leading-relaxed">
              Bằng việc gửi yêu cầu, bạn đồng ý với <a href="#" class="underline hover:text-white transition">chính sách đặt thuê</a> của Stygian Blue.
            </p>
            <button type="submit" id="submitBtn"
                    class="btn-primary flex items-center justify-center gap-2 rounded-xl px-8 py-4 text-base font-bold text-white disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap" 
                    <?= $isLoggedIn ? '' : 'disabled' ?>>
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <span id="submitText">Gửi yêu cầu đặt thuê</span>
              <svg class="hidden w-5 h-5 animate-spin" id="submitSpinner" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </button>
          </div>
        </form>

        <!-- Cost Summary Sidebar -->
        <aside class="glass-card rounded-3xl p-6 sm:p-8 space-y-6 lg:sticky lg:top-8 lg:col-span-1">
          <div class="flex items-center gap-3 pb-5 border-b border-white/20">
            <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500/30 to-sky-500/30 border border-emerald-400/30">
              <svg class="w-6 h-6 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold text-white">Tóm tắt chi phí</h2>
              <p class="text-xs text-sky-200/70 mt-0.5">Ước tính chi phí thuê</p>
            </div>
          </div>

          <!-- Costume Info Card -->
          <div class="rounded-2xl bg-gradient-to-br from-white/10 to-white/5 border border-white/20 p-4 space-y-3">
            <div class="flex items-start gap-3">
              <div class="w-16 h-16 rounded-xl bg-slate-700/50 flex items-center justify-center text-slate-400 text-xs flex-shrink-0 overflow-hidden">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-white text-sm mb-1 truncate"><?= tp_escape($costume['TEN']) ?></h3>
                <div class="flex flex-wrap items-center gap-1.5 text-xs text-sky-200/80">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-500/20 border border-sky-400/30">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                    <?= tp_escape($costume['SIZE']) ?>
                  </span>
                  <span>•</span>
                  <span><?= tp_escape($costume['MAU_SAC']) ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Cost Breakdown -->
          <dl class="space-y-4 text-sm">
            <div class="flex items-center justify-between py-2">
              <dt class="flex items-center gap-2 text-sky-200/80">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>
                Đơn giá / ngày
              </dt>
              <dd class="text-base font-bold text-white" id="priceLabel"><?= number_format($pricePerDay, 0, ',', '.') ?> ₫</dd>
            </div>
            
            <div class="flex items-center justify-between py-2">
              <dt class="flex items-center gap-2 text-sky-200/80">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                Số ngày thuê
              </dt>
              <dd class="text-base font-bold text-white" id="dayCount">
                <span class="skeleton inline-block w-16 h-5 rounded"></span>
              </dd>
            </div>

            <div class="flex items-center justify-between py-2">
              <dt class="flex items-center gap-2 text-sky-200/80">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM13 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z"/></svg>
                Số lượng
              </dt>
              <dd class="text-base font-bold text-white" id="qtyLabel"><?= $defaultQty ?></dd>
            </div>

            <div class="border-t-2 border-white/30 pt-4">
              <div class="flex items-center justify-between mb-1">
                <dt class="text-sky-200/80 font-semibold">Tạm tính</dt>
                <dd class="text-2xl font-bold text-white" id="estimateTotal">
                  <span class="skeleton inline-block w-28 h-7 rounded"></span>
                </dd>
              </div>
              <p class="text-xs text-sky-200/60 text-right">Chưa bao gồm phụ phí (nếu có)</p>
            </div>

            <div class="rounded-xl bg-gradient-to-r from-amber-500/20 to-orange-500/20 border border-amber-400/40 p-4">
              <div class="flex items-start gap-2 mb-2">
                <svg class="w-5 h-5 text-amber-300 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                <div class="flex-1">
                  <p class="text-xs font-bold text-amber-100 mb-1">Đề xuất tiền cọc (30%)</p>
                  <p class="text-lg font-bold text-amber-100" id="depositLabel">
                    <span class="skeleton inline-block w-24 h-6 rounded"></span>
                  </p>
                </div>
              </div>
            </div>
          </dl>

          <!-- Benefits List -->
          <div class="rounded-2xl bg-gradient-to-br from-emerald-500/10 to-sky-500/10 border border-emerald-400/30 p-5 space-y-3">
            <h3 class="text-sm font-bold uppercase tracking-wider text-emerald-300 flex items-center gap-2">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              Ưu đãi
            </h3>
            <ul class="space-y-2.5 text-sm text-sky-100/90">
              <li class="flex items-start gap-2">
                <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>Miễn phí làm mới & ủi hơi nước (thuê ≥3 ngày)</span>
              </li>
              <li class="flex items-start gap-2">
                <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>Hỗ trợ tư vấn phối đồ miễn phí</span>
              </li>
              <li class="flex items-start gap-2">
                <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>Đổi size miễn phí trong 24h đầu</span>
              </li>
            </ul>
          </div>

          <!-- Important Notes -->
          <div class="space-y-2.5 text-xs text-sky-200/70">
            <h3 class="text-xs font-bold uppercase tracking-wider text-white/90 flex items-center gap-2">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
              Lưu ý quan trọng
            </h3>
            <ul class="space-y-2 pl-6">
              <li class="relative before:content-['•'] before:absolute before:-left-4 before:text-sky-400">
                Trễ hạn trả: phụ thu 50% đơn giá/ngày
              </li>
              <li class="relative before:content-['•'] before:absolute before:-left-4 before:text-sky-400">
                Hư hỏng nhẹ: thu phí sửa chữa theo báo giá
              </li>
              <li class="relative before:content-['•'] before:absolute before:-left-4 before:text-sky-400">
                Mất mát/hư hỏng nặng: bồi thường 100% giá trị
              </li>
              <li class="relative before:content-['•'] before:absolute before:-left-4 before:text-sky-400">
                Liên hệ trước nếu cần đổi chi nhánh trả
              </li>
            </ul>
          </div>
        </aside>
      </section>
    <?php } ?>
  </div>

    <?php if (!$errorText && $pricePerDay > 0): ?>
  <script>
    (function(){
      const pricePerDay = <?= (int)$pricePerDay ?>;
      const fromInput = document.getElementById('rentFrom');
      const toInput = document.getElementById('rentTo');
      const startInput = document.getElementById('rentStart');
      const daysVisibleInput = document.getElementById('rentDaysInput');
      const hiddenDaysInput = document.getElementById('rentDays');
      const returnPreview = document.getElementById('returnPreview');
      const qtyInput = document.getElementById('quantity');
      const dayLabel = document.getElementById('dayCount');
      const daysDisplay = document.getElementById('daysDisplay');
      const qtyLabel = document.getElementById('qtyLabel');
      const totalLabel = document.getElementById('estimateTotal');
      const depositLabel = document.getElementById('depositLabel');
      const form = document.getElementById('bookingForm');
      const submitBtn = document.getElementById('submitBtn');
      const submitText = document.getElementById('submitText');
      const submitSpinner = document.getElementById('submitSpinner');
      const availabilityStatus = document.getElementById('availabilityStatus');

      function calcDays(start, end){
        const a = new Date(start);
        const b = new Date(end);
        if (isNaN(a) || isNaN(b)) return 0;
        const ms = b.getTime() - a.getTime();
        return Math.max(0, Math.ceil(ms / 86400000));
      }

      function formatCurrency(amount){
        return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫';
      }

      function formatDateTimeLocal(date){
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
      }

      function formatDisplayFromIso(value){
        if (!value) return '—';
        return value.replace('T',' ');
      }

      function parseDisplayDate(value){
        if (!value) return null;
        const normalized = value.trim().replace(/\s+/g, ' ');
        const parsed = new Date(normalized.replace(' ', 'T'));
        if (isNaN(parsed)) return null;
        return formatDateTimeLocal(parsed);
      }

      function clampDays(raw){
        let parsed = parseInt(raw, 10);
        if (isNaN(parsed)) parsed = 1;
        const limited = Math.max(1, Math.min(30, parsed));
        if (daysVisibleInput) daysVisibleInput.value = limited;
        if (hiddenDaysInput) hiddenDaysInput.value = limited;
        return limited;
      }

      function syncReturnPreview(){
        if (!returnPreview) return;
        if (!toInput?.value) {
          returnPreview.textContent = '—';
          return;
        }
        returnPreview.textContent = formatDisplayFromIso(toInput.value);
      }

      function syncRentalPeriod({ skipAvailability = false } = {}){
        if (!fromInput?.value) {
          if (toInput) toInput.value = '';
          syncReturnPreview();
          updateSummary();
          return;
        }
        const start = new Date(fromInput.value);
        if (isNaN(start.getTime())) {
          if (toInput) toInput.value = '';
          syncReturnPreview();
          updateSummary();
          return;
        }
        const days = clampDays(daysVisibleInput?.value ?? hiddenDaysInput?.value ?? '1');
        const end = new Date(start.getTime());
        end.setDate(end.getDate() + days);
        if (toInput) toInput.value = formatDateTimeLocal(end);
        syncReturnPreview();
        updateSummary();
        if (!skipAvailability) availabilityDebounced();
      }

      function setStartFromDate(date){
        if (!fromInput) return;
        const iso = formatDateTimeLocal(date);
        fromInput.value = iso;
        if (startInput) startInput.value = formatDisplayFromIso(iso);
      }

      function validateDates(){
        const startValue = fromInput?.value || '';
        const endValue = toInput?.value || '';
        const from = new Date(startValue);
        const to = new Date(endValue);
        const now = new Date();
        const days = clampDays(daysVisibleInput?.value ?? hiddenDaysInput?.value ?? '1');
        const errors = [];

        if (isNaN(from.getTime())) {
          errors.push('Vui lòng chọn thời gian nhận');
        } else if (from < now) {
          errors.push('Thời gian nhận phải sau thời điểm hiện tại');
        } else {
          // Kiểm tra giờ làm việc (8h-21h)
          const hour = from.getHours();
          if (hour < 8 || hour >= 21) {
            errors.push('Giờ nhận phải trong khoảng 8:00 - 21:00');
          }
        }
        if (!isNaN(from.getTime()) && !isNaN(to.getTime()) && to <= from) {
          errors.push('Thời gian trả phải sau thời gian nhận');
        }
        if (days > 30) {
          errors.push('Thời gian thuê tối đa 30 ngày');
        }

        if (errors.length > 0) {
          startInput?.classList.add('error');
          daysVisibleInput?.classList.add('error');
        } else {
          startInput?.classList.remove('error');
          daysVisibleInput?.classList.remove('error');
        }

        return errors.length === 0;
      }

      function validatePhone(){
        const phone = document.getElementById('contactPhone');
        if (!phone) return true;
        
        const value = phone.value.trim();
        const isValid = /^[0-9]{10,11}$/.test(value);
        const error = phone.closest('label').querySelector('.error-message');
        
        if (!isValid && value) {
          phone.classList.add('error');
          if (error) {
            error.textContent = 'Số điện thoại phải có 10-11 chữ số';
            error.classList.remove('hidden');
          }
        } else {
          phone.classList.remove('error');
          if (error) error.classList.add('hidden');
        }
        
        return isValid || !value;
      }

      function validateEmail(){
        const email = document.getElementById('contactEmail');
        if (!email) return true;
        
        const value = email.value.trim();
        const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        const error = email.closest('label').querySelector('.error-message');
        
        if (!isValid && value) {
          email.classList.add('error');
          if (error) {
            error.textContent = 'Email không hợp lệ';
            error.classList.remove('hidden');
          }
        } else {
          email.classList.remove('error');
          if (error) error.classList.add('hidden');
        }
        
        return isValid || !value;
      }

      function updateSummary(){
        const from = fromInput?.value;
        const to = toInput?.value;
        const qty = Math.max(1, parseInt(qtyInput?.value || '1', 10));
        const days = calcDays(from, to);

        if (qtyLabel) qtyLabel.textContent = qty;

        if (dayLabel) {
          if (days > 0) {
            dayLabel.textContent = days + ' ngày';
            dayLabel.closest('.skeleton')?.classList.remove('skeleton');
          } else {
            dayLabel.innerHTML = '<span class="skeleton inline-block w-16 h-5 rounded"></span>';
          }
        }
        if (daysDisplay) {
          daysDisplay.textContent = (days > 0 ? days : clampDays(daysVisibleInput?.value || '1')) + ' ngày';
        }

        if (!totalLabel || !depositLabel) return;

        if (days <= 0) {
          totalLabel.innerHTML = '<span class="skeleton inline-block w-28 h-7 rounded"></span>';
          depositLabel.innerHTML = '<span class="skeleton inline-block w-24 h-6 rounded"></span>';
          return;
        }

        const estimated = pricePerDay * days * qty;
        const deposit = Math.round(estimated * 0.3);
        
        totalLabel.textContent = formatCurrency(estimated);
        totalLabel.closest('.skeleton')?.classList.remove('skeleton');
        
        depositLabel.textContent = formatCurrency(deposit);
        depositLabel.closest('.skeleton')?.classList.remove('skeleton');
      }

      window.adjustDays = function(delta){
        const current = parseInt(daysVisibleInput?.value || '1', 10);
        const updated = Math.max(1, Math.min(30, current + delta));
        if (daysVisibleInput) {
          daysVisibleInput.value = updated;
          clampDays(updated);
          syncRentalPeriod();
        }
      };

      window.presetDuration = function(btn){
        const daysParam = btn?.getAttribute('data-days');
        const daysInt = Math.max(1, Math.min(30, parseInt(daysParam,10) || 1));
        clampDays(daysInt);
        const now = new Date();
        now.setDate(now.getDate() + 1);
        now.setHours(10,0,0,0);
        setStartFromDate(now);
        window.flatpickrInstance?.setDate(now, false);
        syncRentalPeriod();
        validateDates();
      };

      window.changeQty = function(delta){
        const current = parseInt(qtyInput.value || '1', 10);
        const newVal = Math.max(1, Math.min(10, current + delta));
        qtyInput.value = newVal;
        updateSummary();
      };

      if (form) {
        form.addEventListener('submit', function(e){
          e.preventDefault();
          
          // Check availability status first
          if (lastAvailabilityCheck === false) {
            availabilityStatus.className = 'flex items-start gap-3 rounded-2xl border-2 border-red-400/60 bg-red-500/20 px-5 py-4';
            availabilityStatus.innerHTML = `
              <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
              </svg>
              <div>
                <p class="text-sm font-semibold text-red-100">Không thể đặt thuê</p>
                <p class="text-sm text-red-200/90 mt-1">Trang phục không khả dụng trong khoảng thời gian này.</p>
              </div>
            `;
            availabilityStatus.classList.remove('hidden');
            availabilityStatus.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
          }
          
          if (lastAvailabilityCheck === null) {
            availabilityStatus.className = 'flex items-start gap-3 rounded-2xl border-2 border-amber-400/60 bg-amber-500/20 px-5 py-4';
            availabilityStatus.innerHTML = `
              <svg class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16z" clip-rule="evenodd"/>
              </svg>
              <div>
                <p class="text-sm font-semibold text-amber-100">Chưa kiểm tra khả dụng</p>
                <p class="text-sm text-amber-200/90 mt-1">Vui lòng đợi hệ thống kiểm tra khả dụng.</p>
              </div>
            `;
            availabilityStatus.classList.remove('hidden');
            availabilityStatus.scrollIntoView({ behavior: 'smooth', block: 'center' });
            checkAvailability();
            return;
          }
          
          const isValid = validateDates() && validatePhone() && validateEmail();
          if (!isValid) {
            availabilityStatus.className = 'flex items-start gap-3 rounded-2xl border-2 border-red-400/60 bg-red-500/20 px-5 py-4';
            availabilityStatus.innerHTML = `
              <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
              </svg>
              <div>
                <p class="text-sm font-semibold text-red-100">Vui lòng kiểm tra lại thông tin</p>
                <p class="text-sm text-red-200/90 mt-1">Có một số trường thông tin chưa hợp lệ. Vui lòng sửa và thử lại.</p>
              </div>
            `;
            availabilityStatus.classList.remove('hidden');
            availabilityStatus.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
          }
          submitBtn.disabled = true;
          submitText.textContent = 'Đang xử lý...';
          submitSpinner.classList.remove('hidden');
          form.submit();
        });
      }

      daysVisibleInput?.addEventListener('input', function(){
        clampDays(this.value);
        syncRentalPeriod();
      });
      daysVisibleInput?.addEventListener('change', function(){
        clampDays(this.value);
        syncRentalPeriod();
      });
      startInput?.addEventListener('blur', function(){
        const iso = parseDisplayDate(this.value);
        if (!iso || iso === fromInput.value) return;
        fromInput.value = iso;
        this.value = formatDisplayFromIso(iso);
        syncRentalPeriod();
        validateDates();
      });
      qtyInput?.addEventListener('input', function(){
        updateSummary();
        availabilityDebounced();
      });

      document.getElementById('contactPhone')?.addEventListener('blur', validatePhone);
      document.getElementById('contactEmail')?.addEventListener('blur', validateEmail);

      document.querySelectorAll('.preset-btn').forEach(btn => {
        const d = parseInt(btn.getAttribute('data-days'),10) || 1;
        const priceSpan = btn.querySelector('[data-price]');
        if (priceSpan) priceSpan.textContent = '(' + new Intl.NumberFormat('vi-VN').format(d * pricePerDay) + '₫)';
      });

      function availabilityDebounced(){
        clearTimeout(availTimer);
        availTimer = setTimeout(checkAvailability, 450);
      }
      let availTimer;
      let lastAvailabilityCheck = null;
      async function checkAvailability(){
        if (!fromInput.value || !toInput.value) return;
        availabilityStatus.className = 'flex items-start gap-3 rounded-2xl border-2 border-sky-400/60 bg-sky-500/20 px-5 py-4';
        availabilityStatus.innerHTML = '<svg class="w-5 h-5 text-sky-300 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM11 13H9V9h2v4zm0-6H9v2h2V7z"/></svg><p class="text-sm text-sky-200">Đang kiểm tra khả dụng...</p>';
        availabilityStatus.classList.remove('hidden');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.title = 'Đang kiểm tra khả dụng...';
        }
        try {
          const url = '../Controller/check_costume_availability.php?id=<?= tp_escape($costumeId) ?>&from=' + encodeURIComponent(fromInput.value) + '&to=' + encodeURIComponent(toInput.value);
          const res = await fetch(url);
          let data = null;
          let rawText = '';
          try {
            data = await res.json();
          } catch (parseErr) {
            try { rawText = await res.clone().text(); } catch(_) {}
          }
          if (!res.ok || data === null) {
            console.warn('[Availability] Non-OK or non-JSON response', { status: res.status, statusText: res.statusText, rawText: rawText && rawText.substring(0, 300) });
            const detail = (res.status ? ('Mã trạng thái: ' + res.status + (res.statusText ? ' ' + res.statusText : '')) : '') + (rawText ? ('\n' + rawText.substring(0, 160)) : '');
            const sanitized = detail.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));
            availabilityStatus.className = 'flex items-start gap-3 rounded-2xl border-2 border-amber-400/60 bg-amber-500/20 px-5 py-4';
            availabilityStatus.innerHTML = '<svg class="w-5 h-5 text-amber-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg><div><p class="text-sm font-semibold text-amber-100">Không kiểm tra được</p><p class="text-xs text-amber-200 mt-1">Thử lại sau.</p>' + (detail ? ('<pre class="mt-2 whitespace-pre-wrap text-[11px] text-amber-200/80">'+ sanitized +'</pre>') : '') + '<div class="mt-2"><button type="button" class="underline text-amber-100 font-semibold" onclick="window.checkAvailability && window.checkAvailability()">Thử lại</button></div></div>';
            lastAvailabilityCheck = false;
            if (submitBtn) {
              submitBtn.disabled = true;
              submitBtn.title = 'Không thể kiểm tra khả dụng. Vui lòng thử lại.';
            }
            return;
          }
          const ok = data.available === true;
          if (ok) {
            console.info('[Availability] Available');
            availabilityStatus.className = 'flex items-start gap-3 rounded-2xl border-2 border-emerald-400/60 bg-emerald-500/20 px-5 py-4';
            availabilityStatus.innerHTML = '<svg class="w-5 h-5 text-emerald-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M13.707 8.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-2-2a1 1 0 111.414-1.414L9 11.586l3.293-3.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><div><p class="text-sm font-semibold text-emerald-100">Khoảng thời gian khả dụng</p><p class="text-xs text-emerald-200 mt-1">Chưa có lịch trùng.</p></div>';
            lastAvailabilityCheck = true;
            if (submitBtn && <?= $isLoggedIn ? 'true' : 'false' ?>) {
              submitBtn.disabled = false;
              submitBtn.title = 'Gửi yêu cầu đặt thuê';
            }
          } else {
            const hint = data.next_available || data.earliest_start || '';
            console.info('[Availability] Unavailable', { hint, conflicts: data.conflicts || null, next_available: data.next_available || null });
            const extra = hint ? ('<p class="text-xs text-red-200 mt-1">Có thể đặt trước từ: <strong>'+ hint +'</strong></p>') : '<p class="text-xs text-red-200 mt-1">Vui lòng chọn thời gian khác.</p>';
            availabilityStatus.className = 'flex items-start gap-3 rounded-2xl border-2 border-red-400/60 bg-red-500/20 px-5 py-4';
            availabilityStatus.innerHTML = '<svg class="w-5 h-5 text-red-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg><div><p class="text-sm font-semibold text-red-100">Không khả dụng</p>'+extra+'</div>';
            lastAvailabilityCheck = false;
            if (submitBtn) {
              submitBtn.disabled = true;
              submitBtn.title = 'Trang phục không khả dụng trong khoảng thời gian này';
            }
          }
        } catch(e) {
          console.error('[Availability] Error', e);
          availabilityStatus.className = 'flex items-start gap-3 rounded-2xl border-2 border-amber-400/60 bg-amber-500/20 px-5 py-4';
          const msg = (e && e.message) ? String(e.message).substring(0,160) : '';
          const sanitized = msg.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));
          availabilityStatus.innerHTML = '<svg class="w-5 h-5 text-amber-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/><path d="M9 7a1 1 0 102 0 1 1 0 00-2 0z"/></svg><div><p class="text-sm font-semibold text-amber-100">Không kiểm tra được</p><p class="text-xs text-amber-200 mt-1">Thử lại sau.</p>'+ (msg ? ('<pre class="mt-2 whitespace-pre-wrap text-[11px] text-amber-200/80">'+ sanitized +'</pre>') : '') + '<div class="mt-2"><button type="button" class="underline text-amber-100 font-semibold" onclick="window.checkAvailability && window.checkAvailability()">Thử lại</button></div></div>';
          lastAvailabilityCheck = false;
          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.title = 'Lỗi khi kiểm tra khả dụng. Vui lòng thử lại.';
          }
        }
      }
      window.checkAvailability = checkAvailability;

      function initStartPicker(){
        if (!startInput || typeof flatpickr === 'undefined') return;
        const defaultDisplay = '<?= tp_escape($defaultFromDisplay) ?>';
        window.flatpickrInstance = flatpickr(startInput, {
          enableTime: true,
          time_24hr: true,
          dateFormat: 'Y-m-d H:i',
          minDate: 'today',
          minTime: '08:00',
          maxTime: '21:00',
          defaultDate: defaultDisplay,
          onChange: function(selected){
            if (!selected[0]) return;
            setStartFromDate(selected[0]);
            syncRentalPeriod();
            validateDates();
          },
          onReady: function(selectedDates, dateStr, instance) {
            // Add hint text
            const cal = instance.calendarContainer;
            if (cal && !cal.querySelector('.working-hours-hint')) {
              const hint = document.createElement('div');
              hint.className = 'working-hours-hint';
              hint.style.cssText = 'padding: 8px 12px; text-align: center; background: rgba(59, 130, 246, 0.1); color: #38bdf8; font-size: 11px; border-top: 1px solid rgba(148, 163, 184, 0.2);';
              hint.textContent = 'Giờ làm việc: 8:00 - 21:00';
              cal.appendChild(hint);
            }
          }
        });
      }

      (function loadFlatpickr(){
        if (!window.flatpickr) {
          const s = document.createElement('script');
          s.src = 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js';
          s.onload = initStartPicker;
          document.head.appendChild(s);
        } else {
          initStartPicker();
        }
      })();

      clampDays(daysVisibleInput?.value ?? hiddenDaysInput?.value ?? '1');
      syncRentalPeriod({ skipAvailability: true });
      updateSummary();
      
      // Initial availability check & disable submit until verified
      <?php if ($isLoggedIn): ?>
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.title = 'Đang kiểm tra khả dụng...';
      }
      setTimeout(() => {
        if (fromInput?.value && toInput?.value) {
          checkAvailability();
        }
      }, 500);
      <?php endif; ?>

      document.querySelectorAll('.input-field').forEach(input => {
        input.addEventListener('focus', function() {
          this.closest('label')?.querySelector('span')?.classList.add('text-sky-300');
        });
        input.addEventListener('blur', function() {
          this.closest('label')?.querySelector('span')?.classList.remove('text-sky-300');
        });
      });
    })();
  </script>
  <?php endif; ?>
</body>
</html>