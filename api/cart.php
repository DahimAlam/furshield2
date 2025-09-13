<?php
session_start();
require_once __DIR__."/../includes/db.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = []; // id => item

function totalize() {
  $items = array_values($_SESSION['cart']);
  $count = 0; $total = 0.0;
  foreach($items as $it){ $count += (int)$it['qty']; $total += $it['qty']*$it['price']; }
  return ["items"=>$items, "count"=>$count, "total"=>round($total,2)];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  echo json_encode(["success"=>true, "data"=>totalize()]); exit;
}

/* POST + CSRF */
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
  http_response_code(419);
  echo json_encode(["success"=>false, "message"=>"CSRF failed"]); exit;
}
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';

if ($action === 'add') {
  $id = (int)($input['id'] ?? 0); $qty = max(1, min(99, (int)($input['qty'] ?? 1)));
  $stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $id); $stmt->execute();
  $p = $stmt->get_result()->fetch_assoc();
  if (!$p) { echo json_encode(["success"=>false, "message"=>"Product not found"]); exit; }
  if (!isset($_SESSION['cart'][$id])) {
    $_SESSION['cart'][$id] = ["id"=>$p['id'],"name"=>$p['name'],"price"=>(float)$p['price'],"image"=>$p['image'],"qty"=>$qty];
  } else {
    $_SESSION['cart'][$id]['qty'] = min(99, $_SESSION['cart'][$id]['qty'] + $qty);
  }
  echo json_encode(["success"=>true, "message"=>"Added", "data"=>totalize()]); exit;
}

if ($action === 'update') {
  $id = (int)($input['id'] ?? 0); $qty = max(1, min(99, (int)($input['qty'] ?? 1)));
  if (isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id]['qty'] = $qty;
  echo json_encode(["success"=>true, "data"=>totalize()]); exit;
}

if ($action === 'remove') {
  $id = (int)($input['id'] ?? 0); unset($_SESSION['cart'][$id]);
  echo json_encode(["success"=>true, "data"=>totalize()]); exit;
}

if ($action === 'clear') {
  $_SESSION['cart'] = [];
  echo json_encode(["success"=>true, "data"=>totalize()]); exit;
}

echo json_encode(["success"=>false, "message"=>"Unknown action"]);
