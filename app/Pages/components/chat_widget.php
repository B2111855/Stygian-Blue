<!-- components/chat_widget.php -->

<!-- Zalo Floating Button -->
<div style="position: fixed; bottom: 90px; right: 20px; z-index: 999;">
  <a href="https://zalo.me/0907814560" target="_blank" aria-label="Chat với Zalo">
    <img src="../../../public/images/zalo.png"
         alt="Chat Zalo"
         width="60"
         height="60"
         style="border-radius: 50%;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                transition: transform 0.3s ease;">
  </a>
</div>

<!-- Messenger Floating Button -->
<div style="position: fixed; bottom: 20px; right: 20px; z-index: 999;">
  <a href="https://m.me/61575562047388" target="_blank" aria-label="Chat với Messenger">
    <img src="../../../public/images/mess_icon.png"
         alt="Chat Messenger"
         width="60"
         height="60"
         style="border-radius: 50%;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                transition: transform 0.3s ease;">
  </a>
</div>

<!-- Hover Effects -->
<style>
  div[style*="position: fixed"] a img:hover {
    transform: scale(1.1);
  }
</style>
