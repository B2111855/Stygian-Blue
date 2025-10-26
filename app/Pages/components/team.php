<?php
// Kết nối: giả định bạn đã có $conn (mysqli) từ include 'database/config.php';
include '../../../database/config.php';

// Map chuyên môn -> role slug (phục vụ filter)
function sb_role_from_specialty(?string $s): string {
  $s = mb_strtolower($s ?? '', 'UTF-8');
  if (preg_match('/(nhi[eê]́p|photographer|chụp|camera|cameraman)/u', $s)) return 'photographer';
  if (preg_match('/(retouch|hậu kỳ|retoucher|skin|dodge|burn|color|grading|composite)/u', $s)) return 'retoucher';
  if (preg_match('/(producer|l[êe]n k[ếe] ho[ạ]ch|đi[ềe]u ph[ốo]i|logistics|scheduling|pre-?production)/u', $s)) return 'producer';
  if (preg_match('/(qu[aả]n l[yý]|manager|gi[aá]m [đd]ốc|cskh|v[ậâ]n h[a]nh|sla|qa|sop|bi)/u', $s)) return 'manager';
  return 'photographer';
}

// Lấy danh sách nhân viên + tên chi nhánh (nếu có)
$sql = "
  SELECT nv.ID_TK,
         COALESCE(nv.HO_TEN, nv.ID_TK)    AS HO_TEN,
         nv.EMAIL,
         nv.SDT,
         nv.CHUYEN_MON,
         nv.ID_CN,
         cn.TEN_CN
  FROM nhan_vien nv
  LEFT JOIN chi_nhanh cn ON cn.ID_CN = nv.ID_CN
  ORDER BY COALESCE(nv.HO_TEN, nv.ID_TK)
";
$stmt = $conn->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();

// Chuẩn hoá dữ liệu cho component
$SB_MEMBERS = [];
while ($r = $res->fetch_assoc()) {
  $role = sb_role_from_specialty($r['CHUYEN_MON'] ?? '');
  // Parse tags từ CHUYEN_MON (phân tách theo dấu phẩy/chấm giữa/ngắt dòng)
  $raw = $r['CHUYEN_MON'] ?? '';
  $parts = preg_split('/[\\n\\r,;·]+/u', $raw);
  $tags = array_values(array_filter(array_map(fn($x)=>trim($x), $parts)));

  // Ảnh: nếu có file upload theo ID_TK thì dùng, không thì placeholder
  $avatarCandidate = "/public/images/avatars/{$r['ID_TK']}.jpg";
  $img = (isset($_SERVER['DOCUMENT_ROOT']) && is_readable($_SERVER['DOCUMENT_ROOT'].$avatarCandidate))
    ? $avatarCandidate
    : '';

  // Kinh nghiệm (exp) chưa có cột chuyên dụng -> để 0 (vẫn sort được)
  $SB_MEMBERS[] = [
    'name'   => $r['HO_TEN'],
    'role'   => $role,
    'exp'    => 0,
    'img'    => $img, // để rỗng nếu không có -> JS sẽ render avatar chữ cái
    'tags'   => $tags,
    'branch' => $r['TEN_CN'] ?? null,
    'socials'=> [] // có thể bổ sung từ bảng/field khác nếu bạn thêm
  ];
}
?>

