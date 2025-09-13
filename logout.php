<?php


require_once __DIR__ . "/includes/db.php"; // defines BASE and starts session

// Clear all session data
$_SESSION = [];

// Delete the session cookie (if set)
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params['path'],
    $params['domain'],
    $params['secure'],
    $params['httponly']
  );
}

// Destroy the session
session_destroy();

// Extra safety: rotate session ID
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
session_regenerate_id(true);

// Redirect (choose one)
header("Location: " . BASE . "/login.php"); // send to Login
// header("Location: " . BASE . "/");       // or send to Home
exit;
