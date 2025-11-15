(function(){
  if (typeof window === 'undefined') {
    return;
  }

  const loader = document.querySelector('[data-sb-loader]');
  if (!loader) {
    return;
  }

  const root = document.documentElement;
  const body = document.body;

  function lockScroll(){
    root.classList.add('sb-prevent-scroll');
    body.classList.add('sb-prevent-scroll');
  }

  function unlockScroll(){
    root.classList.remove('sb-prevent-scroll');
    body.classList.remove('sb-prevent-scroll');
  }

  function hideLoader(){
    if (loader.classList.contains('is-hidden')) {
      return;
    }

    loader.classList.add('is-hidden');
    unlockScroll();
    loader.addEventListener('transitionend', function handleTransitionEnd(){
      loader.removeEventListener('transitionend', handleTransitionEnd);
      if (loader.parentElement) {
        loader.parentElement.removeChild(loader);
      }
    });
    window.setTimeout(function(){
      if (loader && loader.parentElement) {
        loader.parentElement.removeChild(loader);
      }
    }, 500);
  }

  lockScroll();

  if (document.readyState === 'complete') {
    window.setTimeout(hideLoader, 150);
  } else {
    window.addEventListener('load', function(){
      window.setTimeout(hideLoader, 300);
    });
  }

  window.addEventListener('pageshow', function(event){
    if (event.persisted) {
      hideLoader();
    }
  });
})();
