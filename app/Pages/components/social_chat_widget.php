<!-- components/social_chat_widget.php -->
<!-- Dùng đường dẫn tuyệt đối để tránh sai lệch khi include -->
<link rel="stylesheet" href="/StygianBlue/public/css/social_chat.css">

<div class="sb-social-widget" data-social-widget>
  <button type="button" class="sb-social-toggle" id="sb-social-toggle" aria-haspopup="true" aria-expanded="false" aria-label="Mạng xã hội">
    <span class="sb-social-icon">
      <!-- Biểu tượng gộp -->
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="7.5" cy="12" r="5" />
        <circle cx="16.5" cy="12" r="5" />
        <path d="M10 12h4" />
      </svg>
    </span>
  </button>
  <div class="sb-social-popup" id="sb-social-popup" aria-hidden="true" role="menu">
    <div class="sb-social-item" role="none">
      <img src="../../../public/images/zalo.png" alt="Zalo" width="40" height="40" loading="lazy">
      <div class="sb-social-actions" role="group" aria-label="Zalo">
        <button type="button" class="sb-social-btn" data-social-link="https://zalo.me/0907814560" role="menuitem">Chat Zalo</button>
        <button type="button" class="sb-social-btn is-secondary" data-social-copy="0907814560" role="menuitem">Sao chép số</button>
      </div>
    </div>
    <div class="sb-social-item" role="none">
      <img src="../../../public/images/mess_icon.png" alt="Messenger" width="40" height="40" loading="lazy">
      <div class="sb-social-actions" role="group" aria-label="Messenger">
        <button type="button" class="sb-social-btn" data-social-link="https://m.me/61575562047388" role="menuitem">Chat Messenger</button>
        <button type="button" class="sb-social-btn is-secondary" data-social-copy="https://m.me/61575562047388" role="menuitem">Sao chép link</button>
      </div>
    </div>
  </div>
</div>
<script src="/StygianBlue/public/js/social_chat.js" defer></script>