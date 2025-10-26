<?php
// chitiet.php — Trang chi tiết Dịch vụ/Combo (v2.2, Bright, Reviews + Smart Booking)

include '../../../database/config.php'; // $conn = new mysqli(...)

// ---- Helpers ----
function img_url($path)
{
  if (!$path) return '/public/images/placeholder.jpg';
  if (preg_match('~^https?://~i', $path)) return $path;
  if (strpos($path, 'public/images/') === 0) return '/' . $path;
  return '/public/images/dichvu/' . $path;
}
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ---- Read params ----
$type = $_GET['type'] ?? 'service'; // 'service' | 'combo'
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id || !in_array($type, ['service', 'combo'], true)) {
  http_response_code(400);
  echo "Thiếu tham số.";
  exit;
}

// ---- Data buckets ----
$meta = null;
$gallery = [];
$components = []; // combo components
$ratingAvg = null;
$ratingCount = 0;
$reviews = [];     // NEW: feedback list (service only)

// ---- Query theo loại ----
if ($type === 'service') {
  $sql = "
    SELECT s.ID_DV, s.TEN_DV, s.MOTA_DV, s.IMAGE, s.THOI_GIAN, s.GIA_MOI_NHAT
    FROM v_dich_vu_gia_moinhat s
    WHERE s.ID_DV = $id
    LIMIT 1
  ";
  $meta = $conn->query($sql)?->fetch_assoc();

  $sqlR = "
    SELECT ROUND(AVG(NULLIF(XEP_HANG_DV,0)),1) AS avg_rating, COUNT(*) AS rating_count
    FROM phan_hoi_cua_khach_hang
    WHERE ID_DV = $id
  ";
  if ($rs = $conn->query($sqlR)) {
    $r = $rs->fetch_assoc();
    $ratingAvg = $r['avg_rating'];
    $ratingCount = (int)$r['rating_count'];
  }

  $sqlG = "
    SELECT URL, ALT_TEXT, IS_COVER
    FROM dich_vu_hinh_anh
    WHERE ID_DV = $id AND IS_ACTIVE = 1
    ORDER BY IS_COVER DESC, THU_TU ASC, ID_HA ASC
  ";
  if ($rs = $conn->query($sqlG)) while ($row = $rs->fetch_assoc()) $gallery[] = $row;
  if (!$gallery && $meta && $meta['IMAGE']) {
    $gallery[] = ['URL' => $meta['IMAGE'], 'ALT_TEXT' => 'Ảnh bìa', 'IS_COVER' => 1];
  }

  // NEW: Pull feedback for this service (with customer names)
  $sqlFb = "
  SELECT 
    ph.ID_TK,
    ph.ID_DV,
    ph.XEP_HANG_DV,
    ph.NGAY_GUI            AS NGAY_GIO,   -- alias để khớp với phần render
    ph.NOI_DUNG,
    NULL                   AS HINH_ANH,   -- bảng hiện không có cột này
    COALESCE(tk.HO_TEN, 'Khách hàng') AS TEN_KH
  FROM phan_hoi_cua_khach_hang ph
  LEFT JOIN tai_khoan tk ON tk.ID_TK = ph.ID_TK
  WHERE ph.ID_DV = $id
  ORDER BY ph.NGAY_GUI DESC
  LIMIT 12
";
  if ($rs = $conn->query($sqlFb)) {
    while ($row = $rs->fetch_assoc()) $reviews[] = $row;
  }
} else { // combo
  $sql = "SELECT * FROM v_goi_dich_vu_tong_tien WHERE ID_GOI = $id LIMIT 1";
  $meta = $conn->query($sql)?->fetch_assoc();

  $sqlC = "
    SELECT ct.ID_GOI, ct.ID_DV, ct.SO_LUONG, ct.DON_GIA_AP_DUNG,
           dv.TEN_DV, dv.IMAGE, dv.THOI_GIAN,
           (SELECT d.DON_GIA FROM don_gia_dich_vu d
             WHERE d.ID_DV = ct.ID_DV ORDER BY d.NGAY_GIO DESC LIMIT 1) AS GIA_MOI_NHAT
    FROM goi_dich_vu_chi_tiet ct
    JOIN dich_vu dv ON dv.ID_DV = ct.ID_DV
    WHERE ct.ID_GOI = $id
    ORDER BY COALESCE(ct.THU_TU,1), dv.TEN_DV
  ";
  if ($rs = $conn->query($sqlC)) while ($row = $rs->fetch_assoc()) $components[] = $row;

  $sqlG = "
    SELECT URL, ALT_TEXT, IS_COVER
    FROM v_gallery_goi_dich_vu
    WHERE ID_GOI = $id
    ORDER BY IS_COVER DESC, THU_TU ASC
    LIMIT 40
  ";
  if ($rs = $conn->query($sqlG)) while ($row = $rs->fetch_assoc()) $gallery[] = $row;

  if (!$gallery && $meta && $meta['HINH_ANH']) {
    $gallery[] = ['URL' => $meta['HINH_ANH'], 'ALT_TEXT' => 'Ảnh bìa gói', 'IS_COVER' => 1];
  }
}
if (!$meta) {
  http_response_code(404);
  echo "Không tìm thấy.";
  exit;
}

