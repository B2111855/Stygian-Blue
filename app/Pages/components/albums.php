<?php
// albums.php (v5.1) — Stygian Blue Gallery (Bright, Fast, A11Y, One-file)
// Fixes: cover fallback chắc chắn hiển thị, bỏ caption/filename trong lightbox,
//        di chuyển nút "Xem thêm album" xuống cuối trang.

declare(strict_types=1);

// ================== TÙY CHỈNH NHANH ==================
$BASE_DIR        = __DIR__ . '/../../../public/images/albums';  // ĐƯỜNG DẪN THỰC
$BASE_URL_PREFIX = '../../../public/images/albums';              // URL tương đối để <img> src
$ALBUMS_PER_PAGE = 2;    // Số album hiển thị mỗi lần
$IMAGES_LIMIT    = 24;   // Số ảnh tối đa/album (tối ưu hiệu năng)
$ALLOW_EXT       = ['jpg','jpeg','png','gif','webp'];

// ================== HELPERS (AN TOÀN/HIỆU NĂNG) ==================
function safe_text(?string $s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function is_image_file(string $file, array $allow): bool {
  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  return in_array($ext, $allow, true);
}
function is_valid_image_path(string $path): bool {
  if (!is_file($path)) return false;
  $info = @getimagesize($path);
  return $info !== false;
}
/** Liệt kê album theo mtime (mới nhất trước) */
function list_albums_sorted(string $dir): array {
  if (!is_dir($dir)) return [];
  $items = array_diff(@scandir($dir), ['.','..']);
  $albums = [];
  foreach ($items as $name) {
    $path = $dir . '/' . $name;
    if (is_dir($path)) {
      $albums[] = ['name'=>$name, 'path'=>$path, 'mtime'=>(@filemtime($path) ?: 0)];
    }
  }
  usort($albums, fn($a,$b) => $b['mtime'] <=> $a['mtime']);
  return $albums;
}
/** Tạo/lấy thumbnail cho grid (maxW=600). Lưu vào <album>/cache */
function thumb_url(string $albumPath, string $albumUrl, string $file, int $maxW = 600): string {
  $src = $albumPath . '/' . $file;
  $origUrl = $albumUrl . '/' . rawurlencode($file);
  if (!is_valid_image_path($src)) return $origUrl;

  $cacheDir = $albumPath . '/cache';
  if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  $dstBase = $cacheDir . '/' . $file;
  $dst = ($ext === 'webp') ? $dstBase : preg_replace('/\.[^.]+$/', '.jpg', $dstBase);

  if (!file_exists($dst) || @filemtime($dst) < @filemtime($src)) {
    $data = @file_get_contents($src);
    if ($data !== false && function_exists('imagecreatefromstring')) {
      $img = @imagecreatefromstring($data);
      if ($img !== false) {
        $w = imagesx($img); $h = imagesy($img);
        if ($w > 0 && $h > 0) {
          $ratio = $maxW / $w;
          $newW  = ($w > $maxW) ? $maxW : $w;
          $newH  = ($w > $maxW) ? max(1, (int)round($h * $ratio)) : $h;

          $tmp = imagecreatetruecolor($newW, $newH);
          imagealphablending($tmp, false);
          imagesavealpha($tmp, true);
          imagecopyresampled($tmp, $img, 0,0,0,0, $newW,$newH, $w,$h);

          $ok = false;
          if ($ext === 'webp' && function_exists('imagewebp')) {
            $ok = @imagewebp($tmp, $dst, 82);
          } else {
            $bg = imagecreatetruecolor($newW, $newH);
            $bgColor = imagecolorallocate($bg, 255, 255, 255);
            imagefilledrectangle($bg, 0,0, $newW,$newH, $bgColor);
            imagecopy($bg, $tmp, 0,0,0,0, $newW,$newH);
            $ok = @imagejpeg($bg, $dst, 82);
            imagedestroy($bg);
          }
          imagedestroy($tmp);
        }
        imagedestroy($img);
      }
    }
  }
  if (!file_exists($dst)) return $origUrl; // fallback
  $cacheRel = 'cache/' . basename($dst);
  return $albumUrl . '/' . rawurlencode($cacheRel);
}

// ================== BUILD DATA -> JS ==================
$albumsPhp = [];
$albums = list_albums_sorted($BASE_DIR);

foreach ($albums as $al) {
  $albumName = $al['name'];
  $albumPath = $al['path'];
  $albumUrl  = $BASE_URL_PREFIX . '/' . rawurlencode($albumName);

  $files = [];
  foreach (array_diff(@scandir($albumPath), ['.','..']) as $img) {
    $abs = $albumPath . '/' . $img;
    if (is_file($abs) && is_image_file($img, $ALLOW_EXT) && is_valid_image_path($abs)) {
      $files[] = [
        'src'   => $albumUrl . '/' . rawurlencode($img),        // ảnh gốc (lightbox)
        'thumb' => thumb_url($albumPath, $albumUrl, $img, 600), // thumb cho grid/cover
        'alt'   => pathinfo($img, PATHINFO_FILENAME),
      ];
    }
  }
  if ($files) {
    // Natural sort theo tên file
    usort($files, fn($a,$b)=> strnatcasecmp(basename($a['src']), basename($b['src'])));
  }

  $albumsPhp[] = [
    'name'   => $albumName,
    'mtime'  => date('c', $al['mtime']),
    'photos' => array_slice($files, 0, $IMAGES_LIMIT), // giới hạn để nhẹ trang
    'total'  => count($files),
  ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <title>Bộ sưu tập · Stygian Blue</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Tailwind + FontAwesome -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>

  <style>
    /* ===== SCOPED ===== */
    [data-sb-gallery]{overflow-x:hidden}
    [data-sb-gallery] .sb-masonry{column-fill:balance}
    [data-sb-gallery] .break-inside-avoid{break-inside:avoid}
    @media (prefers-reduced-motion: reduce){
      [data-sb-gallery] *{transition:none!important;animation:none!important}
    }
    /* Pills / buttons */
    .chip{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .6rem;border-radius:9999px;font-size:.75rem;font-weight:600;
      background:linear-gradient(180deg,#fff,#f8fafc); color:#0f172a;border:1px solid #e2e8f0; box-shadow:0 1px 0 rgba(2,6,23,.04)}
    .btn-soft{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem .9rem;border-radius:9999px;font-weight:600;
      background:linear-gradient(180deg,#fff,#f8fafc); color:#0f172a;border:1px solid #e2e8f0; box-shadow:0 1px 0 rgba(2,6,23,.04)}
    .btn-primary{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem .9rem;border-radius:9999px;font-weight:700;color:#fff;
      background:linear-gradient(90deg,#06b6d4,#a78bfa); box-shadow:0 8px 20px rgba(6,182,212,.25); border:1px solid transparent}
    .btn-primary:hover{transform:translateY(-1px); filter:brightness(1.05)}
  </style>
</head>
<body class="min-h-screen bg-[linear-gradient(135deg,#e6f6ff,#ffffff,#ffe9f6)] text-slate-900">

<section data-sb-gallery role="region" aria-label="Bộ sưu tập ảnh"
  class="relative overflow-hidden rounded-3xl m-4 mt-6 min-h-[70vh] flex items-start justify-center text-left">
  <div class="relative z-10 w-full max-w-7xl mx-auto p-6">
    <!-- Intro -->
    <header class="text-center mb-8">
      <p class="inline-flex items-center gap-2 text-[13px] md:text-sm text-sky-900 bg-sky-100 border border-sky-200 rounded-full px-3 py-1 mb-3">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        Phòng trưng bày · Album gần đây · Xem nhanh
      </p>
      <h1 class="text-3xl md:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-sky-600 to-fuchsia-600">
        Bộ sưu tập
      </h1>
      <p class="mt-2 text-slate-600">Tìm kiếm, xem nhanh, phóng to và điều hướng ảnh mượt mà.</p>
    </header>

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between mb-4">
      <label class="relative w-full sm:w-80">
        <input id="gal-search" type="search" placeholder="Tìm theo tên album…"
               class="w-full rounded-xl bg-white/90 border border-slate-200 px-4 py-2.5 pr-9 text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-400"
               aria-label="Tìm album theo tên">
        <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-500"></i>
      </label>
      <div class="flex gap-2">
        <select id="gal-sort"
                class="rounded-xl bg-white/90 border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400"
                aria-label="Sắp xếp album">
          <option value="recent">Mới cập nhật</option>
          <option value="az">Tên (A→Z)</option>
        </select>
      </div>
    </div>

    <!-- Albums wrap -->
    <div id="albums-wrap" class="space-y-10" aria-live="polite"></div>

    <!-- Show more button (moved to bottom as requested) -->
    <div class="mt-8 text-center">
      <button id="gal-show-more" class="btn-soft px-5 py-2.5 text-sm" title="Mở thêm album">Xem thêm album</button>
    </div>

    <!-- Lightbox (no caption/filename) -->
    <div class="sb-lightbox fixed inset-0 hidden items-center justify-center text-center bg-black/70 z-50"
      aria-hidden="true" role="dialog" aria-modal="true">
      <button class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 border border-white/20 flex items-center justify-center"
        data-sb-lb-close aria-label="Đóng">
        <i class="fas fa-times"></i>
      </button>
      <div class="relative inline-flex flex-col items-center justify-center max-w-[92vw] max-h-[86vh] p-2 bg-white rounded-2xl border border-slate-200 shadow-2xl">
        <img data-sb-lb-img src="" alt="" class="block max-w-full max-h-[78vh] object-contain mx-auto">
        <!-- ĐÃ BỎ phần caption/filename -->
        <button class="absolute left-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/10 hover:bg-black/20 text-slate-900 border border-slate-200 flex items-center justify-center"
          data-sb-lb-prev aria-label="Ảnh trước">
          <i class="fas fa-chevron-left"></i>
        </button>
        <button class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/10 hover:bg-black/20 text-slate-900 border border-slate-200 flex items-center justify-center"
          data-sb-lb-next aria-label="Ảnh sau">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- Empty -->
    <div id="albums-empty" class="hidden mt-6">
      <div class="rounded-2xl bg-white/80 border border-slate-200 p-6 text-slate-600">
        <i class="far fa-inbox mr-2"></i> Chưa có album nào.
      </div>
    </div>
  </div>
</section>

<script>
  // ===== Inject data từ PHP =====
  window.ALBUMS_DATA = <?php echo json_encode($albumsPhp, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;

  (function(){
    const ALBUMS_PER_PAGE = <?php echo (int)$ALBUMS_PER_PAGE; ?>;
    const IMAGES_LIMIT    = <?php echo (int)$IMAGES_LIMIT; ?>;

    const $  = (s,ctx=document)=>ctx.querySelector(s);
    const $$ = (s,ctx=document)=>Array.from(ctx.querySelectorAll(s));

    const wrap   = $('#albums-wrap');
    const empty  = $('#albums-empty');
    const search = $('#gal-search');
    const sortSel= $('#gal-sort');
    const showMoreBtn = $('#gal-show-more');

    const ALL = (Array.isArray(window.ALBUMS_DATA) ? window.ALBUMS_DATA : []);
    ALL.forEach(a=>{ a._mtime = +new Date(a.mtime || Date.now()); });

    let albums = ALL.slice();
    let renderedCount = 0;

    function albumHTML(album, idx){
      const count = album.photos.length;
      const first = album.photos[0] || null;
      // Cover CỨNG fallback: ưu tiên thumb, rớt về src; thêm onerror để rớt về src nếu thumb lỗi
      const coverSrc  = first ? (first.thumb || first.src) : '';
      const coverFull = first ? first.src : '';
      const id = 'album-'+idx;

      const items = album.photos.map((p,i)=> {
        const src = p.src; // full
        const thumb = p.thumb || p.src;
        return `
        <figure class="group relative overflow-hidden rounded-xl bg-white/80 border border-slate-200 cursor-zoom-in mb-3 break-inside-avoid"
                tabindex="0"
                onkeydown="if(event.key==='Enter'||event.key===' '){this.querySelector('[data-sb-lightbox-open]').click()}">
          <img
            src="${thumb}"
            alt=""
            class="w-full h-auto object-cover transition-transform duration-300 group-hover:scale-105"
            loading="lazy" decoding="async"
            onerror="this.onerror=null;this.src='${src}'">
          <button type="button" class="absolute inset-0" aria-label="Xem ảnh lớn"
            data-sb-lightbox-open data-src="${src}"></button>
        </figure>`;
      }).join('');

      const totalText = album.total > IMAGES_LIMIT
        ? `<span class="text-xs text-slate-500">(Hiển thị ${IMAGES_LIMIT}/${album.total})</span>` : '';

      return `
      <section id="${id}" class="sb-album rounded-3xl bg-white/85 backdrop-blur-xl border border-slate-200 overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 p-5 border-b border-slate-200">
          <div class="min-w-0">
            <h2 class="text-2xl font-bold text-slate-900 truncate flex items-center gap-3">
              <span class="inline-flex w-10 h-10 rounded-xl overflow-hidden border border-slate-200 items-center justify-center bg-white">
                ${coverSrc
                  ? `<img src="${coverSrc}" alt="" class="w-full h-full object-cover" loading="lazy"
                           onerror="this.onerror=null;this.src='${coverFull}'">`
                  : `<i class="fas fa-images" aria-hidden="true"></i>`}
              </span>
              <span>${(album.name||'').replace(/</g,'&lt;')}</span>
              <span class="chip text-xs">${count} ảnh</span>
              ${totalText}
            </h2>
            <p class="text-sm text-slate-600">Cập nhật: ${new Date(album._mtime).toLocaleString('vi-VN')}</p>
          </div>
          <div class="ml-auto flex items-center gap-2">
            <button class="sb-album-toggle btn-soft px-3 py-2 text-sm" aria-expanded="true">Thu gọn</button>
          </div>
        </div>

        <div class="p-5">
          <div class="sb-masonry columns-2 sm:columns-3 md:columns-4 xl:columns-5 2xl:columns-6 [column-gap:0.75rem]" data-sb-album-grid>
            ${items}
          </div>
        </div>
      </section>`;
    }

    function renderNextPage(){
      const remain = albums.slice(renderedCount);
      const page = remain.slice(0, ALBUMS_PER_PAGE);
      if(!page.length) return false;

      const frag = document.createElement('div');
      frag.innerHTML = page.map((a,i)=>albumHTML(a, renderedCount+i)).join('');
      wrap.appendChild(frag);
      renderedCount += page.length;

      if(renderedCount >= albums.length) showMoreBtn.classList.add('hidden');
      else showMoreBtn.classList.remove('hidden');

      bindAlbumToggles();
      bindLightbox();
      return true;
    }

    function rerenderAll() {
      wrap.innerHTML = '';
      renderedCount = 0;
      if(!albums.length){
        empty.classList.remove('hidden');
        showMoreBtn.classList.add('hidden');
        return;
      }
      empty.classList.add('hidden');
      renderNextPage();
    }

    function applySearchSort(){
      const q = (search?.value || '').trim().toLowerCase();
      const mode = (sortSel?.value || 'recent');

      albums = ALL.filter(a => !q || (a.name || '').toLowerCase().includes(q)).slice();
      if(mode === 'recent') albums.sort((a,b)=> b._mtime - a._mtime);
      else if(mode === 'az') albums.sort((a,b)=> (a.name||'').localeCompare(b.name||'', 'vi', {sensitivity:'base'}));

      rerenderAll();
    }

    let t=null;
    search?.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(applySearchSort, 160); });
    sortSel?.addEventListener('change', applySearchSort);
    showMoreBtn?.addEventListener('click', ()=> renderNextPage());

    function bindAlbumToggles(){
      $$('.sb-album').forEach(al => {
        const btn  = $('.sb-album-toggle', al);
        const grid = $('[data-sb-album-grid]', al);
        if(!btn || !grid) return;
        btn.onclick = () => {
          const hidden = grid.classList.toggle('hidden');
          btn.textContent = hidden ? 'Mở rộng' : 'Thu gọn';
          btn.setAttribute('aria-expanded', String(!hidden));
        };
      });
    }

    // ===== Lightbox (no caption) =====
    const lbWrap = document.querySelector('.sb-lightbox');
    const lbImg  = document.querySelector('[data-sb-lb-img]');
    const prevBtn= document.querySelector('[data-sb-lb-prev]');
    const nextBtn= document.querySelector('[data-sb-lb-next]');
    const closeBtn=document.querySelector('[data-sb-lb-close]');
    let currentList = [];
    let currentIdx  = -1;
    let lastFocus   = null;
    let touchX      = null;

    function showAt(i){
      if(!currentList.length) return;
      currentIdx = (i + currentList.length) % currentList.length;
      const src = currentList[currentIdx];
      lbImg.src = src;
      // Preload ảnh tiếp theo
      const preload = new Image();
      preload.src = currentList[(currentIdx+1)%currentList.length];
    }
    function openLB(src, list){
      currentList = list && list.length ? list.slice() : [src];
      const idx = currentList.indexOf(src);
      currentIdx = idx >= 0 ? idx : 0;
      showAt(currentIdx);

      lbWrap.classList.remove('hidden'); lbWrap.classList.add('flex');
      lbWrap.setAttribute('aria-hidden','false');
      lastFocus = document.activeElement;
      document.body.style.overflow = 'hidden';
      closeBtn?.focus();
    }
    function closeLB(){
      lbWrap.classList.add('hidden'); lbWrap.classList.remove('flex');
      lbWrap.setAttribute('aria-hidden','true');
      document.body.style.overflow = '';
      if(lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }
    function nav(d){ showAt(currentIdx + d); }

    function bindLightbox(){
      // mỗi album một list riêng
      $$('.sb-album').forEach(sec=>{
        const nodes = $$('[data-sb-lightbox-open]', sec);
        const list = nodes.map(n=>n.dataset.src);
        nodes.forEach(btn=>{
          btn.addEventListener('click', ()=>{
            openLB(btn.dataset.src, list);
          }, {passive:true});
        });
      });
    }

    prevBtn?.addEventListener('click', ()=>nav(-1));
    nextBtn?.addEventListener('click', ()=>nav(1));
    closeBtn?.addEventListener('click', closeLB);
    lbWrap?.addEventListener('click', e=>{ if(e.target===lbWrap) closeLB(); });
    window.addEventListener('keydown', e=>{
      if(!lbWrap.classList.contains('flex')) return;
      if(e.key==='Escape') closeLB();
      if(e.key==='ArrowRight') nav(1);
      if(e.key==='ArrowLeft') nav(-1);
    });
    lbWrap.addEventListener('touchstart', e=>{ touchX = e.touches[0].clientX; }, {passive:true});
    lbWrap.addEventListener('touchend', e=>{
      if(touchX==null) return;
      const dx = e.changedTouches[0].clientX - touchX;
      if(Math.abs(dx)>40) nav(dx<0?1:-1);
      touchX=null;
    }, {passive:true});

    // INIT
    applySearchSort();
  })();
</script>
</body>
</html>
