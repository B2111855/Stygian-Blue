<!-- footer.php (v3) — Bright, Accessible, SCOPED footer for Stygian Blue -->
<section data-sb-footer role="contentinfo" aria-label="Chân trang Stygian Blue"
  class="relative overflow-hidden rounded-3xl m-4 mt-6">

  <!-- Decorative gradient background (bright) -->
  <div aria-hidden="true" class="sbf-bg"></div>
  <div aria-hidden="true" class="sbf-blob sbf-blob-a"></div>
  <div aria-hidden="true" class="sbf-blob sbf-blob-b"></div>

  <!-- Content -->
  <footer class="sbf footer-container">
    <div class="sbf-brand">
      <span class="sbf-logo" aria-hidden="true">★</span>
      <span class="sbf-name">Stygian Blue Studio</span>
    </div>

    <nav class="sbf-social" aria-label="Liên kết mạng xã hội">
      <a class="sbf-icon" href="https://www.facebook.com" target="_blank" rel="noopener" aria-label="Facebook">
        <i class="fab fa-facebook-f" aria-hidden="true"></i>
      </a>
      <a class="sbf-icon" href="https://www.twitter.com" target="_blank" rel="noopener" aria-label="Twitter/X">
        <i class="fab fa-twitter" aria-hidden="true"></i>
      </a>
      <a class="sbf-icon" href="https://www.instagram.com" target="_blank" rel="noopener" aria-label="Instagram">
        <i class="fab fa-instagram" aria-hidden="true"></i>
      </a>
      <a class="sbf-icon" href="https://www.linkedin.com" target="_blank" rel="noopener" aria-label="LinkedIn">
        <i class="fab fa-linkedin-in" aria-hidden="true"></i>
      </a>
      <a class="sbf-icon" href="https://www.youtube.com" target="_blank" rel="noopener" aria-label="YouTube">
        <i class="fab fa-youtube" aria-hidden="true"></i>
      </a>
      <a class="sbf-icon" href="https://www.pinterest.com" target="_blank" rel="noopener" aria-label="Pinterest">
        <i class="fab fa-pinterest" aria-hidden="true"></i>
      </a>
      <a class="sbf-icon" href="mailto:example@example.com" aria-label="Gửi email">
        <i class="fas fa-envelope" aria-hidden="true"></i>
      </a>
    </nav>

    <div class="sbf-extra">
      <form class="sbf-news" aria-label="Đăng ký nhận tin" onsubmit="return false">
        <label for="sbf-email" class="sbf-label">Nhận ưu đãi & tin mới</label>
        <div class="sbf-inputwrap">
          <input id="sbf-email" type="email" inputmode="email" autocomplete="email" placeholder="Email của bạn"
                 aria-label="Email của bạn" class="sbf-input">
          <button class="sbf-btn" type="button" onclick="alert('Đã gửi yêu cầu!')">
            <i class="fas fa-paper-plane" aria-hidden="true"></i>
            <span>Đăng ký</span>
          </button>
        </div>
      </form>
      <button class="sbf-top" type="button" aria-label="Lên đầu trang" title="Lên đầu trang"
              onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <i class="fas fa-arrow-up" aria-hidden="true"></i>
      </button>
    </div>

    <p class="sbf-copy">© <span id="sbf-year"></span> <strong>Stygian Blue</strong>. All rights reserved.</p>
  </footer>
</section>

