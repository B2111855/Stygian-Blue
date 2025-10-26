<?php
include '../../../database/config.php'; // $conn = new mysqli(...)

// -------- Helpers --------
function img_url($path) {
  if (!$path) return '/public/images/placeholder.jpg';
  if (preg_match('~^https?://~i', $path)) return $path;            // URL tuyệt đối giữ nguyên
  if (strpos($path, 'public/images/') === 0) return '/'.$path;     // đã có prefix public/images
  return '/public/images/dichvu/'.$path;                           // mặc định thư mục dịch vụ
}

// -------- Query: dịch vụ lẻ --------
$sqlServices = "
    SELECT
        dv.ID_DV,
        dv.TEN_DV,
        dv.MOTA_DV,
        dv.IMAGE,
        dv.THOI_GIAN,

        -- giá mới nhất (có thể NULL)
        dg.DON_GIA AS GIA_MOI_NHAT,

        -- thống kê đánh giá
        ROUND(AVG(ph.XEP_HANG_DV), 1)      AS avg_rating,
        COUNT(ph.ID_TK)                    AS rating_count

    FROM dich_vu dv

    -- join lấy đơn giá mới nhất cho từng dịch vụ
    LEFT JOIN (
        SELECT d1.ID_DV, d1.DON_GIA
        FROM don_gia_dich_vu d1
        INNER JOIN (
            SELECT ID_DV, MAX(NGAY_GIO) AS max_time
            FROM don_gia_dich_vu
            GROUP BY ID_DV
        ) d2
        ON d1.ID_DV = d2.ID_DV
        AND d1.NGAY_GIO = d2.max_time
    ) AS dg
    ON dv.ID_DV = dg.ID_DV

    -- join feedback để tính rating
    LEFT JOIN phan_hoi_cua_khach_hang ph
    ON dv.ID_DV = ph.ID_DV

    GROUP BY
        dv.ID_DV,
        dv.TEN_DV,
        dv.MOTA_DV,
        dv.IMAGE,
        dv.THOI_GIAN,
        dg.DON_GIA

    ORDER BY dv.TEN_DV ASC
";

$services = $conn->query($sqlServices);



// -------- Query: gói đang bán & trong hiệu lực (nếu có) --------
$sqlCombos = "
  SELECT g.*
  FROM v_goi_dich_vu_tong_tien g
  WHERE g.TRANG_THAI = 'ban'
    AND (g.HIEU_LUC_TU IS NULL OR g.HIEU_LUC_TU <= NOW())
    AND (g.HIEU_LUC_DEN IS NULL OR g.HIEU_LUC_DEN >= NOW())
  ORDER BY g.TEN_GOI ASC
";
$combos = $conn->query($sqlCombos);

