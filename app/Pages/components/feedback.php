<?php
include '../../../database/config.php';

// mặc định lần đầu: không lọc, phân trang tổng thể
$limit = 6;
$totalQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM phan_hoi_cua_khach_hang");
$totalRow   = mysqli_fetch_assoc($totalQuery);
$total      = (int)($totalRow['total'] ?? 0);
$pages      = max(1, (int)ceil($total / $limit));
$page       = 1; // trang đầu load qua AJAX nên mặc 1
?>
<!-- feedback.php (v4) — Bright + Server-side search/sort/pagination -->
<section data-sb-feedback role="region" aria-label="Phản hồi khách hàng"
  class="relative overflow-hidden rounded-3xl m-4 mt-6 min-h-[70vh] flex items-center justify-center text-center
         bg-[linear-gradient(135deg,#ecfeff,#ffffff,#fdf2f8)] text-slate-900">

  <!-- Pastel bokeh -->
  <div aria-hidden="true" class="pointer-events-none absolute -top-24 -left-24 w-[44rem] h-[44rem] rounded-full blur-3xl opacity-30 bg-cyan-200 mix-blend-screen"></div>
  <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -right-24 w-[36rem] h-[36rem] rounded-full blur-3xl opacity-25 bg-fuchsia-200 mix-blend-screen"></div>

  <div class="relative z-10 w-full max-w-6xl mx-auto p-6">
    <h2 class="text-3xl md:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-sky-600 to-fuchsia-600 mb-6">
      📝 Phản hồi từ khách hàng
    </h2>

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between mb-4 text-left">
      <label class="relative w-full sm:w-80">
        <input id="fbSearch" type="search" inputmode="search" placeholder="Tìm theo tên hoặc nội dung…"
               class="w-full rounded-xl bg-white/90 border border-slate-200 px-4 py-2.5 pr-9 text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-400"
               aria-label="Tìm phản hồi">
        <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-500"></i>
      </label>

      <div class="flex gap-2 justify-end">
        <select id="fbSort" class="rounded-xl bg-white/90 border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400"
                aria-label="Sắp xếp">
          <option value="newest">Mới nhất</option>
          <option value="highest">Điểm cao</option>
          <option value="withPhoto">Có hình ảnh</option>
        </select>
        <button type="button" id="fbRefresh" class="px-3 py-2 rounded-xl text-sm text-slate-700 bg-white/90 border border-slate-200 hover:shadow"
                title="Tải lại"><i class="fas fa-sync-alt"></i></button>
      </div>
    </div>

    <!-- List -->
    <div id="feedback-list" class="text-left" aria-live="polite" aria-busy="true">
      <!-- Skeleton while loading -->
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" data-sb-fb-skeleton>
        <div class="sbf-skeleton-card"></div><div class="sbf-skeleton-card"></div><div class="sbf-skeleton-card"></div>
        <div class="sbf-skeleton-card"></div><div class="sbf-skeleton-card"></div><div class="sbf-skeleton-card"></div>
      </div>
    </div>

    <!-- Empty / Error -->
    <div class="hidden mt-6" data-sb-fb-empty>
      <div class="rounded-2xl bg-white/80 border border-slate-200 p-6 text-slate-600">
        <i class="far fa-inbox mr-2"></i> Chưa có phản hồi nào khớp điều kiện.
      </div>
    </div>
    <div class="hidden mt-6" data-sb-fb-error>
      <div class="rounded-2xl bg-rose-50 border border-rose-200 p-6 text-rose-700">
        <i class="fas fa-exclamation-triangle mr-2"></i> Không tải được phản hồi. Vui lòng thử lại.
      </div>
    </div>

    <!-- Pagination (sẽ cập nhật động theo kết quả server) -->
    <div class="mt-6 flex justify-center gap-2" id="pagination" role="navigation" aria-label="Phân trang">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <button type="button"
          data-sb-fb-page="<?= $i ?>"
          class="sbf-page <?= ($i == $page) ? 'active' : '' ?>"
          aria-current="<?= ($i == $page) ? 'page' : 'false' ?>"
          aria-label="Trang <?= $i ?>"><?= $i ?></button>
      <?php endfor; ?>
    </div>
  </div>
</section>

<!-- (Tùy chọn) Đổi nhãn sắp xếp -->
<select id="fbSort" class="rounded-xl bg-white/90 border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400" aria-label="Sắp xếp">
  <option value="newest">Mới nhất</option>
  <option value="highest">Điểm cao</option>
  <option value="byService">Theo dịch vụ (A→Z)</option>
</select>


<style>
/* ===== SCOPED to [data-sb-feedback] ===== */
@media (prefers-reduced-motion: reduce){
  [data-sb-feedback] *{ animation:none !important; transition:none !important; }
}
[data-sb-feedback]{ overflow-x:hidden; }
[data-sb-feedback] .sbf-page{
  @apply px-3 py-1 rounded border text-sm;
  background: linear-gradient(180deg,#fff,#f8fafc);
  color:#075985; border-color:#e2e8f0;
  box-shadow: 0 1px 0 rgba(2,6,23,.04);
  transition: filter .15s ease, transform .15s ease;
}
[data-sb-feedback] .sbf-page:hover{ filter:brightness(1.05); transform: translateY(-1px); }
[data-sb-feedback] .sbf-page.active{
  background: linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa);
  color:#fff; border-color:transparent;
  box-shadow: 0 8px 18px rgba(14,165,233,.25);
}
[data-sb-feedback] .sbf-skeleton-card{
  height: 150px; border-radius: 16px; border:1px solid #e2e8f0; overflow:hidden;
  background:
    linear-gradient(90deg, rgba(226,232,240,0) 0%, rgba(226,232,240,.7) 50%, rgba(226,232,240,0) 100%),
    linear-gradient(#ffffff,#f8fafc);
  background-size: 200% 100%, 100% 100%;
  animation: sbf-shimmer 1.2s infinite;
}
@keyframes sbf-shimmer{ 0%{ background-position: -200% 0, 0 0; } 100%{ background-position: 200% 0, 0 0; } }
</style>

<script>
(function(){
  const root  = document.querySelector('[data-sb-feedback]');
  if(!root) return;

  const list      = root.querySelector('#feedback-list');
  const skel      = root.querySelector('[data-sb-fb-skeleton]');
  const emptyBox  = root.querySelector('[data-sb-fb-empty]');
  const errBox    = root.querySelector('[data-sb-fb-error]');
  const pagWrap   = root.querySelector('#pagination');
  const qInput    = root.querySelector('#fbSearch');
  const sortSel   = root.querySelector('#fbSort');
  const btnRef    = root.querySelector('#fbRefresh');

  const ENDPOINT  = '../components/load_feedback_ajax.php'; // refactored API (phần 2)
  let currentPage = 1;
  let pages       = pagWrap.querySelectorAll('[data-sb-fb-page]').length || 1;

  function setBusy(v){ list.setAttribute('aria-busy', v? 'true':'false'); }
  function showSkeleton(){ emptyBox.classList.add('hidden'); errBox.classList.add('hidden'); list.innerHTML=''; skel?.classList.remove('hidden'); setBusy(true); }
  function hideSkeleton(){ skel?.classList.add('hidden'); setBusy(false); }

  function renderPagination(totalPages){
    pagWrap.innerHTML = '';
    totalPages = Math.max(1, totalPages|0);
    for(let i=1;i<=totalPages;i++){
      const b = document.createElement('button');
      b.type='button';
      b.className='sbf-page'+(i===currentPage?' active':'');
      b.setAttribute('data-sb-fb-page', i);
      b.setAttribute('aria-label', 'Trang '+i);
      b.setAttribute('aria-current', i===currentPage? 'page':'false');
      b.textContent = i;
      b.addEventListener('click', ()=> load(i, {scroll:true}));
      pagWrap.appendChild(b);
    }
  }

  async function load(page=1, {scroll=false}={}){
    try{
      currentPage = page;
      showSkeleton();
      const url = new URL(ENDPOINT, location.href);
      url.searchParams.set('page', page);
      url.searchParams.set('q', qInput?.value||'');
      url.searchParams.set('sort', sortSel?.value||'newest');

      const res  = await fetch(url.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!res.ok) throw new Error('HTTP '+res.status);
      const html = await res.text();
      hideSkeleton();

      list.innerHTML = html;
      // lấy meta tổng số trang từ template
      const meta = list.querySelector('#fb-meta');
      const totalPages = meta ? parseInt(meta.dataset.totalPages||'1',10) : pages;
      renderPagination(totalPages);

      // rỗng?
      const anyItem = list.querySelector('[data-fb-item]');
      emptyBox.classList.toggle('hidden', !!anyItem);

      // Nếu người dùng chọn byService, sắp xếp lại các thẻ theo tên dịch vụ (client-side)
if ((root.querySelector('#fbSort')?.value || '') === 'byService') {
  const cards = [...list.querySelectorAll('[data-fb-item]')];
  cards.sort((a,b)=>{
    const sa = (a.querySelector('a[title="Xem dịch vụ"]')?.textContent || '').trim().toLowerCase();
    const sb = (b.querySelector('a[title="Xem dịch vụ"]')?.textContent || '').trim().toLowerCase();
    return sa.localeCompare(sb, 'vi', {sensitivity:'base'});
  }).forEach(el=> el.parentElement.appendChild(el));
}
  

      if(scroll){ root.scrollIntoView({behavior:'smooth', block:'start'}); }
    }catch(e){
      hideSkeleton();
      errBox.classList.remove('hidden');
      console.error(e);
    }
  }

  // Debounce search
  let t=null;
  qInput?.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(()=> load(1), 220); });
  sortSel?.addEventListener('change', ()=> load(1));
  btnRef?.addEventListener('click', ()=> load(currentPage));

  // Init
  load(1);
})();
</script>

<?php mysqli_close($conn); ?>
