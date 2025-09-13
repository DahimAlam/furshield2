<?php
session_start();
require_once __DIR__."/../includes/db.php";
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) {
  echo json_encode(["success"=>true, "data"=>["pets"=>[], "vets"=>[], "products"=>[]]]); exit;
}
$like = "%$q%";

/* Pets */
$stmt = $conn->prepare("SELECT id, name, species, avatar FROM pets WHERE name LIKE ? OR species LIKE ? LIMIT 5");
$stmt->bind_param("ss", $like, $like);
$stmt->execute(); $pets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Vets */
$sql = "SELECT u.id, u.name, v.specialization 
        FROM users u JOIN vets v ON v.user_id=u.id
        WHERE u.role='vet' AND (u.name LIKE ? OR v.specialization LIKE ?) LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $like, $like);
$stmt->execute(); $vets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Products */
$stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE name LIKE ? LIMIT 5");
$stmt->bind_param("s", $like);
$stmt->execute(); $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(["success"=>true, "data"=>compact("pets","vets","products")]);
