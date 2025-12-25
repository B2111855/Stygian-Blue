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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('sbSyncLoginState')) {
    function sbSyncLoginState(): bool
    {
        $loggedIn = !empty($_SESSION['ID_TK']);

        if (!headers_sent()) {
            if ($loggedIn) {
                setcookie('sb_logged_in', '1', 0, '/');
            } else {
                setcookie('sb_logged_in', '', time() - 3600, '/');
            }
        }

        return $loggedIn;
    }
}

$isLoggedIn = sbSyncLoginState();
$redirect   = urlencode($_SERVER['REQUEST_URI'] ?? '/');

?>

<script>
window.__SB_IS_LOGGED_IN__ = <?= json_encode($isLoggedIn); ?>;
</script>

<?php if (!$isLoggedIn): ?>
<style>
  /* ===== Styles scoped cho overlay ===== */
  @media (prefers-reduced-motion: reduce){
    #sb-auth-overlay, #sb-auth-overlay * { animation: none !important; transition: none !important; }
  }
  @keyframes sbFade { from {opacity:0} to {opacity:1} }
  @keyframes sbPop  { from {transform: translateY(20px) scale(.95); opacity:0} to {transform: translateY(0) scale(1); opacity:1} }
  @keyframes sbShine { 0%, 100% {opacity:0.4} 50% {opacity:0.8} }

  .sb-no-scroll { overflow: hidden !important; }
  .sb-focus:focus { outline: 3px solid #3b82f6; outline-offset: 2px; border-radius: 8px; }

  .sb-btn { 
    display:inline-flex; align-items:center; justify-content:center; gap:8px; height:48px; 
    border-radius:12px; font-weight:600; font-size:15px; text-decoration:none; cursor:pointer; 
    user-select:none; transition: all 0.2s ease; padding: 0 20px;
  }
  .sb-btn-primary { 
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); 
    color:#fff; border:0; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
  }
  .sb-btn-primary:hover { 
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); 
    transform: translateY(-1px); 
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
  }
  .sb-btn-secondary { 
    background:#fff; color:#1e293b; border:2px solid #3b82f6;
  }
  .sb-btn-secondary:hover { 
    background:#eff6ff; border-color:#2563eb; transform: translateY(-1px);
  }
  .sb-btn-sm { height:auto; padding:10px 14px; border-radius:8px; font-weight:600; font-size:14px; }

  .sb-badge {
    display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;
    border-radius:9999px;background:linear-gradient(135deg, #10b981, #059669);
    color:#fff;font-weight:800;font-size:13px;flex:none;box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
  }

  .sb-grid { display:grid; gap:14px; grid-template-columns: 1fr; }
  @media (min-width: 420px){ .sb-grid { grid-template-columns: 1fr 1fr; } }
  
  .sb-logo-shine { animation: sbShine 2s ease-in-out infinite; }
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
  const getAIChatWidget = ()=> qs('[data-ai-chat-widget]');

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
        position:fixed; inset:0; z-index:999; display:grid; place-items:center;
        background:rgba(2,6,23,.55); backdrop-filter:blur(4px); animation: sbFade .22s ease-out both;
      `
    });

    const card = create('div', {
      style: `
        width:min(94vw,580px); border-radius:20px; background:#ffffff; color:#0f172a;
        box-shadow:0 25px 50px -12px rgba(0,0,0,.25), 0 12px 24px rgba(0,0,0,.12); 
        padding:28px 26px 24px; animation: sbPop .32s cubic-bezier(0.34, 1.56, 0.64, 1) both;
      `
    });

    // Header
    const header = create('div', { style:'display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:8px;' });

    const hwrap  = create('div', { style:'display:flex; align-items:center; gap:14px;' });
    const icon   = create('div', { 'aria-hidden':'true', style:'width:56px; height:56px; border-radius:14px; background:#fff; display:flex; align-items:center; justify-content:center; padding:6px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);' });
    const logoImg = create('img', { src:'../../../public/images/StygianBlue1.png', alt:'Stygian Blue Studio', style:'width:100%; height:100%; object-fit:contain;', className:'sb-logo-shine' });
    icon.append(logoImg);
    const title  = create('div');
    const h1     = create('h1', { id:'sb-auth-title', textContent:'Đăng nhập để trải nghiệm đầy đủ', style:'margin:0; font-size:22px; font-weight:800; color:#0f172a; line-height:1.3;' });
    const sub    = create('p', { id:'sb-auth-desc', textContent:'Quản lý lịch hẹn, theo dõi đơn hàng và nhận ưu đãi độc quyền.', style:'margin:6px 0 0; color:#64748b; font-size:15px; line-height:1.5;' });
    title.append(h1, sub);
    hwrap.append(icon, title);

    const closeX = create('button', {
      type:'button', 'aria-label':'Đóng cửa sổ',
      className:'sb-focus',
      style:'border:0; background:#f1f5f9; width:40px; height:40px; border-radius:12px; font-size:20px; cursor:pointer; color:#64748b; font-weight:600; transition:all 0.2s ease;'
    });
    closeX.textContent = '✕';
    closeX.onmouseenter = ()=> { closeX.style.background = '#e2e8f0'; closeX.style.color = '#1e293b'; closeX.style.transform = 'rotate(90deg)'; };
    closeX.onmouseleave = ()=> { closeX.style.background = '#f1f5f9'; closeX.style.color = '#64748b'; closeX.style.transform = 'rotate(0deg)'; };

    header.append(hwrap, closeX);

    // Bullets
    const note   = create('p', { textContent:'Bạn sẽ nhận được:', style:'margin:16px 0 10px; color:#1e293b; font-weight:700; font-size:15px' });
    const benefits = ['Quản lý lịch hẹn dễ dàng', 'Theo dõi tiến độ đơn hàng', 'Ưu đãi và tư vấn cá nhân hoá'];
    const ul = create('ul', { style:'margin:0 0 18px; padding:0; list-style:none; display:grid; gap:10px; color:#475569; font-size:15px' });
    benefits.forEach(t=>{
      const li = create('li', { style:'display:flex; align-items:center; gap:8px' });
      li.innerHTML = `<span class="sb-badge">✓</span><span>${t}</span>`;
      ul.append(li);
    });

    // Callout
    const callout = create('div', {
      style: 'margin:0 0 20px; padding:14px 16px; border-radius:12px; background:linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border: 2px solid #3b82f6; display:flex; align-items:center; gap:10px;'
    });
    const calloutIcon = create('span', { textContent: '📌', style: 'font-size:20px; flex:none;' });
    const calloutText = create('span', { textContent: 'Đăng nhập ngay để đặt lịch chụp và nhận tư vấn.', style: 'color:#1e40af; font-weight:600; font-size:14.5px; line-height:1.4;' });
    callout.append(calloutIcon, calloutText);

    // Actions
    const grid = create('div', { className: 'sb-grid' });
    const aLogin = create('a', {
      href: loginUrl, role:'button', className:'sb-btn sb-btn-primary sb-focus'
    });
    aLogin.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg><span>Đăng nhập</span>';
    
    const aReg = create('a', {
      href: regUrl, role:'button', className:'sb-btn sb-btn-secondary sb-focus'
    });
    aReg.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg><span>Đăng ký</span>';
    aLogin.addEventListener('click', ()=>setSuppress('auth_nav'));
    aReg  .addEventListener('click', ()=>setSuppress('auth_nav'));
    grid.append(aLogin, aReg);

    // Footer
    const footer = create('div', { style:'margin-top:20px; padding-top:18px; border-top:1px solid #e2e8f0; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; font-size:14px' });
    const continueBtn = create('button', { type:'button', className:'sb-btn-sm sb-focus', style:'border:0; background:transparent; color:#64748b; padding:10px 12px; font-weight:600; display:flex; align-items:center; gap:6px;' });
    continueBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg><span>Xem tiếp không đăng nhập</span>';
    continueBtn.onmouseenter = ()=> { continueBtn.style.background = '#f1f5f9'; continueBtn.style.color = '#1e293b'; };
    continueBtn.onmouseleave = ()=> { continueBtn.style.background = 'transparent'; continueBtn.style.color = '#64748b'; };

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
      // Khôi phục pointer events cho AI chat widget để có thể kéo thả
      const aiWidget = getAIChatWidget();
      if(aiWidget){ 
        aiWidget.style.pointerEvents='auto'; 
        aiWidget.style.filter='none';
      }
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
      // Reset AI widget styles
      const aiWidget = getAIChatWidget();
      if(aiWidget){ 
        aiWidget.style.pointerEvents=''; 
        aiWidget.style.filter='';
      }
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
