<!-- footer.php (v4) — Photography-inspired footer for Stygian Blue -->
<section data-sb-footer role="contentinfo" aria-label="Chân trang Stygian Blue"
  class="relative overflow-hidden rounded-3xl m-4 mt-6">

  <!-- Immersive background layers -->
  <div aria-hidden="true" class="sbf-bg"></div>
  <div aria-hidden="true" class="sbf-noise"></div>
  <div aria-hidden="true" class="sbf-lens"></div>

  <!-- Content -->
  <footer class="sbf footer-container">
    <div class="sbf-main">
      <div class="sbf-about">
        <div class="sbf-brand">
          <span class="sbf-logo" aria-hidden="true">★</span>
          <div>
            <p class="sbf-name">Stygian Blue Studio</p>
            <p class="sbf-tagline">Nơi giữ lại khoảnh khắc bừng sáng bằng ánh sáng và cảm xúc.</p>
          </div>
        </div>
        <div class="sbf-awards">
          <span><i class="fas fa-camera-retro" aria-hidden="true"></i> Studio cao cấp</span>
          <span><i class="fas fa-award" aria-hidden="true"></i> 10+ năm kinh nghiệm</span>
          <span><i class="fas fa-heart" aria-hidden="true"></i> 1.000+ cặp đôi hài lòng</span>
        </div>
        <form class="sbf-news" aria-label="Đăng ký nhận tin" onsubmit="return false">
          <label for="sbf-email" class="sbf-label">Nhận moodboard & ưu đãi studio mỗi tháng</label>
          <div class="sbf-inputwrap">
            <input id="sbf-email" type="email" inputmode="email" autocomplete="email" placeholder="Email của bạn"
                   aria-label="Email của bạn" class="sbf-input">
            <button class="sbf-btn" type="button" onclick="alert('Đã gửi yêu cầu!')">
              <i class="fas fa-paper-plane" aria-hidden="true"></i>
              <span>Đăng ký</span>
            </button>
          </div>
        </form>
        <a class="sbf-cta" href="/booking" aria-label="Đặt lịch chụp ngay">
          <i class="fas fa-calendar-check" aria-hidden="true"></i>
          <span>Đặt lịch chụp</span>
        </a>
      </div>

      <div class="sbf-links">
        <h3 class="sbf-heading">Khám phá</h3>
        <ul>
          <li><a href="/gallery" aria-label="Xem portfolio">Portfolio</a></li>
          <li><a href="/services" aria-label="Xem dịch vụ">Dịch vụ</a></li>
          <li><a href="/pricing" aria-label="Bảng giá">Bảng giá</a></li>
          <li><a href="/about" aria-label="Về Stygian Blue">Về chúng tôi</a></li>
          <li><a href="/blog" aria-label="Nhật ký hậu trường">Blog hậu trường</a></li>
        </ul>
      </div>

      <div class="sbf-contact">
        <h3 class="sbf-heading">Liên hệ</h3>
        <ul>
          <li>
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
            <span>28 Nguyễn Huệ, Q.1, TP.HCM</span>
          </li>
          <li>
            <i class="fas fa-phone" aria-hidden="true"></i>
            <a href="tel:+84901234567">(+84) 90 1234 567</a>
          </li>
          <li>
            <i class="fas fa-envelope-open-text" aria-hidden="true"></i>
            <a href="mailto:hello@stygianblue.vn">hello@stygianblue.vn</a>
          </li>
        </ul>
        <div class="sbf-hours">
          <h4>Giờ làm việc</h4>
          <p>Thứ 2 - CN: 8:30 - 20:30</p>
        </div>
      </div>

      <div class="sbf-gallery">
        <h3 class="sbf-heading">Góc cảm hứng</h3>
        <div class="sbf-tiles" role="list">
          <span class="sbf-tile sbf-tile-a" role="listitem" aria-label="Góc chân dung"></span>
          <span class="sbf-tile sbf-tile-b" role="listitem" aria-label="Góc cưới nghệ thuật"></span>
          <span class="sbf-tile sbf-tile-c" role="listitem" aria-label="Góc du lịch"></span>
        </div>
        <p class="sbf-invite">Theo dõi Instagram để xem bộ ảnh mới nhất.</p>
      </div>
    </div>

    <div class="sbf-bottom">
      <nav class="sbf-social" aria-label="Liên kết mạng xã hội">
        <a class="sbf-icon" href="https://www.facebook.com" target="_blank" rel="noopener" aria-label="Facebook">
          <i class="fab fa-facebook-f" aria-hidden="true"></i>
        </a>
        <a class="sbf-icon" href="https://www.instagram.com" target="_blank" rel="noopener" aria-label="Instagram">
          <i class="fab fa-instagram" aria-hidden="true"></i>
        </a>
        <a class="sbf-icon" href="https://www.pinterest.com" target="_blank" rel="noopener" aria-label="Pinterest">
          <i class="fab fa-pinterest" aria-hidden="true"></i>
        </a>
        <a class="sbf-icon" href="https://www.youtube.com" target="_blank" rel="noopener" aria-label="YouTube">
          <i class="fab fa-youtube" aria-hidden="true"></i>
        </a>
        <a class="sbf-icon" href="mailto:hello@stygianblue.vn" aria-label="Gửi email">
          <i class="fas fa-envelope" aria-hidden="true"></i>
        </a>
      </nav>
      <button class="sbf-top" type="button" aria-label="Lên đầu trang" title="Lên đầu trang"
              onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <i class="fas fa-arrow-up" aria-hidden="true"></i>
      </button>
    </div>

    <p class="sbf-copy">© <span id="sbf-year"></span> <strong>Stygian Blue</strong>. Crafted with ánh sáng & tình yêu.</p>
  </footer>
