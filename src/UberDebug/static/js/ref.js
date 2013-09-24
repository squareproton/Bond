
var ref = (function(){

    var tip = document.getElementById('rTip');

    return function ref (r) {

        var kbds     = r.querySelectorAll('[data-toggle]'),
            tippable = r.querySelectorAll('[data-tip]'),
            tips     = r.querySelectorAll('div');

        for(var j = 0, n = kbds.length; j < n; j++){
            if(kbds[j].parentNode !== r) {
                kbds[j].onclick = function(e){
                    ('exp' in this.dataset) ? delete this.dataset.exp : this.dataset.exp = 1;
                }
            }
        }

        [].filter.call(tips, function(node){
            return node.parentNode == r;
        });

        for(var j = 0, n = tippable.length; j < n; j++){
            tippable[j].tipRef = tips[tippable[j].dataset.tip];
            tippable[j].onmouseover = function(){
                tip.className = 'ref visible';
                tip.innerHTML = this.tipRef.innerHTML;
                window.clearTimeout(tip.fadeOut);
            };
            tippable[j].onmouseout = function(){
                tip.className = 'ref visible fadingOut';
                tip.fadeOut = window.setTimeout(function(){
                    tip.innerHTML = '';
                    tip.className = '';
                }, 250);
            };
        }

        r.onmousemove = function(e){
            if(tip.className.indexOf('visible') < 0)
                return;
            tip.style.top = ((document.documentElement.clientHeight - e.clientY) < tip.offsetHeight + 20 ? Math.max(e.pageY - tip.offsetHeight, 0) : e.pageY) + 'px';
            tip.style.left = ((document.documentElement.clientWidth - e.clientX) < tip.offsetWidth + 20 ? Math.max(e.pageX - tip.offsetWidth, 0) : e.pageX) + 'px';
        };

    }

})();

window.addEventListener('keydown', function(e){
  if(e.keyCode != 88)
    return;

  var kbds = document.querySelectorAll('.ref [data-toggle]'),
      partlyExp = document.querySelectorAll('.ref [data-toggle][data-exp]').length !== kbds.length;

  e.preventDefault();
  for(var i = 0, m = kbds.length; i < m; i++)
    partlyExp ? (kbds[i].dataset.exp = 1) : (delete kbds[i].dataset.exp);
});