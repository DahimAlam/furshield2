<?php
session_start();
require_once __DIR__."/../includes/db.php";
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($method === 'GET' && $action === 'me') {
  echo json_encode(["success"=>true, "data"=>($_SESSION['user'] ?? null)]); exit;
}

if ($method === 'POST') {
  $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(419); echo json_encode(["success"=>false,"message"=>"CSRF failed"]); exit;
  }

  $input = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];

  if (($input['action'] ?? $action) === 'login') {
    $email = trim($input['email'] ?? ''); $pass = (string)($input['password'] ?? '');
    $stmt = $conn->prepare("SELECT id, role, name, email, pass_hash, avatar FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email); $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if (!$u || !password_verify($pass, $u['pass_hash'])) {
      echo json_encode(["success"=>false,"message"=>"Invalid credentials"]); exit;
    }
    unset($u['pass_hash']);
    $_SESSION['user'] = $u;
    echo json_encode(["success"=>true,"message"=>"Welcome","data"=>$u]); exit;
  }

  if (($input['action'] ?? $action) === 'logout') {
    $_SESSION = []; session_destroy();
    echo json_encode(["success"=>true,"message"=>"Logged out"]); exit;
  }
}

echo json_encode(["success"=>false, "message"=>"Unsupported"]);
