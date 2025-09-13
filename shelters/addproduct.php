<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/db.php";
if (!defined('BASE')) define('BASE', '/furshield');

if (empty($_SESSION['user'])) {
  header("Location: " . BASE . "/login.php?next=" . urlencode($_SERVER['REQUEST_URI']));
  exit;
}
$shelterId = (int)$_SESSION['user']['id']; // will be used for created_by + shelter_id

$error = "";
$msg   = "";

/* ---------- ADD PET ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_pet'])) {
  $name    = trim($_POST['name'] ?? '');
  $species = trim($_POST['species'] ?? '');
  $breed   = trim($_POST['breed'] ?? '');
  $age     = trim($_POST['age'] ?? '');
  $gender  = trim($_POST['gender'] ?? '');
  $status  = trim($_POST['status'] ?? 'available');
  $desc    = trim($_POST['description'] ?? '');

  $city       = trim($_POST['city'] ?? '');
  $category   = trim($_POST['category'] ?? '');
  $image_alt  = trim($_POST['image_alt'] ?? '');
  $featured   = isset($_POST['featured'])  ? 1 : 0;
  $spotlight  = isset($_POST['spotlight']) ? 1 : 0;

  // keep as empty string if blank; SQL will NULLIF it
  $urgent_until_str = trim($_POST['urgent_until'] ?? '');

  if ($name === '' || $species === '') {
    $error = "Name & Species required.";
  }

  // Upload image -> /uploads/pets
  $avatar = null;
  if (!$error && !empty($_FILES['avatar']['name'])) {
    if (!empty($_FILES['avatar']['error']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
      $error = "Image upload failed.";
    } else {
      $allow = ['jpg','jpeg','png','webp','gif'];
      $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, $allow, true)) {
        $error = "Only JPG, PNG, WEBP, GIF allowed.";
      } else {
        $fname = "pet_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $dir = realpath(__DIR__ . "/..") . "/uploads/pets";
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $to = $dir . "/" . $fname;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $to)) {
          $avatar = $fname; // store filename in DB
        } else {
          $error = "Could not move uploaded image.";
        }
      }
    }
  }

  if (!$error) {
    $imageMirror = $avatar; // if you also keep a generic "image" column

    // NOTE: include created_by to satisfy FK fk_addoption_user
    $sql = "INSERT INTO addoption
      (created_by, shelter_id, name, species, breed, age, gender, avatar, image, category, image_alt, description, featured, urgent_until, spotlight, status, city, created_at, updated_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, NULLIF(?, ''), ?, ?, ?, NOW(), NOW())";

    // 17 bound params (we'll use all 's' for simplicity; MySQL will coerce ints)
    $types = str_repeat('s', 17);
    $stmt  = $conn->prepare($sql);
    $stmt->bind_param(
      $types,
      $shelterId,        // created_by (FK -> users.id)
      $shelterId,        // shelter_id
      $name,
      $species,
      $breed,
      $age,
      $gender,
      $avatar,
      $imageMirror,
      $category,
      $image_alt,
      $desc,
      $featured,         // 0/1
      $urgent_until_str, // '' => NULL, else date string
      $spotlight,        // 0/1
      $status,
      $city
    );

    if ($stmt->execute()) {
      $msg = "✅ Pet added successfully.";
    } else {
      $error = "❌ DB error: " . $stmt->error;
    }
    $stmt->close();
  }
}

/* ---------- DELETE (scoped to this shelter) ---------- */
if (isset($_GET['delete'])) {
  $petId = (int)$_GET['delete'];
  $stmt = $conn->prepare("DELETE FROM addoption WHERE id=? AND shelter_id=?");
  $stmt->bind_param("ii", $petId, $shelterId);
  $stmt->execute();
  $stmt->close();
  header("Location: addproduct.php");
  exit;
}

/* ---------- LIST (this shelter) ---------- */
$pets = [];
$stmt = $conn->prepare("SELECT id,name,species,breed,age,gender,avatar,status,city
                        FROM addoption WHERE shelter_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $shelterId);
