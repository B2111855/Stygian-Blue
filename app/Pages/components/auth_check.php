<?php
// ========================================================
//  auth_overlay.php — Phiên bản hoàn chỉnh (v4.2)
//  ✅ Bật popup sau 5s nếu chưa đăng nhập
//  ✅ Nền mờ + khóa cuộn (overflow hidden) khi popup bật
//  ✅ "👀 Xem tiếp không đăng nhập" (ghi nhớ sessionStorage)
//  ✅ Câu nhấn mạnh: "Bạn cần đăng nhập để đặt lịch hẹn."
//  ✅ A11y: role="dialog", aria-*, focus trap, ESC đóng, Enter = Login
//  ✅ API: data-requires-login để bật popup tức thì (có data-redirect)
//  ✅ Phân biệt truy cập vs tải lại trang: hiện lại khi reload (cấu hình được)
//  ✅ TTL cho suppress (mặc định 30 phút) — tự bật lại sau TTL
//  ✅ Không phụ thuộc vào Tailwind build
// ========================================================

$isLoggedIn = isset($_SESSION['user']);
$redirect   = urlencode($_SERVER['REQUEST_URI'] ?? '/');

// Đặt cookie đánh dấu trạng thái đăng nhập để JS đọc được
if ($isLoggedIn) {
    // Phiên hiện tại đã đăng nhập → đặt cookie tạm (sẽ mất khi đóng trình duyệt)
    setcookie('sb_logged_in', '1', 0, '/');
} else {
    // Chưa đăng nhập → xóa cookie nếu còn sót
    setcookie('sb_logged_in', '', time() - 3600, '/');
}

?>

<script>
window.__SB_IS_LOGGED_IN__ = <?= $isLoggedIn ? 'true' : 'false' ?>;
</script>

