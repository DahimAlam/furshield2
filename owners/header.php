<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • Owner Dashboard</title>

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
      --ring:#E5E7EB;
      --radius:18px;
      --shadow:0 10px 30px rgba(0,0,0,.08);
      --shadow-sm:0 6px 16px rgba(0,0,0,.06);
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0}
    body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif;line-height:1.5}

    .topbar{position:sticky;top:0;z-index:40;display:grid;grid-template-columns:280px 1fr 320px;gap:16px;align-items:center;padding:16px 22px;background:linear-gradient(90deg,#fffaf3,#fff);border-bottom:1px solid #f3e7d9}
    .brand{display:flex;align-items:center;gap:14px}
    .menu-btn{display:none;place-items:center;width:42px;height:42px;border-radius:12px;background:#fff;border:1px solid #f0e7da;box-shadow:var(--shadow-sm);cursor:pointer}
    .menu-btn i{font-size:20px}
    .logo{font-family:Montserrat, sans-serif;font-size:22px;font-weight:800;letter-spacing:.3px}
    .logo span{color:var(--primary)}
    .role-chip{margin-left:6px;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid #f1e6d7;font-size:12px}

    .search-wrap{display:flex;justify-content:center}
    .search{display:flex;align-items:center;background:#fff;border:1px solid #f1e6d7;border-radius:14px;padding:8px 10px;max-width:680px;width:100%;box-shadow:var(--shadow-sm)}
    .search i{opacity:.6}
    .search input{border:0;outline:0;width:100%;padding:8px 10px;background:transparent;font-size:14px}
    .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:10px 14px;cursor:pointer;font-weight:600}
    .btn-primary{background:linear-gradient(135deg,var(--primary),#ffb444);color:#fff}
    .btn-minor{display:inline-flex;align-items:center;gap:8px;border:1px solid #f0e7da;border-radius:12px;padding:8px 12px;background:#fff;font-weight:600}

    .user{display:flex;align-items:center;gap:12px;justify-content:flex-end}
    .icon-btn{position:relative;display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#fff;border:1px solid #f0e7da;box-shadow:var(--shadow-sm);cursor:pointer}
    .icon-btn .dot{position:absolute;top:6px;right:6px;width:8px;height:8px;background:var(--accent);border-radius:999px}
    .chip{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #f0e7da;border-radius:14px;padding:6px 10px;box-shadow:var(--shadow-sm)}
    .chip img{width:36px;height:36px;border-radius:50%;object-fit:cover}
    .chip .name{font-weight:700}
    .muted{color:var(--muted)}

    .sidebar{position:fixed;left:0;top:0;height:100vh;width:280px;background:#fff;border-right:1px solid #f3e7d9;box-shadow:var(--shadow);padding:18px 16px;display:flex;flex-direction:column;z-index:45;transform:translateX(0);transition:transform .35s ease}
    .sidebar-head{display:flex;align-items:center;gap:12px;margin-top:64px;margin-bottom:18px}
    .avatar img{width:52px;height:52px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.08)}
    .info .nm{font-weight:700}
    .info .sub{color:var(--muted);font-size:12px}
    .nav{display:flex;flex-direction:column;gap:6px;margin-top:8px}
    .nav a{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;color:inherit;text-decoration:none;border:1px solid transparent}
    .nav a:hover{background:#fff7ef;border-color:#f2e5d1}
    .nav a.active{background:linear-gradient(135deg,#fff7ef,#fff);border-color:#f2e5d1;box-shadow:var(--shadow-sm)}
    .nav a.danger{color:#b42318}
    .nav i{width:22px;text-align:center}

    .page{margin-left:280px;padding:28px 24px 60px}


    #nav-toggle{display:none}
    @media (max-width: 1200px){
      .kpis{grid-template-columns:repeat(3,1fr)}
      .grid-3{grid-template-columns:1fr 1fr}
    }
    @media (max-width: 992px){
      .menu-btn{display:grid}
      .sidebar{transform:translateX(-100%)}
      #nav-toggle:checked ~ .sidebar{transform:translateX(0)}
      .page{margin-left:0}
      .topbar{grid-template-columns:1fr 1fr auto}
      .hero{grid-template-columns:1fr}
      .kpis{grid-template-columns:repeat(2,1fr)}
      .mini-cards{grid-template-columns:repeat(2,1fr)}
    }
    @media (max-width: 640px){
      .topbar{grid-template-columns:auto 1fr auto}
      .kpis{grid-template-columns:1fr}
      .grid-2,.grid-3{grid-template-columns:1fr}
      .li,.pet{grid-template-columns:56px 1fr auto}
      .mini-cards{grid-template-columns:1fr 1fr}
      .search{padding:6px 8px}
      .hero-text h1{font-size:26px}
      .ring{width:110px;height:110px}
    }
  </style>
</head>
<body class="bg-app">
  <input id="nav-toggle" type="checkbox" hidden>

  <header class="topbar">
    <div class="brand">
      <label for="nav-toggle" class="menu-btn"><i class="bi bi-list"></i></label>
      <span class="logo">Fur<span>Shield</span></span>
      <span class="role-chip">Owner</span>
    </div>
    <div class="search-wrap">
      <div class="search">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Search pets, vets, orders, tips"/>
        <button class="btn btn-primary"><i class="bi bi-arrow-right"></i></button>
      </div>
    </div>
    <div class="user">
      <button class="icon-btn"><i class="bi bi-bell"></i><span class="dot"></span></button>
      <div class="chip">
        <img src="https://i.pravatar.cc/100?img=12" alt="">
        <div>
          <b class="name">Talha</b>
          <small class="muted">talha@example.com</small>
        </div>
        <i class="bi bi-chevron-down"></i>
      </div>
    </div>
  </header>


  
  </main>
</body>
</html>
