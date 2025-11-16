(function(){
  function initTabs(root){
    const tabs = Array.from(root.querySelectorAll('.ms-tab'));
    const panels = Array.from(root.querySelectorAll('.ms-tab-panel'));
    function activate(idx){
      tabs.forEach((t,i)=> t.classList.toggle('active', i===idx));
      panels.forEach((p,i)=> p.classList.toggle('active', i===idx));
    }
    tabs.forEach((tab, idx)=>{
      tab.addEventListener('click', ()=> activate(idx));
      tab.addEventListener('keydown', (e)=>{
        if(e.key==='ArrowRight'){ e.preventDefault(); const n=(idx+1)%tabs.length; activate(n); tabs[n].focus(); }
        if(e.key==='ArrowLeft'){ e.preventDefault(); const p=(idx-1+tabs.length)%tabs.length; activate(p); tabs[p].focus(); }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.manna-scout').forEach(initTabs);
  });
})();

(function(){
  function onReady(fn){
    if(document.readyState === 'complete' || document.readyState === 'interactive'){
      setTimeout(fn, 0);
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  onReady(function(){
    document.querySelectorAll('.manna-scout').forEach(function(root){
      const tabs = root.querySelectorAll('.ms-tab');
      const panels = root.querySelectorAll('.ms-tab-panel');
      tabs.forEach(function(tab){
        tab.addEventListener('click', function(){
          const targetId = tab.getAttribute('data-target');
          // toggle active classes
          tabs.forEach(function(t){ t.classList.remove('active'); });
          panels.forEach(function(p){ p.classList.remove('active'); });
          tab.classList.add('active');
          const panel = root.querySelector('#' + CSS.escape(targetId));
          if(panel){ panel.classList.add('active'); }
        });
      });

      // Gallery click -> update main image
      root.querySelectorAll('.ms-tab-panel').forEach(function(panel){
        const mainImg = panel.querySelector('.ms-photo img');
        if (!mainImg) return;
        panel.querySelectorAll('.ms-gallery .ms-thumb').forEach(function(thumb){
          thumb.addEventListener('click', function(){
            const img = thumb.querySelector('img');
            if (img && img.src) {
              mainImg.src = img.src;
            }
          });
        });
      });
    });
  });
})();