<style>
/* ====== SCOPED to [data-sb-footer] only ====== */
[data-sb-footer] { position: relative; isolation: isolate; }
[data-sb-footer] .sbf-bg{
  position:absolute; inset:0;
  background: radial-gradient(1200px 600px at 10% -10%, #66f2d2 0%, transparent 60%),
              radial-gradient(1000px 500px at 110% 10%, #a78bfa 0%, transparent 60%),
              linear-gradient(135deg, #0ea5e9 0%, #22d3ee 25%, #a78bfa 60%, #f472b6 100%);
  filter: saturate(1.2) brightness(1.05);
}
[data-sb-footer] .sbf-blob{ position:absolute; border-radius:9999px; filter: blur(50px); mix-blend: screen; opacity:.35; }
[data-sb-footer] .sbf-blob-a{ width:26rem; height:26rem; left:-6rem; top:-6rem; background:#66f2d2; }
[data-sb-footer] .sbf-blob-b{ width:22rem; height:22rem; right:-5rem; bottom:-8rem; background:#a78bfa; }

[data-sb-footer] .footer-container{
  position:relative; z-index:1;
  padding: 28px 20px; border-radius: 16px;
  color:#0b0e2a; text-align:center;
  backdrop-filter: blur(8px);
  background: linear-gradient( to bottom, rgba(255,255,255,.85), rgba(255,255,255,.75) );
  box-shadow: 0 10px 30px rgba(14,165,233,.25);
}

/* Brand */
[data-sb-footer] .sbf-brand{ display:flex; align-items:center; justify-content:center; gap:10px; font-weight:800; letter-spacing:.5px; }
[data-sb-footer] .sbf-logo{ width:28px; height:28px; display:inline-grid; place-items:center; border-radius:8px; color:#0b0e2a;
  background: conic-gradient(from 0deg, #22d3ee, #a78bfa, #f472b6, #22d3ee); box-shadow: 0 4px 16px rgba(34,211,238,.5); }
[data-sb-footer] .sbf-name{ font-size: clamp(18px, 2vw, 22px); background: linear-gradient(90deg, #0ea5e9, #a78bfa); -webkit-background-clip:text; background-clip:text; color:transparent; }

/* Social icons */
[data-sb-footer] .sbf-social{ display:flex; flex-wrap:wrap; justify-content:center; gap:14px; margin:14px 0; }
[data-sb-footer] .sbf-icon{ --s: 44px; width:var(--s); height:var(--s); border-radius:12px; display:inline-grid; place-items:center;
  color:#0b0e2a; background: rgba(255,255,255,.9); border:1px solid rgba(255,255,255,.6);
  transition: transform .25s ease, box-shadow .25s ease, background .25s ease; }
[data-sb-footer] .sbf-icon:hover{ transform: translateY(-2px) scale(1.05); box-shadow: 0 8px 22px rgba(15,23,42,.25); }
[data-sb-footer] .sbf-icon:focus{ outline:2px solid #0ea5e9; outline-offset:2px; }
[data-sb-footer] .sbf-icon .fa-youtube{ color:#ef4444 }
[data-sb-footer] .sbf-icon .fa-instagram{ color:#db2777 }
[data-sb-footer] .sbf-icon .fa-twitter{ color:#0284c7 }
[data-sb-footer] .sbf-icon .fa-facebook-f{ color:#1d4ed8 }
[data-sb-footer] .sbf-icon .fa-linkedin-in{ color:#2563eb }
[data-sb-footer] .sbf-icon .fa-pinterest{ color:#e11d48 }
[data-sb-footer] .sbf-icon .fa-envelope{ color:#0b0e2a }

/* Newsletter */
[data-sb-footer] .sbf-extra{ display:flex; align-items:center; justify-content:center; gap:14px; flex-wrap:wrap; }
[data-sb-footer] .sbf-news{ display:flex; flex-direction:column; align-items:center; gap:8px; }
[data-sb-footer] .sbf-label{ font-size:14px; color:#0b0e2a; opacity:.8 }
[data-sb-footer] .sbf-inputwrap{ display:flex; gap:8px; }
[data-sb-footer] .sbf-input{ width:min(60vw,280px); padding:10px 12px; border-radius:12px; border:1px solid rgba(2,6,23,.15);
  background: rgba(255,255,255,.9); color:#0b0e2a; }
[data-sb-footer] .sbf-input:focus{ outline:2px solid #22d3ee; outline-offset:2px; }
[data-sb-footer] .sbf-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:12px; font-weight:700; color:#04121a;
  background: linear-gradient(90deg,#66f2d2,#a78bfa); border:1px solid rgba(2,6,23,.1);
  box-shadow: 0 6px 18px rgba(102,242,210,.35); transition: transform .2s ease, box-shadow .2s ease; }
[data-sb-footer] .sbf-btn:hover{ transform: translateY(-1px); box-shadow: 0 10px 26px rgba(102,242,210,.5); }

/* Back to top */
[data-sb-footer] .sbf-top{ width:42px; height:42px; display:inline-grid; place-items:center; border-radius:12px;
  background: rgba(255,255,255,.9); border:1px solid rgba(255,255,255,.6); color:#0b0e2a; transition: transform .2s ease; }
[data-sb-footer] .sbf-top:hover{ transform: translateY(-2px); }

/* Copyright */
[data-sb-footer] .sbf-copy{ margin:10px 0 0; font-size:14px; color:#0b0e2a; opacity:.9 }

/* Responsive */
@media (max-width: 480px){
  [data-sb-footer] .sbf-icon{ --s: 38px }
  [data-sb-footer] .sbf-input{ width:min(80vw,260px) }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce){
  [data-sb-footer] .sbf-icon, [data-sb-footer] .sbf-btn, [data-sb-footer] .sbf-top{ transition:none !important }
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
