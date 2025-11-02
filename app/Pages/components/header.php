<?php
// navbar.php (v5) — Upgraded Header: PHP + Tailwind
// Yêu cầu: đã có $conn, $menuServices, $menuCombos, $unpaidCount, $_SESSION
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Stygian Blue Studio</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet" />
  <style>
    :root { --sb-primary:#060935; }
    body{ font-family:'Roboto', sans-serif; }
    /* Scoped header */
    [data-sb-header] ._active-underline{ position:relative }
    [data-sb-header] ._active-underline:after{
      content:""; position:absolute; left:0; right:0; bottom:-6px; height:3px; border-radius:9999px;
      background:linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa)
    }
    @media (prefers-reduced-motion:reduce){
      [data-sb-header] *{ transition:none!important; animation:none!important }
    }
  </style>
</head>

<body class="bg-gray-50 pt-24">
  <!-- Loader (tuỳ chọn, nếu bạn đang dùng) -->
  <div class="loader-box fixed inset-0 bg-[#060935] z-[9999] hidden items-center justify-center transition-opacity duration-700">
    <i class="fas fa-eye text-6xl text-white animate-pulse"></i>
  </div>

  <!-- Header -->
  <header data-sb-header class="fixed top-0 inset-x-0 z-50">
    <!-- Gradient top bar -->
    <div class="h-1.5 bg-[linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa,#f472b6,#0ea5e9)]"></div>

    <!-- Main header -->
    <div id="sbh-container" class="bg-white/90 supports-[backdrop-filter]:backdrop-blur shadow transition-shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
        <!-- Logo -->
        <a href="./home.php" class="group flex items-center gap-3 shrink-0">
          <img src="../../../public/images/logo5.png" alt="Stygian Blue" class="h-10 w-auto transition-transform duration-300 group-hover:scale-105" />
          <span class="text-[color:var(--sb-primary)] font-bold text-lg hidden sm:inline-block">
            Stygian Blue Studio
          </span>
        </a>

        <!-- Desktop nav -->
        <nav class="hidden md:flex items-center gap-6 text-slate-700 font-medium text-[15px]" aria-label="Điều hướng chính" role="menubar">
          <a role="menuitem" href="../views/home.php" class="hover:text-indigo-700 transition">Trang Chủ</a>

          <!-- Dịch vụ -->
          <div class="relative group/menu" data-sb-dichvu>
            <div class="flex items-center gap-1">
              <!-- Click vào chữ: đi trang dịch vụ -->
              <a role="menuitem" href="../views/dichvu.php" class="hover:text-indigo-700 transition inline-flex items-center gap-2 _active-underline">
                Dịch Vụ
              </a>
              <!-- Nút mũi tên: chỉ mở menu -->
              <button
                type="button"
                aria-expanded="false"
                aria-haspopup="true"
                aria-controls="dv-menu"
                id="btnDvToggle"
                class="text-gray-700 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded px-1"
                title="Mở menu Dịch vụ">
                <i class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
              </button>
            </div>

            <!-- Cầu hover để không bị tắt sớm -->
            <div class="absolute left-1/2 -translate-x-1/2 top-full h-3 w-[720px]"></div>

            <!-- Panel -->
            <div
              id="dv-menu"
              class="absolute left-1/2 -translate-x-1/2 mt-3 w-[720px] bg-white rounded-2xl shadow-2xl border border-gray-100 p-5 z-[60]
                     opacity-0 invisible translate-y-1 transition-all duration-200
                     group-hover/menu:opacity-100 group-hover/menu:visible group-hover/menu:translate-y-2"
              role="menu"
              aria-label="Menu Dịch vụ"
              tabindex="-1">
              <div class="grid grid-cols-2 gap-6">
                <!-- Dịch vụ lẻ -->
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
                             class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-100 focus:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
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

                <!-- Combo/Gói -->
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
                             class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-100 focus:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
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

              <!-- CTA -->
              <div class="mt-4 flex items-center justify-end gap-3">
                <a href="../views/khuyenmai.php" class="text-sm px-3 py-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100">
                  <i class="fas fa-tags mr-1"></i> Khuyến mãi
                </a>
                <a href="../views/lienhe.php" class="text-sm px-3 py-1.5 rounded-lg bg-[linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa)] text-white shadow hover:brightness-110">
                  <i class="fas fa-calendar-check mr-1"></i> Đặt lịch ngay
                </a>
              </div>
            </div>
          </div>

          <a role="menuitem" href="../views/albums.php" class="hover:text-indigo-700 transition">Phòng Trưng Bày</a>
          <a role="menuitem" href="../views/trangphuc.php" class="hover:text-indigo-700 transition">Thuê trang phục</a>
          <a role="menuitem" href="../views/gioithieu.php" class="hover:text-indigo-700 transition">Giới Thiệu</a>
          <a role="menuitem" href="../views/lienhe.php" class="hover:text-indigo-700 transition">Liên Hệ Đặt Lịch</a>
        </nav>

        <!-- Actions -->
        <div class="flex items-center gap-4">
          <?php if (empty($_SESSION['ID_TK'])): ?>
            <a href="../../../login.php" class="px-4 py-2 rounded-lg bg-[linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa)] text-white font-semibold shadow hover:brightness-110">
              Đăng nhập
            </a>
          <?php else: ?>
            <!-- Profile dropdown -->
            <div class="relative">
              <button
                id="btnProfile"
                class="h-10 w-10 rounded-full bg-gray-100 text-[color:var(--sb-primary)] grid place-items-center hover:ring-2 hover:ring-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-expanded="false" aria-haspopup="true" aria-controls="profileMenu">
                <i class="fas fa-user"></i>
              </button>

              <div
                id="profileMenu"
                class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl opacity-0 invisible translate-y-1 transition-all duration-150"
                role="menu" tabindex="-1">
                <a href="../views/thongtin.php" class="block px-4 py-2 hover:bg-gray-100 transition">Thông tin</a>
                <a href="xemLichhen.php" class="block px-4 py-2 hover:bg-gray-100 transition">Xem lịch hẹn</a>
                <a href="../views/hoa_don.php" class="relative block px-4 py-2 hover:bg-gray-100 transition">
                  Hóa đơn
                  <?php if (!empty($unpaidCount)): ?>
                    <span class="absolute top-2 right-4 flex h-5 w-5 items-center justify-center text-xs font-bold text-white bg-red-600 rounded-full shadow">
                      <?= (int)$unpaidCount ?>
                    </span>
                  <?php endif; ?>
                </a>
                <a href="#" class="block px-4 py-2 hover:bg-gray-100 transition">Cài đặt</a>
                <a href="../../../logout.php" class="block px-4 py-2 hover:bg-red-100 text-red-600 transition">Đăng xuất</a>
              </div>
            </div>
          <?php endif; ?>

          <!-- Mobile button -->
          <button id="btnMobile"
            class="md:hidden text-[color:var(--sb-primary)] text-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded p-1"
            aria-controls="mobileOverlay" aria-expanded="false" aria-label="Mở menu">
            <i class="fas fa-bars"></i>
          </button>
        </div>
      </div>

      <!-- Mobile overlay menu -->
      <div id="mobileOverlay" class="md:hidden fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-40">
        <nav class="ml-auto h-full w-[86%] max-w-sm bg-white shadow-xl p-4 flex flex-col gap-1 overflow-y-auto" aria-label="Menu di động">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
              <img src="../../../public/images/logo5.png" alt="Stygian Blue" class="h-8 w-auto" />
              <span class="font-bold text-[color:var(--sb-primary)]">Menu</span>
            </div>
            <button id="btnMobileClose" class="text-2xl p-1 rounded hover:bg-gray-100" aria-label="Đóng menu">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <a href="../views/home.php" class="px-3 py-2 rounded hover:bg-gray-100">Trang Chủ</a>

          <!-- Accordion Dịch vụ -->
          <details id="m-acc-dv" class="group">
            <summary class="px-3 py-2 cursor-pointer flex items-center justify-between rounded hover:bg-gray-100">
              <span>Dịch Vụ</span>
              <i class="fas fa-chevron-down text-xs transition group-open:rotate-180"></i>
            </summary>

            <div class="bg-gray-50 rounded-lg p-2 mt-1">
              <div class="text-xs uppercase text-gray-500 px-2 mb-1">Dịch vụ lẻ</div>
              <ul class="space-y-1 mb-2">
                <?php
                $menuServices2 = $conn->query("SELECT ID_DV, TEN_DV FROM dich_vu ORDER BY TEN_DV ASC LIMIT 8");
                if ($menuServices2 && $menuServices2->num_rows):
                  while ($svc = $menuServices2->fetch_assoc()): ?>
                  <li>
                    <a class="block px-3 py-1 rounded hover:bg-white" href="../views/chitiet.php?id=<?= (int)$svc['ID_DV'] ?>">
                      <?= htmlspecialchars($svc['TEN_DV']) ?>
                    </a>
                  </li>
                <?php endwhile; else: ?>
                  <li class="px-3 py-1 text-gray-500 italic">Chưa có dịch vụ</li>
                <?php endif; ?>
              </ul>

              <div class="text-xs uppercase text-gray-500 px-2 mb-1">Combo/Gói</div>
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
                    <a class="block px-3 py-1 rounded hover:bg-white" href="../views/goi_chitiet.php?id_goi=<?= (int)$pkg['ID_GOI'] ?>">
                      <?= htmlspecialchars($pkg['TEN_GOI']) ?>
                    </a>
                  </li>
                <?php endwhile; else: ?>
                  <li class="px-3 py-1 text-gray-500 italic">Chưa có gói</li>
                <?php endif; ?>
              </ul>

              <div class="flex justify-end gap-3 mt-2 pr-1">
                <a href="../views/dichvu.php" class="text-xs text-indigo-700 hover:underline">Tất cả dịch vụ</a>
                <a href="../views/goi.php" class="text-xs text-indigo-700 hover:underline">Tất cả gói</a>
              </div>
            </div>
          </details>

          <a href="../views/albums.php" class="px-3 py-2 rounded hover:bg-gray-100">Phòng Trưng Bày</a>
          <a href="../views/trangphuc.php" class="px-3 py-2 rounded hover:bg-gray-100">Thuê trang phục</a>
          <a href="../views/gioithieu.php" class="px-3 py-2 rounded hover:bg-gray-100">Giới Thiệu</a>
          <a href="../views/lienhe.php" class="px-3 py-2 rounded hover:bg-gray-100">Liên Hệ</a>

          <?php if (empty($_SESSION['ID_TK'])): ?>
            <a href="../../../login.php" class="mt-2 mx-1 inline-block px-4 py-2 rounded bg-[linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa)] text-white text-center shadow hover:brightness-110">
              Đăng nhập
            </a>
          <?php endif; ?>
        </nav>
      </div>
    </div>
  </header>

  <!-- Content placeholder -->
  <main class="max-w-6xl mx-auto p-6 text-slate-700"></main>

  <!-- Header behaviors -->
  <script>
    (function(){
      const container   = document.getElementById('sbh-container');
      const btnDvToggle = document.getElementById('btnDvToggle');
      const dvMenu      = document.getElementById('dv-menu');
      const btnMobile   = document.getElementById('btnMobile');
      const mobileOL    = document.getElementById('mobileOverlay');
      const btnMobileClose = document.getElementById('btnMobileClose');
      const btnProfile  = document.getElementById('btnProfile');
      const profileMenu = document.getElementById('profileMenu');

      // Shadow khi scroll
      function onScroll(){
        const y = window.scrollY || 0;
        container.style.boxShadow = y > 4 ? '0 8px 20px rgba(2,6,23,.12)' : '';
      }
      document.addEventListener('scroll', onScroll, { passive:true });

      // ====== Desktop: Dịch vụ arrow toggle ======
      let dvOpen = false;
      function openDv(){
        dvOpen = true;
        dvMenu.classList.remove('invisible','opacity-0','translate-y-1');
        dvMenu.classList.add('visible','opacity-100','translate-y-2');
        btnDvToggle.setAttribute('aria-expanded','true');
      }
      function closeDv(){
        dvOpen = false;
        dvMenu.classList.add('invisible','opacity-0');
        dvMenu.classList.remove('visible','opacity-100','translate-y-2');
        btnDvToggle.setAttribute('aria-expanded','false');
      }
      btnDvToggle?.addEventListener('click', (e)=>{ e.preventDefault(); dvOpen ? closeDv() : openDv(); });
      document.addEventListener('click', (e)=>{
        if (!dvMenu.contains(e.target) && !btnDvToggle.contains(e.target)) closeDv();
      });
      document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeDv(); });

      // ====== Profile dropdown ======
      let pfOpen = false;
      function openPf(){
        pfOpen = true;
        profileMenu.classList.remove('invisible','opacity-0','translate-y-1');
        profileMenu.classList.add('visible','opacity-100','translate-y-0');
        btnProfile.setAttribute('aria-expanded','true');
      }
      function closePf(){
        pfOpen = false;
        profileMenu.classList.add('invisible','opacity-0','translate-y-1');
        profileMenu.classList.remove('visible','opacity-100','translate-y-0');
        btnProfile.setAttribute('aria-expanded','false');
      }
      btnProfile?.addEventListener('click', (e)=>{ e.stopPropagation(); pfOpen ? closePf() : openPf(); });
      document.addEventListener('click',(e)=>{ if (profileMenu && !profileMenu.contains(e.target) && !btnProfile.contains(e.target)) closePf(); });
      document.addEventListener('keydown',(e)=>{ if(e.key==='Escape') closePf(); });

      // ====== Mobile overlay menu ======
      function openMobile(){
        mobileOL.classList.remove('hidden'); btnMobile.setAttribute('aria-expanded','true');
        // cấm scroll nền
        document.documentElement.style.overflow='hidden';
        document.body.style.overflow='hidden';
      }
      function closeMobile(){
        mobileOL.classList.add('hidden'); btnMobile.setAttribute('aria-expanded','false');
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
