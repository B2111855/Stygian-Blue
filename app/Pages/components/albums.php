<?php
// albums.php (v6.0) — Stygian Blue Fine-Art Gallery Experience
// Nhấn mạnh câu chuyện nhiếp ảnh: hero cinematic, lưới album tối giản, lightbox toàn màn hình.

declare(strict_types=1);

$BASE_DIR        = __DIR__ . '/../../../public/images/albums';  // Đường dẫn vật lý tới album
$BASE_URL_PREFIX = '../../../public/images/albums';             // URL tương đối cho <img>
$ALBUMS_PER_PAGE = 3;                                           // Số album hiển thị mỗi lần tải thêm
$IMAGES_LIMIT    = 24;                                          // Số ảnh tối đa/album
$ALLOW_EXT       = ['jpg','jpeg','png','gif','webp'];

function is_image_file(string $file, array $allow): bool {
  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  return in_array($ext, $allow, true);
}

function is_valid_image_path(string $path): bool {
  if (!is_file($path)) {
    return false;
  }
  $info = @getimagesize($path);
  return $info !== false;
}

function list_albums_sorted(string $dir): array {
  if (!is_dir($dir)) {
    return [];
  }
  $items = array_diff(@scandir($dir), ['.', '..']);
  $albums = [];
  foreach ($items as $name) {
    $path = $dir . '/' . $name;
    if (is_dir($path)) {
      $albums[] = [
        'name'  => $name,
        'path'  => $path,
        'mtime' => (@filemtime($path) ?: 0),
      ];
    }
  }
  usort($albums, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);
  return $albums;
}

function thumb_url(string $albumPath, string $albumUrl, string $file, int $maxW = 640): string {
  $src = $albumPath . '/' . $file;
  $origUrl = $albumUrl . '/' . rawurlencode($file);
  if (!is_valid_image_path($src)) {
    return $origUrl;
  }

  $cacheDir = $albumPath . '/cache';
  if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
  }

  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  $dstBase = $cacheDir . '/' . $file;
  $dst = ($ext === 'webp') ? $dstBase : preg_replace('/\.[^.]+$/', '.jpg', $dstBase);

  if (!file_exists($dst) || @filemtime($dst) < @filemtime($src)) {
    $data = @file_get_contents($src);
    if ($data !== false && function_exists('imagecreatefromstring')) {
      $img = @imagecreatefromstring($data);
      if ($img !== false) {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > 0 && $h > 0) {
          $ratio = $maxW / max(1, $w);
          $newW  = ($w > $maxW) ? $maxW : $w;
          $newH  = ($w > $maxW) ? max(1, (int)round($h * $ratio)) : $h;

          $tmp = imagecreatetruecolor($newW, $newH);
          imagealphablending($tmp, false);
          imagesavealpha($tmp, true);
          imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

          if ($ext === 'webp' && function_exists('imagewebp')) {
            @imagewebp($tmp, $dst, 82);
          } else {
            $bg = imagecreatetruecolor($newW, $newH);
            $bgColor = imagecolorallocate($bg, 255, 255, 255);
            imagefilledrectangle($bg, 0, 0, $newW, $newH, $bgColor);
            imagecopy($bg, $tmp, 0, 0, 0, 0, $newW, $newH);
            @imagejpeg($bg, $dst, 82);
            imagedestroy($bg);
          }
          imagedestroy($tmp);
        }
        imagedestroy($img);
      }
    }
  }

  if (!file_exists($dst)) {
    return $origUrl;
  }
  $cacheRel = 'cache/' . basename($dst);
  return $albumUrl . '/' . rawurlencode($cacheRel);
}

$albumsPhp = [];
$albums = list_albums_sorted($BASE_DIR);