</section>

<style>
/* ====== SCOPED to [data-sb-footer] only ====== */
[data-sb-footer] { position: relative; isolation: isolate; color:#f8fafc; }
[data-sb-footer] .sbf-bg{
  position:absolute; inset:0;
  background:
    linear-gradient(120deg, rgba(7,11,30,.88), rgba(12,19,45,.72) 55%, rgba(10,12,27,.92)),
    url('../../../public/images/backgrounds/204466.jpg') center/cover fixed;
  transform: scale(1.02);
}
[data-sb-footer] .sbf-noise{ position:absolute; inset:0; background: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160"%3E%3Cfilter id="n" x="0" y="0" width="1" height="1"%3E%3CfeTurbulence baseFrequency="0.9" numOctaves="4" stitchTiles="stitch" type="fractalNoise"/%3E%3C/filter%3E%3Crect width="160" height="160" filter="url(%23n)" opacity="0.08"/%3E%3C/svg%3E') repeat; mix-blend-mode: soft-light; }
[data-sb-footer] .sbf-lens{ position:absolute; inset:-30% 20% 45%; border-radius:44% 56% 58% 42% / 40% 48% 52% 60%;
  background: radial-gradient(circle at 30% 30%, rgba(99,102,241,.45), transparent 60%),
              radial-gradient(circle at 70% 40%, rgba(13,148,136,.35), transparent 65%);
  filter: blur(40px); opacity:.85; mix-blend-mode: screen; }

[data-sb-footer] .footer-container{
  position:relative; z-index:1;
  padding: 48px clamp(24px, 6vw, 64px); border-radius: 22px;
  backdrop-filter: blur(14px);
  background: linear-gradient(150deg, rgba(15,23,42,.65), rgba(15,23,42,.45));
  box-shadow: 0 20px 70px rgba(2,6,23,.55);
}

[data-sb-footer] .sbf-main{
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: clamp(24px, 5vw, 42px);
  align-items:start;
}

/* Brand & storytelling */
[data-sb-footer] .sbf-brand{ display:flex; gap:16px; align-items:flex-start; }
[data-sb-footer] .sbf-logo{ min-width:42px; height:42px; display:inline-grid; place-items:center; border-radius:14px; color:#020617;
  background: conic-gradient(from 90deg, #38bdf8, #a855f7, #f472b6, #38bdf8); box-shadow: 0 10px 30px rgba(56,189,248,.6); }
[data-sb-footer] .sbf-name{ font-weight:800; font-size: clamp(20px, 3vw, 26px); margin:0; }
[data-sb-footer] .sbf-tagline{ margin:6px 0 0; font-size:15px; color:rgba(226,232,240,.8); line-height:1.5; }
[data-sb-footer] .sbf-awards{ display:flex; flex-wrap:wrap; gap:10px 16px; margin:18px 0 24px; font-size:13px; color:rgba(221,230,239,.85); }
[data-sb-footer] .sbf-awards span{ display:inline-flex; gap:6px; align-items:center; padding:6px 12px; border-radius:999px;
  background: rgba(15,118,110,.25); border:1px solid rgba(94,234,212,.25); }

/* Newsletter */
[data-sb-footer] .sbf-news{ display:flex; flex-direction:column; align-items:flex-start; gap:10px; width:100%; }
[data-sb-footer] .sbf-label{ font-size:13px; text-transform:uppercase; letter-spacing:.1em; color:rgba(94,234,212,.85); }
[data-sb-footer] .sbf-inputwrap{ display:flex; gap:10px; width:100%; flex-wrap:wrap; }
[data-sb-footer] .sbf-input{ flex:1 1 200px; padding:12px 14px; border-radius:12px; border:1px solid rgba(148,163,184,.25);
  background: rgba(15,23,42,.6); color:#f8fafc; }
[data-sb-footer] .sbf-input::placeholder{ color:rgba(148,163,184,.7); }
[data-sb-footer] .sbf-input:focus{ outline:2px solid rgba(94,234,212,.8); outline-offset:2px; }
[data-sb-footer] .sbf-btn{ display:inline-flex; align-items:center; gap:8px; padding:12px 18px; border-radius:12px; font-weight:700; color:#020617;
  background: linear-gradient(90deg,#5eead4,#38bdf8); border: none;
  box-shadow: 0 12px 32px rgba(34,211,238,.5); transition: transform .25s ease, box-shadow .25s ease; cursor:pointer; }
[data-sb-footer] .sbf-btn:hover{ transform: translateY(-1px) scale(1.01); box-shadow: 0 16px 40px rgba(34,211,238,.55); }

[data-sb-footer] .sbf-cta{ display:inline-flex; align-items:center; gap:10px; margin-top:22px; padding:12px 18px; border-radius:999px;
  font-weight:700; text-decoration:none; color:#0f172a; background: linear-gradient(120deg,#f97316,#facc15);
  box-shadow: 0 18px 36px rgba(249,115,22,.45); transition: transform .25s ease, box-shadow .25s ease; }
[data-sb-footer] .sbf-cta:hover{ transform: translateY(-2px); box-shadow: 0 24px 48px rgba(249,115,22,.6); }

/* Links */
[data-sb-footer] .sbf-heading{ font-size:15px; font-weight:700; text-transform:uppercase; letter-spacing:.14em; color:rgba(148,163,184,.9); margin:0 0 16px; }
[data-sb-footer] .sbf-links ul,
[data-sb-footer] .sbf-contact ul{ list-style:none; margin:0; padding:0; display:grid; gap:12px; }
[data-sb-footer] .sbf-links a{ color:#e0f2fe; text-decoration:none; font-weight:500; }
[data-sb-footer] .sbf-links a:hover{ color:#38bdf8; }

/* Contact */
[data-sb-footer] .sbf-contact li{ display:flex; gap:10px; align-items:flex-start; color:rgba(226,232,240,.85); font-size:14px; }
[data-sb-footer] .sbf-contact i{ margin-top:2px; color:#5eead4; }
[data-sb-footer] .sbf-contact a{ color:inherit; text-decoration:none; }
[data-sb-footer] .sbf-contact a:hover{ color:#38bdf8; }
[data-sb-footer] .sbf-hours{ margin-top:16px; padding:12px 14px; border-radius:12px; background: rgba(148,163,184,.12); border:1px solid rgba(148,163,184,.18); }
[data-sb-footer] .sbf-hours h4{ margin:0 0 6px; font-size:14px; color:rgba(226,232,240,.85); text-transform:uppercase; letter-spacing:.12em; }
[data-sb-footer] .sbf-hours p{ margin:0; font-size:13px; color:rgba(226,232,240,.75); }

/* Gallery preview */
[data-sb-footer] .sbf-gallery{ display:flex; flex-direction:column; gap:16px; }
[data-sb-footer] .sbf-tiles{ display:grid; grid-template-columns:repeat(2, minmax(80px, 120px)); gap:12px; }
[data-sb-footer] .sbf-tile{ position:relative; display:block; padding-top:110%; border-radius:16px; overflow:hidden;
  background-size:cover; background-position:center; filter:saturate(1.05); box-shadow:0 12px 24px rgba(15,23,42,.45);
  transition: transform .3s ease, box-shadow .3s ease; }
[data-sb-footer] .sbf-tile::after{ content:""; position:absolute; inset:0; background: radial-gradient(circle at 70% 30%, rgba(14,165,233,.35), transparent 55%);
  mix-blend-mode:screen; opacity:0; transition: opacity .3s ease; }
[data-sb-footer] .sbf-tile:hover{ transform:translateY(-4px) scale(1.02); box-shadow:0 18px 34px rgba(15,23,42,.55); }
[data-sb-footer] .sbf-tile:hover::after{ opacity:1; }
[data-sb-footer] .sbf-tile-a{ background-image:url('../../../public/images/albums/Ch%C3%A2n%20dung%20%26%20Kho%E1%BA%A3nh%20kh%C3%A1c/z4083519088742_d7e70cdcf52a966fa148d48d8ff11a0c.jpg'); }
[data-sb-footer] .sbf-tile-b{ background-image:url('../../../public/images/backgrounds/204427.jpg'); }
[data-sb-footer] .sbf-tile-c{ background-image:url('../../../public/images/backgrounds/3082874.jpg'); }
[data-sb-footer] .sbf-invite{ margin:0; font-size:13px; color:rgba(226,232,240,.7); }

/* Social row */
[data-sb-footer] .sbf-bottom{ margin-top:42px; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:18px; }
[data-sb-footer] .sbf-social{ display:flex; flex-wrap:wrap; gap:12px; }
[data-sb-footer] .sbf-icon{ --s: 42px; width:var(--s); height:var(--s); border-radius:14px; display:inline-grid; place-items:center;
  color:#0f172a; background: rgba(248,250,252,.92); border:1px solid rgba(148,163,184,.2);
  transition: transform .25s ease, box-shadow .25s ease, background .25s ease; }
[data-sb-footer] .sbf-icon:hover{ transform: translateY(-2px) scale(1.05); box-shadow: 0 14px 30px rgba(15,23,42,.3); }
[data-sb-footer] .sbf-icon:focus{ outline:2px solid #38bdf8; outline-offset:2px; }
[data-sb-footer] .sbf-icon .fa-youtube{ color:#ef4444; }
[data-sb-footer] .sbf-icon .fa-instagram{ color:#db2777; }
[data-sb-footer] .sbf-icon .fa-facebook-f{ color:#1d4ed8; }
[data-sb-footer] .sbf-icon .fa-pinterest{ color:#e11d48; }
[data-sb-footer] .sbf-icon .fa-envelope{ color:#0f172a; }

/* Back to top */
[data-sb-footer] .sbf-top{ width:46px; height:46px; display:inline-grid; place-items:center; border-radius:16px;
  background: rgba(248,250,252,.9); border:1px solid rgba(148,163,184,.4); color:#0f172a; transition: transform .3s ease, box-shadow .3s ease; }
[data-sb-footer] .sbf-top:hover{ transform: translateY(-3px); box-shadow:0 16px 34px rgba(15,23,42,.35); }

/* Copy */
[data-sb-footer] .sbf-copy{ margin:26px 0 0; font-size:13px; color:rgba(226,232,240,.75); text-align:center; }

/* Responsive tweaks */
@media (max-width: 720px){
  [data-sb-footer] .sbf-bottom{ justify-content:center; }
  [data-sb-footer] .sbf-awards{ gap:8px 12px; }
}

@media (max-width: 540px){
  [data-sb-footer] .footer-container{ padding:38px 22px; }
  [data-sb-footer] .sbf-tiles{ grid-template-columns:repeat(3, minmax(72px, 1fr)); }
  [data-sb-footer] .sbf-cta{ width:100%; justify-content:center; }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce){
  [data-sb-footer] .sbf-btn,
  [data-sb-footer] .sbf-cta,
  [data-sb-footer] .sbf-icon,
  [data-sb-footer] .sbf-tile,
  [data-sb-footer] .sbf-top{ transition:none !important; }
}
</style>

<script>
// SCOPED script
(() => {
  const root = document.querySelector('[data-sb-footer]');
  if (!root) return;
  const yearEl = root.querySelector('#sbf-year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();
})();
</script>
