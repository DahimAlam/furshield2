<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!defined('BASE')) define('BASE', '/furshield');

// redirect if not logged in
if (!isset($_SESSION['user'])) {
  header("Location: " . BASE . "/login.php");
  exit;
}

$id = (int)($_GET['id'] ?? 0);
$product = row($conn, "SELECT id,name,price,image FROM products WHERE id=$id");
if (!$product) {
  header("Location: " . BASE . "/catalog.php?error=notfound");
  exit;
}

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if (isset($_SESSION['cart'][$id])) {
  $_SESSION['cart'][$id]['qty'] += 1;
} else {
  $_SESSION['cart'][$id] = [
    'id' => $product['id'],
    'name' => $product['name'],
    'price' => $product['price'],
    'image' => $product['image'],
    'qty' => 1
  ];
}

header("Location: " . BASE . "/cart.php");
exit;
