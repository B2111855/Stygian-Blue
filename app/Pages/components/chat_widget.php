<!-- components/chat_widget.php -->

<link rel="stylesheet" href="../../../public/css/ai_chat.css">

<div data-ai-chat-widget data-endpoint="../Controller/ai_chat.php" data-default-tab="assistant">
  <button class="sb-ai-chat-toggle" type="button" aria-label="Mở trung tâm hỗ trợ Stygian Blue">
    <span class="sb-ai-toggle-icon">AI</span>
  </button>

  <div class="sb-ai-chat-window" role="dialog" aria-label="Trung tâm hỗ trợ Stygian Blue">
    <div class="sb-ai-chat-header">
      <div>
        <h4>Trung tâm hỗ trợ</h4>
        <small>Chọn kênh phù hợp để trò chuyện</small>
      </div>
      <button type="button" data-ai-chat-close aria-label="Đóng chat" class="sb-ai-close">&times;</button>
    </div>

    <div class="sb-ai-chat-tabs" role="tablist">
      <button class="sb-ai-tab is-active" role="tab" aria-selected="true" data-tab="assistant">Trợ lý AI</button>
      <button class="sb-ai-tab" role="tab" aria-selected="false" data-tab="zalo">Zalo</button>
      <button class="sb-ai-tab" role="tab" aria-selected="false" data-tab="messenger">Messenger</button>
    </div>

    <div class="sb-ai-chat-content">
      <div class="sb-ai-panel is-active" data-panel="assistant" role="tabpanel">
        <div class="sb-ai-chat-body" aria-live="polite"></div>

        <div class="sb-ai-typing">Đang soạn phản hồi...</div>

        <div class="sb-ai-chat-input">
          <form autocomplete="off">
            <textarea rows="1" placeholder="Nhập câu hỏi của bạn..." required></textarea>
            <button type="submit" data-ai-chat-send>Gửi</button>
          </form>
        </div>

        <div class="sb-ai-channel-switch" role="group" aria-label="Chuyển kênh hỗ trợ">
          <span class="sb-ai-channel-label">Chuyển nhanh sang:</span>
          <div class="sb-ai-channel-actions">
            <button type="button" class="sb-ai-channel-button" data-switch-tab="zalo">Zalo</button>
            <button type="button" class="sb-ai-channel-button" data-switch-tab="messenger">Messenger</button>
          </div>
        </div>

        <div class="sb-ai-chat-footer">Trò chuyện được ghi lại để hỗ trợ dịch vụ tốt hơn.</div>
      </div>

      <div class="sb-ai-panel" data-panel="zalo" role="tabpanel" aria-hidden="true">
        <div class="sb-ai-service-panel">
          <div class="sb-ai-service-header">
            <img src="../../../public/images/zalo.png" alt="Zalo" width="32" height="32">
            <div>
              <h5>Chat trực tiếp Zalo</h5>
              <p>Ưu tiên kết nối nhanh qua Zalo với đội hỗ trợ của Stygian Blue.</p>
            </div>
          </div>
          <div class="sb-ai-service-actions">
            <button type="button" class="sb-ai-service-button" data-open-link="https://zalo.me/0907814560">Mở chat Zalo</button>
            <button type="button" class="sb-ai-service-button is-secondary" data-copy="0907814560">Sao chép số Zalo</button>
          </div>
        </div>
      </div>

      <div class="sb-ai-panel" data-panel="messenger" role="tabpanel" aria-hidden="true">
        <div class="sb-ai-service-panel">
          <div class="sb-ai-service-header">
            <img src="../../../public/images/mess_icon.png" alt="Messenger" width="32" height="32">
            <div>
              <h5>Chat trực tiếp Messenger</h5>
              <p>Gặp gỡ chuyên viên qua Facebook Messenger để được tư vấn chi tiết.</p>
            </div>
          </div>
          <div class="sb-ai-service-actions">
            <button type="button" class="sb-ai-service-button" data-open-link="https://m.me/61575562047388">Mở chat Messenger</button>
            <button type="button" class="sb-ai-service-button is-secondary" data-copy="https://m.me/61575562047388">Sao chép link Messenger</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../../../public/js/ai_chat.js" defer></script>
