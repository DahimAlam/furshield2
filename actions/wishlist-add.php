<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!logged_in()) {
    header("Location: " . BASE . "/login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $uid = $_SESSION['user']['id'];
    $stmt = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $uid, $id);
    $stmt->execute();
}

header("Location: " . BASE . "/wishlist.php");
exit;
