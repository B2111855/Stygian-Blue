<!-- hero.php (v2, SCOPED) -->
<section data-sb-hero role="banner"
  class="relative overflow-hidden rounded-3xl m-4 mt-[-50px] min-h-[88vh] md:h-[92vh] flex items-center justify-center text-center mt-6">

  <!-- Background image + gradient overlay -->
  <div aria-hidden="true" class="absolute inset-0">
    <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(6,9,53,0.55)_0%,rgba(6,9,53,0.35)_45%,rgba(6,9,53,0.55)_100%)]"></div>
    <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1582719478148-1e7b62b8b84c?auto=format&fit=crop&w=1950&q=80')] bg-cover bg-center will-change-transform mb-6"
         data-sb-bg></div>
  </div>

  <!-- Decorative blobs / bokeh -->
  <div aria-hidden="true" class="pointer-events-none absolute -top-20 -left-20 w-[40rem] h-[40rem] rounded-full blur-3xl opacity-30 bg-sky-400 mix-blend-screen"></div>
  <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -right-24 w-[32rem] h-[32rem] rounded-full blur-3xl opacity-20 bg-indigo-400 mix-blend-screen"></div>

  <!-- Content card -->
  <div class="relative z-10 max-w-4xl mx-auto px-6 pb-6 mt-6">
    <div class="backdrop-blur-xl bg-white/15 border border-white/25 shadow-2xl rounded-3xl px-8 py-10 md:px-12 md:py-14
                opacity-0 translate-y-6 transition-all duration-700 ease-out will-change-transform"
         data-sb-card>

      <p class="inline-flex items-center gap-2 text-[13px] md:text-sm text-white/80 bg-white/10 border border-white/20 rounded-full px-3 py-1 mb-4">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        Studio đa chi nhánh · Đặt lịch nhanh · Chất lượng chuẩn
      </p>

      <h1 class="text-4xl md:text-6xl font-extrabold leading-tight tracking-tight text-white drop-shadow-sm">
        Chào mừng bạn đã đến với <span class="text-sky-300">Stygian Blue Studio</span>
      </h1>

      <p class="mt-4 md:mt-6 text-base md:text-xl text-white/85 leading-relaxed">
        Nơi hiện thực hóa những tầm nhìn ẩn giấu, ghi lại khoảnh khắc bằng ánh sáng & cảm xúc tinh tế.
      </p>

      <!-- CTAs -->
      <div class="mt-7 md:mt-9 flex flex-col sm:flex-row gap-3 justify-center">
        <a href="./albums.php"
           class="inline-flex items-center justify-center gap-2 px-7 py-3 rounded-full font-semibold
                  bg-sky-500 hover:bg-sky-600 text-white shadow-lg shadow-sky-900/30
                  transition-transform hover:-translate-y-[2px]">
          <i class="fas fa-images"></i> Khám phá album
        </a>
        <a href="../views/lienhe.php"
           class="inline-flex items-center justify-center gap-2 px-7 py-3 rounded-full font-semibold
                  bg-white/15 hover:bg-white/25 text-white border border-white/30
                  backdrop-blur-md transition-transform hover:-translate-y-[2px]">
          <i class="fas fa-calendar-check"></i> Đặt lịch ngay
        </a>
      </div>

      <!-- Quick stats -->
      <div class="mt-8 grid grid-cols-3 gap-4 text-white/90">
        <div class="rounded-2xl bg-white/10 border border-white/20 p-4">
          <div class="text-2xl md:text-3xl font-bold" data-sb-count="3500">3,500+</div>
          <div class="text-xs md:text-sm text-white/70">Bộ ảnh hoàn thiện</div>
        </div>
        <div class="rounded-2xl bg-white/10 border border-white/20 p-4">
          <div class="text-2xl md:text-3xl font-bold" data-sb-count="98">98%</div>
          <div class="text-xs md:text-sm text-white/70">Khách hàng hài lòng</div>
        </div>
        <div class="rounded-2xl bg-white/10 border border-white/20 p-4">
          <div class="text-2xl md:text-3xl font-bold" data-sb-count="12">12+</div>
          <div class="text-xs md:text-sm text-white/70">Phong cách chụp</div>
        </div>
      </div>

      <!-- Trust row -->
      <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-white/70 text-xs">
        <i class="fas fa-shield-alt"></i> Ảnh độ phân giải cao
        <span class="mx-2 opacity-40">•</span>
        <i class="fas fa-clock"></i> Hẹn nhanh – đúng giờ
        <span class="mx-2 opacity-40">•</span>
        <i class="fas fa-magic"></i> Chỉnh màu chuẩn studio
      </div>
    </div>

    <!-- Scroll cue -->
    <button aria-label="Cuộn xuống"
            class="group mt-8 mx-auto block text-white/70 hover:text-white transition"
            onclick="window.scrollTo({top: window.innerHeight*0.9, behavior: 'smooth'});">
      <span class="block text-xs tracking-widest uppercase">Cuộn xuống</span>
      <i class="fas fa-chevron-down text-2xl block mb-6 group-hover:translate-y-1 transition-transform"></i>
    </button>
  </div>
</section>

<!-- Styles: SCOPED vào [data-sb-hero] -->
<style>
  @media (prefers-reduced-motion: reduce) {
    [data-sb-hero] [data-sb-card],
    [data-sb-hero] [data-sb-bg] { transition: none !important; animation: none !important; }
  }
</style>

<!-- JS: SCOPED (fade-in + parallax + counter) -->
<script>
  (function () {
    const root = document.querySelector('[data-sb-hero]');
    if (!root) return;

    const card = root.querySelector('[data-sb-card]');
    const bg   = root.querySelector('[data-sb-bg]');

    // 1) Fade-in khi trang sẵn sàng (chỉ trong hero)
    window.addEventListener('load', () => {
      requestAnimationFrame(() => {
        if (!card) return;
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      });
    });

    // 2) Parallax nền (query trong hero, không đụng component khác)
    let lastY = 0;
    function onScroll() {
      if (!bg) return;
      const y = window.scrollY || window.pageYOffset;
      if (Math.abs(y - lastY) < 2) return;
      lastY = y;
      const offset = Math.min(40, y * 0.06);
      bg.style.transform = `translateY(${offset}px) scale(1.03)`;
    }
    document.addEventListener('scroll', onScroll, { passive: true });

    // 3) Counter chỉ chạy khi hero vào viewport
    const counters = root.querySelectorAll('[data-sb-count]');
    let counted = false;
    const io = new IntersectionObserver((entries) => {
      if (!counted && entries.some(e => e.isIntersecting)) {
        counted = true;
        counters.forEach(el => {
          const to = parseInt(el.getAttribute('data-sb-count'), 10) || 0;
          const isPercent = (el.textContent.trim().endsWith('%'));
          animateNumber(el, to, 900, isPercent);
        });
      }
    }, { threshold: 0.35 });
    io.observe(root);

    function animateNumber(el, to, duration, isPercent) {
      const start = 0, t0 = performance.now();
      function tick(t) {
        const p = Math.min(1, (t - t0) / duration);
        const eased = (p < 0.5) ? 2*p*p : -1 + (4 - 2*p) * p; // easeInOutQuad
        const val = Math.floor(start + (to - start) * eased);
        el.textContent = isPercent
          ? (val + '%')
          : new Intl.NumberFormat('vi-VN').format(val) + (to >= 1000 ? '+' : '');
        if (p < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    }
  })();
</script>
