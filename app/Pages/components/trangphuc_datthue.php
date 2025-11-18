
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
  require_once '../../../database/config.php';
}

require_once __DIR__ . '/../../helpers/assets.php';

if (!function_exists('tp_escape')) {
    function tp_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$costumeId = $_GET['id'] ?? '';
$defaultFrom = $_GET['from'] ?? '';
$defaultTo   = $_GET['to']   ?? '';
$defaultQty  = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
if ($defaultQty < 1) {
    $defaultQty = 1;
}

$costume   = null;
$errorText = null;

if (!ctype_digit((string)$costumeId)) {
    $errorText = 'Trang phục không tồn tại hoặc đã bị xóa.';
} else {
    $priceSelect = "0 AS DON_GIA, NULL AS HIEU_LUC_TU";
    $priceJoin   = '';

    $hasPriceView = false;
    if ($check = $conn->query("SHOW FULL TABLES LIKE 'v_trang_phuc_don_gia_moinhat'")) {
        $hasPriceView = $check->num_rows > 0;
        $check->free();
    }

    if ($hasPriceView) {
        $priceSelect = "COALESCE(gia.DON_GIA, 0) AS DON_GIA, gia.HIEU_LUC_TU";
        $priceJoin   = "LEFT JOIN v_trang_phuc_don_gia_moinhat gia ON gia.ID_TP = tp.ID_TP";
    } else {
        $hasPriceTable = false;
        if ($check = $conn->query("SHOW TABLES LIKE 'don_gia_trang_phuc'")) {
            $hasPriceTable = $check->num_rows > 0;
            $check->free();
        }

        if ($hasPriceTable) {
            $priceSelect = "COALESCE(gia.DON_GIA, 0) AS DON_GIA, gia.HIEU_LUC_TU";
            $priceJoin   = "LEFT JOIN (\n                SELECT x.ID_TP, x.DON_GIA, x.NGAY_GIO AS HIEU_LUC_TU\n                FROM don_gia_trang_phuc x\n                JOIN (\n                    SELECT ID_TP, MAX(NGAY_GIO) AS MG\n                    FROM don_gia_trang_phuc\n                    GROUP BY ID_TP\n                ) m ON m.ID_TP = x.ID_TP AND m.MG = x.NGAY_GIO\n            ) gia ON gia.ID_TP = tp.ID_TP";
        }
    }

    $sql = "SELECT tp.ID_TP, tp.TEN_TP, tp.SIZE, tp.MAU, tp.ID_CN, tp.TINH_TRANG, cn.TEN_CN, cn.DIA_CHI_CN,\n        $priceSelect\n    FROM trang_phuc tp\n    JOIN CHI_NHANH cn ON cn.ID_CN = tp.ID_CN\n    $priceJoin\n    WHERE tp.IS_ACTIVE = 1 AND tp.ID_TP = ?\n    LIMIT 1";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $costumeId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                $costume = $result->fetch_assoc();
            }
        }
        $stmt->close();
    }

    if (!$costume) {
        $errorText = 'Không tìm thấy trang phục hoặc trang phục đã ngưng cho thuê.';
    }
}

$isLoggedIn = !empty($_SESSION['ID_TK']);
$userInfo   = null;
if ($isLoggedIn) {
    $accountId = $_SESSION['ID_TK'];
    if ($accStmt = $conn->prepare('SELECT HO_TEN, EMAIL, SDT FROM tai_khoan WHERE ID_TK = ? LIMIT 1')) {
        $accStmt->bind_param('s', $accountId);
        if ($accStmt->execute()) {
            $accResult = $accStmt->get_result();
            if ($accResult) {
                $userInfo = $accResult->fetch_assoc();
            }
        }
        $accStmt->close();
    }
}

if ($defaultFrom === '') {
    $defaultFrom = date('Y-m-d\TH:i', strtotime('+1 day 10:00'));
}
if ($defaultTo === '') {
    $defaultTo = date('Y-m-d\TH:i', strtotime('+3 day 18:00'));
}

