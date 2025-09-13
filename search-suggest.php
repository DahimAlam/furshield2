<?php
require_once __DIR__.'/includes/db.php';
header('Content-Type: application/json');
$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode([]); exit; }

$stmt = $conn->prepare("SELECT id, name FROM pets WHERE name LIKE CONCAT('%', ?, '%') LIMIT 5");
$stmt->bind_param("s", $q);
$stmt->execute();
$res = $stmt->get_result();

$suggestions = [];
while ($row = $res->fetch_assoc()) {
  $suggestions[] = [ 'id' => $row['id'], 'name' => $row['name'] ];
}
echo json_encode($suggestions);
