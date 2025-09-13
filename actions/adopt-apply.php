<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

function bad($msg){ http_response_code(400); exit($msg); }
function hascol(mysqli $c, string $t, string $col): bool {
  $t = $c->real_escape_string($t); $col = $c->real_escape_string($col);
  $q = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
  return $q && $q->num_rows>0;
}

if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) bad('Invalid CSRF.');
if (empty($_SESSION['user']) || ($_SESSION['user']['role']??'')!=='owner') bad('Login as owner required.');

$applicant_id = (int)$_SESSION['user']['id'];
$pet_id       = (int)($_POST['pet_id'] ?? 0);         // this is addoption.id from pet.php
$phone        = trim($_POST['phone'] ?? '');
$message      = trim($_POST['message'] ?? '');
$experience   = trim($_POST['experience'] ?? '');

if ($pet_id<=0) bad('Invalid pet.');
if ($phone==='') bad('Phone is required.');

// fetch animal from addoption
$stmt = $conn->prepare("SELECT id, shelter_id, status FROM addoption WHERE id=? LIMIT 1");
$stmt->bind_param('i', $pet_id);
$stmt->execute();
$pet = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$pet) bad('Pet not found.');
if (!in_array($pet['status'], ['available','pending'], true)) bad('Pet not available.');
$shelter_id = (int)$pet['shelter_id'];

// already pending?
$already = 0;
if (hascol($conn,'request','addoption_id')) {
  $q = $conn->prepare("SELECT COUNT(*) c FROM request WHERE applicant_id=? AND (addoption_id=? OR pet_id=?) AND status IN ('pending','pre-approved')");
  $q->bind_param('iii', $applicant_id, $pet_id, $pet_id);
} else {
  // legacy ONLY pets column (shouldn't happen in your case)
  $q = $conn->prepare("SELECT COUNT(*) c FROM request WHERE applicant_id=? AND pet_id=? AND status IN ('pending','pre-approved')");
  $q->bind_param('ii', $applicant_id, $pet_id);
}
$q->execute();
$already = (int)$q->get_result()->fetch_assoc()['c'];
$q->close();
if ($already>0) bad('You already have a pending application.');

// Insert request (new flow => addoption_id)
if (hascol($conn,'request','addoption_id')) {
  $stmt = $conn->prepare("
    INSERT INTO request (pet_id, addoption_id, pet_source, applicant_id, shelter_id, phone, message, experience, status, created_at, updated_at)
    VALUES (NULL, ?, 'addoption', ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
  ");
  $stmt->bind_param('iiisss', $pet_id, $applicant_id, $shelter_id, $phone, $message, $experience);
} else {
  // fallback (unlikely in your DB now)
  $stmt = $conn->prepare("
    INSERT INTO request (pet_id, applicant_id, shelter_id, phone, message, experience, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
  ");
  $stmt->bind_param('iiisss', $pet_id, $applicant_id, $shelter_id, $phone, $message, $experience);
}

if (!$stmt->execute()) {
  $err = $stmt->error;
  $stmt->close();
  bad('DB error: '.$err);
}
$stmt->close();

// (Optional) mark pet pending to avoid duplicates quickly
$conn->query("UPDATE addoption SET status='pending' WHERE id=". (int)$pet_id ." AND status='available'");

// redirect back to pet page with success
header("Location: ".BASE."/pet.php?id=".$pet_id."&ok=1#apply");
exit;
