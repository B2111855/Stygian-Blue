(function(){
  const root = document.querySelector('[data-social-widget]');
  if(!root) return;
  const toggle = root.querySelector('#sb-social-toggle');
  const popup = root.querySelector('#sb-social-popup');
  const linkButtons = Array.from(root.querySelectorAll('[data-social-link]'));
  const copyButtons = Array.from(root.querySelectorAll('[data-social-copy]'));
  let isOpen = false;

  const closePopup = () => {
    if(!isOpen) return;
    isOpen = false;
    popup.classList.remove('is-open');
    popup.setAttribute('aria-hidden','true');
    toggle.setAttribute('aria-expanded','false');
  };
  const openPopup = () => {
    if(isOpen) return;
    isOpen = true;
    popup.classList.add('is-open');
    popup.setAttribute('aria-hidden','false');
    toggle.setAttribute('aria-expanded','true');
  };

  toggle.addEventListener('click',()=>{
    if(isOpen) closePopup(); else openPopup();
  });

  document.addEventListener('click',(e)=>{
    if(!root.contains(e.target)) closePopup();
  });

  linkButtons.forEach(btn=>{
    btn.addEventListener('click',()=>{
      const url = btn.dataset.socialLink;
      if(!url) return;
      window.open(url,'_blank','noopener');
    });
  });

  copyButtons.forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const text = btn.dataset.socialCopy;
      if(!text) return;
      try {
        if(navigator.clipboard && navigator.clipboard.writeText){
          await navigator.clipboard.writeText(text);
        } else {
          const ta = document.createElement('textarea');
          ta.value = text; ta.style.position='absolute'; ta.style.left='-9999px';
          document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
        }
        const original = btn.textContent;
        btn.textContent = 'Đã sao chép';
        btn.disabled = true;
        setTimeout(()=>{ btn.textContent = original; btn.disabled = false; },1600);
      } catch(err){
        console.warn('Copy failed', err);
        window.prompt('Sao chép thủ công:', text);
      }
    });
  });
})();