<!-- navbar.php (v4) — Bright, Accessible, SCOPED Header for Stygian Blue -->
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stygian Blue Studio</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet" />
  <script src="http://localhost:8080/StygianBlue/public/js/loader_box.js"></script>
  <style>
    body{font-family:'Roboto',sans-serif}
    /* ====== SCOPED to [data-sb-header] ====== */
    [data-sb-header] .dropdown-menu{transition:all .25s ease}
    [data-sb-header] ._active-underline{position:relative}
    [data-sb-header] ._active-underline:after{content:"";position:absolute;left:0;right:0;bottom:-6px;height:3px;border-radius:9999px;background:linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa)}
    @media (prefers-reduced-motion: reduce){[data-sb-header] *{transition:none!important;animation:none!important}}
  </style>
</head>
<body class="bg-gray-50 pt-24">
  <!-- Loader -->
  <div class="loader-box fixed top-0 left-0 w-full h-full bg-[#060935] z-[9999] flex items-center justify-center transition-opacity duration-1000">
    <i class="fas fa-eye text-6xl text-white animate-pulse"></i>
  </div>

  <!-- Header -->
  <header data-sb-header class="fixed top-0 w-full z-50">
    <!-- Bright gradient bar -->
    <div class="h-1.5 bg-[linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa,#f472b6,#0ea5e9)]"></div>

    <div class="bg-white/90 backdrop-blur supports-[backdrop-filter]:backdrop-blur shadow-md" id="sbh-container">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
        <!-- Logo -->
        <a href="./home.php" class="flex items-center space-x-3 group">
          <img src="../../../public/images/logo5.png" alt="Logo Stygian Blue" class="h-10 w-auto transition-transform duration-300 group-hover:scale-105" />
          <span class="text-[#060935] font-bold text-lg hidden sm:inline-block">Stygian Blue Studio</span>
        </a>

        <!-- Desktop nav -->
        <nav class="hidden md:flex items-center gap-6 text-slate-700 font-semibold text-[15px]" aria-label="Điều hướng chính">
          <a href="../views/home.php" class="hover:text-indigo-700 transition">Trang Chủ</a>

          <!-- Dịch vụ (desktop) -->
          <div class="relative group/menu" data-sb-dichvu>
            <div class="flex items-center gap-1">
              <!-- Click logo = vào trang dịch vụ -->
              <a href="../views/dichvu.php" class="hover:text-indigo-700 transition inline-flex items-center gap-2 _active-underline">
                Dịch Vụ
              </a>
              <!-- Arrow = chỉ mở menu -->
              <button type="button" aria-expanded="false" aria-controls="dv-menu" id="btnDvToggle"
                class="text-gray-700 hover:text-indigo-700 focus:outline-none" title="Mở menu Dịch vụ">
                <i class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
              </button>
            </div>

            <!-- Hover bridge -->
            <div class="absolute left-1/2 -translate-x-1/2 top-full h-3 w-[720px]"></div>

            <!-- Panel -->
            <div id="dv-menu"
              class="absolute left-1/2 -translate-x-1/2 mt-3 w-[720px] bg-white rounded-2xl shadow-2xl border border-gray-100 p-5 z-[60]
                     opacity-0 invisible translate-y-1 transition-all duration-200
                     group-hover/menu:opacity-100 group-hover/menu:visible group-hover/menu:translate-y-2
                     focus-within:opacity-100 focus-within:visible focus-within:translate-y-2"
              role="menu" aria-label="Menu Dịch vụ">
              <div class="grid grid-cols-2 gap-6">
                <!-- Dịch vụ lẻ -->
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <h4 class="text-[#060935] font-bold">Dịch vụ lẻ</h4>
                    <a href="../views/dichvu.php" class="text-sm text-indigo-700 hover:underline">Xem tất cả</a>
                  </div>
                  <ul class="max-h-64 overflow-auto pr-1 space-y-1" role="none">
                    <?php if ($menuServices && $menuServices->num_rows): ?>
                      <?php $menuServices->data_seek(0); while ($svc = $menuServices->fetch_assoc()): ?>
                        <li role="none">
                          <a role="menuitem" href="../views/chitiet.php?id=<?= (int)$svc['ID_DV'] ?>"
                             class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-100">
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
                    <h4 class="text-[#060935] font-bold">Combo / Gói</h4>
                    <a href="../views/goi.php" class="text-sm text-indigo-700 hover:underline">Xem tất cả</a>
                  </div>
                  <ul class="max-h-64 overflow-auto pr-1 space-y-1" role="none">
                    <?php if ($menuCombos && $menuCombos->num_rows): ?>
                      <?php $menuCombos->data_seek(0); while ($pkg = $menuCombos->fetch_assoc()): ?>
                        <li role="none">
                          <a role="menuitem" href="../views/goi_chitiet.php?id=<?= (int)$pkg['ID_GOI'] ?>"
                             class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-100">
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

          <a href="../views/albums.php" class="hover:text-indigo-700 transition">Phòng Trưng Bày</a>
          <a href="../views/thietbi.php" class="hover:text-indigo-700 transition">Trang Phục & Thiết Bị</a>
          <a href="../views/gioithieu.php" class="hover:text-indigo-700 transition">Giới Thiệu</a>
          <a href="../views/lienhe.php" class="hover:text-indigo-700 transition">Liên Hệ Đặt Lịch</a>
        </nav>

        <div class="flex items-center gap-4">
          <?php if (empty($_SESSION['ID_TK'])): ?>
            <a href="../../../login.php" class="px-4 py-2 rounded-lg bg-[linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa)] text-white font-semibold shadow hover:brightness-110">
              Đăng nhập
            </a>
          <?php else: ?>
            <!-- Profile dropdown -->
            <div class="relative group ml-2">
              <i class="fas fa-user text-2xl text-[#060935] cursor-pointer" aria-hidden="true"></i>
              <div class="dropdown-menu absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl opacity-0 group-hover:opacity-100 group-hover:visible invisible transform group-hover:translate-y-1">
                <a href="../views/thongtin.php" class="block px-4 py-2 hover:bg-gray-100 transition">Thông tin</a>
                <a href="xemLichhen.php" class="block px-4 py-2 hover:bg-gray-100 transition">Xem lịch hẹn</a>
                <a href="../views/hoa_don.php" class="relative block px-4 py-2 hover:bg-gray-100 transition">
                  Hóa đơn
                  <?php if ($unpaidCount > 0): ?>
                    <span class="absolute top-2 right-4 flex h-5 w-5 items-center justify-center text-xs font-bold text-white bg-red-600 rounded-full shadow animate-bounce">
                      <?= $unpaidCount ?>
                    </span>
                  <?php endif; ?>
                </a>
                <a href="#" class="block px-4 py-2 hover:bg-gray-100 transition">Cài đặt</a>
                <a href="../../../logout.php" class="block px-4 py-2 hover:bg-red-100 text-red-600 transition">Đăng xuất</a>
              </div>
            </div>
          <?php endif; ?>

          <!-- Mobile button -->
          <div class="md:hidden">
            <button id="btnMobile" class="text-[#060935] text-2xl focus:outline-none" aria-controls="mobileMenu" aria-expanded="false" aria-label="Mở menu">
              <i class="fas fa-bars"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile nav -->
      <nav id="mobileMenu" class="md:hidden hidden flex flex-col items-stretch bg-white shadow-md py-3 space-y-1" aria-label="Menu di động">
        <a href="../views/home.php" class="px-4 py-2 text-gray-700 hover:text-indigo-700 transition">Trang Chủ</a>

        <!-- Accordion Dịch vụ -->
        <details class="group px-2" id="m-acc-dv">
          <summary class="px-2 py-2 cursor-pointer flex items-center justify-between text-gray-700 hover:text-indigo-700">
            <span>Dịch Vụ</span>
            <i class="fas fa-chevron-down text-xs transition group-open:rotate-180"></i>
          </summary>
          <div class="bg-gray-50 rounded-lg p-2">
            <div class="text-xs uppercase text-gray-500 px-2 mb-1">Dịch vụ lẻ</div>
            <ul class="space-y-1 mb-2">
              <?php $menuServices2 = $conn->query("SELECT ID_DV, TEN_DV FROM dich_vu ORDER BY TEN_DV ASC LIMIT 8");
              if ($menuServices2 && $menuServices2->num_rows): while ($svc = $menuServices2->fetch_assoc()): ?>
                <li><a class="block px-3 py-1 rounded hover:bg-white" href="../views/chitiet.php?id=<?= (int)$svc['ID_DV'] ?>"><?= htmlspecialchars($svc['TEN_DV']) ?></a></li>
              <?php endwhile; else: ?>
                <li class="px-3 py-1 text-gray-500 italic">Chưa có dịch vụ</li>
              <?php endif; ?>
            </ul>

            <div class="text-xs uppercase text-gray-500 px-2 mb-1">Combo/Gói</div>
            <ul class="space-y-1">
              <?php $menuCombos2 = $conn->query("SELECT ID_GOI, TEN_GOI FROM goi_dich_vu WHERE TRANG_THAI='ban' AND (HIEU_LUC_TU IS NULL OR HIEU_LUC_TU<=NOW()) AND (HIEU_LUC_DEN IS NULL OR HIEU_LUC_DEN>=NOW()) ORDER BY TEN_GOI ASC LIMIT 8");
              if ($menuCombos2 && $menuCombos2->num_rows): while ($pkg = $menuCombos2->fetch_assoc()): ?>
                <li><a class="block px-3 py-1 rounded hover:bg-white" href="../views/goi_chitiet.php?id=<?= (int)$pkg['ID_GOI'] ?>"><?= htmlspecialchars($pkg['TEN_GOI']) ?></a></li>
              <?php endwhile; else: ?>
                <li class="px-3 py-1 text-gray-500 italic">Chưa có gói</li>
              <?php endif; ?>
            </ul>
            <div class="flex justify-end gap-2 mt-2 pr-1">
              <a href="../views/dichvu.php" class="text-xs text-indigo-700 hover:underline">Tất cả dịch vụ</a>
              <a href="../views/goi.php" class="text-xs text-indigo-700 hover:underline">Tất cả gói</a>
            </div>
          </div>
        </details>

        <a href="../views/albums.php" class="px-4 py-2 text-gray-700 hover:text-indigo-700 transition">Phòng Trưng Bày</a>
        <a href="../views/thietbi.php" class="px-4 py-2 text-gray-700 hover:text-indigo-700 transition">Trang Phục & Thiết Bị</a>
        <a href="../views/gioithieu.php" class="px-4 py-2 text-gray-700 hover:text-indigo-700 transition">Giới Thiệu</a>
        <a href="../views/lienhe.php" class="px-4 py-2 text-gray-700 hover:text-indigo-700 transition">Liên Hệ</a>

        <?php if (empty($_SESSION['ID_TK'])): ?>
          <a href="../../../login.php" class="mt-2 mx-4 inline-block px-4 py-2 rounded bg-[linear-gradient(90deg,#0ea5e9,#22d3ee,#a78bfa)] text-white text-center shadow hover:brightness-110">Đăng nhập</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <!-- Page content demo spacing -->
  <main class="max-w-6xl mx-auto p-6 text-slate-700">
  </main>

  <script>
  // ===== SCOPED header behaviors =====
  (function(){
    const root = document.querySelector('[data-sb-header]');
    if(!root) return;
    // Sticky shadow intensify on scroll
    const container = document.getElementById('sbh-container');
    function onScroll(){
      const y = window.scrollY||0;
      container.style.boxShadow = y>4 ? '0 6px 20px rgba(2,6,23,.12)' : '';
    }
    document.addEventListener('scroll', onScroll, {passive:true});

    // Desktop: Dịch vụ arrow toggles panel (click). Hover still works via CSS.
    const toggleBtn = root.querySelector('#btnDvToggle');
    const panel = root.querySelector('#dv-menu');
    let opened = false;
    function openPanel(){ opened=true; panel.classList.remove('invisible','opacity-0','translate-y-1'); panel.classList.add('visible','opacity-100','translate-y-2'); toggleBtn.setAttribute('aria-expanded','true'); }
    function closePanel(){ opened=false; panel.classList.add('invisible','opacity-0'); panel.classList.remove('visible','opacity-100','translate-y-2'); toggleBtn.setAttribute('aria-expanded','false'); }
    toggleBtn?.addEventListener('click', (e)=>{ e.preventDefault(); opened?closePanel():openPanel(); });
    document.addEventListener('click', (e)=>{ if(!panel.contains(e.target) && !toggleBtn.contains(e.target)) closePanel(); });
    root.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closePanel(); });

    // Mobile toggle
    const btnMobile = document.getElementById('btnMobile');
    const mobile = document.getElementById('mobileMenu');
    btnMobile?.addEventListener('click', ()=>{
      const isHidden = mobile.classList.toggle('hidden');
      btnMobile.setAttribute('aria-expanded', String(!isHidden));
    });
  })();
  </script>
</body>
</html>
