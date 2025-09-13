<?php
// UTF-8 without BOM, no spaces before <?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* Kill session data */
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  // Clear on root path so cookie definitely dies everywhere
  setcookie(session_name(), '', time() - 42000, '/', $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
}
session_destroy();

/* Redirect to login (owners/ se parent me login.php hai) */
header('Location: /furshield/login.php'); // <- agar tumhara login yahan nahi, path update karo
exit;
