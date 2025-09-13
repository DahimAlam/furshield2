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
      --primary:#F59E0B; --accent:#EF4444; --bg:#FFF7ED; --text:#1F2937;
      --muted:#6B7280; --card:#FFFFFF; --ring:#f0e7da;
      --radius:18px; --shadow:0 10px 30px rgba(0,0,0,.08);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:var(--text);font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
    h1,h2,h3,h4,h5,h6{font-family:Montserrat,Poppins,sans-serif;margin:0 0 .5rem}
    a{text-decoration:none;color:inherit}

    /* Layout */
    .app{min-height:100vh;display:grid;grid-template-columns:280px 1fr;grid-template-rows:auto 1fr}
    aside{grid-row:1/3;background:linear-gradient(180deg,#fff, #fff8ef);border-right:1px solid var(--ring);padding:20px;position:sticky;top:0;height:100vh}

    /* Brand */
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}
    .brand .logo{width:44px;height:44px;border-radius:12px;background:radial-gradient(60% 60% at 30% 30%, #ffd796, #ffb84d 45%, #f59e0b 100%);display:grid;place-items:center;color:#fff;box-shadow:var(--shadow)}
    .brand h2{font-size:1.25rem;line-height:1.1}
    .brand small{display:block;color:var(--muted);font-weight:500}

    /* Sidebar */
    .menu{display:flex;flex-direction:column;gap:10px}
    .menu h4{font-size:.85rem;color:var(--muted);margin:16px 0 6px;text-transform:uppercase;letter-spacing:.06em}
    .menu a{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;border:1px solid transparent}
    .menu a:hover{background:#fff;border-color:var(--ring)}
    .menu a.active{background:linear-gradient(180deg,#fff,#fff3e0);border-color:#ffd9a3;box-shadow:inset 0 0 0 1px #ffe5bf}
    .menu i.bi{font-size:1.1rem;color:var(--primary)}
  </style>
</head>
<body>
  <!-- Sidebar -->
  <aside>
    <div class="brand">
      <div class="logo"><i class="bi bi-shield-heart"></i></div>
      <div>
        <h2>FurShield</h2>
        <small>Shelter Admin</small>
      </div>
    </div>

    <nav class="menu">
      <h4>Dashboard</h4>
      <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Overview</a>

      <h4>Profile Management</h4>
      <a href="info.php"><i class="bi bi-person-gear"></i> Update Shelter Info</a>

      <h4>Pets Management (CRUD)</h4>
      <a href="addproduct.php"><i class="bi bi-collection"></i> Add / Manage Pets</a>
      <a href="request.php"><i class="bi bi-lightning-charge"></i> Adoption / Spotlight</a>

      <h4>Adoption Requests</h4>
      <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>

    </nav>
  </aside>
</body>
</html>
