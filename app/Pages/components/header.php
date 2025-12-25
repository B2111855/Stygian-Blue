<?php
// navbar.php (v6) — Header for photography studio experience: PHP + TailwindCSS
require_once __DIR__ . '/../../helpers/assets.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Stygian Blue Studio</title>
  <?= sb_tailwind_link_tag(); ?>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../../public/css/loader.css" />
  <script src="../../../public/js/loader_spinner.js" defer></script>
  <style>
    :root{
      --sb-primary:#0f172a;
      --sb-accent:#38bdf8;
      --sb-highlight:#f472b6;
      --sb-slate:#475569;
    }
    body{
      font-family:'Roboto', sans-serif;
      /* background:linear-gradient(180deg,#020617 0%,#0b1220 20%,#f8fafc 68%); */
      color:var(--sb-primary);
    }
    [data-sb-header]{
      background:linear-gradient(135deg,rgba(2,6,23,.95),rgba(15,23,42,.8));
      color:var(--sb-primary);
    }
    [data-sb-header] .sb-topbar{
      max-height:58px;
      overflow:hidden;
      transition:max-height .3s ease, opacity .3s ease, filter .3s ease;
      backdrop-filter:blur(6px);
    }
    [data-sb-header].is-scrolled .sb-topbar{
      max-height:0;
      opacity:0;
      filter:blur(3px);
    }
    #sbh-container{
      transition:background .35s ease, border .35s ease, box-shadow .35s ease, margin .35s ease;
    }
    [data-sb-header].is-scrolled #sbh-container{
      background:rgba(255,255,255,.94);
      border-color:rgba(148,163,184,.35);
    }
    [data-sb-header] .sb-nav-link{
      position:relative;
      display:inline-flex;
      align-items:center;
      gap:0.4rem;
      padding:.45rem 0;
      font-weight:500;
      color:var(--sb-primary);
      letter-spacing:.015em;
      white-space:nowrap;
      transition:color .2s ease, transform .2s ease;
    }
    [data-sb-header] .sb-nav-link i{
      color:#94a3b8;
      transition:color .2s ease;
    }
    [data-sb-header] .sb-nav-link:hover,
    [data-sb-header] .sb-nav-link:focus-visible{
      color:var(--sb-accent);
      transform:translateY(-1px);
    }
    [data-sb-header] .sb-nav-link:hover i,
    [data-sb-header] .sb-nav-link:focus-visible i{
      color:var(--sb-accent);
    }
    [data-sb-header] .sb-nav-link:after{
      content:"";
      position:absolute;
      left:50%;
      bottom:-10px;
      width:0;
      height:2px;
      border-radius:9999px;
      background:linear-gradient(90deg,#38bdf8,#818cf8,#f472b6);
      transition:width .26s ease,left .26s ease,opacity .26s ease;
      opacity:0;
    }
    [data-sb-header] .sb-nav-link:hover:after,
    [data-sb-header] .sb-nav-link:focus-visible:after,
    [data-sb-header] .sb-nav-link._active-underline:after{
      width:100%;
      left:0;
      opacity:1;
    }
    [data-sb-header] .sb-flyout{
      box-shadow:0 36px 70px -40px rgba(15,23,42,.65);
      border:1px solid rgba(226,232,240,.7);
    }
    [data-sb-header] .sb-primary-btn{
      background:linear-gradient(135deg,#38bdf8,#818cf8,#f472b6);
      color:#fff;
      border-radius:9999px;
      font-weight:600;
      padding:.55rem 1.3rem;
      display:inline-flex;
      align-items:center;
      gap:.45rem;
      white-space:nowrap;
      box-shadow:0 24px 52px -32px rgba(79,70,229,.7);
      transition:transform .2s ease, box-shadow .2s ease, filter .2s ease;
    }
    [data-sb-header] .sb-primary-btn:hover{
      transform:translateY(-2px);
      filter:brightness(1.08);
      box-shadow:0 28px 56px -28px rgba(59,130,246,.72);
    }
    [data-sb-header] .sb-chip{
      border-radius:9999px;
      padding:.35rem .75rem;
      background:rgba(148,163,184,.16);
      color:var(--sb-primary);
      font-weight:500;
      letter-spacing:.05em;
      text-transform:uppercase;
      font-size:.64rem;
    }
    [data-sb-header] .sb-mobile-cta{
      background:linear-gradient(140deg,rgba(56,189,248,.95),rgba(129,140,248,.92));
      border-radius:22px;
      color:#fff;
      box-shadow:0 28px 58px -28px rgba(30,64,175,.65);
    }
    [data-sb-header] .sb-mobile-cta a{
      border-radius:9999px;
      background:rgba(255,255,255,.18);
      padding:.55rem 1.2rem;
      font-weight:600;
    }
    [data-sb-header] .sb-pill-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      white-space:nowrap;
    }
    [data-sb-header] .sb-profile-menu{
      padding:.35rem 0;
      background:#fff;
    }
    [data-sb-header] .sb-menu-item{
      display:flex;
      align-items:flex-start;
      gap:.75rem;
      padding:.65rem 1rem;
      border-radius:.85rem;
      color:var(--sb-primary);
      transition:background .2s ease, transform .2s ease;
    }
    [data-sb-header] .sb-menu-item:hover,
    [data-sb-header] .sb-menu-item:focus-visible{
      background:rgba(148,163,184,.12);
      transform:translateX(2px);
    }
    [data-sb-header] .sb-menu-item:focus-visible{
      outline:2px solid rgba(129,140,248,.35);
      outline-offset:2px;
    }
    [data-sb-header] .sb-menu-item--alert{
      background:linear-gradient(120deg,rgba(56,189,248,.12),rgba(236,72,153,.08));
    }
    [data-sb-header] .sb-menu-item--danger{
      color:#dc2626;
    }
    [data-sb-header] .sb-menu-item--danger:hover,
    [data-sb-header] .sb-menu-item--danger:focus-visible{
      background:rgba(248,113,113,.16);
      transform:translateX(2px);
    }
    [data-sb-header] .sb-menu-item--danger .sb-menu-sub{
      color:#b91c1c;
    }
    [data-sb-header] .sb-menu-icon{
      width:34px;
      height:34px;
      border-radius:50%;
      display:grid;
      place-items:center;
      background:rgba(148,163,184,.16);
      color:#475569;
      flex-shrink:0;
      margin-top:2px;
    }
    [data-sb-header] .sb-menu-icon--profile{
      background:rgba(129,140,248,.15);
      color:#4f46e5;
    }
    [data-sb-header] .sb-menu-icon--schedule{
      background:rgba(56,189,248,.16);
      color:#0284c7;
    }
    [data-sb-header] .sb-menu-item--alert .sb-menu-icon--schedule{
      background:rgba(56,189,248,.22);
      color:#0369a1;
    }
    [data-sb-header] .sb-menu-icon--billing{
      background:rgba(251,191,36,.2);
      color:#d97706;
    }
    [data-sb-header] .sb-menu-icon--settings{
      background:rgba(129,140,248,.14);
      color:#4338ca;
    }
    [data-sb-header] .sb-menu-icon--logout{
      background:rgba(248,113,113,.16);
      color:#dc2626;
    }
    [data-sb-header] .sb-menu-text{
      display:flex;
      flex-direction:column;
      gap:.12rem;
      min-width:0;
    }
    [data-sb-header] .sb-menu-title{
      font-weight:600;
      font-size:.95rem;
      letter-spacing:.01em;
    }
    [data-sb-header] .sb-menu-sub{
      font-size:.78rem;
      color:#64748b;
      line-height:1.35;
      max-width:230px;
      white-space:normal;
    }
    [data-sb-header] .sb-badge{
      margin-left:auto;
      align-self:center;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-width:28px;
      height:24px;
      padding:0 .55rem;
      border-radius:9999px;
      font-size:.72rem;
      font-weight:600;
      color:#fff;
      background:linear-gradient(135deg,#0ea5e9,#6366f1,#ec4899);
      box-shadow:0 10px 24px -16px rgba(59,130,246,.65);
    }
    [data-sb-header] .sb-menu-divider{
      height:1px;
      background:rgba(226,232,240,.9);
      margin:.35rem 1rem;
    }
    [data-sb-header] .sb-menu-note{
      font-size:.75rem;
      color:#0f172a;
      background:rgba(56,189,248,.08);
      border-left:3px solid rgba(56,189,248,.45);
      margin:.25rem 1rem .6rem;
      padding:.45rem .6rem;
      border-radius:.6rem;
      line-height:1.35;
    }
    #mobileOverlay nav::-webkit-scrollbar{
      width:8px;
    }
    #mobileOverlay nav::-webkit-scrollbar-thumb{
      background:#cbd5f5;
      border-radius:9999px;
    }
    @media (prefers-reduced-motion:reduce){
      [data-sb-header] *{
        transition:none!important;
        animation:none!important;
      }
    }

    /* --- Reusable Hero Overlay for detail pages --- */
    .sb-hero{ position:relative; isolation:isolate; }
    .sb-hero::after{
      content:""; position:absolute; inset:0; z-index:0; pointer-events:none;
      background:linear-gradient(
        to bottom,
        rgba(0,0,0,calc(var(--scrim-alpha, .42) + .10)) 0%,
        rgba(0,0,0,var(--scrim-alpha, .42)) 45%,
        rgba(0,0,0,calc(var(--scrim-alpha, .42) - .12)) 100%
      );
    }
    .sb-hero > *{ position:relative; z-index:1; }
    .sb-hero .hero-title{ color:#fff; text-shadow:0 1px 2px rgba(0,0,0,.55), 0 8px 20px rgba(0,0,0,.35); }
    .sb-hero .hero-subtitle{ color:rgba(255,255,255,.92); text-shadow:0 1px 1px rgba(0,0,0,.45); }
    .sb-hero [data-crumbs]{ color:rgba(255,255,255,.85); text-shadow:0 1px 1px rgba(0,0,0,.45); }
    .sb-hero[data-luma="light"]{ --scrim-alpha:.50; }
    .sb-hero[data-luma="dark"]{ --scrim-alpha:.28; }
    /* Optional: glass pill utility */
    .glass-pill{
      -webkit-backdrop-filter: blur(8px) saturate(150%);
      backdrop-filter: blur(8px) saturate(150%);
      background: rgba(255,255,255,.66);
      border: 1px solid rgba(255,255,255,.28);
      box-shadow: 0 8px 26px rgba(0,0,0,.15);
      color: #101828;
      border-radius: 999px;
      padding: 8px 14px;
    }
  </style>
  <script src="../../../public/js/hero_scrim.js" defer></script>
</head>

<body class="bg-slate-50/60 pt-28">
  <div class="sb-loader" data-sb-loader role="status" aria-live="polite" aria-label="Đang tải nội dung">
    <div class="sb-loader__spinner" aria-hidden="true">
      <span></span>
      <span></span>
      <span></span>
      <span></span>
    </div>
  </div>
  <!-- Header -->
  <header data-sb-header class="fixed top-0 inset-x-0 z-50 text-[color:var(--sb-primary)]">
    <!-- Gradient top bar -->
    <div class="h-1.5 bg-[linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa,#f472b6,#0ea5e9)]"></div>

    <!-- Info bar -->
    <div class="sb-topbar hidden md:block border-b border-white/10 bg-white/5">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between text-[13px] text-white/85">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
          <span class="flex items-center gap-2">
            <i class="fas fa-phone text-xs text-cyan-300"></i>
            <span>Hotline đặt lịch: <strong class="font-semibold text-white">0907814560</strong></span>
          </span>
          <span class="hidden xl:flex items-center gap-2">
            <i class="fas fa-location-dot text-xs text-cyan-300"></i>
            <span>Studio: 12B2 - KDC 30 - Nguyễn Văn Linh - Cần Thơ </span>
          </span>
        </div>
        <div class="flex items-center gap-4">
          <span class="hidden lg:flex items-center gap-2">
            <i class="fas fa-clock text-xs text-cyan-300"></i>
            <span>Đón khách: 08:00 - 21:00</span>
          </span>
          <div class="flex items-center gap-3 text-white text-sm">
            <a href="https://www.facebook.com/" target="_blank" rel="noopener" class="hover:text-cyan-300 transition" aria-label="Facebook">
              <i class="fab fa-facebook-f"></i>
            </a>
            <a href="https://www.instagram.com/" target="_blank" rel="noopener" class="hover:text-cyan-300 transition" aria-label="Instagram">
              <i class="fab fa-instagram"></i>
            </a>
            <a href="https://www.tiktok.com/" target="_blank" rel="noopener" class="hover:text-cyan-300 transition" aria-label="TikTok">
              <i class="fab fa-tiktok"></i>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Main header -->
    <div
      id="sbh-container"
      class="relative border border-white/20 bg-white/80 supports-[backdrop-filter]:backdrop-blur-lg shadow-[0_20px_48px_-32px_rgba(15,23,42,.6)] transition-shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-6">
        <!-- Logo -->
        <a href="./home.php" class="group flex items-center gap-3 shrink-0">
          <img src="../../../public/images/StygianBlueLogo.png" alt="Stygian Blue" class="h-11 w-auto transition-transform duration-300 group-hover:scale-105" />
          <span class="hidden sm:flex flex-col leading-tight">
            <span class="text-[color:var(--sb-primary)] font-bold text-lg tracking-wide">Stygian Blue Studio</span>
            <span class="text-xs uppercase tracking-[0.32em] text-slate-400">Fine Art Photography</span>
          </span>
        </a>

        <!-- Desktop nav -->
        <nav class="hidden md:flex items-center gap-7 text-[15px]" aria-label="Điều hướng chính" role="menubar">
          <a role="menuitem" href="../views/home.php" class="sb-nav-link hover:text-indigo-700 transition">
            <i class="fas fa-house text-xs"></i> Trang Chủ
          </a>

          <!-- Dịch vụ -->
          <div class="relative group/menu" data-sb-dichvu>
            <div class="flex items-center gap-2">
              <a role="menuitem" href="../views/dichvu.php" class="sb-nav-link inline-flex items-center gap-2 _active-underline">
                <i class="fas fa-camera-retro text-xs"></i> Dịch Vụ
              </a>
              <button
                type="button"
                aria-expanded="false"
                aria-haspopup="true"
                aria-controls="dv-menu"
                id="btnDvToggle"
                class="text-gray-600 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded px-1"
                title="Mở menu Dịch vụ">
                <i class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
              </button>
            </div>

            <div class="absolute left-1/2 -translate-x-1/2 top-full h-3 w-[720px]"></div>

            <div
              id="dv-menu"
              class="sb-flyout absolute left-1/2 -translate-x-1/2 mt-3 w-[720px] bg-white/95 rounded-2xl p-6 z-[60]
                     opacity-0 invisible translate-y-1 transition-all duration-200
                     group-hover/menu:opacity-100 group-hover/menu:visible group-hover/menu:translate-y-2"
              role="menu"
              aria-label="Menu Dịch vụ"
              tabindex="-1">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                  <span class="sb-chip">Studio vibes</span>
                  <span class="sb-chip hidden md:inline-flex">Ekip sáng tạo</span>
                </div>
                <a href="../views/lienhe.php" class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:underline">
                  <i class="fas fa-calendar-check"></i> Đặt lịch tư vấn
                </a>
              </div>
              <div class="grid grid-cols-2 gap-6">
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <h4 class="text-[color:var(--sb-primary)] font-bold">Dịch vụ lẻ</h4>
                    <a href="../views/dichvu.php" class="text-sm text-indigo-700 hover:underline">Xem tất cả</a>
                  </div>
                  <ul class="max-h-64 overflow-auto pr-1 space-y-1">
                    <?php if (!empty($menuServices) && $menuServices->num_rows): ?>
                      <?php $menuServices->data_seek(0); while ($svc = $menuServices->fetch_assoc()): ?>
                        <li>
                          <a href="../views/chitiet.php?id=<?= (int)$svc['ID_DV'] ?>"
                             class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-100 focus:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                            <i class="fas fa-camera text-gray-500"></i>
                            <span class="truncate"><?= htmlspecialchars($svc['TEN_DV']) ?></span>
                          </a>
                        </li>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <li class="text-gray-500 italic px-2">Chưa có dịch vụ</li>
                    <?php endif; ?>
                  </ul>
                </div>

                <div>
                  <div class="flex items-center justify-between mb-2">
                    <h4 class="text-[color:var(--sb-primary)] font-bold">Combo / Gói</h4>
                    <a href="../views/goi.php" class="text-sm text-indigo-700 hover:underline">Xem tất cả</a>
                  </div>
                  <ul class="max-h-64 overflow-auto pr-1 space-y-1">
                    <?php if (!empty($menuCombos) && $menuCombos->num_rows): ?>
                      <?php $menuCombos->data_seek(0); while ($pkg = $menuCombos->fetch_assoc()): ?>
                        <li>
                          <a href="../views/goi_chitiet.php?id_goi=<?= (int)$pkg['ID_GOI'] ?>"
                             class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-100 focus:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                            <i class="fas fa-gift text-gray-500"></i>
                            <span class="truncate"><?= htmlspecialchars($pkg['TEN_GOI']) ?></span>
                          </a>
                        </li>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <li class="text-gray-500 italic px-2">Chưa có gói</li>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>

              <div class="mt-4 flex items-center justify-between gap-3">
                <p class="text-sm text-slate-500">Khám phá concept độc quyền theo mùa &nbsp;★</p>
                <div class="flex items-center gap-2">
                  <a href="../views/khuyenmai.php" class="text-sm px-3 py-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100">
                    <i class="fas fa-tags mr-1"></i> Khuyến mãi
                  </a>
                  <a href="../views/lienhe.php" class="text-sm px-3 py-1.5 rounded-lg bg-[linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa)] text-white shadow hover:brightness-110">
                    <i class="fas fa-calendar-check mr-1"></i> Đặt lịch ngay
                  </a>
                </div>
              </div>
            </div>
          </div>

          <a role="menuitem" href="../views/albums.php" class="sb-nav-link transition">
            <i class="fas fa-images text-xs"></i> Phòng Trưng Bày
          </a>
          <a role="menuitem" href="../views/trangphuc.php" class="sb-nav-link transition">
            <i class="fas fa-person-dress text-xs"></i> Thuê trang phục
          </a>
          <a role="menuitem" href="../views/gioithieu.php" class="sb-nav-link transition">
            <i class="fas fa-star text-xs"></i> Giới Thiệu
          </a>
        </nav>

        <!-- Actions -->
        <div class="flex items-center gap-4">
          <a href="../views/lienhe.php" class="sb-primary-btn hidden md:inline-flex">
            <i class="fas fa-bolt"></i> Đặt lịch chụp
          </a>

          <?php if (empty($_SESSION['ID_TK'])): ?>
            <a href="../../../login.php" class="sb-pill-btn px-4 py-2 rounded-full border border-slate-300/80 text-[color:var(--sb-primary)] font-semibold hover:bg-white/80">
              Đăng nhập
            </a>
          <?php else: ?>
            <div class="relative">
              <button
                id="btnProfile"
                class="h-11 w-11 rounded-full bg-white border border-slate-200/70 text-[color:var(--sb-primary)] grid place-items-center hover:ring-2 hover:ring-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-expanded="false" aria-haspopup="true" aria-controls="profileMenu">
                <i class="fas fa-user"></i>
              </button>

              <div
                id="profileMenu"
                class="sb-profile-menu absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-slate-100 opacity-0 invisible translate-y-1 transition-all duration-150"
                role="menu" tabindex="-1">
                <a href="../views/thongtin.php" class="sb-menu-item" role="menuitem">
                  <span class="sb-menu-icon sb-menu-icon--profile"><i class="fas fa-id-card"></i></span>
                  <span class="sb-menu-text">
                    <span class="sb-menu-title">Thông tin tài khoản</span>
                    <span class="sb-menu-sub">Cập nhật hồ sơ, mật khẩu và thông tin liên hệ</span>
                  </span>
                </a>

                <?php $scheduleCountSafe = isset($scheduleUpdateCount) ? (int)$scheduleUpdateCount : 0; ?>
                <a href="xemLichhen.php" class="sb-menu-item<?= $scheduleCountSafe > 0 ? ' sb-menu-item--alert' : '' ?>" role="menuitem">
                  <span class="sb-menu-icon sb-menu-icon--schedule"><i class="fas fa-calendar-check"></i></span>
                  <span class="sb-menu-text">
                    <span class="sb-menu-title">Lịch hẹn & Đơn thuê</span>
                    <span class="sb-menu-sub">
                      <?php if ($scheduleCountSafe > 0): ?>
                        Có <?= $scheduleCountSafe > 99 ? '99+' : $scheduleCountSafe ?> cập nhật lịch hẹn mới trong 7 ngày qua
                      <?php else: ?>
                        Kiểm tra trạng thái và chi tiết lịch chụp đã đặt
                      <?php endif; ?>
                    </span>
                  </span>
                  <?php if ($scheduleCountSafe > 0): ?>
                    <span class="sb-badge" aria-label="Cập nhật lịch hẹn mới">
                      <?= $scheduleCountSafe > 99 ? '99+' : $scheduleCountSafe ?>
                    </span>
                  <?php endif; ?>
                </a>

                <?php if (!empty($scheduleLatestNote) && $scheduleCountSafe > 0): ?>
                  <div class="sb-menu-note">
                    <strong><i class="fas fa-bell mr-1"></i> Cập nhật gần nhất:</strong><br />
                    <?= htmlspecialchars($scheduleLatestNote) ?>
                  </div>
                <?php endif; ?>

                <?php $unpaidCountSafe = isset($unpaidCount) ? (int)$unpaidCount : 0; ?>
                <a href="../views/hoa_don.php" class="sb-menu-item<?= $unpaidCountSafe > 0 ? ' sb-menu-item--alert' : '' ?>" role="menuitem">
                  <span class="sb-menu-icon sb-menu-icon--billing"><i class="fas fa-file-invoice-dollar"></i></span>
                  <span class="sb-menu-text">
                    <span class="sb-menu-title">Hóa đơn &amp; thanh toán</span>
                    <span class="sb-menu-sub">
                      <?php if ($unpaidCountSafe > 0): ?>
                        Có <?= $unpaidCountSafe > 99 ? '99+' : $unpaidCountSafe ?> hóa đơn đang chờ thanh toán
                      <?php else: ?>
                        Theo dõi lịch sử thanh toán và hóa đơn đã hoàn tất
                      <?php endif; ?>
                    </span>
                  </span>
                  <?php if ($unpaidCountSafe > 0): ?>
                    <span class="sb-badge" aria-label="Hóa đơn chưa thanh toán">
                      <?= $unpaidCountSafe > 99 ? '99+' : $unpaidCountSafe ?>
                    </span>
                  <?php endif; ?>
                </a>

                <div class="sb-menu-divider" role="none"></div>

                <a href="#" class="sb-menu-item" role="menuitem">
                  <span class="sb-menu-icon sb-menu-icon--settings"><i class="fas fa-gear"></i></span>
                  <span class="sb-menu-text">
                    <span class="sb-menu-title">Cài đặt</span>
                    <span class="sb-menu-sub">Tuỳ chỉnh thông báo, bảo mật và quyền riêng tư</span>
                  </span>
                </a>
                <a href="../../../logout.php" class="sb-menu-item sb-menu-item--danger" role="menuitem">
                  <span class="sb-menu-icon sb-menu-icon--logout"><i class="fas fa-arrow-right-from-bracket"></i></span>
                  <span class="sb-menu-text">
                    <span class="sb-menu-title">Đăng xuất</span>
                    <span class="sb-menu-sub">Thoát khỏi tài khoản Stygian Blue</span>
                  </span>
                </a>
              </div>
            </div>
          <?php endif; ?>

          <button id="btnMobile"
            class="md:hidden text-[color:var(--sb-primary)] text-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded p-1"
            aria-controls="mobileOverlay" aria-expanded="false" aria-label="Mở menu">
            <i class="fas fa-bars"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile overlay menu -->
    <div id="mobileOverlay" class="md:hidden fixed inset-0 bg-black/70 backdrop-blur-sm hidden z-40">
      <nav class="ml-auto h-full w-[86%] max-w-sm bg-white shadow-2xl p-6 flex flex-col gap-3 overflow-y-auto" aria-label="Menu di động">
        <div class="flex items-center justify-between mb-1">
          <div class="flex items-center gap-2">
            <img src="../../../public/images/StygianBlueLogo.png" alt="Stygian Blue" class="h-9 w-auto" />
            <span class="font-bold text-[color:var(--sb-primary)]">Menu</span>
          </div>
          <button id="btnMobileClose" class="text-2xl p-1 rounded hover:bg-gray-100" aria-label="Đóng menu">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="sb-mobile-cta p-5 mt-1">
          <p class="text-xs uppercase tracking-[0.32em] text-white/70">Stygian Blue Studio</p>
          <p class="mt-2 text-lg font-semibold leading-snug">Lưu giữ khoảnh khắc nghệ thuật</p>
          <p class="text-sm text-white/85 mt-2 leading-relaxed">
            Đặt concept cá nhân, gia đình, couple và doanh nghiệp với ekip tạo dựng phong cách riêng cho từng shoot hình.
          </p>
          <a href="../views/lienhe.php" class="mt-4 inline-flex items-center gap-2 text-sm">
            <i class="fas fa-calendar-check"></i> Đặt lịch chụp
          </a>
        </div>

        <a href="../views/home.php" class="px-4 py-2 rounded-lg hover:bg-gray-100">Trang Chủ</a>

        <details id="m-acc-dv" class="group rounded-lg border border-slate-200/70 overflow-hidden">
          <summary class="px-4 py-3 cursor-pointer flex items-center justify-between bg-slate-50 hover:bg-slate-100">
            <span>Dịch Vụ</span>
            <i class="fas fa-chevron-down text-xs transition group-open:rotate-180"></i>
          </summary>

          <div class="bg-white p-3">
            <div class="text-xs uppercase text-gray-500 px-1 mb-1">Dịch vụ lẻ</div>
            <ul class="space-y-1 mb-2">
              <?php
              $menuServices2 = $conn->query("SELECT ID_DV, TEN_DV FROM dich_vu ORDER BY TEN_DV ASC LIMIT 8");
              if ($menuServices2 && $menuServices2->num_rows):
                while ($svc = $menuServices2->fetch_assoc()): ?>
                <li>
                  <a class="block px-3 py-1.5 rounded-lg hover:bg-slate-100" href="../views/chitiet.php?id=<?= (int)$svc['ID_DV'] ?>">
                    <?= htmlspecialchars($svc['TEN_DV']) ?>
                  </a>
                </li>
              <?php endwhile; else: ?>
                <li class="px-3 py-1 text-gray-500 italic">Chưa có dịch vụ</li>
              <?php endif; ?>
            </ul>

            <div class="text-xs uppercase text-gray-500 px-1 mb-1">Combo/Gói</div>
            <ul class="space-y-1">
              <?php
              $menuCombos2 = $conn->query("
                SELECT ID_GOI, TEN_GOI
                FROM goi_dich_vu
                WHERE TRANG_THAI='ban'
                  AND (HIEU_LUC_TU IS NULL OR HIEU_LUC_TU<=NOW())
                  AND (HIEU_LUC_DEN IS NULL OR HIEU_LUC_DEN>=NOW())
                ORDER BY TEN_GOI ASC
                LIMIT 8
              ");
              if ($menuCombos2 && $menuCombos2->num_rows):
                while ($pkg = $menuCombos2->fetch_assoc()): ?>
                <li>
                  <a class="block px-3 py-1.5 rounded-lg hover:bg-slate-100" href="../views/goi_chitiet.php?id_goi=<?= (int)$pkg['ID_GOI'] ?>">
                    <?= htmlspecialchars($pkg['TEN_GOI']) ?>
                  </a>
                </li>
              <?php endwhile; else: ?>
                <li class="px-3 py-1 text-gray-500 italic">Chưa có gói</li>
              <?php endif; ?>
            </ul>

            <div class="flex justify-end gap-3 mt-3 pr-1">
              <a href="../views/dichvu.php" class="text-xs text-indigo-700 hover:underline">Tất cả dịch vụ</a>
              <a href="../views/goi.php" class="text-xs text-indigo-700 hover:underline">Tất cả gói</a>
            </div>
          </div>
        </details>

        <a href="../views/albums.php" class="px-4 py-2 rounded-lg hover:bg-gray-100">Phòng Trưng Bày</a>
        <a href="../views/trangphuc.php" class="px-4 py-2 rounded-lg hover:bg-gray-100">Thuê trang phục</a>
        <a href="../views/gioithieu.php" class="px-4 py-2 rounded-lg hover:bg-gray-100">Giới Thiệu</a>
        <a href="../views/lienhe.php" class="px-4 py-2 rounded-lg hover:bg-gray-100">Liên Hệ</a>

        <?php if (empty($_SESSION['ID_TK'])): ?>
          <a href="../../../login.php" class="sb-pill-btn mt-2 px-4 py-2 rounded-lg border border-slate-200 text-center font-medium text-[color:var(--sb-primary)] hover:bg-slate-50">
            Đăng nhập
          </a>
        <?php endif; ?>

        <div class="mt-6 border-t border-slate-200/80 pt-4 text-sm text-slate-600 space-y-3">
          <div class="flex items-center gap-3">
            <i class="fas fa-phone text-slate-400"></i>
            <span>0901 234 567</span>
          </div>
          <div class="flex items-center gap-3">
            <i class="fas fa-envelope text-slate-400"></i>
            <span>hello@stygianblue.vn</span>
          </div>
          <div class="flex items-center gap-3">
            <i class="fas fa-location-dot text-slate-400"></i>
            <span>85 Nguyễn Trãi, Quận 1, TP.HCM</span>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <!-- Content placeholder -->
  <main class="max-w-6xl mx-auto p-6 text-slate-700"></main>

  <!-- Header behaviors -->
  <script>
    (function(){
      const sbHeader    = document.querySelector('[data-sb-header]');
      const container   = document.getElementById('sbh-container');
      const btnDvToggle = document.getElementById('btnDvToggle');
      const dvMenu      = document.getElementById('dv-menu');
      const btnMobile   = document.getElementById('btnMobile');
      const mobileOL    = document.getElementById('mobileOverlay');
      const btnMobileClose = document.getElementById('btnMobileClose');
      const btnProfile  = document.getElementById('btnProfile');
      const profileMenu = document.getElementById('profileMenu');

      function onScroll(){
        const y = window.scrollY || 0;
        if (container){
          container.style.boxShadow = y > 6 ? '0 28px 58px -36px rgba(15,23,42,.6)' : '0 20px 48px -32px rgba(15,23,42,.6)';
        }
        sbHeader?.classList.toggle('is-scrolled', y > 80);
      }
      document.addEventListener('scroll', onScroll, { passive:true });
      onScroll();

      let dvOpen = false;
      function openDv(){
        if (!dvMenu || !btnDvToggle) return;
        dvOpen = true;
        dvMenu.classList.remove('invisible','opacity-0','translate-y-1');
        dvMenu.classList.add('visible','opacity-100','translate-y-2');
        btnDvToggle.setAttribute('aria-expanded','true');
      }
      function closeDv(){
        if (!dvMenu || !btnDvToggle) return;
        dvOpen = false;
        dvMenu.classList.add('invisible','opacity-0');
        dvMenu.classList.remove('visible','opacity-100','translate-y-2');
        btnDvToggle.setAttribute('aria-expanded','false');
      }
      btnDvToggle?.addEventListener('click', (e)=>{ e.preventDefault(); dvOpen ? closeDv() : openDv(); });
      document.addEventListener('click', (e)=>{
        if (dvMenu && btnDvToggle && !dvMenu.contains(e.target) && !btnDvToggle.contains(e.target)) closeDv();
      });
      document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeDv(); });

      let pfOpen = false;
      function openPf(){
        if (!profileMenu || !btnProfile) return;
        pfOpen = true;
        profileMenu.classList.remove('invisible','opacity-0','translate-y-1');
        profileMenu.classList.add('visible','opacity-100','translate-y-0');
        btnProfile.setAttribute('aria-expanded','true');
      }
      function closePf(){
        if (!profileMenu || !btnProfile) return;
        pfOpen = false;
        profileMenu.classList.add('invisible','opacity-0','translate-y-1');
        profileMenu.classList.remove('visible','opacity-100','translate-y-0');
        btnProfile.setAttribute('aria-expanded','false');
      }
      btnProfile?.addEventListener('click', (e)=>{ e.stopPropagation(); pfOpen ? closePf() : openPf(); });
      document.addEventListener('click',(e)=>{ if (profileMenu && btnProfile && !profileMenu.contains(e.target) && !btnProfile.contains(e.target)) closePf(); });
      document.addEventListener('keydown',(e)=>{ if(e.key==='Escape') closePf(); });

      function openMobile(){
        if (!mobileOL || !btnMobile) return;
        mobileOL.classList.remove('hidden');
        btnMobile.setAttribute('aria-expanded','true');
        document.documentElement.style.overflow='hidden';
        document.body.style.overflow='hidden';
      }
      function closeMobile(){
        if (!mobileOL || !btnMobile) return;
        mobileOL.classList.add('hidden');
        btnMobile.setAttribute('aria-expanded','false');
        document.documentElement.style.overflow='';
        document.body.style.overflow='';
      }
      btnMobile?.addEventListener('click', openMobile);
      btnMobileClose?.addEventListener('click', closeMobile);
      mobileOL?.addEventListener('click', (e)=>{ if (e.target === mobileOL) closeMobile(); });
      document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeMobile(); });
    })();
  </script>
</body>
</html>
