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
$fromQuery = $_GET['from'] ?? '';
$toQuery   = $_GET['to']   ?? '';
$qtyQuery  = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
if ($qtyQuery < 1) {
    $qtyQuery = 1;
}

$costume    = null;
$gallery    = [];
$recommend  = [];
$errorText  = null;

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

    $sql = "SELECT\n        tp.ID_TP, tp.TEN_TP, tp.SIZE, tp.MAU, tp.TINH_TRANG, tp.NGAY_GIAT_CUOI, tp.GHI_CHU,\n        tp.ID_CN, cn.TEN_CN, cn.DIA_CHI_CN, cn.SDT_CN,\n        loai.TEN_LOAI, loai.MOTA AS MOTA_LOAI,\n        $priceSelect\n    FROM trang_phuc tp\n    JOIN CHI_NHANH cn ON cn.ID_CN = tp.ID_CN\n    LEFT JOIN trang_phuc_loai loai ON loai.ID_LOAI = tp.ID_LOAI\n    $priceJoin\n    WHERE tp.IS_ACTIVE = 1 AND tp.ID_TP = ?\n    LIMIT 1";

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
        $errorText = 'Không tìm thấy thông tin trang phục phù hợp.';
    } else {
        if ($imgStmt = $conn->prepare("SELECT URL, ALT_TEXT, IS_COVER FROM trang_phuc_hinh_anh WHERE ID_TP = ? AND IS_ACTIVE = 1 ORDER BY IS_COVER DESC, THU_TU ASC, ID_HA ASC")) {
            $imgStmt->bind_param('i', $costumeId);
            if ($imgStmt->execute()) {
                $imgResult = $imgStmt->get_result();
                if ($imgResult) {
                    while ($row = $imgResult->fetch_assoc()) {
                        $gallery[] = $row;
                    }
                }
            }
            $imgStmt->close();
        }

        $recommendSql = "SELECT tp.ID_TP, tp.TEN_TP, tp.SIZE, tp.MAU, tp.ID_CN, cn.TEN_CN, $priceSelect, ha.URL AS HINH_ANH\n        FROM trang_phuc tp\n        JOIN CHI_NHANH cn ON cn.ID_CN = tp.ID_CN\n        $priceJoin\n        LEFT JOIN (\n            SELECT ID_TP, SUBSTRING_INDEX(GROUP_CONCAT(URL ORDER BY IS_COVER DESC, THU_TU ASC, ID_HA ASC SEPARATOR '||'), '||', 1) AS URL\n            FROM trang_phuc_hinh_anh\n            WHERE IS_ACTIVE = 1\n            GROUP BY ID_TP\n        ) ha ON ha.ID_TP = tp.ID_TP\n        WHERE tp.IS_ACTIVE = 1 AND tp.ID_TP <> ?";

        $params = [$costumeId];
        $types  = 'i';

        if (!empty($costume['TEN_LOAI'])) {
            $recommendSql .= ' AND tp.ID_LOAI = (SELECT ID_LOAI FROM trang_phuc WHERE ID_TP = ? LIMIT 1)';
            $types .= 'i';
            $params[] = $costumeId;
        } else {
            $recommendSql .= ' AND tp.ID_CN = ?';
            $types .= 'i';
            $params[] = $costume['ID_CN'];
        }

        $recommendSql .= ' ORDER BY tp.TINH_TRANG = "san_sang" DESC, tp.TEN_TP ASC LIMIT 4';

        if ($recStmt = $conn->prepare($recommendSql)) {
            $recStmt->bind_param($types, ...$params);
            if ($recStmt->execute()) {
                if ($recResult = $recStmt->get_result()) {
                    while ($row = $recResult->fetch_assoc()) {
                        $recommend[] = $row;
                    }
                }
            }
            $recStmt->close();
        }
    }
}

$statusMap = [
    'san_sang'  => ['Sẵn sàng cho thuê', 'bg-emerald-50 text-emerald-700 border border-emerald-200'],
    'dang_thue' => ['Đang có lịch thuê', 'bg-indigo-50 text-indigo-700 border border-indigo-200'],
    'bao_tri'   => ['Đang bảo trì', 'bg-amber-50 text-amber-700 border border-amber-200'],
    'ngung'     => ['Ngưng cho thuê', 'bg-rose-50 text-rose-700 border border-rose-200'],
];