foreach ($albums as $al) {
  $albumName = $al['name'];
  $albumPath = $al['path'];
  $albumUrl  = $BASE_URL_PREFIX . '/' . rawurlencode($albumName);

  $files = [];
  foreach (array_diff(@scandir($albumPath), ['.', '..']) as $img) {
    $abs = $albumPath . '/' . $img;
    if (is_file($abs) && is_image_file($img, $ALLOW_EXT) && is_valid_image_path($abs)) {
      $files[] = [
        'src'   => $albumUrl . '/' . rawurlencode($img),
        'thumb' => thumb_url($albumPath, $albumUrl, $img, 640),
        'alt'   => pathinfo($img, PATHINFO_FILENAME),
      ];
    }
  }

  if ($files) {
    usort($files, static fn($a, $b) => strnatcasecmp(basename($a['src']), basename($b['src'])));
  }

  $albumsPhp[] = [
    'name'   => $albumName,
    'mtime'  => date('c', $al['mtime']),
    'photos' => array_slice($files, 0, $IMAGES_LIMIT),
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
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

  <style>
              /* Vùng gallery mang hơi hướng fine-art, nhiều khoảng trắng */
              body.sb-gallery-page{
                font-family: 'Roboto', sans-serif;
                background: radial-gradient(circle at top left, rgba(15,23,42,.95), rgba(2,6,23,1)) fixed,
                            linear-gradient(180deg, rgba(2,6,23,.92) 0%, rgba(15,23,42,.88) 35%, #0f172a 100%);
                color: #e2e8f0;
              }
              [data-sb-gallery]{position:relative; overflow:hidden;}
              .sb-hero{
                position:relative;
                border-radius:32px;
                overflow:hidden;
                background:linear-gradient(140deg,rgba(12,18,34,.85),rgba(12,18,34,.55)),
                           url('../../../public/images/backgrounds/107619.jpg') center/cover;
                min-height:420px;
                box-shadow:0 32px 120px -48px rgba(2,6,23,.85);
              }
              .sb-hero::after{
                content:"";
                position:absolute; inset:0;
                background:radial-gradient(circle at 20% 20%,rgba(56,189,248,.35),transparent 45%),
                            radial-gradient(circle at 80% 30%,rgba(129,140,248,.3),transparent 45%),
                            linear-gradient(120deg,rgba(15,118,230,.18),rgba(168,85,247,.18));
                mix-blend-mode:screen;
                pointer-events:none;
              }
              .sb-hero__content{position:relative; z-index:1; padding:4.5rem 3rem; max-width:680px;}
              .sb-hero__tag{
                display:inline-flex; align-items:center; gap:.6rem;
                padding:.45rem .9rem; border-radius:9999px; font-size:.75rem; letter-spacing:.1em;
                background:rgba(56,189,248,.12); text-transform:uppercase; color:#bae6fd; font-weight:600;
              }
              .sb-hero__headline{
                margin-top:1.5rem;
                font-size:clamp(2.5rem,4vw,3.6rem);
                line-height:1.1;
                color:#f8fafc;
                text-shadow:0 18px 46px rgba(0,0,0,.4);
              }
              .sb-hero__lead{
                margin-top:1.3rem; max-width:56ch; color:#cbd5f5; font-size:1.05rem; line-height:1.65;
              }
              .sb-hero__actions{margin-top:2.4rem; display:flex; flex-wrap:wrap; gap:1rem;}
              .btn-primary{
                display:inline-flex; align-items:center; gap:.6rem; padding:.75rem 1.6rem; border-radius:9999px;
                font-weight:600; background:linear-gradient(120deg,#38bdf8,#a855f7); color:#0f172a;
                box-shadow:0 35px 80px -40px rgba(56,189,248,.8);
              }
              .btn-primary:hover{filter:brightness(1.08); transform:translateY(-1px);}
              .btn-ghost{
                display:inline-flex; align-items:center; gap:.75rem; padding:.75rem 1.5rem; border-radius:9999px;
                font-weight:600; color:#e2e8f0; border:1px solid rgba(148,163,184,.25); background:rgba(15,23,42,.35);
                backdrop-filter:blur(8px);
              }
              .btn-ghost:hover{border-color:rgba(148,163,184,.45); color:#f8fafc;}

              .sb-curation{
                margin-top:4.5rem;
                padding:3rem;
                border-radius:28px;
                background:rgba(15,23,42,.78);
                box-shadow:0 32px 90px -48px rgba(8,47,73,.85);
                backdrop-filter:blur(24px);
              }
              .sb-curation__header{display:flex; flex-wrap:wrap; gap:1.5rem; align-items:center; justify-content:space-between; margin-bottom:2.4rem;}
              .sb-curation__title{font-size:1.9rem; font-weight:700; color:#f8fafc;}
              .sb-controls{display:flex; flex-wrap:wrap; gap:1rem;}
              .sb-controls input[type="search"], .sb-controls select{
                border-radius:9999px; border:1px solid rgba(148,163,184,.28);
                background:rgba(2,6,23,.55); color:#e2e8f0;
                padding:.65rem 1.1rem;
                min-width:240px;
              }
              .sb-controls input::placeholder{color:#94a3b8;}
              .sb-controls input:focus, .sb-controls select:focus{
                outline:none; border-color:rgba(56,189,248,.65); box-shadow:0 0 0 3px rgba(56,189,248,.18);
              }

              .sb-album-card{
                border-radius:26px;
                background:rgba(12,22,38,.9);
                border:1px solid rgba(148,163,184,.25);
                box-shadow:0 28px 80px -48px rgba(2,6,23,.75);
                overflow:hidden;
              }
              .sb-album-card header{padding:2rem 2.4rem 1.2rem 2.4rem; display:flex; flex-wrap:wrap; gap:1.5rem; align-items:flex-end; justify-content:space-between;}
              .sb-album-title{
                display:flex; align-items:center; gap:1rem; color:#f8fafc; font-size:1.6rem; font-weight:700;
              }
              .sb-album-thumb{
                width:62px; height:62px; border-radius:18px; overflow:hidden; border:1px solid rgba(148,163,184,.3);
                box-shadow:0 18px 40px -24px rgba(8,47,73,.8);
                flex-shrink:0;
              }
              .sb-album-meta{font-size:.85rem; color:#cbd5f5;}
              .sb-toggle{
                display:inline-flex; align-items:center; gap:.5rem; padding:.55rem 1.2rem; border-radius:9999px;
                background:rgba(56,189,248,.12); color:#38bdf8; font-weight:600; border:1px solid rgba(56,189,248,.3);
              }

              .sb-masonry{column-count:1; column-gap:1.2rem; padding:0 2rem 2.4rem;}
              @media (min-width:640px){ .sb-masonry{column-count:2;} }
              @media (min-width:960px){ .sb-masonry{column-count:3;} }
              @media (min-width:1280px){ .sb-masonry{column-count:4;} }
              .sb-shot{position:relative; margin-bottom:1.2rem; overflow:hidden; border-radius:22px; border:1px solid rgba(148,163,184,.22);
                background:rgba(2,6,23,.5);
              }
              .sb-shot img{display:block; width:100%; height:auto; object-fit:cover; transition:transform .6s ease, filter .6s ease;}
              .sb-shot::after{content:""; position:absolute; inset:0; background:linear-gradient(180deg,transparent 12%,rgba(15,23,42,.35) 100%);
                opacity:0; transition:opacity .35s ease;}
              .sb-shot:hover img{transform:scale(1.04); filter:contrast(1.1) saturate(1.05);}
              .sb-shot:hover::after{opacity:1;}
              .sb-shot button{position:absolute; inset:0; border:none; background:transparent; cursor:zoom-in;}

              .sb-narrative{margin-top:5rem; display:grid; gap:2.8rem; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); align-items:center;}
              .sb-narrative__img{
                position:relative; border-radius:30px; overflow:hidden; border:1px solid rgba(148,163,184,.28);
                box-shadow:0 38px 120px -48px rgba(15,23,42,.8);
                min-height:320px;
                background:linear-gradient(130deg,rgba(6,182,212,.3),rgba(129,140,248,.25));
              }
              .sb-narrative__img::after{
                content:""; position:absolute; inset:0;
                background:url('../../../public/images/dichvu/anhcuoi.jpg') center/cover;
                mix-blend-mode:luminosity; opacity:.55;
              }
              .sb-narrative__content{
                padding:2.4rem 2.6rem;
                border-radius:28px;
                background:rgba(255,255,255,.88);
                color:#0f172a;
                box-shadow:0 30px 80px -48px rgba(15,23,42,.38);
              }
              .sb-narrative__content h2{font-size:2.1rem; font-weight:700; color:#0f172a;}
              .sb-narrative__content p{margin-top:1.2rem; color:#334155; line-height:1.7;}
              .sb-narrative__list{margin-top:1.6rem; display:grid; gap:1rem;}
              .sb-narrative__list li{display:flex; gap:.8rem; align-items:flex-start; color:#1f2937; font-weight:500;}
              .sb-narrative__list i{color:#0ea5e9; margin-top:.25rem; font-size:1rem;}

              .sb-testimonial{
                margin-top:4rem;
                border-radius:28px;
                padding:3rem;
                background:linear-gradient(145deg,rgba(15,23,42,.94),rgba(30,64,175,.82));
                border:1px solid rgba(148,163,184,.35);
                box-shadow:0 32px 92px -48px rgba(15,23,42,.9);
                color:#f8fafc;
              }
              .sb-testimonial blockquote{font-size:1.25rem; line-height:1.85; color:inherit; position:relative; padding-left:3.2rem;}
              .sb-testimonial blockquote::before{
                content:"\201C"; position:absolute; left:0; top:-.6rem; font-size:4.4rem; color:rgba(224,242,254,.45);
              }
              .sb-testimonial footer{margin-top:1.5rem; color:#e0f2fe; font-size:.95rem; font-weight:500;}

              .sb-cta{
                margin-top:4.5rem;
                border-radius:32px;
                background:linear-gradient(120deg,rgba(255,255,255,.98),rgba(241,245,249,.95));
                padding:3.2rem;
                border:1px solid rgba(148,163,184,.3);
                display:flex;
                flex-direction:column;
                gap:1.6rem;
                box-shadow:0 36px 90px -46px rgba(15,23,42,.35);
                color:#0f172a;
              }
              .sb-cta h3{font-size:2rem; font-weight:700; color:#0f172a;}
              .sb-cta p{color:#1f2937; max-width:60ch;}
              .sb-cta .btn-primary{color:#0f172a;}
              .sb-cta .btn-ghost{background:#1f2937; color:#f8fafc; border-color:#1f2937; box-shadow:0 18px 44px -24px rgba(15,23,42,.4);}
              .sb-cta .btn-ghost:hover{color:#fff; filter:brightness(1.05);}

              .sb-lightbox{backdrop-filter:blur(18px);}
              .sb-lightbox img{box-shadow:0 45px 120px -60px rgba(2,6,23,.9); border-radius:20px; background:rgba(15,23,42,1);}

              #albums-empty{color:#cbd5f5;}

              @media (max-width:640px){
                .sb-hero__content{padding:3.4rem 2.1rem;}
                .sb-curation{padding:2rem;}
                .sb-album-card header{padding:1.6rem 1.8rem 1rem;}
                .sb-masonry{padding:0 1.5rem 2rem;}
                .sb-narrative__content{padding:1.8rem 1.6rem;}
              }

              @media (prefers-reduced-motion: reduce){
                *{transition:none!important;animation:none!important;}
              }
            </style>
          </head>
          <body class="sb-gallery-page min-h-screen">

          <section data-sb-gallery role="region" aria-label="Bộ sưu tập Stygian Blue" class="relative max-w-7xl mx-auto px-6 py-10">
            <div class="sb-hero">
              <div class="sb-hero__content">
                <span class="sb-hero__tag"><i class="fas fa-aperture"></i> Fine-art chronicle</span>
                <h1 class="sb-hero__headline">Những khoảnh khắc Stygian Blue lưu giữ bằng ánh sáng và cảm xúc.</h1>
                <p class="sb-hero__lead">
                  Mỗi album là một câu chuyện được kể bằng bố cục, ánh sáng và nhịp thở của nhân vật.
                  Cùng khám phá những bộ sưu tập mới nhất, nơi mọi chi tiết được chăm chút để giữ trọn chất lượng ảnh.
                </p>
                <div class="sb-hero__actions">
                  <a href="../views/lienhe.php" class="btn-primary" aria-label="Đặt hẹn sáng tạo">
                    <i class="fas fa-calendar-check"></i>
                    Đặt hẹn sáng tạo
                  </a>
                  <a href="#sb-curation" class="btn-ghost" aria-label="Xem các bộ sưu tập">
                    <i class="fas fa-images"></i>
                    Xem bộ sưu tập
                  </a>
                </div>
              </div>
            </div>

            <section id="sb-curation" class="sb-curation mt-12">
              <div class="sb-curation__header">
                <div>
                  <p class="uppercase tracking-[0.28em] text-xs text-sky-200/70">Selected Galleries</p>
                  <h2 class="sb-curation__title">Phòng trưng bày Stygian Blue</h2>
                  <p class="text-sm text-sky-100/60 max-w-xl mt-2">
                    Chọn một album để đắm chìm trong ánh sáng và sắc độ tinh chỉnh. Bạn có thể tìm nhanh hoặc sắp xếp theo tên.
                  </p>
                </div>
                <div class="sb-controls">
                  <label class="relative">
                    <input id="gal-search" type="search" placeholder="Tìm theo tên album" aria-label="Tìm kiếm album">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sky-200/60"><i class="fas fa-search"></i></span>
                  </label>
                  <label>
                    <select id="gal-sort" aria-label="Sắp xếp album">
                      <option value="recent">Mới nhất</option>
                      <option value="az">A - Z</option>
                    </select>
                  </label>
                </div>
              </div>

              <div id="albums-wrap" class="space-y-10" aria-live="polite"></div>

              <div class="mt-6 text-center">
                <button id="gal-show-more" class="btn-ghost px-8" title="Xem thêm album">Xem thêm album</button>
              </div>

              <div id="albums-empty" class="hidden mt-8 text-center">
                <span class="inline-flex items-center gap-3 px-4 py-3 rounded-full bg-white/5 border border-sky-200/20">
                  <i class="far fa-circle-xmark"></i> Chưa có album phù hợp.
                </span>
              </div>
            </section>

            <section class="sb-narrative mt-16">
              <div class="sb-narrative__img" aria-hidden="true"></div>
              <div class="sb-narrative__content">
                <h2>Tập trung vào chất lượng hình ảnh và chiều sâu cảm xúc</h2>
                <p>
                  Từ việc lựa chọn ống kính, dựng ánh sáng studio cho tới hậu kỳ tinh xảo, đội ngũ Stygian Blue luôn hướng tới trải nghiệm thị giác trọn vẹn.
                  Các bộ sưu tập được tuyển chọn kỹ càng để kể lại một hành trình tình cảm, thời trang hay chân dung theo phong cách fine-art riêng biệt.
                </p>
                <ul class="sb-narrative__list">
                  <li><i class="fas fa-star"></i> Quy trình chọn ảnh thủ công đảm bảo mỗi tấm đều là phiên bản tốt nhất.</li>
                  <li><i class="fas fa-flask"></i> Màu sắc được calibrate trên hệ thống màn hình chuyên dụng, giữ đúng mood.</li>
                  <li><i class="fas fa-heart"></i> Câu chuyện được kể lại bằng cảm xúc thật của nhân vật, không sa đà quảng cáo.</li>
                </ul>
              </div>
            </section>

            <section class="sb-testimonial mt-12" aria-label="Cảm nhận khách hàng">
              <blockquote>
                “Các album của Stygian Blue không chỉ là ảnh đẹp, mà còn là những mảng ký ức giàu cảm xúc. Ánh sáng, bố cục và sự tĩnh lặng tinh tế khiến tôi muốn ngắm lại nhiều lần.”
              </blockquote>
              <footer>— Trọng Nghĩa · Nhà phát triển, cha đẻ của dự án Stygian Blue Studio</footer>
            </section>

            <section class="sb-cta mt-14" aria-label="Đặt hẹn sáng tạo">
              <h3>Sẵn sàng tạo nên bộ sưu tập của bạn?</h3>
              <p>
                Hãy để đội ngũ Stygian Blue đồng hành từ concept, trang phục đến hậu kỳ in ấn. Chúng tôi luôn dành thời gian lắng nghe câu chuyện để viết nên ký ức bằng hình ảnh.
              </p>
              <div class="flex flex-wrap gap-3">
                <a href="../views/lienhe.php" class="btn-primary">
                  <i class="fas fa-pen-nib"></i>
                  Đặt lịch tư vấn
                </a>
                <a href="tel:0901234567" class="btn-ghost">
                  <i class="fas fa-phone"></i>
                  0901 234 567
                </a>
              </div>
            </section>

            <div class="sb-lightbox fixed inset-0 hidden items-center justify-center text-center bg-black/80 z-50" aria-hidden="true" role="dialog" aria-modal="true">
              <button class="absolute top-6 right-6 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 border border-white/30 flex items-center justify-center text-white"
                data-sb-lb-close aria-label="Đóng">
                <i class="fas fa-times"></i>
              </button>
              <div class="relative inline-flex flex-col items-center justify-center max-w-[92vw] max-h-[88vh] p-3">
                <img data-sb-lb-img src="" alt="" class="max-w-full max-h-[80vh] object-contain">
                <button class="absolute left-0 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white border border-white/25 flex items-center justify-center"
                  data-sb-lb-prev aria-label="Ảnh trước">
                  <i class="fas fa-chevron-left"></i>
                </button>
                <button class="absolute right-0 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white border border-white/25 flex items-center justify-center"
                  data-sb-lb-next aria-label="Ảnh sau">
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
  </section>

<script>
  window.ALBUMS_DATA = <?php echo json_encode($albumsPhp, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  (function(){
    const ALBUMS_PER_PAGE = <?php echo (int)$ALBUMS_PER_PAGE; ?>;
    const IMAGES_LIMIT    = <?php echo (int)$IMAGES_LIMIT; ?>;

    const $  = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
    const escapeHtml = (str = '') => str
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

    const wrap        = $('#albums-wrap');
    const empty       = $('#albums-empty');
    const searchInput = $('#gal-search');
    const sortSelect  = $('#gal-sort');
    const showMoreBtn = $('#gal-show-more');

    const ALL = Array.isArray(window.ALBUMS_DATA) ? window.ALBUMS_DATA : [];
    ALL.forEach(a => { a._mtime = +new Date(a.mtime || Date.now()); });

    let albums = ALL.slice();
    let renderedCount = 0;

    function albumHTML(album, idx){
      const count = album.photos.length;
      const first = album.photos[0] || null;
      const coverThumb = first ? (first.thumb || first.src) : '';
      const coverFull  = first ? first.src : '';
      const displayName = escapeHtml(album.name || 'Album');
      const updated = new Date(album._mtime).toLocaleString('vi-VN');

      const items = album.photos.map(photo => {
        const thumb = photo.thumb || photo.src;
        const full  = photo.src;
        return `
        <figure class="sb-shot" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' '){this.querySelector('button[data-sb-lightbox-open]').click();}">
          <img src="${thumb}" alt="" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='${full}'" />
          <button type="button" aria-label="Xem ảnh lớn" data-sb-lightbox-open data-src="${full}"></button>
        </figure>`;
      }).join('');

      const totalText = album.total > IMAGES_LIMIT
        ? `<span class="sb-album-meta">Đang hiển thị ${IMAGES_LIMIT}/${album.total} ảnh</span>`
        : '';

      return `
      <section id="album-${idx}" class="sb-album-card">
        <header>
          <div class="flex items-start gap-4">
            <div class="sb-album-thumb">
              ${coverThumb
                ? `<img src="${coverThumb}" alt="" loading="lazy" onerror="this.onerror=null;this.src='${coverFull}'" />`
                : '<div class="w-full h-full flex items-center justify-center bg-slate-800 text-slate-400"><i class="fas fa-images"></i></div>'}
            </div>
            <div>
              <div class="sb-album-title">${displayName}<span class="text-sm font-medium text-cyan-200">${count} ảnh</span></div>
              <p class="sb-album-meta">Cập nhật: ${updated}</p>
              ${totalText}
            </div>
          </div>
          <button class="sb-toggle" type="button" aria-expanded="true">Thu gọn</button>
        </header>
        <div class="sb-masonry" data-sb-album-grid>
          ${items}
        </div>
      </section>`;
    }

    function renderNextPage(){
      const remain = albums.slice(renderedCount);
      const page = remain.slice(0, ALBUMS_PER_PAGE);
      if (!page.length) {
        return false;
      }
      const frag = document.createElement('div');
      frag.innerHTML = page.map((a, i) => albumHTML(a, renderedCount + i)).join('');
      wrap.appendChild(frag);
      renderedCount += page.length;

      if (renderedCount >= albums.length) {
        showMoreBtn.classList.add('hidden');
      } else {
        showMoreBtn.classList.remove('hidden');
      }

      bindAlbumToggles();
      bindLightbox();
      return true;
    }

    function rerenderAll(){
      wrap.innerHTML = '';
      renderedCount = 0;
      if (!albums.length) {
        empty.classList.remove('hidden');
        showMoreBtn.classList.add('hidden');
        return;
      }
      empty.classList.add('hidden');
      renderNextPage();
    }

    function applySearchSort(){
      const keyword = (searchInput?.value || '').trim().toLowerCase();
      const mode = (sortSelect?.value || 'recent');

      albums = ALL.filter(album => !keyword || (album.name || '').toLowerCase().includes(keyword));
      if (mode === 'recent') {
        albums.sort((a, b) => b._mtime - a._mtime);
      } else if (mode === 'az') {
        albums.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'vi', { sensitivity: 'base' }));
      }
      rerenderAll();
    }

    let typingTimer = null;
    searchInput?.addEventListener('input', () => {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(applySearchSort, 160);
    });
    sortSelect?.addEventListener('change', applySearchSort);
    showMoreBtn?.addEventListener('click', () => renderNextPage());

    function bindAlbumToggles(){
      $$('.sb-album-card').forEach(section => {
        const toggle = section.querySelector('.sb-toggle');
        const grid = section.querySelector('[data-sb-album-grid]');
        if (!toggle || !grid) {
          return;
        }
        toggle.onclick = () => {
          const hidden = grid.classList.toggle('hidden');
          toggle.textContent = hidden ? 'Mở rộng' : 'Thu gọn';
          toggle.setAttribute('aria-expanded', String(!hidden));
        };
      });
    }

    const lbWrap  = document.querySelector('.sb-lightbox');
    const lbImg   = document.querySelector('[data-sb-lb-img]');
    const prevBtn = document.querySelector('[data-sb-lb-prev]');
    const nextBtn = document.querySelector('[data-sb-lb-next]');
    const closeBtn= document.querySelector('[data-sb-lb-close]');
    let currentList = [];
    let currentIdx  = -1;
    let lastFocus   = null;
    let touchX      = null;

    function showAt(index){
      if (!currentList.length) {
        return;
      }
      currentIdx = (index + currentList.length) % currentList.length;
      const src = currentList[currentIdx];
      lbImg.src = src;
      const preload = new Image();
      preload.src = currentList[(currentIdx + 1) % currentList.length];
    }

    function openLightbox(src, list){
      currentList = list && list.length ? list.slice() : [src];
      const idx = currentList.indexOf(src);
      currentIdx = idx >= 0 ? idx : 0;
      showAt(currentIdx);

      lbWrap.classList.remove('hidden');
      lbWrap.classList.add('flex');
      lbWrap.setAttribute('aria-hidden', 'false');
      lastFocus = document.activeElement;
      document.body.style.overflow = 'hidden';
      closeBtn?.focus();
    }

    function closeLightbox(){
      lbWrap.classList.add('hidden');
      lbWrap.classList.remove('flex');
      lbWrap.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (lastFocus && typeof lastFocus.focus === 'function') {
        lastFocus.focus();
      }
    }

    function navigate(delta){
      showAt(currentIdx + delta);
    }

    function bindLightbox(){
      $$('.sb-album-card').forEach(section => {
        const triggers = $$('[data-sb-lightbox-open]', section);
        const list = triggers.map(node => node.dataset.src);
        triggers.forEach(btn => {
          btn.addEventListener('click', () => openLightbox(btn.dataset.src, list), { passive: true });
        });
      });
    }

    prevBtn?.addEventListener('click', () => navigate(-1));
    nextBtn?.addEventListener('click', () => navigate(1));
    closeBtn?.addEventListener('click', closeLightbox);
    lbWrap?.addEventListener('click', event => { if (event.target === lbWrap) closeLightbox(); });
    window.addEventListener('keydown', event => {
      if (!lbWrap.classList.contains('flex')) {
        return;
      }
      if (event.key === 'Escape') closeLightbox();
      if (event.key === 'ArrowRight') navigate(1);
      if (event.key === 'ArrowLeft') navigate(-1);
    });
    lbWrap?.addEventListener('touchstart', event => { touchX = event.touches[0].clientX; }, { passive: true });
    lbWrap?.addEventListener('touchend', event => {
      if (touchX == null) {
        return;
      }
      const dx = event.changedTouches[0].clientX - touchX;
      if (Math.abs(dx) > 40) {
        navigate(dx < 0 ? 1 : -1);
      }
      touchX = null;
    }, { passive: true });

    applySearchSort();
  })();
</script>
</body>
</html>
