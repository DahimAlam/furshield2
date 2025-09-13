<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// BASE sirf ek dafa hi define hoga
if (!defined('BASE')) define('BASE', '/furshield');

$DB_HOST = 'mysql.railway.internal';
$DB_USER = 'root';
$DB_PASS = 'XsILAIaogMiurbBLrWdfTKqEZnhzrTFR ';
$DB_NAME = 'railway';
$port       = "3306";

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// rows helper
if (!function_exists('rows')) {
  function rows($conn, $query, $params = []) {
    if (empty($params)) {
      $res = $conn->query($query);
    } else {
      $stmt = $conn->prepare($query);
      if (!$stmt) return [];
      $types = str_repeat("s", count($params));
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $res = $stmt->get_result();
    }

    $data = [];
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $data[] = $row;
      }
    }
    return $data;
  }
}

// row helper
if (!function_exists('row')) {
  function row($conn, $query, $params = []) {
    $all = rows($conn, $query, $params);
    return $all[0] ?? null;
  }
}

if (!function_exists('media')) {
  function media($path, $type=''){
    if(!$path) return BASE.'/assets/placeholder/'.($type?:'default').'.jpg';
    if(preg_match('~^https?://~i',$path)) return $path;
    if(str_starts_with($path,'/')) return BASE.$path;
    return BASE.'/'.ltrim($path,'/');
  }
}
