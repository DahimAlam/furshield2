
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
      --primary:#F59E0B; --accent:#EF4444; --bg:#FFF7ED; --text:#1F2937;
      --card:#FFFFFF; --muted:#6B7280; --ring:#E5E7EB;
      --radius:18px; --shadow:0 10px 30px rgba(0,0,0,.08); --shadow-sm:0 6px 16px rgba(0,0,0,.06);
    }
    *{box-sizing:border-box} html,body{height:100%} body{margin:0}
    body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif;line-height:1.5}

    .sidebar{
      position:fixed;left:0;top:0;height:100vh;width:280px;background:#fff;
      border-right:1px solid #f3e7d9;box-shadow:var(--shadow);padding:18px 16px;
      display:flex;flex-direction:column;z-index:45;
      /* SCROLL */
      overflow-y:auto; overscroll-behavior:contain; scrollbar-gutter:stable both-edges
    }
    .sidebar::-webkit-scrollbar{width:10px}
    .sidebar::-webkit-scrollbar-track{background:#fff}
    .sidebar::-webkit-scrollbar-thumb{background:#f2e5d1;border-radius:12px;border:2px solid #fff}
    .sidebar{scrollbar-color:#f2e5d1 #fff}

    .sidebar-head{display:flex;align-items:center;gap:12px;margin-top:15px;margin-bottom:18px}
    .avatar img{width:52px;height:52px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.08)}
    .info .nm{font-weight:700} .info .sub{color:var(--muted);font-size:12px}
    .nav{display:flex;flex-direction:column;gap:6px;margin-top:8px;padding-bottom:24px}
    .nav a{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;color:inherit;text-decoration:none;border:1px solid transparent}
    .nav a:hover{background:#fff7ef;border-color:#f2e5d1}
    .nav a.active{background:linear-gradient(135deg,#fff7ef,#fff);border-color:#f2e5d1;box-shadow:var(--shadow-sm)}
    .nav a.danger{color:#b42318}
    .nav i{width:22px;text-align:center}

    .page{margin-left:280px;padding:28px 24px 60px}

    @media (max-width: 992px){
      .sidebar{transform:translateX(-100%);transition:transform .35s ease}
      #nav-toggle:checked ~ .sidebar{transform:translateX(0)}
      .page{margin-left:0}
    }
  </style>
</head>
<body class="bg-app">
  <input id="nav-toggle" type="checkbox" hidden>

  <aside class="sidebar">
    <div class="sidebar-head">
      <div class="avatar"><img src="https://i.pravatar.cc/100?img=12" alt=""></div>
      <div class="info">
        <div class="nm"></div>
        <div class="sub">Karachi, PK</div>
      </div>
    </div>
    <nav class="nav">
      <a class="active" href="dashboard.php"><i class="bi bi-grid-1x2"></i><span>Dashboard</span></a>
      <a href="profile.php"><i class="bi bi-person-vcard"></i><span>Profile</span></a>
      <a href="mypet.php"><i class="bi bi-emoji-smile"></i><span>My Pets</span></a>
      <a href="HealthRecords.php"><i class="bi bi-shield-plus"></i><span>Health Records</span></a>
      <a href="appointement.php"><i class="bi bi-calendar2-check"></i><span>Appointments</span></a>
      <a href="addopt.php"><i class="bi bi-heart"></i><span>Adoption</span></a>
      <a href="orders.php"><i class="bi bi-bag"></i><span>Orders</span></a>
      <!-- <a><i class="bi bi-bookmark-heart"></i><span>Wishlist</span></a> -->
      <a href="caregide.php"><i class="bi bi-journal-richtext"></i><span>Care Guides</span></a>
       <a href="../index.php"><i class="bi bi-house"></i><span>Return To Home</span></a>

      <!-- <a><i class="bi bi-chat-square-dots"></i><span>Support</span></a> -->
      <a class="danger" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>

    </nav>
  </aside>

 
</body>
</html>