<?php if (!$isLoggedIn): ?>
<style>
  /* ===== Styles scoped cho overlay ===== */
  @media (prefers-reduced-motion: reduce){
    #sb-auth-overlay, #sb-auth-overlay * { animation: none !important; transition: none !important; }
  }
  @keyframes sbFade { from {opacity:0} to {opacity:1} }
  @keyframes sbPop  { from {transform: translateY(10px) scale(.98); opacity:0} to {transform: translateY(0) scale(1); opacity:1} }

  .sb-no-scroll { overflow: hidden !important; }
  .sb-focus:focus { outline: 3px solid #22d3ee; outline-offset: 2px; }

  .sb-btn { display:inline-flex; align-items:center; justify-content:center; height:44px; border-radius:12px; font-weight:700; text-decoration:none; cursor:pointer; user-select:none; }
  .sb-btn-primary { background:#0284c7; color:#fff; border:0; }
  .sb-btn-primary:hover { background:#0369a1; }
  .sb-btn-ghost { background:#fff; color:#0f172a; border:1px solid #cbd5e1; }
  .sb-btn-ghost:hover { background:#f1f5f9; }
  .sb-btn-sm { height:auto; padding:8px 10px; border-radius:8px; font-weight:600; }

  .sb-badge {
    display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;
    border-radius:9999px;background:#ecfdf5;color:#047857;font-weight:800;font-size:12px;flex:none
  }

  .sb-grid { display:grid; gap:12px; grid-template-columns: 1fr; }
  @media (min-width: 420px){ .sb-grid { grid-template-columns: 1fr 1fr; } }
</style>

<script>
(function(){
  const IS_LOGGED_IN   = !!window.__SB_IS_LOGGED_IN__;
   // Hàm đọc cookie thực tế để kiểm tra login hiện tại
  function isCurrentlyLoggedInByCookie() {
    return document.cookie.split(';').some(c => c.trim().startsWith('sb_logged_in=1'));
  }
  const SUPPRESS_KEY   = 'sb_auth_overlay_suppress';
  const SUPPRESS_TTL_MS= 30 * 60 * 1000; // 30 phút
  const DELAY_MS       = 5000;           // bật sau 5 giây
  const BLUR_SEL       = '#app, main, #root'; // ưu tiên blur app/main/root
  const FORCE_ON_RELOAD= true;           // hiện lại khi reload trang

  const qs  = (s,r=document)=>r.querySelector(s);
  const qsa = (s,r=document)=>Array.from(r.querySelectorAll(s));
  const create = (t,attrs={}) => Object.assign(document.createElement(t), attrs);

  /* ---------- suppress with TTL ---------- */
  function setSuppress(reason='manual'){
    try{ sessionStorage.setItem(SUPPRESS_KEY, JSON.stringify({ reason, ts: Date.now() })); }catch(_){}
  }
  function readSuppress(){
    try{ return JSON.parse(sessionStorage.getItem(SUPPRESS_KEY) || 'null'); }catch(_){ return null; }
  }
  function isSuppressed(){
    const v = readSuppress();
    if(!v) return false;
    if(Date.now() - (v.ts || 0) > SUPPRESS_TTL_MS){
      sessionStorage.removeItem(SUPPRESS_KEY);
      return false;
    }
    return true;
  }
  function clearSuppress(){ sessionStorage.removeItem(SUPPRESS_KEY); }

  /* ---------- navigation type: navigate | reload | back_forward ---------- */
  function getNavType(){
    const nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
    if(nav && nav.type) return nav.type;
    if(performance.navigation && performance.navigation.type === 1) return 'reload';
    return 'navigate';
  }

  const getBlurRoot = ()=> qs('#app') || qs('main') || qs('#root') || document.body;

  let singleton; // đảm bảo chỉ 1 instance

  function buildOverlay(redirectUrl){
    if (singleton) return singleton;

    const redirect = redirectUrl || (location.pathname + location.search);
    const loginUrl = '/StygianBlue/login.php?redirect=' + encodeURIComponent(redirect);
    const regUrl   = '/StygianBlue/register.php?redirect=' + encodeURIComponent(redirect);

    const overlay = create('div', {
      id: 'sb-auth-overlay',
      role:'dialog',
      'aria-modal': 'true',
      'aria-labelledby':'sb-auth-title',
      'aria-describedby':'sb-auth-desc',
      style: `
        position:fixed; inset:0; z-index:999999; display:grid; place-items:center;
        background:rgba(2,6,23,.55); backdrop-filter:blur(4px); animation: sbFade .22s ease-out both;
      `
    });

    const card = create('div', {
      style: `
        width:min(92vw,620px); border-radius:18px; background:#ffffff; color:#0f172a;
        box-shadow:0 24px 72px rgba(0,0,0,.28); padding:22px 20px 16px; animation: sbPop .28s ease-out both;
      `
    });

    // Header
    const header = create('div', { style:'display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:8px;' });

    const hwrap  = create('div', { style:'display:flex; align-items:center; gap:12px;' });
    const icon   = create('div', { 'aria-hidden':'true', style:'width:40px; height:40px; border-radius:12px; background:#e0f2fe; display:flex; align-items:center; justify-content:center; font-size:22px; color:#0369a1; font-weight:900;' });
    icon.textContent = '🔒';
    const title  = create('div');
    const h1     = create('h1', { id:'sb-auth-title', textContent:'Đăng nhập để dùng đầy đủ tính năng', style:'margin:0; font-size:20px; font-weight:800' });
    const sub    = create('p', { id:'sb-auth-desc', textContent:'Bạn cần đăng nhập để đặt lịch hẹn, theo dõi hóa đơn và nhận ưu đãi cá nhân hoá.', style:'margin:4px 0 0; color:#64748b; font-size:14px' });
    title.append(h1, sub);
    hwrap.append(icon, title);

    const closeX = create('button', {
      type:'button', 'aria-label':'Đóng cửa sổ',
      className:'sb-focus',
      style:'border:0; background:transparent; width:36px; height:36px; border-radius:10px; font-size:18px; cursor:pointer; color:#475569;'
    });
    closeX.textContent = '✕';
    closeX.onmouseenter = ()=> closeX.style.background = '#f1f5f9';
    closeX.onmouseleave = ()=> closeX.style.background = 'transparent';

    header.append(hwrap, closeX);

    // Bullets
    const note   = create('p', { textContent:'Lợi ích khi đăng nhập:', style:'margin:10px 0 8px; color:#0f172a; font-weight:700; font-size:14px' });
    const benefits = ['Lưu & đồng bộ lịch hẹn, hóa đơn', 'Theo dõi tiến độ & nhận thông báo', 'Nhận ưu đãi & đề xuất phù hợp'];
    const ul = create('ul', { style:'margin:8px 0 16px; padding:0; list-style:none; display:grid; gap:8px; color:#475569; font-size:14px' });
    benefits.forEach(t=>{
      const li = create('li', { style:'display:flex; align-items:center; gap:8px' });
      li.innerHTML = `<span class="sb-badge">✓</span><span>${t}</span>`;
      ul.append(li);
    });

    // Callout
    const callout = create('div', {
      style: 'margin:6px 0 14px; padding:10px 12px; border-radius:12px; background:#f0f9ff; color:#0369a1; font-weight:700;'
    });
    callout.textContent = 'Bạn cần đăng nhập để đặt lịch hẹn.';

    // Actions
    const grid = create('div', { className: 'sb-grid' });
    const aLogin = create('a', {
      href: loginUrl, role:'button', className:'sb-btn sb-btn-primary sb-focus',
      textContent:'Chuyển đến trang đăng nhập', style:'font-size:14px;'
    });
    const aReg = create('a', {
      href: regUrl, role:'button', className:'sb-btn sb-btn-ghost sb-focus',
      textContent:'Chuyển đến trang đăng ký', style:'font-size:14px;'
    });
    aLogin.addEventListener('click', ()=>setSuppress('auth_nav'));
    aReg  .addEventListener('click', ()=>setSuppress('auth_nav'));
    grid.append(aLogin, aReg);

    // Footer
    const footer = create('div', { style:'margin-top:14px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; font-size:13px' });
    const continueBtn = create('button', { type:'button', className:'sb-btn-sm sb-focus', textContent:'👀 Xem tiếp không đăng nhập', style:'border:0; background:transparent; color:#475569; padding:8px 10px;' });
    continueBtn.onmouseenter = ()=> continueBtn.style.background = '#f1f5f9';
    continueBtn.onmouseleave = ()=> continueBtn.style.background = 'transparent';

    const right = create('div', { style:'display:flex; gap:14px; flex-wrap:wrap;' });
    const goHome = create('a', { href:'/', className:'sb-focus', textContent:'⟵ Về trang chủ', style:'color:#0369a1; text-decoration:none;' });
    goHome.onmouseenter = ()=> goHome.style.textDecoration='underline';
    goHome.onmouseleave = ()=> goHome.style.textDecoration='none';
    const help = create('a', { href:'/app/Pages/help.php', className:'sb-focus', textContent:'Cần hỗ trợ?', style:'color:#64748b; text-decoration:none;' });
    help.onmouseenter = ()=> help.style.textDecoration='underline';
    help.onmouseleave = ()=> help.style.textDecoration='none';
    right.append(goHome, help);

    footer.append(continueBtn, right);

    card.append(header, note, ul, callout, grid, footer);
    overlay.append(card);

    // ===== open/close with blur + scroll lock + focus trap =====
    const blurRoot = getBlurRoot();
    let lastActive = null;
    let cleanupFns = [];

    function trapFocus(e){
      if (e.key !== 'Tab') return;
      const focusables = qsa('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])', overlay)
        .filter(el=> el.offsetParent !== null);
      if (!focusables.length) return;
      const first = focusables[0];
      const last  = focusables[focusables.length-1];
      if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
      else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
    }

    function onKey(e){
      if (e.key === 'Escape'){ close(true); }
      if (e.key === 'Enter'){ aLogin.click(); }
    }

    function open(){
      lastActive = document.activeElement;
      document.body.appendChild(overlay);
      // mờ nền
      blurRoot.style.filter='blur(3px)';
      blurRoot.style.pointerEvents='none';
      blurRoot.style.userSelect='none';
      // khóa cuộn
      document.documentElement.classList.add('sb-no-scroll');
      document.body.classList.add('sb-no-scroll');
      // bind events
      overlay.addEventListener('keydown', trapFocus);
      document.addEventListener('keydown', onKey);
      closeX.addEventListener('click', ()=>close(true));
      continueBtn.addEventListener('click', ()=>close(true));
      // click nền ngoài để đóng
      overlay.addEventListener('click', (ev)=>{
        if (ev.target === overlay){ close(true); }
      });
      // focus
      setTimeout(()=> aLogin.focus(), 50);
      cleanupFns = [
        ()=> overlay.removeEventListener('keydown', trapFocus),
        ()=> document.removeEventListener('keydown', onKey),
      ];
    }

    function close(persist){
      try { cleanupFns.forEach(fn=>fn()); } catch(e){}
      cleanupFns = [];
      overlay.remove();
      blurRoot.style.filter='';
      blurRoot.style.pointerEvents='';
      blurRoot.style.userSelect='';
      document.documentElement.classList.remove('sb-no-scroll');
      document.body.classList.remove('sb-no-scroll');
      if (persist) setSuppress('continue');
      if (lastActive && typeof lastActive.focus === 'function') lastActive.focus();
    }

    singleton = { overlay, open, close };
    return singleton;
  }

  /* ---------- Boot logic: phân biệt navigate | reload | back/forward ---------- */
 function bootDelayed(){
  // Nếu hiện tại cookie báo đã đăng nhập thì không bật popup
  if (isCurrentlyLoggedInByCookie()) return;

  const navType = getNavType();

  if (navType === 'reload' && FORCE_ON_RELOAD){
    const inst = buildOverlay();
    setTimeout(() => {
      // Kiểm tra lần nữa SÁT LÚC BẬT
      if (!isCurrentlyLoggedInByCookie()) inst.open();
    }, DELAY_MS);
    return;
  }

  if (!isSuppressed()){
    const inst = buildOverlay();
    setTimeout(() => {
      if (!isCurrentlyLoggedInByCookie()) inst.open();
    }, DELAY_MS);
  }
}

  function bindRequireLogin(){
    if (IS_LOGGED_IN) return;
    const els = qsa('[data-requires-login]');
    if (!els.length) return;
    const inst = buildOverlay();
    els.forEach(el=>{
      el.addEventListener('click', (e)=>{
        if (isSuppressed()) return; // đã "xem tiếp" trong TTL thì không chặn nữa
        e.preventDefault();
        const r = el.getAttribute('data-redirect');
        if (r){
          try{
            const loginA = qs('a[href^="/app/Pages/login.php?"]', inst.overlay);
            const regA   = qs('a[href^="/app/Pages/register.php?"]', inst.overlay);
            const loginUrl = '/app/Pages/login.php?redirect=' + encodeURIComponent(r);
            const regUrl   = '/app/Pages/register.php?redirect=' + encodeURIComponent(r);
            if (loginA) loginA.href = loginUrl;
            if (regA)   regA.href   = regUrl;
          }catch(_){}
        }
        inst.open();
      }, { passive:false });
    });
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', bootDelayed);
    document.addEventListener('DOMContentLoaded', bindRequireLogin);
  } else {
    bootDelayed();
    bindRequireLogin();
  }

  // Public API
  window.SBAuthOverlay = {
    triggerNow: (redirectUrl)=>{
      if (IS_LOGGED_IN) return;
      const { open } = buildOverlay(redirectUrl);
      open();
    },
    suppress: ()=>setSuppress('manual'),
    clearSuppress: ()=>clearSuppress()
  };
})();
</script>
<?php endif; ?>
