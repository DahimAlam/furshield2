    </div>
  </main>

  <footer class="border-top py-5">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
      <div class="d-flex align-items-center gap-2">
        <span class="logo-badge"><i class="bi bi-shield-heart text-white"></i></span>
        <strong>FurShield</strong>
      </div>
      <ul class="list-inline mb-0 small">
        <li class="list-inline-item"><a class="link-muted" href="<?php echo BASE; ?>/about.php">About</a></li>
        <li class="list-inline-item"><a class="link-muted" href="<?php echo BASE; ?>/contact.php">Contact</a></li>
        <li class="list-inline-item"><a class="link-muted" href="<?php echo BASE; ?>/product-list.php">Catalog</a></li>
      </ul>
      <small class="text-muted">© <?php echo date('Y'); ?> FurShield — All rights reserved.</small>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
(function(){
  const el   = document.getElementById('fsLoader');
  const bar  = document.getElementById('fsLoaderBar');
  if(!el || !bar) return;

  let barTick = null, shown = false, holdTimer = null, minHold = 450;

  function startBar(){
    clearInterval(barTick);
    bar.style.width = '0%';
    let p = 0;
    barTick = setInterval(()=>{
      p += Math.max(1, (95 - p) * 0.07); // ease to 95%
      bar.style.width = p + '%';
    }, 120);
  }
  function endBar(){
    clearInterval(barTick);
    bar.style.width = '100%';
    setTimeout(()=> bar.style.width='0%', 400);
  }

  function show(reason=''){
    if(shown) return;
    shown = true;
    el.classList.remove('is-hidden');
    startBar();
    holdTimer = setTimeout(()=>{}, minHold);
  }
  function hide(){
    if(!shown) return;
    const finish = ()=>{ el.classList.add('is-hidden'); shown=false; endBar(); };
    const left = Math.max(0, minHold - (performance.now() % minHold));
    setTimeout(finish, left);
  }

  window.FSLoader = { show, hide };

  if (window.__fsLoader?.autoShow) {
    show('auto');
    window.addEventListener('load', hide, { once:true });
    setTimeout(hide, 3000); // safety
  }

  window.addEventListener('beforeunload', () => { show('nav'); });

  document.addEventListener('click', (e)=>{
    const a = e.target.closest('a');
    if(!a) return;
    const url = a.getAttribute('href') || '';
    if (a.hasAttribute('data-no-loader') || url.startsWith('#') || url.startsWith('tel:') || url.startsWith('mailto:')) return;
    const same = url.indexOf('http')!==0 || url.startsWith(location.origin);
    if (same) show('link');
  });

  document.addEventListener('submit', (e)=>{
    if (e.target.matches('form[data-loader], form.js-show-loader')) show('form');
  });

  const origFetch = window.fetch;
  window.fetch = function(input, init){
    const t = setTimeout(()=> show('fetch'), 250);
    return origFetch(input, init).finally(()=>{ clearTimeout(t); hide(); });
  };

  const XHR = XMLHttpRequest.prototype, origOpen = XHR.open, origSend = XHR.send;
  XHR.open = function(){ this.__fs_t = setTimeout(()=> show('xhr'), 250); return origOpen.apply(this, arguments); };
  XHR.send = function(){ this.addEventListener('loadend', ()=>{ clearTimeout(this.__fs_t); hide(); }); return origSend.apply(this, arguments); };
})();
</script>

</body>
</html>
