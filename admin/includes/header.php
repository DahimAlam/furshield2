<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FurShield — Admin</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">


  <link rel="stylesheet" href="<?php echo BASE; ?>/assets/css/theme-sand-sunset.css">
  <link rel="stylesheet" href="<?php echo BASE; ?>/assets/css/admin-style.css">
  <link rel="stylesheet" href="<?php echo BASE; ?>/assets/css/admin-pages.css">

</head>
<body class="bg-app">
<div class="admin-wrapper d-flex">
  
  <?php include __DIR__ . '/sidebar.php'; ?>

  <main class="admin-main flex-grow-1">
    <nav class="admin-topbar d-flex align-items-center justify-content-between px-3">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-light d-lg-none" id="sidebarToggle">
          <i class="bi bi-list"></i>
        </button>
        <span class="fw-bold">Admin Panel</span>
      </div>

      <div class="d-flex align-items-center gap-3">
        <span class="small text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'Admin'); ?></span>
        <a href="<?php echo BASE; ?>/logout.php" class="btn btn-sm btn-outline-danger">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
      </div>
    </nav>

    <div class="p-4">