$coverImage = $gallery[0]['URL'] ?? '../../../public/images/bg01.png';
$coverAlt   = $gallery[0]['ALT_TEXT'] ?? ($costume['TEN_TP'] ?? 'Trang phục');
$statusKey  = $costume['TINH_TRANG'] ?? null;
$statusInfo = $statusKey && isset($statusMap[$statusKey]) ? $statusMap[$statusKey] : null;
$priceValue = isset($costume['DON_GIA']) ? (int)$costume['DON_GIA'] : 0;

$prefillQuery = http_build_query([
    'id'  => $costumeId,
    'from' => $fromQuery,
    'to'   => $toQuery,
    'qty'  => $qtyQuery,
]);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chi tiết trang phục<?= $costume ? ' – ' . tp_escape($costume['TEN_TP']) : '' ?></title>
  <?= sb_tailwind_link_tag(); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .gradient-bg { background: linear-gradient(130deg, rgba(15,23,42,0.92) 0%, rgba(79,70,229,0.88) 38%, rgba(14,165,233,0.85) 100%); }
    .glass-panel { backdrop-filter: blur(12px); background: rgba(255,255,255,0.82); border: 1px solid rgba(148,163,184,0.25); }
    .detail-chip { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:999px; font-weight:600; font-size:0.8rem; background:rgba(15,118,110,0.08); color:#0f766e; }
    .thumb-active { outline:2px solid #4f46e5; outline-offset:2px; }
    .info-grid { display:grid; gap:1.25rem; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); }
  </style>
</head>
<body class="bg-slate-950 text-slate-100">
  <div class="gradient-bg min-h-screen">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <header class="flex items-center justify-between gap-4 text-white">
        <a href="trangphuc.php" class="text-sm font-medium uppercase tracking-[0.3em] text-sky-200 hover:text-white transition">Trở lại danh sách</a>
        <div class="text-right">
          <p class="text-xs uppercase tracking-[0.4em] text-sky-200">Stygian Blue Studio</p>
          <h1 class="text-2xl font-semibold">Chi tiết trang phục</h1>
        </div>
      </header>

      <?php if ($errorText): ?>
        <section class="mt-12 glass-panel rounded-3xl px-10 py-16 text-center text-slate-700 bg-white">
          <h2 class="text-3xl font-semibold mb-4 text-slate-900"><?= tp_escape($errorText) ?></h2>
          <p class="text-slate-600 mb-6">Trang phục có thể đã bị chuyển trạng thái hoặc không còn khả dụng. Vui lòng quay lại danh sách để chọn trang phục khác.</p>
          <a href="trangphuc.php" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-700">Xem các trang phục khác</a>
        </section>
      <?php else: ?>
        <section class="mt-10 grid gap-10 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] items-start">
          <div class="space-y-6">
            <div class="glass-panel rounded-3xl overflow-hidden">
              <div class="relative">
                <img id="tp-main-img" src="<?= tp_escape($coverImage) ?>" alt="<?= tp_escape($coverAlt) ?>" class="w-full object-cover aspect-[4/3]">
                <?php if ($statusInfo): ?>
                  <span class="absolute top-6 left-6 inline-flex items-center rounded-full px-4 py-1.5 text-sm font-semibold <?= tp_escape($statusInfo[1]) ?>">
                    <?= tp_escape($statusInfo[0]) ?>
                  </span>
                <?php endif; ?>
              </div>

              <?php if (count($gallery) > 1): ?>
                <div class="px-6 pb-6 pt-4 bg-white/60">
                  <div class="grid grid-cols-4 sm:grid-cols-5 gap-3">
                    <?php foreach ($gallery as $index => $image): ?>
                      <button type="button" class="rounded-2xl overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 transition" data-thumb="<?= tp_escape($image['URL']) ?>" data-alt="<?= tp_escape($image['ALT_TEXT'] ?? $costume['TEN_TP']) ?>">
                        <img src="<?= tp_escape($image['URL']) ?>" alt="<?= tp_escape($image['ALT_TEXT'] ?? $costume['TEN_TP']) ?>" class="object-cover w-full aspect-[3/4] <?= $index === 0 ? 'thumb-active' : '' ?>">
                      </button>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <div class="glass-panel rounded-3xl p-8 bg-white/80 text-slate-800">
              <header class="border-b border-slate-200 pb-6 mb-6">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-[0.4em]">Thông tin tổng quát</p>
                <h2 class="text-3xl font-semibold text-slate-900 mt-2"><?= tp_escape($costume['TEN_TP']) ?></h2>
                <?php if (!empty($costume['TEN_LOAI'])): ?>
                  <p class="mt-2 text-base text-slate-600">Thuộc nhóm <?= tp_escape($costume['TEN_LOAI']) ?> tại chi nhánh <?= tp_escape($costume['TEN_CN']) ?>.</p>
                <?php else: ?>
                  <p class="mt-2 text-base text-slate-600">Sẵn có tại chi nhánh <?= tp_escape($costume['TEN_CN']) ?>.</p>
                <?php endif; ?>
              </header>

              <div class="info-grid">
                <div class="space-y-2">
                  <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-[0.3em]">Đặc điểm</h3>
                  <ul class="space-y-1 text-sm text-slate-700">
                    <?php if (!empty($costume['SIZE'])): ?>
                      <li>Size: <span class="font-medium text-slate-900"><?= tp_escape($costume['SIZE']) ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($costume['MAU'])): ?>
                      <li>Màu sắc: <span class="font-medium text-slate-900"><?= tp_escape($costume['MAU']) ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($costume['NGAY_GIAT_CUOI']) && $costume['NGAY_GIAT_CUOI'] !== '0000-00-00'): ?>
                      <li>Giặt gần nhất: <span class="font-medium text-slate-900"><?= tp_escape($costume['NGAY_GIAT_CUOI']) ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($costume['GHI_CHU'])): ?>
                      <li>Ghi chú: <span class="font-medium text-slate-900"><?= tp_escape($costume['GHI_CHU']) ?></span></li>
                    <?php endif; ?>
                  </ul>
                </div>

                <div class="space-y-2">
                  <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-[0.3em]">Giá & ưu đãi</h3>
                  <p class="text-3xl font-semibold text-indigo-600">
                    <?= number_format($priceValue, 0, ',', '.') ?>
                    <span class="text-base font-medium text-slate-500">₫/ngày</span>
                  </p>
                  <?php if (!empty($costume['HIEU_LUC_TU'])): ?>
                    <p class="text-xs text-slate-500">Áp dụng từ <?= tp_escape($costume['HIEU_LUC_TU']) ?></p>
                  <?php endif; ?>
                  <p class="text-sm text-slate-600">Giảm 15% phí giặt hấp khi thuê từ 3 ngày trở lên.</p>
                </div>

                <div class="space-y-2">
                  <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-[0.3em]">Thông tin chi nhánh</h3>
                  <p class="font-medium text-slate-900"><?= tp_escape($costume['TEN_CN']) ?></p>
                  <?php if (!empty($costume['DIA_CHI_CN'])): ?>
                    <p class="text-sm text-slate-600">Địa chỉ: <?= tp_escape($costume['DIA_CHI_CN']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($costume['SDT_CN'])): ?>
                    <p class="text-sm text-slate-600">Liên hệ: <?= tp_escape($costume['SDT_CN']) ?></p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="mt-8 flex flex-wrap gap-3">
                <span class="detail-chip">Bộ sưu tập mới</span>
                <span class="detail-chip" style="background:rgba(79,70,229,0.12); color:#4338ca;">Kiểm tra & vệ sinh định kỳ</span>
                <span class="detail-chip" style="background:rgba(14,165,233,0.12); color:#0ea5e9;">Hỗ trợ phối phụ kiện</span>
              </div>
            </div>
          </div>

          <aside class="glass-panel rounded-3xl p-8 bg-white/85 text-slate-800 space-y-6">
            <?php if (!empty($_SESSION['message']) && !empty($_SESSION['message_type'])): ?>
              <div class="rounded-2xl px-4 py-3 text-sm font-medium <?= $_SESSION['message_type'] === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                <?= tp_escape($_SESSION['message']) ?>
              </div>
              <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div>
              <h3 class="text-lg font-semibold text-slate-900">Đặt thuê nhanh</h3>
              <p class="text-sm text-slate-600">Chọn ngày và hoàn tất đặt thuê trực tuyến. Hệ thống sẽ giữ trang phục cho bạn ngay khi xác nhận.</p>
            </div>

            <div class="rounded-2xl border border-slate-200 p-5 space-y-3 bg-white/90">
              <p class="text-sm text-slate-500 uppercase tracking-[0.3em]">Ước tính chi phí</p>
              <p class="text-3xl font-semibold text-indigo-600" id="estimateText">Chọn ngày để xem chi phí</p>
              <p class="text-xs text-slate-500">Áp dụng cho <?= $qtyQuery ?> bộ. Bạn có thể điều chỉnh lại trong bước đặt thuê.</p>
              <button
                type="button"
                class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                onclick="window.location.href='trangphuc_datthue.php?<?= tp_escape($prefillQuery) ?>'"
              >
                Bắt đầu đặt thuê
              </button>
            </div>

            <div class="space-y-2">
              <h4 class="text-sm font-semibold text-slate-500 uppercase tracking-[0.3em]">Quy trình</h4>
              <ol class="space-y-2 text-sm text-slate-600 list-decimal list-inside">
                <li>Chọn ngày nhận và trả trang phục.</li>
                <li>Nhập thông tin liên hệ và ghi chú về buổi chụp.</li>
                <li>Xác nhận đặt thuê và chờ duyệt trong vòng 2 giờ làm việc.</li>
              </ol>
            </div>

            <div class="space-y-2 text-sm text-slate-600">
              <h4 class="text-sm font-semibold text-slate-500 uppercase tracking-[0.3em]">Lưu ý vệ sinh & bảo quản</h4>
              <p>Trang phục được vệ sinh công nghiệp ngay sau mỗi lượt thuê. Vui lòng tránh sử dụng hóa chất tẩy và hoàn trả đúng hạn để được miễn phí phát sinh.</p>
            </div>
          </aside>
        </section>

        <?php if ($recommend): ?>
          <section class="mt-12 glass-panel rounded-3xl p-8 bg-white/85 text-slate-800">
            <header class="mb-6">
              <h2 class="text-2xl font-semibold text-slate-900">Trang phục tương tự</h2>
              <p class="text-sm text-slate-600">Những lựa chọn khác cùng phong cách để bạn cân nhắc.</p>
            </header>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
              <?php foreach ($recommend as $item): ?>
                <article class="rounded-3xl border border-slate-200/60 bg-white/90 overflow-hidden shadow-sm">
                  <img src="<?= tp_escape($item['HINH_ANH'] ?: '../../../public/images/bg01.png') ?>" alt="<?= tp_escape($item['TEN_TP']) ?>" class="w-full aspect-[3/4] object-cover">
                  <div class="p-5 space-y-2">
                    <h3 class="text-lg font-semibold text-slate-900 leading-tight"><?= tp_escape($item['TEN_TP']) ?></h3>
                    <p class="text-sm text-slate-600">Chi nhánh <?= tp_escape($item['TEN_CN']) ?></p>
                    <p class="text-base font-semibold text-indigo-600">
                      <?= number_format((int)($item['DON_GIA'] ?? 0), 0, ',', '.') ?> <span class="text-xs text-slate-500">₫/ngày</span>
                    </p>
                    <a href="trangphuc_chitiet.php?id=<?= (int)$item['ID_TP'] ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:text-indigo-600">Xem chi tiết</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($priceValue > 0): ?>
  <script>
    (function(){
      const estimateText = document.getElementById('estimateText');
      const price = <?= (int)$priceValue ?>;
      const qty   = <?= (int)$qtyQuery ?>;
      const from  = <?= json_encode($fromQuery) ?>;
      const to    = <?= json_encode($toQuery) ?>;

      function calcDays(start, end) {
        const a = new Date(start);
        const b = new Date(end);
        if (isNaN(a) || isNaN(b)) return 0;
        const diff = b.getTime() - a.getTime();
        return Math.max(0, Math.ceil(diff / 86400000));
      }

      const days = calcDays(from, to);
      if (estimateText) {
        if (days <= 0) {
          estimateText.textContent = 'Chọn ngày để xem chi phí';
        } else {
          const estimated = price * days * Math.max(1, qty);
          estimateText.textContent = new Intl.NumberFormat('vi-VN').format(estimated) + ' ₫';
        }
      }

      const thumbs = document.querySelectorAll('[data-thumb]');
      const mainImg = document.getElementById('tp-main-img');
      thumbs.forEach(btn => {
        btn.addEventListener('click', () => {
          thumbs.forEach(other => {
            const img = other.querySelector('img');
            if (img) img.classList.remove('thumb-active');
          });
          const img = btn.querySelector('img');
          if (img) img.classList.add('thumb-active');
          if (mainImg) {
            mainImg.src = btn.dataset.thumb;
            mainImg.alt = btn.dataset.alt || mainImg.alt;
          }
        });
      });
    })();
  </script>
  <?php endif; ?>
</body>
</html>