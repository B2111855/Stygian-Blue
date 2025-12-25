<!-- components/chat_widget.php -->
<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$guestKey = $_SESSION['sb_ai_guest_key'] ?? null;
if ($guestKey === null) {
  try {
    $guestKey = bin2hex(random_bytes(8));
  } catch (Exception $exception) {
    $guestKey = substr(hash('sha256', session_id() ?: microtime(true)), 0, 16);
  }
  $_SESSION['sb_ai_guest_key'] = $guestKey;
}

$chatUserKey = !empty($_SESSION['ID_TK'])
  ? 'user_' . $_SESSION['ID_TK']
  : 'guest_' . $guestKey;
?>

<link rel="stylesheet" href="../../../public/css/ai_chat.css">
<link rel="stylesheet" href="../../../public/css/ai_chat_overrides.css">

<!-- Widget AI Chat tách riêng -->
<div data-ai-chat-widget data-endpoint="../Controller/ai_chat.php" data-default-tab="assistant" data-user-key="<?= htmlspecialchars($chatUserKey, ENT_QUOTES, 'UTF-8'); ?>">
  <!-- Speed Dial mở nhanh các kênh: AI / Zalo / Messenger -->
  <div class="sb-ai-speed-dial">
    <div class="sb-ai-dial-actions">
      <!-- AI Assistant (tạm ẩn để tránh xung đột JS) -->
      <button class="sb-ai-dial-btn sb-ai-btn-ai hidden" type="button" title="Hỏi trợ lý AI" data-dial-action="assistant" aria-hidden="true" tabindex="-1" style="display:none">
        <span class="sb-ai-dial-badge" data-dial-badge="assistant"></span>
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="10" rx="2"></rect>
          <circle cx="12" cy="5" r="2"></circle>
          <path d="M12 7v4"></path>
          <line x1="8" y1="16" x2="8" y2="16"></line>
          <line x1="16" y1="16" x2="16" y2="16"></line>
        </svg>
      </button>

      <!-- Zalo (mở link ngoài) -->
      <button class="sb-ai-dial-btn sb-ai-btn-zalo" type="button" title="Chat Zalo" data-open-link="https://zalo.me/0907814560">
        <span class="sb-ai-dial-badge" data-dial-badge="zalo"></span>
        <img src="../../../public/images/zalo.png" alt="Zalo" width="28" height="28">
      </button>

      <!-- Messenger (mở link ngoài) -->
      <button class="sb-ai-dial-btn sb-ai-btn-mess" type="button" title="Messenger" data-open-link="https://m.me/595410993662132">
        <span class="sb-ai-dial-badge" data-dial-badge="messenger"></span>
        <img src="../../../public/images/mess_icon.png" alt="Messenger" width="28" height="28">
      </button>
    </div>

    <button class="sb-ai-chat-toggle" type="button" aria-label="Mở speed dial AI">
      <span class="sb-ai-toggle-icon">
        <img src="../../../public/images/mail.png" alt="Chat icon" width="40" height="40">
      </span>
      <span class="sb-ai-toggle-close" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </span>
    </button>
  </div>

  <div class="sb-ai-chat-window" role="dialog" aria-label="Trung tâm hỗ trợ Stygian Blue" aria-modal="false" data-chat-dialog>
    <div class="sb-ai-chat-header">
      <div class="sb-ai-header-info">
        <div class="sb-ai-header-icon" data-header-icon></div>
        <div class="sb-ai-header-text">
            <h4 data-header-title>Trợ lý AI</h4>
            <small data-header-subtitle>Luôn sẵn sàng hỗ trợ bạn</small>
        </div>
        <span class="sb-ai-status-pill" data-status-pill>Online</span>
      </div>
      <button type="button" data-ai-chat-close aria-label="Đóng chat" class="sb-ai-close">&times;</button>
    </div>

    <div class="sb-ai-chat-content" data-chat-content>
      <div class="sb-ai-panel is-active" data-panel="assistant" role="tabpanel" aria-labelledby="sb-ai-assistant-label">
        <div class="sb-ai-quick-replies" data-quick-replies>
          <button type="button" class="sb-ai-quick-reply" data-quick-text="📅 Đặt lịch hẹn">
            📅 Đặt lịch hẹn
          </button>
          <button type="button" class="sb-ai-quick-reply" data-quick-text="💰 Xem bảng giá">
            💰 Xem bảng giá
          </button>
          <button type="button" class="sb-ai-quick-reply" data-quick-text="📍 Địa chỉ chi nhánh">
            📍 Địa chỉ chi nhánh
          </button>
          <button type="button" class="sb-ai-quick-reply" data-quick-text="⏰ Giờ làm việc">
            ⏰ Giờ làm việc
          </button>
        </div>
        <div class="sb-ai-messages-container">
          <div class="sb-ai-chat-body" aria-live="polite"></div>
          <button type="button" class="sb-ai-scroll-bottom" data-scroll-bottom aria-label="Xuống cuối" title="Xuống cuối">↓</button>
        </div>

        <div class="sb-ai-typing">
          Trợ lý AI đang soạn tin
          <span class="sb-ai-typing-dots">
            <span class="sb-ai-typing-dot"></span>
            <span class="sb-ai-typing-dot"></span>
            <span class="sb-ai-typing-dot"></span>
          </span>
        </div>

        <div class="sb-ai-chat-input">
          <form autocomplete="off">
            <textarea rows="1" placeholder="Nhập câu hỏi" required></textarea>
            <button type="submit" data-ai-chat-send>Gửi</button>
          </form>
        </div>



        <div class="sb-ai-chat-footer" id="sb-ai-assistant-label">Trò chuyện được ghi lại để hỗ trợ dịch vụ tốt hơn.</div>
      </div>

      <!-- Đã bỏ panel Zalo & Messenger khỏi AI Chat -->
    </div>
    <!-- Toast stack for errors/info -->
    <div class="sb-ai-toast-stack" aria-live="assertive" aria-atomic="true"></div>
  </div>
</div>

<script src="../../../public/js/ai_chat.js" defer></script>