// map chi tiết từng gói (để tính % tiết kiệm & hiển thị nhanh)
$comboDetails = [];
if ($combos && $combos->num_rows > 0) {
  $ids = [];
  $combos->data_seek(0);
  while ($row = $combos->fetch_assoc()) $ids[] = (int)$row['ID_GOI'];
  if ($ids) {
    $idList = implode(',', array_map('intval', $ids));
    $sqlComboDetail = "
      SELECT ct.ID_GOI, ct.ID_DV, ct.SO_LUONG, ct.DON_GIA_AP_DUNG,
             dv.TEN_DV, dv.IMAGE, dv.THOI_GIAN,
             (SELECT d.DON_GIA FROM don_gia_dich_vu d
               WHERE d.ID_DV = ct.ID_DV ORDER BY d.NGAY_GIO DESC LIMIT 1) AS GIA_MOI_NHAT
      FROM goi_dich_vu_chi_tiet ct
      JOIN dich_vu dv ON dv.ID_DV = ct.ID_DV
      WHERE ct.ID_GOI IN ($idList)
      ORDER BY ct.ID_GOI, COALESCE(ct.THU_TU,1), dv.TEN_DV
    ";
    $rs = $conn->query($sqlComboDetail);
    while ($item = $rs->fetch_assoc()) {
      $gid = (int)$item['ID_GOI'];
      if (!isset($comboDetails[$gid])) $comboDetails[$gid] = [];
      $comboDetails[$gid][] = $item;
    }
  }
  // reset pointer để render
  $combos->data_seek(0);
}
?>
<!-- services-combos.php (v4) — Bright Theme, SCOPED, A11Y -->
<section data-sb-services role="region" aria-label="Dịch vụ & Gói ưu đãi"
  class="relative overflow-hidden rounded-3xl m-4 mt-6 min-h-[70vh] flex items-start justify-center text-left
         bg-[linear-gradient(135deg,#e6f6ff,#ffffff,#ffe9f6)] text-slate-900">

  <!-- Pastel bokeh -->
  <div aria-hidden="true" class="pointer-events-none absolute -top-24 -left-24 w-[44rem] h-[44rem] rounded-full blur-3xl opacity-30 bg-cyan-200 mix-blend-screen"></div>
  <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -right-24 w-[36rem] h-[36rem] rounded-full blur-3xl opacity-25 bg-fuchsia-200 mix-blend-screen"></div>

  <div class="relative z-10 w-full max-w-7xl mx-auto p-6">
    <h1 class="text-3xl md:text-4xl font-extrabold text-center mb-6 text-transparent bg-clip-text bg-gradient-to-r from-sky-600 to-fuchsia-600">
      Dịch vụ & Gói ưu đãi
    </h1>

    <!-- Tabs + Tools -->
    <div class="flex flex-col md:flex-row md:items-center gap-3 mb-4" role="tablist" aria-label="Chuyển tab dịch vụ/combos">
      <div class="flex items-center gap-2">
        <button role="tab" id="tabbtn-services" aria-controls="tab-services" aria-selected="true"
                data-tab="tab-services"
                class="sb-tab px-4 py-2 rounded-xl bg-white text-slate-900 font-medium border border-slate-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-400">
          Dịch vụ lẻ
        </button>
        <button role="tab" id="tabbtn-combos" aria-controls="tab-combos" aria-selected="false"
                data-tab="tab-combos"
                class="sb-tab px-4 py-2 rounded-xl bg-white/70 text-slate-700 hover:bg-white border border-slate-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-400">
          Combo / Gói
        </button>
      </div>

      <div class="md:ml-auto flex items-center gap-2">
        <label class="relative">
          <input id="sb-search" type="search" placeholder="Tìm theo tên…"
                 class="peer w-56 md:w-72 px-3 py-2 rounded-xl bg-white/90 text-slate-800 placeholder-slate-400 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-cyan-400"
                 aria-label="Tìm kiếm theo tên">
          <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 peer-focus:text-cyan-500"></i>
        </label>
        <button id="sb-search-clear" class="px-3 py-2 rounded-xl bg-white/70 text-slate-700 border border-slate-200 hover:bg-white hidden" title="Xóa tìm kiếm">
          <i class="fas fa-times"></i>
        </button>
        <select id="sb-sort" aria-label="Sắp xếp"
                class="px-3 py-2 rounded-xl bg-white/90 text-slate-800 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-cyan-400">
          <option value="">Sắp xếp</option>
          <option value="priceAsc">Giá thấp → cao</option>
          <option value="priceDesc">Giá cao → thấp</option>
          <option value="ratingDesc">Đánh giá cao</option>
          <option value="durationAsc">Thời lượng ngắn</option>
        </select>
      </div>
    </div>

    <!-- Tab: Dịch vụ lẻ -->
   <div id="tab-services" role="tabpanel" aria-labelledby="tabbtn-services"
     class="sb-pane grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-4">

  <?php if ($services && $services->num_rows):
    $services->data_seek(0);
    while ($s = $services->fetch_assoc()):
      $img          = img_url($s['IMAGE']);
      $giaRaw       = is_null($s['GIA_MOI_NHAT']) ? 0 : (int)$s['GIA_MOI_NHAT'];
      $rating       = $s['avg_rating'] ? (float)$s['avg_rating'] : 0;
      $ratingCount  = (int)$s['rating_count'];
      $duration     = (int)$s['THOI_GIAN'];
      $serviceId    = (int)$s['ID_DV'];
      $isEquipment  = ($serviceId === 3); // Thuê trang thiết bị
      $detailUrl    = '/StygianBlue/app/Pages/views/chitiet.php?type=service&id='.$serviceId;

      // Link đặt lịch (form liên hệ), truyền sẵn dịch vụ
      $bookingUrl   = 'http://localhost:8080/StygianBlue/app/Pages/views/lienhe.php?dv='.$serviceId;

      // Link xem danh sách & giá thiết bị
      $equipListUrl = '/StygianBlue/app/Pages/views/thietbi.php';
  ?>
  <article class="sb-card group rounded-2xl overflow-hidden bg-white/85 backdrop-blur-xl border border-slate-200 hover:shadow-xl transition focus-within:ring-2 focus-within:ring-cyan-400"
           tabindex="0"
           data-name="<?= htmlspecialchars($s['TEN_DV']) ?>"
           data-price="<?= $giaRaw ?>"
           data-rating="<?= $rating ?>"
           data-duration="<?= $duration ?>">

    <!-- Ảnh: clickable -->
    <a class="block relative aspect-[16/9] focus:outline-none focus:ring-2 focus:ring-cyan-400"
       href="<?= htmlspecialchars($isEquipment ? $equipListUrl : $detailUrl) ?>"
       aria-label="<?= $isEquipment 
          ? 'Xem danh sách & giá thiết bị cho thuê' 
          : 'Xem chi tiết dịch vụ: '.htmlspecialchars($s['TEN_DV']) ?>">
      
      <img loading="lazy"
           src="<?= htmlspecialchars($img) ?>"
           alt="<?= htmlspecialchars($s['TEN_DV']) ?>"
           class="w-full h-full object-cover">

      <div class="absolute top-2 left-2 flex items-center gap-2">
        <span class="chip chip-time">~ <?= $duration ?> phút</span>
        <?php if ($rating >= 4.5): ?>
          <span class="chip chip-hot"><i class="fas fa-fire-alt"></i> Hot</span>
        <?php endif; ?>
        <?php if ($isEquipment): ?>
          <span class="chip chip-hot bg-indigo-600/90 text-white border-indigo-700/50">
            Thiết bị
          </span>
        <?php endif; ?>
      </div>

      <div class="absolute inset-0 bg-gradient-to-t from-white/70 to-transparent"></div>
    </a>

    <div class="p-4">
      <!-- Tên dịch vụ -->
      <h3 class="text-lg font-semibold text-slate-900 line-clamp-2">
        <a href="<?= htmlspecialchars($isEquipment ? $equipListUrl : $detailUrl) ?>" class="hover:underline">
          <?= htmlspecialchars($s['TEN_DV']) ?>
        </a>
      </h3>

      <!-- Rating -->
      <div class="flex items-center gap-2 mt-1 text-amber-500">
        <span class="sb-stars"
              aria-label="Đánh giá"
              data-value="<?= $rating ?>"
              title="<?= $rating ? $rating : '–' ?>/5"></span>
        <span class="text-sm text-slate-600">
          (<?= $rating ? $rating : '–' ?>/5 · <?= $ratingCount ?>)
        </span>
      </div>

      <!-- Giá -->
      <div class="mt-2 font-bold text-xl text-transparent bg-clip-text bg-gradient-to-r from-cyan-600 to-fuchsia-600">
        <?php if ($isEquipment): ?>
          <!-- DỊCH VỤ: THUÊ TRANG THIẾT BỊ -->
          Giá thuê: tùy theo thiết bị
        <?php else: ?>
          <?= $giaRaw > 0 
                ? number_format($giaRaw, 0, ',', '.') . ' đ'
                : 'Liên hệ' ?>
        <?php endif; ?>
      </div>

      <!-- Mô tả -->
      <p class="mt-2 text-sm text-slate-600 line-clamp-3"
         title="<?= htmlspecialchars($s['MOTA_DV']) ?>">
        <?= htmlspecialchars($s['MOTA_DV']) ?>
        <?php if ($isEquipment): ?>
          <span class="block text-[11px] text-slate-500 mt-1">
            Máy ảnh, ống kính, đèn flash, chân máy, gimbal... Nhận tại chi nhánh.
          </span>
        <?php endif; ?>
      </p>

      <!-- CTA -->
      <div class="mt-4 flex gap-2">
        <?php if ($isEquipment): ?>
          <!-- THUÊ THIẾT BỊ -->
          <a href="<?= htmlspecialchars($equipListUrl) ?>"
             class="btn-soft flex-1 text-center whitespace-nowrap">
            <i class="fas fa-list-ul"></i> Danh sách & giá
          </a>

          <a href="<?= htmlspecialchars($bookingUrl) ?>"
             class="btn-primary flex-1 text-center whitespace-nowrap">
            <i class="fas fa-calendar-check"></i> Thuê ngay
          </a>
        <?php else: ?>
          <!-- DỊCH VỤ THƯỜNG -->
          <a href="<?= htmlspecialchars($bookingUrl) ?>"
             class="btn-primary flex-1 text-center whitespace-nowrap">
            <i class="fas fa-calendar-check"></i> Đặt lịch
          </a>

          <a href="<?= htmlspecialchars($detailUrl) ?>"
             class="btn-soft flex-1 text-center whitespace-nowrap">
            <i class="fas fa-images"></i> Xem mẫu
          </a>
        <?php endif; ?>
      </div>
    </div>
  </article>
  <?php endwhile; else: ?>
    <p class="text-slate-600">Không có dịch vụ nào.</p>
  <?php endif; ?>
</div>

    <!-- Tab: Combo / Gói -->
    <div id="tab-combos" role="tabpanel" aria-labelledby="tabbtn-combos"
         class="sb-pane hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-4">
      <?php if ($combos && $combos->num_rows):
        $combos->data_seek(0);
        while ($g = $combos->fetch_assoc()):
          $gid  = (int)$g['ID_GOI'];
          $img  = img_url($g['HINH_ANH']);
          $tong = (int)$g['TONG_GIA_GOI'];
          $tienLe = 0;
          if (isset($comboDetails[$gid])) {
            foreach ($comboDetails[$gid] as $it) {
              $giaItem = !is_null($it['DON_GIA_AP_DUNG']) ? (int)$it['DON_GIA_AP_DUNG'] : (int)$it['GIA_MOI_NHAT'];
              $tienLe += $giaItem * max(1, (int)$it['SO_LUONG']);
            }
          }
          $savePercent = ($tienLe>0 && $tong>0) ? round( (1 - ($tong/$tienLe))*100 ) : 0;
          $comboUrl  = '/StygianBlue/app/Pages/views/chitiet.php?type=combo&id='.$gid;
      ?>
      <article class="sb-card group rounded-2xl overflow-hidden bg-white/85 backdrop-blur-xl border border-slate-200 hover:shadow-xl transition focus-within:ring-2 focus-within:ring-cyan-400"
               tabindex="0"
               data-name="<?= htmlspecialchars($g['TEN_GOI']) ?>"
               data-price="<?= $tong ?>"
               data-rating="0"
               data-duration="0">
        <a class="block relative aspect-[16/9] focus:outline-none focus:ring-2 focus:ring-cyan-400"
           href="<?= htmlspecialchars($comboUrl) ?>" aria-label="Xem chi tiết gói: <?= htmlspecialchars($g['TEN_GOI']) ?>">
          <img loading="lazy" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($g['TEN_GOI']) ?>" class="w-full h-full object-cover">
          <div class="absolute top-2 left-2 flex items-center gap-2">
            <?php if ($savePercent>0): ?><span class="chip chip-save">Tiết kiệm <?= $savePercent ?>%</span><?php endif; ?>
          </div>
          <div class="absolute top-2 right-2 text-xs px-2.5 py-1.5 rounded-full bg-emerald-100/90 text-emerald-800 border border-emerald-200 shadow-sm">
            <?= htmlspecialchars($g['TRANG_THAI']) ?>
          </div>
          <div class="absolute inset-0 bg-gradient-to-t from-white/70 to-transparent"></div>
        </a>

        <div class="p-4">
          <h3 class="text-lg font-semibold text-slate-900 line-clamp-2">
            <a href="<?= htmlspecialchars($comboUrl) ?>" class="hover:underline">
              <?= htmlspecialchars($g['TEN_GOI']) ?>
            </a>
          </h3>
          <div class="mt-1 text-sm text-slate-600">
            <?php if ($g['HIEU_LUC_TU'] || $g['HIEU_LUC_DEN']): ?>
              Hiệu lực: <?= $g['HIEU_LUC_TU'] ? date('d/m/Y', strtotime($g['HIEU_LUC_TU'])) : '–' ?> → <?= $g['HIEU_LUC_DEN'] ? date('d/m/Y', strtotime($g['HIEU_LUC_DEN'])) : '–' ?>
            <?php else: ?>
              Hiệu lực: đang bán
            <?php endif; ?>
          </div>
          <div class="mt-2 font-bold text-xl text-transparent bg-clip-text bg-gradient-to-r from-cyan-600 to-fuchsia-600">
            <?= number_format($tong,0,',','.') ?> đ
          </div>
          <p class="mt-2 text-sm text-slate-600 line-clamp-3"><?= htmlspecialchars($g['MO_TA']) ?></p>
          <div class="mt-4 flex gap-2">
            <button class="btn-primary"><i class="fas fa-check-circle"></i> Chọn gói</button>
            <a class="btn-soft" href="<?= htmlspecialchars($comboUrl) ?>"><i class="fas fa-list"></i> Xem chi tiết</a>
          </div>
        </div>
      </article>
      <?php endwhile; else: ?>
        <p class="text-slate-600">Chưa có gói/combos khả dụng.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<style>
  /* ===== SCOPED to [data-sb-services] ===== */
  @media (prefers-reduced-motion: reduce){
    [data-sb-services] *{ transition:none !important; animation:none !important; }
  }
  [data-sb-services]{ overflow-x:hidden; }

  /* Buttons & chips */
  [data-sb-services] .btn-primary{
    display:inline-flex;align-items:center;gap:.5rem;padding:.6rem .9rem;border-radius:9999px;font-weight:700;
    color:#fff;background:linear-gradient(90deg,#06b6d4,#a78bfa); border:1px solid transparent;
    box-shadow:0 8px 20px rgba(6,182,212,.25); transition:transform .15s ease,filter .15s ease;
  }
  [data-sb-services] .btn-primary:hover{ transform:translateY(-1px); filter:brightness(1.05); }
  [data-sb-services] .btn-soft{
    display:inline-flex;align-items:center;gap:.5rem;padding:.6rem .9rem;border-radius:9999px;font-weight:600;
    background:linear-gradient(180deg,#fff,#f8fafc); color:#0f172a;border:1px solid #e2e8f0; box-shadow:0 1px 0 rgba(2,6,23,.04);
  }

  [data-sb-services] .chip{
    display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .6rem;border-radius:9999px;font-size:.75rem;font-weight:600;
    background:linear-gradient(180deg,#fff,#f8fafc); color:#0f172a;border:1px solid #e2e8f0; box-shadow:0 1px 0 rgba(2,6,23,.04);
  }
  [data-sb-services] .chip-time{ background:linear-gradient(180deg,#ecfeff,#f0f9ff); border-color:#bae6fd; color:#075985; }
  [data-sb-services] .chip-hot{ background:linear-gradient(180deg,#fff7ed,#ffedd5); border-color:#fed7aa; color:#9a3412; }
  [data-sb-services] .chip-save{ background:linear-gradient(180deg,#f0fdf4,#dcfce7); border-color:#bbf7d0; color:#065f46; }

  /* Stars (CSS-only with mask width) */
  [data-sb-services] .sb-stars{
    --value:0; /* 0..5 */ --pct: calc(var(--value) / 5 * 100%);
    position:relative;display:inline-block;font-size:0;line-height:1;
  }
  [data-sb-services] .sb-stars::before{content:"★★★★★";letter-spacing:2px;color:#fbbf24;font-size:14px}
  [data-sb-services] .sb-stars::after{content:"★★★★★";letter-spacing:2px;color:#d1d5db;font-size:14px;position:absolute;left:0;top:0;width:calc(100% - var(--pct));overflow:hidden}

  /* Cards */
  [data-sb-services] .sb-card{ transform:translateZ(0); }
  [data-sb-services] .sb-card:hover img{ transform:scale(1.03); transition:transform .35s ease; }
  [data-sb-services] .sb-card img{ transition:transform .35s ease; }

  /* Clamp fallback */
  .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .line-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
</style>

<script>
(() => {
  const root = document.querySelector('[data-sb-services]');
  if (!root) return;

  const tabBtns = Array.from(root.querySelectorAll('.sb-tab'));
  const panes   = Array.from(root.querySelectorAll('.sb-pane'));
  const search  = root.querySelector('#sb-search');
  const clearBtn= root.querySelector('#sb-search-clear');
  const sortSel = root.querySelector('#sb-sort');

  function setTab(btn){
    tabBtns.forEach(b=>{b.setAttribute('aria-selected','false'); b.classList.remove('bg-white'); b.classList.add('bg-white/70','text-slate-700');});
    panes.forEach(p=>p.classList.add('hidden'));
    btn.setAttribute('aria-selected','true');
    root.querySelector('#'+btn.dataset.tab)?.classList.remove('hidden');
    btn.classList.remove('bg-white/70','text-slate-700');
    btn.classList.add('bg-white','text-slate-900');
    // Re-apply search & sort cho pane hiện tại
    applySearch(); applySort();
  }
  tabBtns.forEach(btn => btn.addEventListener('click', () => setTab(btn)));
  // Keyboard nav for tabs
  root.addEventListener('keydown', (e)=>{
    if (e.target.matches('.sb-tab')){
      const i = tabBtns.indexOf(e.target);
      if (e.key === 'ArrowRight') tabBtns[(i+1)%tabBtns.length].focus();
      if (e.key === 'ArrowLeft')  tabBtns[(i-1+tabBtns.length)%tabBtns.length].focus();
      if (e.key === 'Enter' || e.key === ' ') setTab(e.target);
    }
  });
  // Default tab
  setTab(tabBtns[0]);

  function activeCards(){
    const active = panes.find(p=>!p.classList.contains('hidden')) || panes[0];
    return Array.from(active.querySelectorAll('.sb-card'));
  }

  function renderStars(){
    root.querySelectorAll('.sb-stars').forEach(el=>{
      const v = parseFloat(el.dataset.value || '0');
      el.style.setProperty('--value', isNaN(v)?0:Math.max(0, Math.min(5, v)));
    });
  }
  renderStars();

  function applySearch(){
    const q = (search?.value || '').toLowerCase().trim();
    clearBtn.classList.toggle('hidden', !q.length);
    activeCards().forEach(c => {
      const name = (c.dataset.name || '').toLowerCase();
      c.classList.toggle('hidden', q && !name.includes(q));
    });
  }
  function applySort(){
    const val = sortSel.value;
    const cards = activeCards();
    const grid  = cards[0]?.parentElement; if (!grid) return;
    const get = (el,key) => parseFloat(el.dataset[key]||'0') || 0;
    const cmp = {
      priceAsc:  (a,b)=> get(a,'price') - get(b,'price'),
      priceDesc: (a,b)=> get(b,'price') - get(a,'price'),
      ratingDesc:(a,b)=> get(b,'rating') - get(a,'rating'),
      durationAsc:(a,b)=> get(a,'duration') - get(b,'duration')
    }[val];
    if (!cmp) return;
    cards.sort(cmp).forEach(x=>grid.appendChild(x));
  }

  search?.addEventListener('input', ()=>{ applySearch(); });
  clearBtn?.addEventListener('click', ()=>{ search.value=''; applySearch(); });
  sortSel?.addEventListener('change', ()=>{ applySort(); });
})();
</script>