$minDate = date('Y-m-d\TH:i');
$pricePerDay = isset($costume['DON_GIA']) ? (int)$costume['DON_GIA'] : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đặt thuê trang phục<?= $costume ? ' – ' . tp_escape($costume['TEN_TP']) : '' ?></title>
  <?= sb_tailwind_link_tag(); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .hero-bg { background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #22d3ee 100%); }
    .glass-card { backdrop-filter: blur(14px); background: rgba(15,23,42,0.72); border: 1px solid rgba(148,163,184,0.35); }
    label span { letter-spacing: 0.06em; }
  </style>
</head>
<body class="hero-bg min-h-screen text-slate-100">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <header class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs font-medium uppercase tracking-[0.4em] text-sky-200">Stygian Blue Studio</p>
        <h1 class="text-3xl font-semibold">Đặt thuê trang phục</h1>
        <?php if ($costume): ?>
          <p class="text-sm text-sky-100/80 mt-2"><?= tp_escape($costume['TEN_TP']) ?> · Chi nhánh <?= tp_escape($costume['TEN_CN']) ?></p>
        <?php endif; ?>
      </div>
      <a href="trangphuc_chitiet.php?id=<?= tp_escape($costumeId) ?>" class="inline-flex items-center justify-center rounded-xl border border-sky-200/70 px-4 py-2 text-sm font-semibold text-sky-100 transition hover:bg-white/10">Quay lại chi tiết</a>
    </header>

    <?php if ($errorText): ?>
      <section class="mt-12 glass-card rounded-3xl px-10 py-16 text-center">
        <h2 class="text-3xl font-semibold mb-4"><?= tp_escape($errorText) ?></h2>
        <p class="text-slate-200/80 mb-6">Vui lòng kiểm tra lại hoặc chọn một trang phục khác để tiếp tục.</p>
        <a href="trangphuc.php" class="inline-flex items-center justify-center rounded-xl bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-200">Xem danh sách trang phục</a>
      </section>
    <?php else: ?>
      <section class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] items-start">
        <form method="POST" action="../Controller/process_costume_booking.php" class="glass-card rounded-3xl p-8 space-y-6">
          <input type="hidden" name="costume_id" value="<?= tp_escape($costumeId) ?>">
          <input type="hidden" name="branch_id" value="<?= tp_escape($costume['ID_CN']) ?>">
          <input type="hidden" name="price_per_day" value="<?= $pricePerDay ?>">
          <input type="hidden" name="return_to" value="trangphuc_chitiet.php?id=<?= tp_escape($costumeId) ?>">

          <div>
            <h2 class="text-xl font-semibold text-white">Thông tin lịch thuê</h2>
            <p class="text-sm text-sky-100/80 mt-1">Chọn thời gian nhận và trả trang phục. Hệ thống sẽ kiểm tra trùng lịch ngay sau khi bạn gửi yêu cầu.</p>
          </div>

          <div class="grid gap-5 md:grid-cols-2">
            <label class="flex flex-col gap-2 text-sm">
              <span class="text-sky-100/80 uppercase font-semibold">Nhận trang phục</span>
              <input type="datetime-local" name="rent_from" required value="<?= tp_escape($defaultFrom) ?>" min="<?= tp_escape($minDate) ?>" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-base text-white placeholder:text-slate-300 focus:border-sky-300 focus:ring-2 focus:ring-sky-200/60">
            </label>
            <label class="flex flex-col gap-2 text-sm">
              <span class="text-sky-100/80 uppercase font-semibold">Trả dự kiến</span>
              <input type="datetime-local" name="rent_to" required value="<?= tp_escape($defaultTo) ?>" min="<?= tp_escape($minDate) ?>" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-base text-white placeholder:text-slate-300 focus:border-sky-300 focus:ring-2 focus:ring-sky-200/60">
            </label>
            <label class="flex flex-col gap-2 text-sm">
              <span class="text-sky-100/80 uppercase font-semibold">Số lượng bộ</span>
              <input type="number" name="quantity" min="1" value="<?= tp_escape($defaultQty) ?>" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-base text-white focus:border-sky-300 focus:ring-2 focus:ring-sky-200/60">
            </label>
            <label class="flex flex-col gap-2 text-sm">
              <span class="text-sky-100/80 uppercase font-semibold">Ghi chú phối đồ (tuỳ chọn)</span>
              <input type="text" name="style_note" maxlength="120" placeholder="Ví dụ: cần phụ kiện voan, phù hợp concept retro" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-base text-white placeholder:text-slate-300 focus:border-sky-300 focus:ring-2 focus:ring-sky-200/60">
            </label>
          </div>

          <div class="grid gap-5 md:grid-cols-2">
            <label class="flex flex-col gap-2 text-sm">
              <span class="text-sky-100/80 uppercase font-semibold">Họ và tên</span>
              <input type="text" name="contact_name" value="<?= tp_escape($userInfo['HO_TEN'] ?? '') ?>" <?= $isLoggedIn ? 'required' : '' ?> placeholder="Nguyễn Văn A" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-base text-white placeholder:text-slate-300 focus:border-sky-300 focus:ring-2 focus:ring-sky-200/60">
            </label>
            <label class="flex flex-col gap-2 text-sm">
              <span class="text-sky-100/80 uppercase font-semibold">Số điện thoại</span>
              <input type="tel" name="contact_phone" value="<?= tp_escape($userInfo['SDT'] ?? '') ?>" <?= $isLoggedIn ? 'required' : '' ?> placeholder="0912 345 678" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-base text-white placeholder:text-slate-300 focus:border-sky-300 focus:ring-2 focus:ring-sky-200/60">
            </label>
            <label class="flex flex-col gap-2 text-sm md:col-span-2">
              <span class="text-sky-100/80 uppercase font-semibold">Email nhận thông báo</span>
              <input type="email" name="contact_email" value="<?= tp_escape($userInfo['EMAIL'] ?? '') ?>" <?= $isLoggedIn ? 'required' : '' ?> placeholder="ban@example.com" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-base text-white placeholder:text-slate-300 focus:border-sky-300 focus:ring-2 focus:ring-sky-200/60">
            </label>
            <label class="flex flex-col gap-2 text-sm md:col-span-2">
              <span class="text-sky-100/80 uppercase font-semibold">Ghi chú thêm cho studio</span>
              <textarea name="note" rows="3" placeholder="Mong muốn về bối cảnh, giờ lấy đồ, người nhận hộ..." class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-base text-white placeholder:text-slate-300 focus:border-sky-300 focus:ring-2 focus:ring-sky-200/60"></textarea>
            </label>
          </div>

          <?php if (!$isLoggedIn): ?>
            <div class="rounded-2xl border border-amber-200/60 bg-amber-100/20 px-4 py-3 text-sm text-amber-100">
              Bạn cần đăng nhập để hoàn tất đặt thuê. Vui lòng <a href="../../../login.php?redirect=<?= urlencode('app/Pages/Views/trangphuc_datthue.php?id=' . $costumeId) ?>" class="underline font-semibold">đăng nhập</a> trước khi gửi yêu cầu.
            </div>
          <?php endif; ?>

          <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-sky-100/80">Bằng việc gửi yêu cầu, bạn đồng ý với chính sách đặt thuê và hoàn trả của Stygian Blue.</p>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-200" <?= $isLoggedIn ? '' : 'disabled' ?>>Gửi yêu cầu đặt thuê</button>
          </div>
        </form>

        <aside class="glass-card rounded-3xl p-8 space-y-6">
          <div>
            <h2 class="text-xl font-semibold text-white">Tóm tắt chi phí</h2>
            <p class="text-sm text-sky-100/80 mt-1">Ước tính dựa trên số ngày thuê và đơn giá hiện hành. Có thể thay đổi nếu phát sinh phụ phí khi trả.</p>
          </div>

          <dl class="space-y-4 text-sm">
            <div class="flex items-center justify-between">
              <dt class="text-sky-100/80">Đơn giá mỗi ngày</dt>
              <dd class="text-base font-semibold text-white" id="priceLabel"><?= number_format($pricePerDay, 0, ',', '.') ?> ₫</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-sky-100/80">Số ngày dự kiến</dt>
              <dd class="text-base font-semibold text-white" id="dayCount">—</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-sky-100/80">Số lượng</dt>
              <dd class="text-base font-semibold text-white" id="qtyLabel"><?= $defaultQty ?></dd>
            </div>
            <div class="flex items-center justify-between border-t border-white/20 pt-4">
              <dt class="text-sky-100/80">Tạm tính</dt>
              <dd class="text-lg font-semibold text-white" id="estimateTotal">—</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-sky-100/80">Đề xuất tiền cọc (30%)</dt>
              <dd class="text-base font-semibold text-white" id="depositLabel">—</dd>
            </div>
          </dl>

          <div class="space-y-2 text-sm text-sky-100/80">
            <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-white/90">Lưu ý</h3>
            <ul class="list-disc list-inside space-y-1">
              <li>Miễn phí làm mới và ủi hơi nước cho các lượt thuê từ 3 ngày trở lên.</li>
              <li>Trễ hạn trả sẽ tính phí 50% đơn giá mỗi ngày.</li>
              <li>Hãy liên hệ trước nếu cần thay đổi chi nhánh trả trang phục.</li>
            </ul>
          </div>
        </aside>
      </section>
    <?php endif; ?>
  </div>

  <?php if (!$errorText && $pricePerDay > 0): ?>
  <script>
    (function(){
      const pricePerDay = <?= (int)$pricePerDay ?>;
      const fromInput = document.querySelector('input[name="rent_from"]');
      const toInput   = document.querySelector('input[name="rent_to"]');
      const qtyInput  = document.querySelector('input[name="quantity"]');
      const dayLabel  = document.getElementById('dayCount');
      const qtyLabel  = document.getElementById('qtyLabel');
      const totalLabel = document.getElementById('estimateTotal');
      const depositLabel = document.getElementById('depositLabel');

      function calcDays(start, end){
        const a = new Date(start);
        const b = new Date(end);
        if (isNaN(a) || isNaN(b)) return 0;
        const ms = b.getTime() - a.getTime();
        return Math.max(0, Math.ceil(ms / 86400000));
      }

      function update(){
        const from = fromInput?.value;
        const to   = toInput?.value;
        const qty  = Math.max(1, parseInt(qtyInput?.value || '1', 10));
        const days = calcDays(from, to);

        if (dayLabel) {
          dayLabel.textContent = days > 0 ? days + ' ngày' : '—';
        }
        if (qtyLabel) {
          qtyLabel.textContent = qty;
        }

        if (!totalLabel || !depositLabel) return;
        if (days <= 0) {
          totalLabel.textContent = 'Chọn thời gian';
          depositLabel.textContent = '—';
          return;
        }

        const estimated = pricePerDay * days * qty;
        const deposit   = Math.round(estimated * 0.3);
        const fmt       = new Intl.NumberFormat('vi-VN');
        totalLabel.textContent = fmt.format(estimated) + ' ₫';
        depositLabel.textContent = fmt.format(deposit) + ' ₫';
      }

      ['change','input'].forEach(evt => {
        fromInput?.addEventListener(evt, update);
        toInput?.addEventListener(evt, update);
        qtyInput?.addEventListener(evt, update);
      });

      update();
    })();
  </script>
  <?php endif; ?>
</body>
</html>