<?php if (session_status()===PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FurShield • Vet Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="../assets/theme-sand-sunset.css" rel="stylesheet"/>
  <link href="assets/css/style.css" rel="stylesheet"/>
</head>
<body class="bg-app">
<header class="topbar d-flex align-items-center justify-content-between px-3">
  <div class="d-flex align-items-center gap-3">
    <button class="btn btn-light shadow-none d-lg-none" id="toggleSidebar"><i class="bi bi-list"></i></button>
    <a class="brand d-flex align-items-center gap-2" href="dashboard.php">
      <span class="logo-dot"></span>
      <span class="fw-bold">FurShield</span>
      <span class="text-muted d-none d-md-inline">• Vet Panel</span>
    </a>
  </div>
  <div class="d-flex align-items-center gap-3">
    <span class="text-muted small d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?></span>
    <a href="../logout.php" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
  </div>
</header>
<div class="app-wrap">
