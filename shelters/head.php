<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <style>
 :root{
      --primary:#F59E0B; /* amber */
      --accent:#EF4444; /* red */
      --bg:#FFF7ED;     /* warm sand */
      --text:#1F2937;   /* slate-800 */
      --muted:#6B7280;  /* gray-500 */
      --card:#FFFFFF;   /* white */
      --ring:#f0e7da;   /* sand ring */
      --radius:18px;
      --shadow:0 10px 30px rgba(0,0,0,.08);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:var(--text);font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
    h1,h2,h3,h4,h5,h6{font-family:Montserrat, Poppins, sans-serif;margin:0 0 .5rem}
    a{text-decoration:none;color:inherit}

    /* Layout */
    .app{min-height:100vh;display:grid;grid-template-columns:280px 1fr;grid-template-rows:auto 1fr}
    aside{grid-row:1/3;background:linear-gradient(180deg,#fff, #fff8ef);border-right:1px solid var(--ring);padding:20px;position:sticky;top:0;height:100vh}
    header{grid-column:2;background:#fff;border-bottom:1px solid var(--ring);padding:14px 20px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:10}
    main{grid-column:2;padding:24px}

    /* Brand */
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}
    .brand .logo{width:44px;height:44px;border-radius:12px;background:radial-gradient(60% 60% at 30% 30%, #ffd796, #ffb84d 45%, #f59e0b 100%);display:grid;place-items:center;color:#fff;box-shadow:var(--shadow)}
    .brand h2{font-size:1.25rem;line-height:1.1}
    .brand small{display:block;color:var(--muted);font-weight:500}


    /* Topbar */
    .search{flex:1;display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--ring);border-radius:12px;padding:8px 12px}
    .search input{border:0;outline:0;width:100%;font:inherit}
    .badge{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid var(--ring);border-radius:999px;padding:8px 12px}
    .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;border:1px solid var(--ring);padding:10px 14px;background:#fff}
    .btn-primary{border:0;background:linear-gradient(90deg,#fbbf24,#f59e0b);color:#111;box-shadow:0 6px 16px rgba(245,158,11,.25)}
    .btn-outline{background:#fff;border:1px solid var(--ring)}

    </style>
</head>
<body>
     <input id="menu-toggle" type="checkbox" hidden>
    <header>
      <label for="menu-toggle" class="btn mobilebar"><i class="bi bi-list"></i> Menu</label>
      <div class="search">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Search pets, adopters, requests…" />
      </div>
      <span class="badge"><i class="bi bi-bell"></i> Alerts</span>
      <a class="btn btn-primary" href="#profile">Shelter Admin</a>
    </header>
</body>
</html>