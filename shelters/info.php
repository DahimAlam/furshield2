<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$ROOT = dirname(__DIR__); // C:\xampp\htdocs\furshield

// includes from project root
require_once $ROOT . '/includes/db.php';
require_once $ROOT . '/includes/auth.php';

if (!defined('BASE')) define('BASE', '/furshield');

/** Recommended: use your auth helper */
if (function_exists('require_role')) {
  require_role('shelter'); // redirects to login if not a shelter
} else {
  // Fallback guard if auth.php doesn't provide require_role()
  if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== 'shelter')) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? BASE . '/');
    header("Location: " . BASE . "/login.php?next={$next}");
    exit;
  }
}

// Now it's safe to read the user
$user       = $_SESSION['user'];          // guaranteed set here
$shelterId  = (int)($user['id'] ?? 0);    // use this in your queries
$shelterEmail = $user['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Shelter Info • FurShield</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <style>
    :root{
      --primary:#F59E0B; --bg:#FFF7ED; --text:#1F2937;
      --muted:#6B7280; --card:#fff; --ring:#e5e7eb;
      --radius:18px; --shadow:0 10px 30px rgba(0,0,0,.08);
    }
    body{margin:0;background:var(--bg);font-family:Poppins,sans-serif;color:var(--text);}
    .wrap{width:100%;margin:30px auto;padding:0 16px}
    .card{background:var(--card);border:1px solid var(--ring);border-radius:var(--radius);box-shadow:var(--shadow);padding:24px}
    h2{font-family:Montserrat;font-size:1.4rem;margin:0 0 20px;color:var(--primary)}
    label{display:block;margin:12px 0 6px;font-weight:600;font-size:.9rem}
    input,textarea{width:100%;padding:12px 14px;border:1px solid var(--ring);border-radius:12px;background:#fff;font-size:1rem}
    input:focus,textarea:focus{outline:2px solid var(--primary);border-color:var(--primary)}
    .avatar{width:100px;height:100px;border-radius:16px;background:#fff4e2;display:grid;place-items:center;color:#b45309;font-size:28px;overflow:hidden;border:1px solid var(--ring)}
    .btn{padding:12px 18px;border:0;border-radius:12px;font-weight:600;cursor:pointer;font-size:1rem}
    .btn-primary{background:linear-gradient(90deg,#fbbf24,#f59e0b);color:#111;box-shadow:0 6px 16px rgba(245,158,11,.25)}
    .preview{margin-top:24px;padding:18px;border:1px dashed var(--ring);border-radius:var(--radius);background:#fff9f0}
    .preview strong{font-size:1.1rem}
    .preview .muted{color:var(--muted);font-size:.9rem}
  </style>
</head>
<body>
  <div class="app">
    <?php include("head.php") ?>
    <?php include("sidebar.php") ?>

    <main class="wrap">
      <section class="card">
         <!-- ✅ Preview inside same card -->
        <div class="preview" style="margin-bottom:30px;">
          <h3><i class="bi bi-eye"></i> Preview</h3>
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
            <div class="avatar">
              <?php if(!empty($shelter['image'])): ?>
                <img src="../<?=htmlspecialchars($shelter['image'])?>" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
                <i class="bi bi-image"></i>
              <?php endif; ?>
            </div>
            <div>
              <strong><?=htmlspecialchars($shelter['name'] ?? 'Shelter')?></strong>
              <div class="muted"><?=htmlspecialchars($shelter['email'] ?? '')?></div>
              <div class="muted"><?=htmlspecialchars($shelter['phone'] ?? '')?></div>
            </div>
          </div>
          <p class="muted" style="font-size:.9rem">This is how your shelter will appear in the platform.</p>
        </div>
        <h2><i class="bi bi-building"></i> Shelter Information</h2>
        <form method="post" enctype="multipart/form-data">
          <label>Shelter Name</label>
          <input type="text" value="<?=htmlspecialchars($shelter['name'] ?? '')?>"/>

          <label>Email</label>
          <input type="email" value="<?=htmlspecialchars($shelter['email'] ?? '')?>"/>

          <label>Phone</label>
          <input type="text" value="<?=htmlspecialchars($shelter['phone'] ?? '')?>"/>

          <label>Upload Logo</label>
          <input type="file"/>

          <label>About Shelter</label>
          <textarea placeholder="Write about your shelter’s mission…"></textarea>

          <div style="margin-top:18px">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
          </div>
        </form>

       
      </section>
    </main>
  </div>
</body>
</html>
