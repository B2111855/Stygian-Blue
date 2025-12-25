<!-- footer.php (v5) — Clean & Professional footer for Stygian Blue -->
<section data-sb-footer role="contentinfo" aria-label="Chân trang Stygian Blue"
  class="relative overflow-hidden m-4 mt-6">

  <!-- Background layer -->
  <div aria-hidden="true" class="sbf-bg"></div>

  <!-- Content -->
  <footer class="sbf footer-container">
    <div class="sbf-main">
      <!-- Brand -->
      <div class="sbf-about">
        <div class="sbf-brand">
          <img src="../../../public/images/StygianBlue1.png" alt="Stygian Blue Logo" class="sbf-logo">
          <div>
            <p class="sbf-name">Stygian Blue Studio</p>
            <p class="sbf-tagline">Lưu giữ khoảnh khắc đẹp nhất của bạn</p>
          </div>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="sbf-links">
        <h3 class="sbf-heading">Dịch vụ</h3>
        <ul>
          <li><a href="/gallery">Portfolio</a></li>
          <li><a href="/services">Dịch vụ</a></li>
          <li><a href="/pricing">Bảng giá</a></li>
          <li><a href="/booking">Đặt lịch</a></li>
        </ul>
      </div>

      <!-- Company Info -->
      <div class="sbf-links">
        <h3 class="sbf-heading">Thông tin</h3>
        <ul>
          <li><a href="/about">Về chúng tôi</a></li>
          <li><a href="/blog">Blog</a></li>
          <li><a href="/contact">Liên hệ</a></li>
          <li><a href="/terms">Điều khoản</a></li>
        </ul>
      </div>

      <!-- Contact -->
      <div class="sbf-contact">
        <h3 class="sbf-heading">Liên hệ</h3>
        <ul>
          <li>
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
            <span>12B2 Nguyễn Văn Linh, Tân An, Ninh kiều, Cần Thơ</span>
          </li>
          <li>
            <i class="fas fa-phone" aria-hidden="true"></i>
            <a href="tel:+84907814560">(+84) 90 7814 560</a>
          </li>
          <li>
            <i class="fas fa-envelope" aria-hidden="true"></i>
            <a href="mailto:stygianblue.studio.vn@gmail.com">stygianblue.studio.vn@gmail.com</a>
          </li>
        </ul>
      </div>
    </div>

    <!-- Bottom bar -->
    <div class="sbf-bottom">
      <p class="sbf-copy">© <span id="sbf-year"></span> Stygian Blue Studio. All rights reserved.</p>
      
      <nav class="sbf-social" aria-label="Mạng xã hội">
        <a class="sbf-icon" href="https://www.facebook.com/profile.php?id=61575562047388" target="_blank" rel="noopener" aria-label="Facebook">
          <i class="fab fa-facebook-f" aria-hidden="true"></i>
        </a>
        <a class="sbf-icon" href="https://www.instagram.com" target="_blank" rel="noopener" aria-label="Instagram">
          <i class="fab fa-instagram" aria-hidden="true"></i>
        </a>
        <a class="sbf-icon" href="https://www.youtube.com" target="_blank" rel="noopener" aria-label="YouTube">
          <i class="fab fa-youtube" aria-hidden="true"></i>
        </a>
      </nav>
    </div>
  </footer>
</section>

<style>
/* ====== Clean & Professional Footer Styles ====== */
[data-sb-footer] { 
  position: relative; 
  isolation: isolate; 
  color: #f8fafc; 
  border-radius: 1rem;
}

/* Background */
[data-sb-footer] .sbf-bg {
  position: absolute; 
  inset: 0;
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  border-radius: inherit;
}

/* Container */
[data-sb-footer] .footer-container {
  position: relative; 
  z-index: 1;
  padding: 3rem clamp(1.5rem, 5vw, 4rem);
  max-width: 1400px;
  margin: 0 auto;
}

/* Main grid */
[data-sb-footer] .sbf-main {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1.5fr;
  gap: 3rem;
  padding-bottom: 2.5rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.2);
}

/* Brand */
[data-sb-footer] .sbf-brand { 
  display: flex; 
  gap: 1rem; 
  align-items: flex-start; 
}

