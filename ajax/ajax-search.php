<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim($_GET['term'] ?? '');
if ($term === '' || mb_strlen($term) < 2) {
  echo json_encode([]); exit;
}

$stmt = $conn->prepare("
  SELECT id, name, species, breed, COALESCE(avatar,'placeholder.png') AS avatar
  FROM pets
  WHERE name LIKE CONCAT('%',?,'%')
     OR species LIKE CONCAT('%',?,'%')
     OR breed LIKE CONCAT('%',?,'%')
  ORDER BY spotlight DESC, id DESC
  LIMIT 8
");
$stmt->bind_param('sss', $term, $term, $term);
$stmt->execute();
$res = $stmt->get_result();
$data = [];
while ($row = $res->fetch_assoc()) $data[] = $row;

echo json_encode($data);