$stmt->execute();
$pets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • Add Pet</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <style>
    :root{--ring:#f0e7da;--shadow:0 10px 30px rgba(0,0,0,.08);--primary:#F59E0B}
    .card{background:#fff;border:1px solid var(--ring);border-radius:18px;box-shadow:var(--shadow);padding:20px;margin-bottom:20px}
    h3{font-family:Montserrat, Poppins, sans-serif;margin-top:0;color:#F59E0B}
    label{font-weight:600;display:block;margin:10px 0 4px}
    input,select,textarea{width:100%;padding:10px;border:1px solid var(--ring);border-radius:12px}
    button{padding:10px 16px;border-radius:12px;border:0;cursor:pointer}
    .btn{display:inline-flex;align-items:center;gap:6px}
    .btn-primary{background:linear-gradient(90deg,#fbbf24,#f59e0b);color:#111;font-weight:600}
    .btn-danger{background:#fff0f0;color:#991b1b;border:1px solid #fecaca}
    table{width:100%;border-collapse:separate;border-spacing:0 10px}
    th{color:#6B7280;text-align:left;padding:6px}
    td{background:#fff;border:1px solid var(--ring);padding:10px;vertical-align:middle}
    tr td:first-child{border-radius:12px 0 0 12px}
    tr td:last-child{border-radius:0 12px 12px 0}
    .row2{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
    .row3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    img.thumb{width:50px;height:50px;border-radius:12px;object-fit:cover}
    .msg{margin:10px 0;color:#065f46}
    .error{margin:10px 0;color:#b91c1c}
  </style>
</head>
<body>
  <div class="app">
    <?php include("head.php"); ?>
    <?php include("sidebar.php"); ?>

    <main class="wrap">
      <section class="card">
        <h3><i class="bi bi-plus-circle"></i> Add Pet</h3>
        <?php if($msg):   ?><div class="msg"><?= $msg ?></div><?php endif; ?>
        <?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="add_pet" value="1"/>

          <div class="row2">
            <div><label>Pet Name</label><input type="text" name="name" required></div>
            <div>
              <label>Species</label>
              <select name="species" required>
                <option>Dog</option><option>Cat</option><option>Bird</option><option>Other</option>
              </select>
            </div>
            <div><label>Breed</label><input type="text" name="breed"></div>
            <div><label>Age</label><input type="text" name="age" placeholder="e.g. 2 years, 3 months"></div>
            <div>
              <label>Gender</label>
              <select name="gender"><option>Female</option><option>Male</option></select>
            </div>
            <div>
              <label>Status</label>
              <select name="status">
                <option value="available">available</option>
                <option value="pending">pending</option>
                <option value="adopted">adopted</option>
              </select>
            </div>
          </div>

          <div class="row2">
            <div><label>City (optional)</label><input type="text" name="city" placeholder="Karachi / Lahore"></div>
            <div><label>Category (optional)</label><input type="text" name="category" placeholder="Puppy / Senior / etc."></div>
          </div>

          <div class="row2">
            <div><label>Image Alt (optional)</label><input type="text" name="image_alt" placeholder="Cute brown labrador"></div>
            <div><label>Urgent Until (optional)</label><input type="date" name="urgent_until"></div>
          </div>

          <div class="row3">
            <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="featured" value="1"> Featured</label>
            <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="spotlight" value="1"> Spotlight</label>
          </div>

          <label>Photo</label>
          <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp,.gif">

          <label>Description</label>
          <textarea name="description" placeholder="Temperament, health, notes…"></textarea>

          <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Save Pet</button>
        </form>
      </section>

      <section class="card">
        <h3><i class="bi bi-collection"></i> My Pets</h3>
        <table>
          <thead>
            <tr>
              <th>Avatar</th><th>Name</th><th>Species</th><th>Breed</th><th>Age</th><th>Gender</th><th>City</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($pets as $p): ?>
              <tr>
                <td><?php if($p['avatar']): ?><img class="thumb" src="../uploads/pets/<?=e($p['avatar'])?>" alt=""><?php endif; ?></td>
                <td><?=e($p['name'])?></td>
                <td><?=e($p['species'])?></td>
                <td><?=e($p['breed'])?></td>
                <td><?=e($p['age'])?></td>
                <td><?=e($p['gender'])?></td>
                <td><?=e($p['city'] ?? '')?></td>
                <td><?=e($p['status'])?></td>
                <td>
                  <a href="?delete=<?=$p['id']?>" class="btn btn-danger" onclick="return confirm('Delete this pet?')">
                    <i class="bi bi-trash"></i> Delete
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($pets)): ?>
              <tr><td colspan="9">No pets added yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>