[data-sb-footer] .sbf-logo { 
  width: 50px; 
  height: 50px; 
  object-fit: contain;
  border-radius: 0.5rem; 
  background: #ffffff;
  padding: 0.25rem;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

[data-sb-footer] .sbf-name { 
  font-weight: 700; 
  font-size: clamp(1.125rem, 2vw, 1.375rem); 
  margin: 0;
  color: #f8fafc;
}

[data-sb-footer] .sbf-tagline { 
  margin: 0.375rem 0 0; 
  font-size: 0.875rem; 
  color: rgba(226, 232, 240, 0.7); 
  line-height: 1.5; 
}

/* Links */
[data-sb-footer] .sbf-heading { 
  font-size: 0.875rem; 
  font-weight: 600; 
  text-transform: uppercase; 
  letter-spacing: 0.05em; 
  color: rgba(148, 163, 184, 0.9); 
  margin: 0 0 1rem; 
}

[data-sb-footer] .sbf-links ul,
[data-sb-footer] .sbf-contact ul { 
  list-style: none; 
  margin: 0; 
  padding: 0; 
  display: flex;
  flex-direction: column;
  gap: 0.625rem; 
}

[data-sb-footer] .sbf-links a { 
  color: rgba(226, 232, 240, 0.85); 
  text-decoration: none; 
  font-size: 0.9375rem;
  transition: color 0.2s ease;
}

[data-sb-footer] .sbf-links a:hover { 
  color: #60a5fa; 
}

/* Contact */
[data-sb-footer] .sbf-contact li { 
  display: flex; 
  gap: 0.625rem; 
  align-items: flex-start; 
  color: rgba(226, 232, 240, 0.85); 
  font-size: 0.875rem; 
}

[data-sb-footer] .sbf-contact i { 
  margin-top: 0.125rem; 
  color: #60a5fa; 
  min-width: 1rem;
}

[data-sb-footer] .sbf-contact a { 
  color: inherit; 
  text-decoration: none;
  transition: color 0.2s ease;
}

[data-sb-footer] .sbf-contact a:hover { 
  color: #60a5fa; 
}

/* Bottom bar */
[data-sb-footer] .sbf-bottom { 
  margin-top: 2rem; 
  display: flex; 
  flex-wrap: wrap; 
  justify-content: space-between; 
  align-items: center; 
  gap: 1.5rem; 
}

[data-sb-footer] .sbf-copy { 
  margin: 0; 
  font-size: 0.875rem; 
  color: rgba(226, 232, 240, 0.6); 
}

/* Social icons */
[data-sb-footer] .sbf-social { 
  display: flex; 
  gap: 0.75rem; 
}

[data-sb-footer] .sbf-icon { 
  width: 36px; 
  height: 36px; 
  border-radius: 0.5rem; 
  display: inline-grid; 
  place-items: center;
  color: #f8fafc; 
  background: rgba(100, 116, 139, 0.2);
  border: 1px solid rgba(148, 163, 184, 0.2);
  transition: all 0.2s ease;
  text-decoration: none;
}

[data-sb-footer] .sbf-icon:hover { 
  background: rgba(100, 116, 139, 0.3);
  border-color: rgba(148, 163, 184, 0.4);
  transform: translateY(-2px);
}

[data-sb-footer] .sbf-icon:focus { 
  outline: 2px solid #60a5fa; 
  outline-offset: 2px; 
}

/* Responsive */
@media (max-width: 1024px) {
  [data-sb-footer] .sbf-main {
    grid-template-columns: 1fr 1fr;
    gap: 2.5rem;
  }
}

@media (max-width: 640px) {
  [data-sb-footer] .footer-container { 
    padding: 2rem 1.5rem; 
  }
  
  [data-sb-footer] .sbf-main {
    grid-template-columns: 1fr;
    gap: 2rem;
  }
  
  [data-sb-footer] .sbf-bottom { 
    flex-direction: column;
    text-align: center;
    gap: 1rem;
  }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  [data-sb-footer] .sbf-icon,
  [data-sb-footer] .sbf-links a,
  [data-sb-footer] .sbf-contact a { 
    transition: none !important; 
  }
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
