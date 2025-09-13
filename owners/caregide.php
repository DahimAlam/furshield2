<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • Care Guides</title>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>

  <style>
    :root{
      --primary:#F59E0B;
      --accent:#EF4444;
      --bg:#FFF7ED;
      --text:#1F2937;
      --card:#FFFFFF;
      --muted:#6B7280;
      --border:#f1e6d7;
      --radius:18px;
      --shadow:0 10px 30px rgba(0,0,0,.08);
      --shadow-sm:0 6px 16px rgba(0,0,0,.06);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0}
    body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif;line-height:1.5}

    /* Page shell */
    .page{margin-left:280px;padding:28px 24px 60px}

    /* Head */
    .page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .page-title h1{margin:0;font-family:Montserrat,sans-serif;font-size:28px}
    .breadcrumbs{font-size:13px;color:var(--muted)}
    .tag{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid var(--border);font-size:12px}

    /* Cards / layout */
    .card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .muted{color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    @media (max-width: 1060px){ .grid{grid-template-columns:repeat(2,1fr)} }
    @media (max-width: 640px){ .page{margin-left:0} .grid{grid-template-columns:1fr} }

    /* Toolbar */
    .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px}
    .stat{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid var(--border);font-weight:600}
    .input,.select{border:1px solid var(--border);background:#fff;border-radius:12px;padding:10px 12px;font-size:14px;outline:0}
    .input:focus,.select:focus{box-shadow:0 0 0 4px #ffe7c6;border-color:#f2cf97}
    .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:10px 14px;cursor:pointer;font-weight:600}
    .btn-primary{background:linear-gradient(135deg,var(--primary),#ffb444);color:#fff}
    .btn-ghost{background:#fff;border:1px solid var(--border);color:#92400e}
    .switch{--w:46px;--h:26px;position:relative;width:var(--w);height:var(--h);background:#e5e7eb;border-radius:999px;cursor:pointer;display:inline-block}
    .switch i{position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;transition:.25s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
    .switch.on{background:#fbbf24}
    .switch.on i{transform:translateX(20px)}

    /* Guide Card */
    .gcard{position:relative;display:flex;flex-direction:column;gap:10px;border:1px solid var(--border);border-radius:16px;background:#fff;box-shadow:var(--shadow-sm);padding:14px}
    .g-top{display:flex;justify-content:space-between;align-items:center;gap:10px}
    .badges{display:flex;gap:6px;flex-wrap:wrap}
    .badge{font-size:11px;padding:6px 10px;border-radius:999px;background:#111827;color:#fff}
    .badge.outline{background:#fff;color:#92400e;border:1px solid var(--border)}
    .g-title{margin:4px 0 0;font-family:Montserrat,sans-serif;font-size:18px}
    .g-excerpt{margin:0;color:#4b5563}
    .g-meta{display:flex;gap:8px;flex-wrap:wrap}
    .chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#fff;font-size:12px}
    .chip.ok{background:#ecfdf5;border-color:#bbf7d0;color:#047857}
    .g-tags{display:flex;gap:8px;flex-wrap:wrap}
    .tag-btn{font-size:12px;padding:6px 10px;border-radius:999px;background:#fff7ef;border:1px solid var(--border);cursor:pointer}
    .g-actions{display:flex;gap:8px;margin-top:6px}
    .icon-btn{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:#fff;border:1px solid var(--border);cursor:pointer}
    .icon-btn:hover{box-shadow:var(--shadow-sm);transform:translateY(-1px)}
    .saved .icon-btn[data-act="save"]{background:#fff7ef;border-color:#f2cf97}
    .done .g-title{text-decoration:line-through;color:#9ca3af}

    /* Modal Reader */
    .modal{position:fixed;inset:0;display:none;place-items:center;background:rgba(0,0,0,.25);z-index:100}
    .modal.open{display:grid}
    .sheet{width:min(980px,95vw);height:min(86vh,860px);background:#fff;border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden}
    .sheet-head{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid #f3e7d9}
    .sheet-head h3{margin:0;font-family:Montserrat,sans-serif}
    .sheet-body{flex:1;overflow:auto;padding:16px}
    .sheet-foot{display:flex;justify-content:space-between;gap:8px;padding:12px 16px;border-top:1px solid #f3e7d9}
    .close-x{background:#fff;border:1px solid var(--border);width:36px;height:36px;border-radius:10px;display:grid;place-items:center;cursor:pointer}
    article h4{margin:10px 0 6px}
    article p{margin:0 0 10px}
  </style>
</head>
<body class="bg-app">

<?php include("sidebar.php")?>

<main class="page">
  <div class="page-head">
    <div class="page-title">
      <div class="breadcrumbs">Owner • Learn</div>
      <h1>Care Guides</h1>
    </div>
    <span class="tag"><i class="bi bi-journal-text"></i> Read & Save</span>
  </div>

  <section class="card">
    <!-- Toolbar -->
    <div class="toolbar">
      <div class="stat"><i class="bi bi-journals"></i> Total: <span id="count">0</span></div>
      <div class="stat"><i class="bi bi-bookmark-heart"></i> Saved: <span id="savedCount">0</span></div>
      <div class="stat"><i class="bi bi-check2-circle"></i> Done: <span id="doneCount">0</span></div>

      <select class="select" id="catFilter">
        <option value="All">All Categories</option>
        <option>Guide</option>
        <option>Blog</option>
        <option>FAQ</option>
        <option>Tip</option>
      </select>

      <select class="select" id="speciesFilter">
        <option value="All">All Species</option>
        <option>Dog</option>
        <option>Cat</option>
        <option>Bird</option>
        <option>Rabbit</option>
        <option>General</option>
      </select>

      <select class="select" id="sortBy">
        <option value="newest">Newest</option>
        <option value="popular">Popular</option>
        <option value="short">Shortest</option>
      </select>

      <input class="input" id="search" placeholder="Search title, tag, keyword…">
      <label style="display:flex;align-items:center;gap:8px">
        <span class="muted" style="font-size:13px">Saved only</span>
        <span id="savedSwitch" class="switch"><i></i></span>
      </label>

      <button class="btn btn-ghost" id="clearFilters"><i class="bi bi-eraser"></i> Clear</button>
    </div>

    <!-- Guides grid -->
    <div id="grid" class="grid"></div>
  </section>
</main>

<!-- Reader Modal -->
<div id="reader" class="modal" aria-hidden="true">
  <div class="sheet">
    <div class="sheet-head">
      <h3 id="readerTitle">Guide</h3>
      <div style="display:flex;gap:8px;align-items:center">
        <button class="icon-btn" id="printBtn" title="Print"><i class="bi bi-printer"></i></button>
        <button class="icon-btn" id="copyLinkBtn" title="Copy link"><i class="bi bi-link-45deg"></i></button>
        <button class="close-x" id="readerClose"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div class="sheet-body">
      <article id="readerBody"></article>
    </div>
    <div class="sheet-foot">
      <div class="muted" id="readerMeta"></div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-ghost" id="markDone"><i class="bi bi-check2-circle"></i> Mark as Done</button>
        <button class="btn btn-primary" id="saveToggle"><i class="bi bi-bookmark-plus"></i> Save</button>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const LS_GUIDES = 'fs_guides';
  const LS_GUIDE_SAVED = 'fs_guide_saved';
  const LS_GUIDE_DONE = 'fs_guide_done';

  const $ = id => document.getElementById(id);

  // UI refs
  const grid = $('grid');
  const countEl = $('count');
  const savedCountEl = $('savedCount');
  const doneCountEl = $('doneCount');
  const catFilter = $('catFilter');
  const speciesFilter = $('speciesFilter');
  const sortBy = $('sortBy');
  const search = $('search');
  const clearFilters = $('clearFilters');
  const savedSwitch = $('savedSwitch');

  // Reader
  const reader = $('reader');
  const readerTitle = $('readerTitle');
  const readerBody = $('readerBody');
  const readerMeta = $('readerMeta');
  const readerClose = $('readerClose');
  const printBtn = $('printBtn');
  const copyLinkBtn = $('copyLinkBtn');
  const markDoneBtn = $('markDone');
  const saveToggleBtn = $('saveToggle');

  // State
  let guides = [];
  let saved = new Set();
  let done = new Set();
  let savedOnly = false;
  let currentId = null;

  // Seeds
  const seeds = [
    {
      id:'g_vax_puppy',
      type:'Guide', species:'Dog', title:'Vaccination Timeline for Puppies',
      read:5, likes:214, date:'2025-09-25',
      tags:['vaccination','puppy','schedule'],
      excerpt:'Core vs non-core vaccines, timing, and reminders — a simple checklist.',
      content: `
        <h4>Overview</h4>
        <p>Puppies need a series of boosters (DHPP) starting at 6–8 weeks, then every 3–4 weeks until 16–18 weeks.</p>
        <h4>Core Schedule</h4>
        <ul>
          <li>6–8 weeks: DHPP #1</li>
          <li>10–12 weeks: DHPP #2</li>
          <li>14–16 weeks: DHPP #3 + Rabies</li>
        </ul>
        <p><b>Tip:</b> Book the next appointment before leaving the clinic.</p>
      `
    },
    {
      id:'g_allergy_season',
      type:'Blog', species:'General', title:'Seasonal Allergies: What to Watch',
      read:4, likes:167, date:'2025-10-01',
      tags:['allergies','itching','pollen'],
      excerpt:'Common signs, quick relief at home, and when to visit a vet.',
      content: `
        <p>Watch for persistent itching, paw licking, red ears, or watery eyes. Short-term relief can include gentle baths and avoiding known outdoor triggers.</p>
        <p>Consult your vet if symptoms last more than a week.</p>
      `
    },
    {
      id:'g_cat_litter',
      type:'FAQ', species:'Cat', title:'How often should I change cat litter?',
      read:2, likes:98, date:'2025-09-18',
      tags:['litter','hygiene','indoor'],
      excerpt:'Short FAQ on litter box cleaning frequency and odor control.',
      content: `
        <p>Scoop daily; replace all litter and wash the tray every 1–2 weeks. Clumping litter may last longer; adjust if you have multiple cats.</p>
      `
    },
    {
      id:'g_bird_diet',
      type:'Guide', species:'Bird', title:'Balanced Diet for Pet Birds',
      read:6, likes:76, date:'2025-09-10',
      tags:['nutrition','seed','pellets'],
      excerpt:'Seeds alone aren’t enough — pellets, veggies, and clean water are key.',
      content: `
        <p>Offer a pellet-based diet with fresh greens (spinach, kale), occasional fruits, and avoid avocado and chocolate.</p>
      `
    },
    {
      id:'g_rabbit_groom',
      type:'Tip', species:'Rabbit', title:'Grooming Your Rabbit (Quick Tips)',
      read:3, likes:82, date:'2025-10-03',
      tags:['grooming','brushing'],
      excerpt:'Weekly brushing, nail trims every 4–6 weeks, and gentle handling.',
      content: `
        <ul>
          <li>Use a soft brush; rabbits have delicate skin.</li>
          <li>Never bathe unless advised by a vet.</li>
        </ul>
      `
    },
    {
      id:'g_med_upload',
      type:'FAQ', species:'General', title:'How do I upload vet certificates?',
      read:2, likes:54, date:'2025-09-05',
      tags:['upload','health records'],
      excerpt:'Step-by-step upload guide and supported file types.',
      content: `
        <ol>
          <li>Go to <b>Health Records</b> → <b>Add Record</b>.</li>
          <li>Choose PDF/JPG/PNG and save.</li>
        </ol>
      `
    }
  ];

  // Storage helpers
  function load(){
    const raw = localStorage.getItem(LS_GUIDES);
    guides = raw ? JSON.parse(raw) : seeds;
    localStorage.setItem(LS_GUIDES, JSON.stringify(guides));

    const s = localStorage.getItem(LS_GUIDE_SAVED);
    if (s) try { saved = new Set(JSON.parse(s)); } catch {}

    const d = localStorage.getItem(LS_GUIDE_DONE);
    if (d) try { done = new Set(JSON.parse(d)); } catch {}
  }
  function saveSaved(){ localStorage.setItem(LS_GUIDE_SAVED, JSON.stringify([...saved])); }
  function saveDone(){ localStorage.setItem(LS_GUIDE_DONE, JSON.stringify([...done])); }

  // Render
  function render(){
    countEl.textContent = guides.length;
    savedCountEl.textContent = saved.size;
    doneCountEl.textContent = done.size;

    const cat = catFilter.value;
    const sp  = speciesFilter.value;
    const q   = (search.value||'').trim().toLowerCase();

    let list = guides.filter(g=>{
      const okCat = (cat==='All' || g.type===cat);
      const okSp  = (sp==='All' || g.species===sp);
      const okSaved = (!savedOnly || saved.has(g.id));
      const hay = `${g.title} ${g.tags.join(' ')} ${g.excerpt}`.toLowerCase();
      const okQ = !q || hay.includes(q);
      return okCat && okSp && okSaved && okQ;
    });

    const sortMode = sortBy.value;
    list.sort((a,b)=>{
      if (sortMode==='popular') return b.likes - a.likes;
      if (sortMode==='short') return a.read - b.read;
      // newest
      return (b.date > a.date) ? 1 : -1;
    });

    if (list.length===0){
      grid.innerHTML = `<div class="muted" style="padding:20px">No matching guides.</div>`;
      return;
    }

    grid.innerHTML = list.map(g => guideCardHtml(g)).join('');
    grid.querySelectorAll('.tag-btn').forEach(btn=>{
      btn.addEventListener('click', ()=>{ search.value = btn.dataset.tag; render(); });
    });
    grid.querySelectorAll('.icon-btn').forEach(b=>{
      b.addEventListener('click', (e)=>{
        const id = e.currentTarget.closest('.gcard').dataset.id;
        const act = e.currentTarget.dataset.act;
        if (act==='save') toggleSave(id);
        if (act==='open') openReader(id);
      });
    });
  }

  function guideCardHtml(g){
    const isSaved = saved.has(g.id);
    const isDone  = done.has(g.id);
    const badges = `
      <div class="badges">
        <span class="badge">${g.type}</span>
        <span class="badge outline">${g.species}</span>
      </div>
    `;
    const meta = `
      <div class="g-meta">
        <span class="chip"><i class="bi bi-clock"></i> ${g.read} min</span>
        <span class="chip"><i class="bi bi-heart"></i> ${g.likes}</span>
        ${isDone ? '<span class="chip ok"><i class="bi bi-check2"></i> Done</span>' : ''}
      </div>
    `;
    const tags = `
      <div class="g-tags">
        ${g.tags.map(t=>`<button class="tag-btn" data-tag="${t}">#${t}</button>`).join('')}
      </div>
    `;
    return `
      <div class="gcard ${isSaved?'saved':''} ${isDone?'done':''}" data-id="${g.id}">
        <div class="g-top">
          ${badges}
          <button class="icon-btn" data-act="save" title="${isSaved?'Unsave':'Save'}">
            <i class="bi ${isSaved?'bi-bookmark-fill':'bi-bookmark'}"></i>
          </button>
        </div>
        <h3 class="g-title">${g.title}</h3>
        <p class="g-excerpt">${g.excerpt}</p>
        ${meta}
        ${tags}
        <div class="g-actions">
          <button class="btn btn-ghost icon-left" data-act="open"><i class="bi bi-eye"></i> Read</button>
          <button class="btn btn-primary" data-act="open"><i class="bi bi-arrow-right-circle"></i> Open</button>
        </div>
      </div>
    `;
  }

  // Reader
  function openReader(id){
    currentId = id;
    const g = guides.find(x=>x.id===id);
    if (!g) return;
    readerTitle.textContent = g.title;
    readerBody.innerHTML = g.content;
    readerMeta.innerHTML = `
      <span class="chip"><i class="bi bi-journal"></i> ${g.type}</span>
      <span class="chip"><i class="bi bi-bandaid"></i> ${g.species}</span>
      <span class="chip"><i class="bi bi-clock"></i> ${g.read} min</span>
      <span class="chip"><i class="bi bi-calendar3"></i> ${g.date}</span>
    `;
    // footer buttons state
    saveToggleBtn.innerHTML = saved.has(id)
      ? '<i class="bi bi-bookmark-dash"></i> Unsave'
      : '<i class="bi bi-bookmark-plus"></i> Save';
    markDoneBtn.innerHTML = done.has(id)
      ? '<i class="bi bi-arrow-counterclockwise"></i> Mark as Unread'
      : '<i class="bi bi-check2-circle"></i> Mark as Done';

    reader.classList.add('open');
    reader.setAttribute('aria-hidden','false');
    // deep link
    history.replaceState(null,'', location.pathname + '#'+id);
  }
  function closeReader(){
    reader.classList.remove('open');
    reader.setAttribute('aria-hidden','true');
  }

  // Actions
  function toggleSave(id){
    if (saved.has(id)) saved.delete(id); else saved.add(id);
    saveSaved();
    render();
    if (currentId===id) openReader(id); // refresh footer state
  }
  function toggleDone(id){
    if (done.has(id)) done.delete(id); else done.add(id);
    saveDone();
    render();
    if (currentId===id) openReader(id);
  }

  // Events
  [catFilter, speciesFilter, sortBy].forEach(el=>el.addEventListener('change', render));
  search.addEventListener('input', render);
  clearFilters.addEventListener('click', ()=>{
    catFilter.value='All'; speciesFilter.value='All'; sortBy.value='newest';
    search.value=''; savedOnly=false; savedSwitch.classList.remove('on'); render();
  });
  savedSwitch.addEventListener('click', ()=>{
    savedOnly = !savedOnly;
    savedSwitch.classList.toggle('on', savedOnly);
    render();
  });

  // Reader events
  readerClose.addEventListener('click', closeReader);
  reader.addEventListener('click', (e)=>{ if (e.target===reader) closeReader(); });
  saveToggleBtn.addEventListener('click', ()=> currentId && toggleSave(currentId));
  markDoneBtn.addEventListener('click', ()=> currentId && toggleDone(currentId));
  printBtn.addEventListener('click', ()=> window.print());
  copyLinkBtn.addEventListener('click', ()=>{
    const url = location.origin + location.pathname + '#' + (currentId||'');
    navigator.clipboard.writeText(url).then(()=> alert('Link copied!'));
  });

  // Init
  load();
  // open by hash if present
  if (location.hash){
    const id = location.hash.slice(1);
    if (guides.find(g=>g.id===id)) openReader(id);
  }
  render();
})();
</script>
</body>
</html>
