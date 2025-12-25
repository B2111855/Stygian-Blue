(function(){
  try{
    var sections = document.querySelectorAll('.sb-hero');
    if(!sections.length) return;
    sections.forEach(function(section){
      var imgEl = section.querySelector('img');
      var src = imgEl ? (imgEl.currentSrc || imgEl.src) : null;
      if(!src){
        var bg = getComputedStyle(section).backgroundImage;
        var m = bg && bg.match(/url\(["']?(.+?)["']?\)/);
        if(m) src = m[1];
      }
      if(!src) return;

      var img = new Image();
      img.crossOrigin = 'anonymous';
      img.src = src;
      img.onload = function(){
        try{
          var canvas = document.createElement('canvas');
          var ctx = canvas.getContext('2d');
          var w = canvas.width = Math.min(64, img.naturalWidth || img.width || 64);
          var h = canvas.height = Math.min(64, img.naturalHeight || img.height || 64);
          ctx.drawImage(img, 0, 0, w, h);
          var data = ctx.getImageData(0, 0, w, h).data;
          var r=0,g=0,b=0,n=0;
          for(var i=0;i<data.length;i+=4){ r+=data[i]; g+=data[i+1]; b+=data[i+2]; n++; }
          if(!n) return;
          var R=r/n, G=g/n, B=b/n;
          var luma = 0.2126*R + 0.7152*G + 0.0722*B; // Rec.709
          section.dataset.luma = (luma >= 170) ? 'light' : 'dark';
        }catch(err){ /* no-op */ }
      };
    });
  }catch(e){ /* silent fail */ }
})();