<!-- team.php (v4, bright) — Stygian Blue Team Component -->
<section data-sb-team role="region" aria-label="Đội ngũ Stygian Blue"
  class="relative overflow-hidden rounded-3xl m-4 mt-6 min-h-[70vh] flex items-center justify-center text-center bg-white text-slate-800">

  <!-- Decorative background (soft pastel bokeh) -->
  <div aria-hidden="true" class="pointer-events-none absolute -top-28 -left-24 w-[48rem] h-[48rem] rounded-full blur-3xl opacity-30 bg-gradient-to-br from-sky-300 via-cyan-200 to-indigo-200 mix-blend-multiply"></div>
  <div aria-hidden="true" class="pointer-events-none absolute -bottom-28 -right-24 w-[40rem] h-[40rem] rounded-full blur-3xl opacity-25 bg-gradient-to-br from-pink-200 via-rose-100 to-amber-100 mix-blend-multiply"></div>

  <div class="relative z-10 w-full max-w-7xl mx-auto px-6 py-10 md:py-14 text-left">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
      <div>
        <p class="inline-flex items-center gap-2 text-[13px] md:text-sm text-sky-900 bg-sky-100 border border-sky-200 rounded-full px-3 py-1 mb-3">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          Đội ngũ sáng tạo · Nhiếp ảnh · Hậu kỳ · Vận hành
        </p>
        <h2 class="text-3xl md:text-5xl font-extrabold tracking-tight">
          Gặp gỡ <span class="text-sky-600">Stygian Blue</span>
        </h2>
        <p class="mt-2 md:mt-3 text-slate-600 max-w-2xl">Những con người đứng sau mỗi khung hình — kết hợp chuyên môn, quy trình và tinh thần dịch vụ để mang đến trải nghiệm liền mạch.</p>
      </div>

      <!-- Search + Filters -->
      <div class="w-full md:w-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 md:gap-3">
        <label class="relative col-span-1 sm:col-span-2 md:col-span-2">
          <input aria-label="Tìm thành viên" type="search" placeholder="Tìm theo tên, kỹ năng..."
                 class="w-full rounded-full bg-white border border-slate-300 px-4 py-2.5 pr-10 text-sm placeholder-slate-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                 data-sb-team-search>
          <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.3-4.3"></path>
          </svg>
        </label>
        <select aria-label="Lọc vai trò" data-sb-team-filter-role
                class="rounded-full bg-white border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
          <option value="">Tất cả vai trò</option>
          <option value="photographer">Photographer</option>
          <option value="retoucher">Retoucher</option>
          <option value="producer">Producer</option>
          <option value="manager">Manager</option>
        </select>
        <select aria-label="Sắp xếp" data-sb-team-sort
                class="rounded-full bg-white border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
          <option value="name">Sắp xếp: Tên (A→Z)</option>
          <option value="role">Sắp xếp: Vai trò</option>
          <option value="exp">Sắp xếp: Kinh nghiệm</option>
        </select>
      </div>
    </div>

    <!-- Grid -->
    <div class="mt-6 md:mt-8 grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5" data-sb-team-grid>
      <?php foreach ($SB_MEMBERS as $m): ?>
        <article tabindex="0"
          class="group rounded-3xl bg-white border border-slate-200 overflow-hidden focus:outline-none focus:ring-2 focus:ring-sky-500 shadow-sm hover:shadow-md transition-shadow"
          data-sb-team-card
          data-role="<?= htmlspecialchars($m['role']) ?>"
          data-exp="<?= (int)$m['exp'] ?>"
          data-name="<?= htmlspecialchars($m['name']) ?>">

          <div class="relative h-44">
            <?php if ($m['img'] !== ''): ?>
              <img loading="lazy" src="<?= htmlspecialchars($m['img']) ?>"
                   alt="<?= htmlspecialchars($m['name']).' — '.ucfirst($m['role']) ?>"
                   class="w-full h-full object-cover" data-avatar-img>
            <?php else: ?>
              <!-- Avatar chữ cái (fallback) -->
              <div class="w-full h-full grid place-items-center bg-gradient-to-br from-sky-100 to-indigo-100" data-avatar-fallback>
                <span class="text-5xl font-black text-sky-700" data-avatar-initials><?= htmlspecialchars(mb_strtoupper(mb_substr($m['name'],0,1,'UTF-8'),'UTF-8')) ?></span>
              </div>
            <?php endif; ?>
            <div class="absolute inset-0 bg-gradient-to-t from-white/90 via-white/30 to-transparent"></div>
            <div class="absolute bottom-3 left-3 inline-flex items-center gap-2 text-xs bg-white/90 backdrop-blur-md border border-slate-200 px-2.5 py-1 rounded-full shadow-sm">
              <?php
                $icon = [
                  'photographer'=>'fa-camera',
                  'retoucher'=>'fa-magic',
                  'producer'=>'fa-clipboard-list',
                  'manager'=>'fa-user-shield',
                ][$m['role']] ?? 'fa-user';
              ?>
              <i class="fas <?= $icon ?> text-sky-600"></i>
              <?= ucfirst($m['role']) ?> · <?= (int)$m['exp'] ?>y
            </div>
          </div>

          <div class="p-5">
            <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
              <span><?= htmlspecialchars($m['name']) ?></span>
              <?php if (!empty($m['branch'])): ?>
                <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200">CN: <?= htmlspecialchars($m['branch']) ?></span>
              <?php endif; ?>
            </h3>
            <p class="text-slate-600 text-sm">
              <?php
                $subtitle = implode(' · ', array_slice($m['tags'], 0, 3));
                echo $subtitle !== '' ? htmlspecialchars($subtitle) : '—';
              ?>
            </p>

            <div class="mt-3 flex flex-wrap gap-2 text-xs">
              <?php foreach ($m['tags'] as $t): ?>
                <span class="px-2 py-1 rounded-full bg-slate-50 border border-slate-200 text-slate-700 chip"><?= htmlspecialchars($t) ?></span>
              <?php endforeach; ?>
            </div>

            <div class="mt-4 flex items-center gap-2">
              <button class="inline-flex items-center gap-2 px-3 py-2 rounded-full bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold transition-transform hover:-translate-y-[1px] shadow-sm"
                      data-sb-team-open-modal>
                <i class="fas fa-info-circle"></i> Hồ sơ
              </button>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- Empty state -->
    <div class="hidden mt-10 text-center" data-sb-team-empty>
      <p class="text-slate-600">Không tìm thấy thành viên phù hợp.</p>
    </div>
  </div>

  <!-- Modal (SCOPED) -->
  <div class="hidden fixed inset-0 z-50 items-center justify-center p-4" data-sb-team-modal-wrap aria-hidden="true">
    <div class="absolute inset-0 bg-slate-900/50" data-sb-team-modal-close></div>
    <div class="relative z-10 w-full max-w-xl rounded-3xl bg-white border border-slate-200 shadow-2xl overflow-hidden">
      <button aria-label="Đóng" class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white hover:bg-slate-50 border border-slate-200 flex items-center justify-center shadow-sm"><i class="fas fa-times"></i></button>
      <div class="grid md:grid-cols-2">
        <div class="relative h-48 md:h-full">
          <img data-sb-team-modal-img alt="Avatar" class="w-full h-full object-cover" src="">
          <div class="absolute inset-0 bg-gradient-to-t from-white via-white/40 to-transparent"></div>
        </div>
        <div class="p-6">
          <h3 class="text-2xl font-bold text-slate-900" data-sb-team-modal-name>Họ tên</h3>
          <p class="text-sky-700" data-sb-team-modal-role>Vai trò</p>
          <p class="mt-3 text-slate-700 text-sm" data-sb-team-modal-bio>Mô tả, kinh nghiệm, phong cách làm việc…</p>
          <div class="mt-4 flex flex-wrap gap-2 text-xs" data-sb-team-modal-tags></div>
          <div class="mt-5 flex items-center gap-2" data-sb-team-modal-actions></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Styles: SCOPED to [data-sb-team] -->