// ---- View model ----
$title = ($type === 'service') ? $meta['TEN_DV'] : $meta['TEN_GOI'];
$price = ($type === 'service')
  ? (int)($meta['GIA_MOI_NHAT'] ?? 0)
  : (int)($meta['TONG_GIA_GOI'] ?? 0);
$duration = ($type === 'service') ? (int)($meta['THOI_GIAN'] ?? 0) : 0;

$VIEW_BASE = '/StygianBlue/app/Pages/views/';
$BACK_URL  = ($type === 'service') ? $VIEW_BASE . 'dichvu.php' : $VIEW_BASE . 'goi.php';
$CONTACT_URL = $VIEW_BASE . 'lienhe.php'; // nơi đặt lịch phức tạp của bạn
$IS_LOGGED_IN = !empty($_SESSION['ID_TK']);
?>
<!doctype html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title><?= h($title) ?> · Stygian Blue</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    /* ===== SCOPED to [data-sb-detail] ===== */
    @media (prefers-reduced-motion: reduce) {
      [data-sb-detail] * {
        transition: none !important;
        animation: none !important
      }
    }

    [data-sb-detail] .thumb {
      opacity: .65;
      filter: saturate(.9);
      transition: transform .2s ease, opacity .2s ease, filter .2s ease
    }

    [data-sb-detail] .thumb[aria-current="true"] {
      opacity: 1;
      filter: saturate(1);
      box-shadow: 0 0 0 2px #22d3ee inset
    }

    [data-sb-detail] .thumb:hover {
      transform: translateY(-2px);
      opacity: .9
    }

    [data-sb-detail] .btn-pri {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .7rem 1rem;
      border-radius: 9999px;
      font-weight: 700;
      color: #fff;
      background: linear-gradient(90deg, #06b6d4, #a78bfa);
      border: 1px solid transparent;
      box-shadow: 0 10px 24px rgba(6, 182, 212, .25)
    }

    [data-sb-detail] .btn-soft {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .7rem 1rem;
      border-radius: 9999px;
      font-weight: 600;
      background: linear-gradient(180deg, #fff, #f8fafc);
      color: #0f172a;
      border: 1px solid #e2e8f0;
      box-shadow: 0 1px 0 rgba(2, 6, 23, .04)
    }

    [data-sb-detail] .chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .35rem .6rem;
      border-radius: 9999px;
      font-size: .75rem;
      font-weight: 600;
      background: linear-gradient(180deg, #ecfeff, #f0f9ff);
      color: #075985;
      border: 1px solid #bae6fd
    }

    [data-sb-detail] .tab-btn[aria-selected="true"] {
      background: linear-gradient(180deg, #fff, #f8fafc);
      color: #0f172a;
      border-color: #e2e8f0;
      box-shadow: 0 1px 0 rgba(2, 6, 23, .04)
    }

    [data-sb-detail] .tab-btn {
      color: #0f172a99
    }

    [data-sb-detail] .kpi {
      background: linear-gradient(180deg, #fff, #f8fafc);
      border: 1px solid #e2e8f0;
      border-radius: 1rem;
      padding: 1rem
    }

    [data-sb-detail] .lb-wrap {
      backdrop-filter: blur(2px)
    }

    [data-sb-detail] .lb-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(15, 23, 42, .55);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, .2);
      width: 42px;
      height: 42px;
      border-radius: 9999px;
      display: flex;
      align-items: center;
      justify-content: center
    }

    [data-sb-detail] .lb-btn:hover {
      background: rgba(15, 23, 42, .7)
    }

    /* Reviews */
    [data-sb-detail] .rv-card {
      background: linear-gradient(180deg, #fff, #f8fafc);
      border: 1px solid #e2e8f0;
      border-radius: 1rem;
      padding: 1rem
    }

    [data-sb-detail] .stars {
      --v: 0;
      --w: calc(var(--v)/5*100%);
      position: relative;
      display: inline-block;
      font-size: 0;
      line-height: 1
    }

    [data-sb-detail] .stars::before {
      content: "★★★★★";
      letter-spacing: 2px;
      color: #fbbf24;
      font-size: 14px
    }

    [data-sb-detail] .stars::after {
      content: "★★★★★";
      letter-spacing: 2px;
      color: #cbd5e1;
      font-size: 14px;
      position: absolute;
      left: 0;
      top: 0;
      width: calc(100% - var(--w));
      overflow: hidden
    }
  </style>
</head>

<body class="bg-[linear-gradient(135deg,#e6f6ff,#ffffff,#ffe9f6)] text-slate-900">
  <main data-sb-detail class="relative max-w-7xl mx-auto px-4 md:px-6 py-6">
    <!-- Pill intro + Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
      <p class="inline-flex items-center gap-2 text-[13px] md:text-sm text-sky-900 bg-sky-100 border border-sky-200 rounded-full px-3 py-1 w-fit">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <?= $type === 'service' ? 'Dịch vụ lẻ · Đặt lịch nhanh · Hậu kỳ chuẩn' : 'Combo / Gói · Tiết kiệm · Chuẩn hóa' ?>
      </p>
      <nav aria-label="Breadcrumb" class="text-sm text-slate-600">
        <a href="<?= $VIEW_BASE ?>home.php" class="hover:underline">Trang chủ</a>
        <span class="mx-1">/</span>
        <a href="<?= $type === 'service' ? $VIEW_BASE . 'dichvu.php' : $VIEW_BASE . 'goi.php' ?>" class="hover:underline">
          <?= $type === 'service' ? 'Dịch vụ' : 'Gói ưu đãi' ?>
        </a>
        <span class="mx-1">/</span>
        <span class="text-slate-800 font-medium"><?= h($title) ?></span>
      </nav>
    </div>

    <!-- Header -->
    <header class="flex flex-col md:flex-row gap-5 md:items-end md:justify-between mb-4">
      <div>
        <h1 class="text-3xl md:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-sky-600 to-fuchsia-600">
          <?= h($title) ?>
        </h1>
        <?php if ($type === 'service'): ?>
          <div class="mt-2 flex flex-wrap items-center gap-3 text-slate-700">
            <?php if ($duration): ?><span class="chip">~ <?= $duration ?> phút</span><?php endif; ?>
            <?php if (!is_null($ratingAvg)): ?>
              <span class="chip">Đánh giá: <strong><?= $ratingAvg ?: '–' ?>/5</strong> (<?= $ratingCount ?>)</span>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="mt-2 flex flex-wrap items-center gap-3 text-slate-700">
            <span class="chip">Trạng thái: <strong><?= h($meta['TRANG_THAI'] ?? '–') ?></strong></span>
            <span class="chip">
              Hiệu lực:
              <?= $meta['HIEU_LUC_TU'] ? date('d/m/Y', strtotime($meta['HIEU_LUC_TU'])) : '–' ?>
              →
              <?= $meta['HIEU_LUC_DEN'] ? date('d/m/Y', strtotime($meta['HIEU_LUC_DEN'])) : '–' ?>
            </span>
          </div>
        <?php endif; ?>
      </div>

      <div class="text-right">
        <div class="text-2xl md:text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-cyan-600 to-fuchsia-600">
          <?= $price > 0 ? number_format($price, 0, ',', '.') . ' đ' : 'Liên hệ' ?>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 justify-end">
          <!-- CHANGED: Đặt lịch ngay (no inline form) -->
          <button class="btn-pri" id="sb-book-now">
            <i class="fas fa-calendar-check"></i> Đặt lịch ngay
          </button>
          <a href="<?= $BACK_URL ?>" class="btn-soft"><i class="fas fa-arrow-left"></i> Quay lại</a>
          <button class="btn-soft" id="sb-share"><i class="fas fa-share-alt"></i> Chia sẻ</button>
        </div>
      </div>
    </header>

    <!-- Main two-column -->
    <section class="grid lg:grid-cols-12 gap-6">
      <!-- LEFT: Gallery -->
      <div class="lg:col-span-8">
        <div class="relative rounded-2xl overflow-hidden bg-white/80 border border-slate-200">
          <img id="sb-main" src="<?= h(img_url($gallery[0]['URL'] ?? '')) ?>" alt="<?= h($gallery[0]['ALT_TEXT'] ?? $title) ?>"
            class="w-full max-h-[64vh] object-contain bg-white">
          <button class="lb-btn left-3" aria-label="Ảnh trước" id="sb-prev"><i class="fas fa-chevron-left"></i></button>
          <button class="lb-btn right-3" aria-label="Ảnh tiếp" id="sb-next"><i class="fas fa-chevron-right"></i></button>
          <button class="absolute bottom-3 right-3 px-3 py-1.5 rounded-full text-xs bg-black/50 text-white border border-white/20"
            id="sb-open">Phóng to</button>
        </div>
        <div class="mt-3 grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2">
          <?php foreach ($gallery as $i => $g): ?>
            <button class="thumb group relative aspect-square rounded-xl overflow-hidden bg-white border border-slate-200"
              data-idx="<?= $i ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>">
              <img src="<?= h(img_url($g['URL'])) ?>" alt="<?= h($g['ALT_TEXT'] ?: $title) ?>" class="w-full h-full object-cover">
              <?php if (!empty($g['IS_COVER'])): ?>
                <span class="absolute top-1 left-1 text-[10px] px-1.5 py-0.5 rounded bg-cyan-100 text-cyan-800 border border-cyan-200">Cover</span>
              <?php endif; ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- RIGHT: Sticky info  -->
      <aside class="lg:col-span-4">
        <div class="lg:sticky lg:top-20 space-y-4">

          <!-- NEW: Mô tả (dời từ tab sang cột phải) -->
          <article class="rounded-2xl bg-white/80 border border-slate-200 p-5 leading-relaxed text-slate-800">
            <h2 class="text-base font-semibold text-slate-900 mb-2">Mô tả</h2>
            <?php if ($type === 'service'): ?>
              <?= nl2br(h($meta['MOTA_DV'] ?? '')) ?>
            <?php else: ?>
              <?= nl2br(h($meta['MO_TA'] ?? '')) ?>
            <?php endif; ?>
          </article>

          <div class="kpi">
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div>
                <div class="text-slate-500">Giá</div>
                <div class="font-semibold"><?= $price ? number_format($price, 0, ',', '.') . ' đ' : 'Liên hệ' ?></div>
              </div>
              <div>
                <div class="text-slate-500">Thời lượng</div>
                <div class="font-semibold"><?= isset($duration) ? (int)$duration . ' phút' : '—' ?></div>
              </div>
              <?php if ($type === 'service'): ?>
                <div>
                  <div class="text-slate-500">Đánh giá</div>
                  <div class="font-semibold"><?= $ratingAvg ?: '–' ?>/5 (<?= $ratingCount ?>)</div>
                </div>
              <?php else: ?>
                <div>
                  <div class="text-slate-500">Trạng thái</div>
                  <div class="font-semibold"><?= h($meta['TRANG_THAI'] ?? '–') ?></div>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <!-- Bạn có thể thêm note, chính sách… tại đây nếu cần -->

        </div>
      </aside>

    </section>

    <!-- Tabs: Mô tả / Thành phần / Đánh giá -->
    <section class="mt-8">
      <div class="flex flex-wrap gap-2">
        <?php if ($type === 'combo'): ?>
          <button class="tab-btn px-4 py-2 rounded-xl border border-slate-200" data-tab="comps" aria-selected="true">Thành phần gói</button>
        <?php endif; ?>
        <button class="tab-btn px-4 py-2 rounded-xl border border-slate-200" data-tab="reviews" aria-selected="<?= $type === 'combo' ? 'false' : 'true' ?>">Đánh giá</button>
      </div>

      <div class="mt-4">
      </div>

      <?php if ($type === 'combo'): ?>
        <div data-panel="comps" class="hidden">
          <div class="rounded-2xl bg-white/80 border border-slate-200 overflow-hidden">
            <?php if ($components): ?>
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                  <tr>
                    <th class="text-left px-4 py-2">Dịch vụ</th>
                    <th class="text-left px-4 py-2">Thời lượng</th>
                    <th class="text-center px-4 py-2">SL</th>
                    <th class="text-right px-4 py-2">Đơn giá</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                  <?php foreach ($components as $it):
                    $giaItem = !is_null($it['DON_GIA_AP_DUNG']) ? (int)$it['DON_GIA_AP_DUNG'] : (int)$it['GIA_MOI_NHAT'];
                  ?>
                    <tr>
                      <td class="px-4 py-2">
                        <div class="flex items-center gap-3">
                          <img src="<?= h(img_url($it['IMAGE'])) ?>" class="w-12 h-12 object-cover rounded-md border border-slate-200" alt="">
                          <div class="font-medium text-slate-800"><?= h($it['TEN_DV']) ?></div>
                        </div>
                      </td>
                      <td class="px-4 py-2 text-slate-700">~ <?= (int)$it['THOI_GIAN'] ?>’</td>
                      <td class="px-4 py-2 text-center"><?= (int)$it['SO_LUONG'] ?></td>
                      <td class="px-4 py-2 text-right font-semibold text-slate-800"><?= number_format($giaItem, 0, ',', '.') ?> đ</td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="p-5 text-slate-600">Gói chưa có chi tiết.</div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- NEW: Reviews panel for this service -->
      <div data-panel="reviews" class="hidden">
        <div class="rounded-2xl p-5 bg-white/60 border border-slate-200">
          <?php if ($type === 'service'): ?>
            <?php if (!empty($reviews)): ?>
              <div class="mb-4 flex items-center justify-between">
                <div class="text-slate-700">
                  <strong><?= count($reviews) ?></strong> phản hồi gần đây cho <span class="font-semibold"><?= h($title) ?></span>
                </div>
                <?php if ($ratingAvg !== null): ?>
                  <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-slate-200">
                    <span class="stars" style="--v:<?= (float)$ratingAvg ?>;"></span>
                    <span class="text-sm text-slate-700"><strong><?= $ratingAvg ?></strong>/5 (<?= $ratingCount ?>)</span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($reviews as $rv):
                  $score = (float)($rv['XEP_HANG_DV'] ?? 0);
                  $name  = trim((string)$rv['TEN_KH']);
                  $when  = !empty($rv['NGAY_GIO']) ? date('d/m/Y H:i', strtotime($rv['NGAY_GIO'])) : '';
                  $text  = $rv['NOI_DUNG'] ?? ''; // tên cột nội dung có thể khác; dùng fallback này
                  $imgRv = !empty($rv['HINH_ANH']) ? img_url($rv['HINH_ANH']) : null;
                ?>
                  <article class="rv-card">
                    <div class="flex items-center justify-between">
                      <div class="font-semibold text-slate-800 truncate"><?= h($name ?: 'Khách hàng') ?></div>
                      <div class="text-xs text-slate-500"><?= h($when) ?></div>
                    </div>
                    <div class="mt-1"><span class="stars" style="--v:<?= $score ?>;"></span></div>
                    <?php if ($text): ?>
                      <p class="mt-2 text-sm text-slate-700 line-clamp-3" title="<?= h($text) ?>"><?= h($text) ?></p>
                    <?php endif; ?>
                    <?php if ($imgRv): ?>
                      <img src="<?= h($imgRv) ?>" alt="Ảnh minh họa phản hồi" class="mt-2 w-full h-32 object-cover rounded-md border border-slate-200">
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-slate-600">Chưa có phản hồi cho dịch vụ này.</div>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-slate-600">Phản hồi áp dụng theo từng dịch vụ thành phần — vui lòng mở chi tiết dịch vụ để xem.</div>
          <?php endif; ?>
        </div>
      </div>
      </div>
    </section>




    <!-- Lightbox -->
    <div id="sb-lb" class="lb-wrap fixed inset-0 hidden items-center justify-center bg-black/70 z-50">
      <button class="absolute top-3 right-3 w-10 h-10 rounded-full bg-white text-slate-800 flex items-center justify-center border border-slate-200" id="lb-close" aria-label="Đóng">✕</button>
      <button class="lb-btn left-4" id="lb-prev" aria-label="Ảnh trước"><i class="fas fa-chevron-left"></i></button>
      <img id="lb-img" alt="" class="max-h-[92vh] max-w-[92vw] rounded-xl shadow-2xl border border-white/10 bg-white">
      <button class="lb-btn right-4" id="lb-next" aria-label="Ảnh tiếp"><i class="fas fa-chevron-right"></i></button>
    </div>

    <!-- NEW: CTA cuối trang -->
    <section class="mt-8">
      <div class="rounded-2xl border border-slate-200 bg-white/80 p-5 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="text-slate-800">
          <div class="font-semibold">Sẵn sàng đặt lịch?</div>
          <div class="text-sm text-slate-600">Chúng mình sẽ tư vấn nhanh và giữ lịch phù hợp cho bạn.</div>
        </div>
        <button class="btn-pri" id="sb-book-now-bottom" type="button">
          <i class="fas fa-calendar-check"></i> Đặt lịch ngay
        </button>
      </div>
    </section>
  </main>

  <script>
    (() => {
      const root = document.querySelector('[data-sb-detail]');
      if (!root) return;

      // ---- Gallery state ----
      const images = <?= json_encode(array_map(fn($g) => img_url($g['URL']), $gallery), JSON_UNESCAPED_SLASHES) ?>;
      const alts = <?= json_encode(array_map(fn($g) => ($g['ALT_TEXT'] ?? $title), $gallery), JSON_UNESCAPED_UNICODE) ?>;
      let idx = 0;

      const main = document.getElementById('sb-main');
      const thumbs = [...root.querySelectorAll('.thumb')];
      const prevBtn = document.getElementById('sb-prev');
      const nextBtn = document.getElementById('sb-next');
      const openLb = document.getElementById('sb-open');

      function setIdx(i) {
        if (!images.length) return;
        idx = (i + images.length) % images.length;
        main.src = images[idx];
        main.alt = alts[idx] || 'Ảnh';
        thumbs.forEach((t, k) => t.setAttribute('aria-current', String(k === idx)));
        const pre = new Image();
        pre.src = images[(idx + 1) % images.length] || '';
      }

      thumbs.forEach(t => {
        t.addEventListener('click', () => setIdx(parseInt(t.dataset.idx, 10) || 0));
        t.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') setIdx(parseInt(t.dataset.idx, 10) || 0);
        });
        t.tabIndex = 0;
      });
      prevBtn?.addEventListener('click', () => setIdx(idx - 1));
      nextBtn?.addEventListener('click', () => setIdx(idx + 1));
      document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') setIdx(idx - 1);
        if (e.key === 'ArrowRight') setIdx(idx + 1);
      });

      // ---- Lightbox ----
      const lb = document.getElementById('sb-lb');
      const lbImg = document.getElementById('lb-img');
      const lbPrev = document.getElementById('lb-prev');
      const lbNext = document.getElementById('lb-next');
      const lbClose = document.getElementById('lb-close');

      function openLightbox() {
        if (!images.length) return;
        lb.classList.remove('hidden');
        lb.classList.add('flex');
        lbImg.src = images[idx];
        lbImg.alt = alts[idx] || 'Ảnh';
      }

      function closeLightbox() {
        lb.classList.add('hidden');
        lb.classList.remove('flex');
      }

      function lbSet(n) {
        setIdx(n);
        if (images.length) {
          lbImg.src = images[idx];
          lbImg.alt = alts[idx] || 'Ảnh';
        }
      }

      openLb?.addEventListener('click', openLightbox);
      lbClose?.addEventListener('click', closeLightbox);
      lbPrev?.addEventListener('click', () => lbSet(idx - 1));
      lbNext?.addEventListener('click', () => lbSet(idx + 1));
      lb.addEventListener('click', (e) => {
        if (e.target === lb) closeLightbox();
      });
      window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLightbox();
        if (!lb.classList.contains('hidden')) {
          if (e.key === 'ArrowLeft') lbSet(idx - 1);
          if (e.key === 'ArrowRight') lbSet(idx + 1);
        }
      });

      setIdx(0);

      // ---- Tabs ----
      const tabBtns = [...root.querySelectorAll('.tab-btn')];
      const panels = {
        desc: root.querySelector('[data-panel="desc"]'),
        comps: root.querySelector('[data-panel="comps"]'),
        reviews: root.querySelector('[data-panel="reviews"]'),
      };

      function showTab(name) {
        Object.entries(panels).forEach(([k, el]) => el && el.classList.toggle('hidden', k !== name));
        tabBtns.forEach(b => b.setAttribute('aria-selected', String(b.dataset.tab === name)));
      }
      tabBtns.forEach(b => b.addEventListener('click', () => showTab(b.dataset.tab)));
      showTab('desc');

      // ---- Share ----
      const shareBtn = document.getElementById('sb-share');
      shareBtn?.addEventListener('click', async () => {
        const data = {
          title: document.title,
          text: 'Xem ' + <?= json_encode($type === 'service' ? 'dịch vụ' : 'gói') ?> + ' tại Stygian Blue',
          url: location.href
        };
        if (navigator.share) {
          try {
            await navigator.share(data);
          } catch (e) {}
        } else {
          try {
            await navigator.clipboard.writeText(location.href);
            shareBtn.textContent = 'Đã sao chép liên kết';
            setTimeout(() => shareBtn.innerHTML = '<i class="fas fa-share-alt"></i> Chia sẻ', 1600);
          } catch (e) {}
        }
      });

      // ---- Booking: smart redirect with login check ----
      const bookBtn = document.getElementById('sb-book-now');
      const isLoggedIn = <?= $IS_LOGGED_IN ? 'true' : 'false' ?>;
      const contactUrl = "<?= $CONTACT_URL ?>";
      const loginUrl = "<?= '/StygianBlue/login.php' ?>";
      const nextUrl = encodeURIComponent(location.href);

      bookBtn?.addEventListener('click', () => {
        if (isLoggedIn) {
          // Đi thẳng tới trang Liên hệ (gợi ý trước loại + id)
          const q = new URLSearchParams({
            type: <?= json_encode($type) ?>,
            id: String(<?= $id ?>)
          });
          location.href = contactUrl + "?" + q.toString();
        } else {
          if (confirm("Bạn cần đăng nhập để tiếp tục đặt lịch. Chuyển đến trang đăng nhập?")) {
            // login + redirect back
            location.href = loginUrl + "?redirect=" + nextUrl;
          }
        }
      });
    })();
  </script>
</body>

</html>