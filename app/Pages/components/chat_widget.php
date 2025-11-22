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
      <!-- AI Assistant -->
      <button class="sb-ai-dial-btn sb-ai-btn-ai" type="button" title="Hỏi trợ lý AI" data-dial-action="assistant">
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
      <button class="sb-ai-dial-btn sb-ai-btn-zalo" type="button" title="Chat Zalo" data-open-link="https://zalo.me/0909123456">
        <span class="sb-ai-dial-badge" data-dial-badge="zalo"></span>
        <span style="font-weight:900;font-size:18px;color:#fff">Z</span>
      </button>

      <!-- Messenger (mở link ngoài) -->
      <button class="sb-ai-dial-btn sb-ai-btn-mess" type="button" title="Messenger" data-open-link="https://m.me/TEN_PAGE_CUA_BAN">
        <span class="sb-ai-dial-badge" data-dial-badge="messenger"></span>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
        </svg>
      </button>
    </div>

    <button class="sb-ai-chat-toggle" type="button" aria-label="Mở speed dial AI">
      <span class="sb-ai-toggle-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="28" height="28">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      </span>
      <span class="sb-ai-toggle-close" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