<style>
  [data-sb-team] .chip { box-shadow: 0 0 0 0 rgba(0,0,0,0); transition: box-shadow .2s ease, transform .2s ease; }
  [data-sb-team] .chip:hover { box-shadow: 0 4px 12px rgba(2,6,23,0.06); transform: translateY(-1px); }
  [data-sb-team] .is-fading { opacity: .5; }
  [data-sb-team] .is-ready { opacity: 1; transition: opacity .2s ease; }
  @media (prefers-reduced-motion: reduce) {
    [data-sb-team] * { animation: none !important; transition: none !important; }
  }
</style>

<!-- JS: SCOPED (search/filter/sort/paginate/modal), no global pollution -->
<script>
(() => {
  const root = document.querySelector('[data-sb-team]');
  if (!root) return;

  /* =========== ELEMENTS =========== */
  const grid   = root.querySelector('[data-sb-team-grid]');
  const cards  = Array.from(root.querySelectorAll('[data-sb-team-card]'));
  const search = root.querySelector('[data-sb-team-search]');
  const fRole  = root.querySelector('[data-sb-team-filter-role]');
  const sort   = root.querySelector('[data-sb-team-sort]');
  const empty  = root.querySelector('[data-sb-team-empty]');
  const modalWrap = root.querySelector('[data-sb-team-modal-wrap]');
  const modalImg  = root.querySelector('[data-sb-team-modal-img]');
  const modalName = root.querySelector('[data-sb-team-modal-name]');
  const modalRole = root.querySelector('[data-sb-team-modal-role]');
  const modalBio  = root.querySelector('[data-sb-team-modal-bio]');
  const modalTags = root.querySelector('[data-sb-team-modal-tags]');
  const modalAct  = root.querySelector('[data-sb-team-modal-actions]');

  /* =========== PAGINATION (4 người/trang) =========== */
  const PAGE_SIZE = 4;
  let currentPage = 1;

  const pagWrap = document.createElement('div');
  pagWrap.className = "mt-8 flex flex-wrap items-center justify-center gap-3 text-sm select-none";
  pagWrap.innerHTML = `
    <div class="inline-flex items-center gap-2 bg-white border border-slate-200 rounded-full px-2 py-1 shadow-sm">
      <button data-prev class="px-3 py-1.5 rounded-full hover:bg-slate-50">« Trước</button>
      <span data-page-info class="text-slate-600"></span>
      <button data-next class="px-3 py-1.5 rounded-full hover:bg-slate-50">Sau »</button>
    </div>`;
  grid.after(pagWrap);
  const btnPrev  = pagWrap.querySelector('[data-prev]');
  const btnNext  = pagWrap.querySelector('[data-next]');
  const pageInfo = pagWrap.querySelector('[data-page-info]');

  /* =========== DATA / FILTERS =========== */
  const collator = new Intl.Collator('vi', { sensitivity: 'base' });
  const normalize = (str) => (str||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase();
  const enrich = (el) => ({
    el,
    name: (el.dataset.name||'').trim(),
    role: (el.dataset.role||'').trim(),
    exp:  parseInt(el.dataset.exp||'0',10),
    img:  el.querySelector('[data-avatar-img]')?.src||'',
    tags: Array.from(el.querySelectorAll('.p-5 .flex.flex-wrap span.chip')).map(s=>s.textContent.trim()),
    socials: Array.from(el.querySelectorAll('.p-5 a')).map(a=>({href:a.getAttribute('href'), html:a.innerHTML}))
  });

  let state = cards.map(enrich);
  let filtered = [];

  /* =========== RENDER =========== */
  function render(){
    const q = normalize(search?.value||'');
    const r = (fRole?.value||'').toLowerCase();
    const s = (sort?.value||'name');

    filtered = state.filter(x=>{
      const hitQ = !q || normalize(x.name).includes(q) || x.tags.some(t=>normalize(t).includes(q));
      const hitR = !r || x.role===r;
      return hitQ && hitR;
    });

    filtered.sort((a,b)=>{
      if (s==='name') return collator.compare(a.name,b.name);
      if (s==='role') return collator.compare(a.role,b.role)||collator.compare(a.name,b.name);
      if (s==='exp')  return (b.exp-a.exp)||collator.compare(a.name,b.name);
      return 0;
    });

    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    currentPage = Math.min(currentPage, totalPages);

    // Fade-out
    grid.classList.remove('is-ready');
    grid.classList.add('is-fading');

    // Apply visibility
    cards.forEach(c=>c.classList.add('hidden'));
    const start = (currentPage-1)*PAGE_SIZE;
    const end = start + PAGE_SIZE;
    filtered.slice(start,end).forEach(x=>x.el.classList.remove('hidden'));

    empty?.classList.toggle('hidden', filtered.length!==0);
    pagWrap.classList.toggle('hidden', filtered.length<=PAGE_SIZE);
    pageInfo.textContent = `Trang ${currentPage}/${totalPages}`;
    btnPrev.disabled = currentPage===1;
    btnNext.disabled = currentPage===totalPages;

    // Fade-in
    requestAnimationFrame(()=> {
      grid.classList.remove('is-fading');
      grid.classList.add('is-ready');
    });
  }

  /* =========== INTERACTIONS =========== */
  let t; search?.addEventListener('input',()=>{
    clearTimeout(t);
    t=setTimeout(()=>{currentPage=1; render(); resetAutoPage();},150);
  });
  [fRole, sort].forEach(el=>{
    el?.addEventListener('change',()=>{currentPage=1; render(); resetAutoPage();});
  });
  btnPrev?.addEventListener('click',()=>{
    if(currentPage>1){currentPage--; render(); resetAutoPage();}
  });
  btnNext?.addEventListener('click',()=>{
    const totalPages=Math.ceil(filtered.length/PAGE_SIZE);
    if(currentPage<totalPages){currentPage++; render(); resetAutoPage();}
  });

  /* =========== MODAL + Focus Trap =========== */
  let lastFocused = null;
  const trapFocus = (e)=>{
    const f = modalWrap.querySelectorAll('button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])');
    if(!f.length) return;
    const first=f[0], last=f[f.length-1];
    if(e.key==='Tab'){
      if(e.shiftKey && document.activeElement===first){e.preventDefault();last.focus();}
      else if(!e.shiftKey && document.activeElement===last){e.preventDefault();first.focus();}
    }
  };

  root.addEventListener('click',(e)=>{
    const btn=e.target.closest('[data-sb-team-open-modal]');
    if(!btn) return;
    const card=btn.closest('[data-sb-team-card]');
    if(!card) return;
    const data=enrich(card);
    lastFocused=btn;

    modalImg.src=data.img||card.querySelector('[data-avatar-img]')?.src||'';
    modalName.textContent=data.name;
    modalRole.textContent=`${data.role[0].toUpperCase()+data.role.slice(1)} • ${data.exp} năm`;
    modalBio.textContent=`Kinh nghiệm ${data.exp} năm. Chuyên môn: ${data.tags.join(', ')}.`;

    modalTags.innerHTML='';
    data.tags.forEach(t=>{
      const span=document.createElement('span');
      span.className='px-2 py-1 rounded-full bg-slate-50 border border-slate-200 text-slate-700';
      span.textContent=t; modalTags.appendChild(span);
    });
    modalAct.innerHTML='';
    data.socials.forEach(s=>{
      const a=document.createElement('a');
      a.className='inline-flex items-center gap-2 px-3 py-2 rounded-full bg-white hover:bg-slate-50 border border-slate-200 text-sm shadow-sm';
      a.href=s.href||'#'; a.innerHTML=s.html; a.target='_blank';
      modalAct.appendChild(a);
    });

    modalWrap.classList.remove('hidden'); modalWrap.classList.add('flex');
    modalWrap.setAttribute('aria-hidden','false');
    modalWrap.addEventListener('keydown',trapFocus);
    modalWrap.querySelector('button').focus();
    pauseAutoPage();
  });

  function closeModal(){
    modalWrap.classList.add('hidden');
    modalWrap.classList.remove('flex');
    modalWrap.setAttribute('aria-hidden','true');
    modalWrap.removeEventListener('keydown',trapFocus);
    if(lastFocused) lastFocused.focus();
    resumeAutoPage();
  }

  modalWrap?.addEventListener('click',(e)=>{
    if(e.target.matches('[data-sb-team-modal-close], [data-sb-team-modal-close] *') || e.target===modalWrap) closeModal();
  });
  root.addEventListener('keydown',(e)=>{if(e.key==='Escape') closeModal();});

  /* =========== AUTO PAGE (5s) =========== */
  let autoTimer=null, autoPaused=false;
  function startAutoPage(){
    stopAutoPage();
    autoTimer=setInterval(()=>{
      if(autoPaused) return;
      const totalPages=Math.ceil(filtered.length/PAGE_SIZE);
      if(totalPages<=1) return;
      currentPage = currentPage < totalPages ? currentPage+1 : 1;
      render();
    },5000);
  }
  function stopAutoPage(){ if(autoTimer){clearInterval(autoTimer); autoTimer=null;} }
  function pauseAutoPage(){autoPaused=true;}
  function resumeAutoPage(){autoPaused=false;}
  function resetAutoPage(){stopAutoPage(); startAutoPage();}

  // Pause on hover / focus
  root.addEventListener('mouseenter',()=>pauseAutoPage());
  root.addEventListener('mouseleave',()=>resumeAutoPage());
  root.addEventListener('focusin',()=>pauseAutoPage());
  root.addEventListener('focusout',()=>resumeAutoPage());

  /* =========== INIT =========== */
  render();
  startAutoPage();
})();
</script>
