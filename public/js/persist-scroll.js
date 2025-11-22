(function(){
  function throttle(fn, wait){
    let last = 0; let timer = null;
    return function(){
      const now = Date.now();
      const remaining = wait - (now - last);
      const ctx = this; const args = arguments;
      if (remaining <= 0) {
        if (timer) { clearTimeout(timer); timer = null; }
        last = now;
        fn.apply(ctx, args);
      } else if (!timer) {
        timer = setTimeout(function(){
          last = Date.now();
          timer = null;
          fn.apply(ctx, args);
        }, remaining);
      }
    };
  }

  function makeKey(base){
    // Persist by role/sidebar type across internal navigation
    return 'sb:scroll:' + base;
  }

  function restore(el, key){
    try{
      const v = localStorage.getItem(key);
      if (v !== null) {
        const n = parseInt(v, 10);
        if (!isNaN(n)) {
          el.scrollTop = n;
        }
      }
    }catch(e){ /* ignore */ }
  }

  function save(el, key){
    try{
      localStorage.setItem(key, String(el.scrollTop || 0));
    }catch(e){ /* ignore */ }
  }

  function init(){
    const nodes = document.querySelectorAll('[data-persist-scroll]');
    nodes.forEach(function(el){
      const base = el.getAttribute('data-persist-scroll') || 'sidebar';
      const key = makeKey(base);
      restore(el, key);

      const onScroll = throttle(function(){ save(el, key); }, 120);
      el.addEventListener('scroll', onScroll, { passive: true });

      // Save on page lifecycle events as backup
      window.addEventListener('beforeunload', function(){ save(el, key); });
      window.addEventListener('pagehide', function(){ save(el, key); });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
