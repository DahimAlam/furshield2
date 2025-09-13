<?php
// ---- NO OUTPUT BEFORE THIS LINE (UTF-8 without BOM) ----
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

/* -------- Auth guard (Owner only) -------- */
$LOGIN = '../login.php';                           // apne project ke hisaab se path adjust kar sakte ho
if (empty($_SESSION['user']['id'])) { header('Location: '.$LOGIN); exit; }
$ownerId = (int)$_SESSION['user']['id'];
$displayName = htmlspecialchars($_SESSION['user']['name'] ?? 'Owner', ENT_QUOTES, 'UTF-8');

/* -------- Small helpers -------- */
function has_table(mysqli $c, string $t): bool {
  $t = $c->real_escape_string($t);
  if ($res = $c->query("SHOW TABLES LIKE '$t'")) { $ok = $res->num_rows>0; $res->free(); return $ok; }
  return false;
}
function table_cols(mysqli $c, string $t): array {
  $cols = [];
  if ($res = $c->query("SHOW COLUMNS FROM `$t`")) {
    while ($r = $res->fetch_assoc()) $cols[strtolower($r['Field'])] = true;
    $res->free();
  }
  return $cols;
}
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function years_from(?string $date): ?int {
  if (!$date) return null;
  try {
    $d = new DateTime($date);
    $now = new DateTime();
    return (int)$now->diff($d)->y;
  } catch(Throwable $e){ return null; }
}

/* -------- KPI: Pets count -------- */
$petCount = 0;
if (has_table($conn,'pets')) {
  $pc = table_cols($conn,'pets');
  $ownerCol = !empty($pc['owner_id']) ? 'owner_id' : (!empty($pc['user_id']) ? 'user_id' : null);
  if ($ownerCol) {
    $sql = "SELECT COUNT(*) c FROM pets WHERE $ownerCol=?";
    $stmt = $conn->prepare($sql); $stmt->bind_param('i',$ownerId); $stmt->execute();
    $stmt->bind_result($petCount); $stmt->fetch(); $stmt->close();
  }
}

/* -------- KPI: Upcoming appointments count -------- */
$upcomingCount = 0;
if (has_table($conn,'appointments')) {
  $ac = table_cols($conn,'appointments');
  $ownerCol = !empty($ac['owner_id']) ? 'owner_id' : null;
  $timeCol  = !empty($ac['appointment_time']) ? 'appointment_time' : (!empty($ac['time']) ? 'time' : null);
  $statusCol = !empty($ac['status']) ? 'status' : null;

  if ($ownerCol && $timeCol) {
    // pending/approved ko upcoming ma count karte hain
    $sql = $statusCol
      ? "SELECT COUNT(*) c FROM appointments WHERE $ownerCol=? AND $timeCol>=NOW() AND $statusCol IN ('pending','approved')"
      : "SELECT COUNT(*) c FROM appointments WHERE $ownerCol=? AND $timeCol>=NOW()";
    $stmt = $conn->prepare($sql); $stmt->bind_param('i',$ownerId); $stmt->execute();
    $stmt->bind_result($upcomingCount); $stmt->fetch(); $stmt->close();
  }
}

/* -------- KPI: Vaccines due -------- */
$vaccinesDue = 0;
if (has_table($conn,'vaccinations') && has_table($conn,'pets')) {
  $vc = table_cols($conn,'vaccinations');
  $pc = table_cols($conn,'pets');
  $ownerCol = !empty($pc['owner_id']) ? 'owner_id' : (!empty($pc['user_id']) ? 'user_id' : null);
  $dueCol   = !empty($vc['due_date']) ? 'due_date' : null;
  $doneCol  = !empty($vc['is_done']) ? 'is_done' : (!empty($vc['status']) ? 'status' : null);
  if ($ownerCol && $dueCol) {
    $sql = "SELECT COUNT(*) c
            FROM vaccinations v
            JOIN pets p ON p.id=v.pet_id
            WHERE p.$ownerCol=? AND v.$dueCol<=CURDATE() AND (";
    // not done condition
    if ($doneCol==='is_done')      $sql .= "COALESCE(v.is_done,0)=0)";
    elseif ($doneCol==='status')   $sql .= "LOWER(COALESCE(v.status,'')) NOT IN ('done','completed','given'))";
    else                           $sql .= "1=1)";
    $stmt = $conn->prepare($sql); $stmt->bind_param('i',$ownerId); $stmt->execute();
    $stmt->bind_result($vaccinesDue); $stmt->fetch(); $stmt->close();
  }
}

/* -------- KPI: Adoption requests -------- */
$adoptionCount = 0;
if (has_table($conn,'adoptions') || has_table($conn,'adoption_requests')) {
  $tbl = has_table($conn,'adoptions') ? 'adoptions' : 'adoption_requests';
  $acols = table_cols($conn,$tbl);
  $ownerCol = !empty($acols['owner_id']) ? 'owner_id' : (!empty($acols['user_id']) ? 'user_id' : null);
  $statusCol = !empty($acols['status']) ? 'status' : null;
  if ($ownerCol) {
    $sql = $statusCol
      ? "SELECT COUNT(*) c FROM `$tbl` WHERE $ownerCol=? AND LOWER($statusCol) IN ('pending','requested')"
      : "SELECT COUNT(*) c FROM `$tbl` WHERE $ownerCol=?";
    $stmt = $conn->prepare($sql); $stmt->bind_param('i',$ownerId); $stmt->execute();
    $stmt->bind_result($adoptionCount); $stmt->fetch(); $stmt->close();
  }
}

/* -------- KPI: Wishlist count -------- */
$wishlistCount = 0;
if (has_table($conn,'wishlist')) {
  $wc = table_cols($conn,'wishlist');
  $userCol = !empty($wc['user_id']) ? 'user_id' : (!empty($wc['owner_id']) ? 'owner_id' : null);
  if ($userCol) {
    $sql = "SELECT COUNT(*) c FROM wishlist WHERE $userCol=?";
    $stmt = $conn->prepare($sql); $stmt->bind_param('i',$ownerId); $stmt->execute();
    $stmt->bind_result($wishlistCount); $stmt->fetch(); $stmt->close();
  }
}

/* -------- My Pets (top 3) -------- */
$PET_IMG_BASE = '../images/pet/';  // adjust if needed
$pets = [];
if (has_table($conn,'pets')) {
  $pc = table_cols($conn,'pets');
  $ownerCol = !empty($pc['owner_id']) ? 'owner_id' : (!empty($pc['user_id']) ? 'user_id' : null);
  if ($ownerCol) {
    // Select what we can find
    $name = !empty($pc['name']) ? 'name' : 'id';
    $type = !empty($pc['type']) ? 'type' : (!empty($pc['species']) ? 'species' : (!empty($pc['category'])?'category':"''"));
    $breed= !empty($pc['breed']) ? 'breed' : "''";
    $gender = !empty($pc['gender']) ? 'gender' : (!empty($pc['sex'])?'sex':"''");
    $dob = !empty($pc['dob']) ? 'dob' : (!empty($pc['birthdate'])?'birthdate':null);
    $ageY = !empty($pc['age_years']) ? 'age_years' : null;
    $img = !empty($pc['image']) ? 'image' : (!empty($pc['photo'])?'photo':(!empty($pc['avatar'])?'avatar':(!empty($pc['picture'])?'picture':null)));

    $sql = "SELECT id, $name AS nm, $type AS tp, $breed AS brd, ".
           ($gender ? "$gender AS gndr, " : "'' AS gndr, ").
           ($dob ? "$dob AS dob, " : "NULL AS dob, ").
           ($ageY ? "$ageY AS agey, " : "NULL AS agey, ").
           ($img ? "$img AS img" : "NULL AS img").
           " FROM pets WHERE $ownerCol=? ORDER BY id DESC LIMIT 3";
    $stmt = $conn->prepare($sql); $stmt->bind_param('i',$ownerId); $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()){
      $age = $r['agey'] !== null ? (int)$r['agey'] : years_from($r['dob']);
      $pets[] = [
        'name' => $r['nm'] ?: 'Pet #'.$r['id'],
        'type' => $r['tp'] ?: 'Pet',
        'breed'=> $r['brd'] ?: '',
        'gender'=> $r['gndr'] ?: '',
        'age'  => $age,
        'img'  => $r['img'] ? ($PET_IMG_BASE.$r['img']) : null,
      ];
    }
    $stmt->close();
  }
}

/* -------- Upcoming Appointments (top 3) -------- */
$appts = [];
if (has_table($conn,'appointments')) {
  $ac = table_cols($conn,'appointments');
  $pc = has_table($conn,'pets') ? table_cols($conn,'pets') : [];
  $uc = has_table($conn,'users') ? table_cols($conn,'users') : [];

  $ownerCol = !empty($ac['owner_id']) ? 'owner_id' : null;
  $timeCol  = !empty($ac['appointment_time']) ? 'appointment_time' : (!empty($ac['time']) ? 'time' : null);
  $statusCol= !empty($ac['status']) ? 'status' : null;

  $petJoin = (has_table($conn,'pets') && !empty($ac['pet_id'])) ? "JOIN pets p ON p.id=a.pet_id" : "LEFT JOIN pets p ON 1=0";
  $petName = !empty($pc['name']) ? 'p.name' : 'NULL';

  $vetJoin = (has_table($conn,'users') && !empty($ac['vet_id'])) ? "JOIN users v ON v.id=a.vet_id" : "LEFT JOIN users v ON 1=0";
  $vetName = !empty($uc['name']) ? 'v.name' : 'NULL';

  if ($ownerCol && $timeCol) {
    $sql = "SELECT a.id, $timeCol AS atime, ".($petName)." AS pet_name, ".($vetName)." AS vet_name, ".
           ($statusCol ? "a.$statusCol AS st" : "NULL AS st")."
           FROM appointments a
           $petJoin
           $vetJoin
           WHERE a.$ownerCol=? AND a.$timeCol>=NOW() ".
           ($statusCol ? "AND a.$statusCol IN ('pending','approved')" : "")."
           ORDER BY a.$timeCol ASC
           LIMIT 3";
    $stmt = $conn->prepare($sql); $stmt->bind_param('i',$ownerId); $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()){
      $dt = new DateTime($r['atime']);
      $appts[] = [
        'when' => $dt->format('D, d M • g:i A'),
        'pet'  => $r['pet_name'] ?: '—',
        'vet'  => $r['vet_name'] ?: '—',
      ];
    }
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • Owner Dashboard</title>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>

  <style>
    :root{
      --primary:#F59E0B; --accent:#EF4444; --bg:#FFF7ED; --text:#1F2937;
      --card:#FFFFFF; --muted:#6B7280; --ring:#E5E7EB;
      --radius:18px; --shadow:0 10px 30px rgba(0,0,0,.08); --shadow-sm:0 6px 16px rgba(0,0,0,.06);
    }
    *{box-sizing:border-box} html,body{height:100%} body{margin:0}
    body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif;line-height:1.5}

    .page{margin-left:280px;padding:28px 24px 60px}
    .hero{display:grid;grid-template-columns:1fr;gap:24px;align-items:stretch}
    .hero-text{background:linear-gradient(135deg,#fff,#fff8ec);border:1px solid #f2e5d1;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow)}
    .hero-text h1{font-family:Montserrat,sans-serif;font-size:32px;margin:0 0 6px}
    .hero-text h1 span{color:var(--primary)}
    .hero-text p{margin:0 0 16px;color:#4b5563}
    .quick{display:flex;flex-wrap:wrap;gap:12px}
    .qbtn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;background:#fff;border:1px solid #f0e7da;box-shadow:var(--shadow-sm);text-decoration:none;color:inherit;font-weight:600}

    .kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin:24px 0}
    .kpi{position:relative;display:grid;grid-template-columns:auto 1fr;align-items:center;gap:12px;background:#fff;border:1px solid #f1e6d7;border-radius:16px;padding:14px 14px;box-shadow:var(--shadow-sm);overflow:hidden}
    .kpi .ico{display:grid;place-items:center;width:44px;height:44px;border-radius:12px;background:#fff7ef;border:1px solid #f2e5d1}
    .kpi .ico i{font-size:20px;color:#b45309}
    .kpi .meta b{display:block;font-family:Montserrat,sans-serif;font-size:22px}
    .kpi .meta span{font-size:12px;color:var(--muted)}
    .spark{position:absolute;right:-10px;bottom:-10px;width:120px;height:60px;background:radial-gradient(120px 60px at 0% 100%,#ffd79a,transparent 70%)}

    .card{background:#fff;border:1px solid #f1e6d7;border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .card-head h2{font-family:Montserrat,sans-serif;margin:0;font-size:20px}

    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
    .grid-3{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}

    .pets{display:flex;flex-direction:column;gap:12px}
    .pet{display:grid;grid-template-columns:64px 1fr auto;gap:12px;align-items:center;border:1px solid #f4eadc;padding:10px;border-radius:14px;background:#fff}
    .pet img{width:64px;height:64px;border-radius:12px;object-fit:cover}
    .pet b{font-family:Montserrat,sans-serif}
    .tags{display:flex;gap:8px;margin-top:6px;flex-wrap:wrap}
    .chip{padding:6px 10px}
    .chip.ok{background:#ecfdf5;border-color:#bbf7d0;color:#047857}
    .chip.warn{background:#fff7ed;border-color:#fde68a;color:#b45309}
    .chip.info{background:#eef2ff;border-color:#e0e7ff;color:#3730a3}
    .pet-actions{display:flex;gap:8px}
    .icon-btn{width:38px;height:38px}
    .icon-btn.danger{border-color:#ffe0e0;color:#b42318}

    .timeline{display:flex;flex-direction:column;gap:14px}
    .tl-item{display:grid;grid-template-columns:16px 1fr;gap:12px;align-items:start}
    .dot{width:12px;height:12px;border-radius:50%;margin-top:6px;border:3px solid #fff;box-shadow:0 0 0 3px #fdeacc}
    .dot.ok{background:#10b981}.dot.warn{background:#f59e0b}.dot.info{background:#6366f1}
    .tl-meta b{display:block;font-family:Montserrat,sans-serif}
    .tl-meta span{color:var(--muted);font-size:13px}
    .tl-actions{display:flex;gap:8px;margin-top:8px}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:999px;background:linear-gradient(135deg,var(--primary),#ffb444);color:#fff;text-decoration:none;font-weight:600;font-size:13px}
    .pill.ghost{background:#fff;border:1px solid #f0e7da;color:#92400e}

    .list{display:flex;flex-direction:column;gap:12px}
    .li{display:grid;grid-template-columns:64px 1fr auto;gap:12px;align-items:center;border:1px solid #f4eadc;padding:10px;border-radius:14px;background:#fff}
    .li img{width:64px;height:64px;border-radius:12px;object-fit:cover}

    .notif{display:flex;flex-direction:column;gap:10px}
    .n{display:grid;grid-template-columns:24px 1fr auto;gap:12px;align-items:center;border:1px dashed #f4eadc;padding:10px;border-radius:14px;background:#fff}
    .n i{font-size:18px;color:#b45309}
    .time{color:var(--muted);font-size:12px}

    .records{display:flex;flex-direction:column;gap:10px}
    .rec{display:grid;grid-template-columns:24px 1fr auto;gap:12px;align-items:center;border:1px solid #f4eadc;padding:10px;border-radius:14px;background:#fff}
    .rec i{font-size:18px;color:#0ea5e9}

    .guides{display:flex;flex-direction:column;gap:10px}
    .guide{position:relative;display:flex;flex-direction:column;gap:2px;border:1px solid #f4eadc;padding:12px;border-radius:14px;background:#fff;text-decoration:none;color:inherit}
    .badge{position:absolute;top:-10px;left:12px;background:#111827;color:#fff;font-size:11px;padding:4px 8px;border-radius:999px}
    .feedback-box{margin-top:6px;display:flex;align-items:center;justify-content:space-between;border-top:1px dashed #f4eadc;padding-top:10px}
    .stars i{font-size:16px;color:#f59e0b}

    .foot{display:flex;align-items:center;justify-content:space-between;margin-top:20px;padding-top:12px;border-top:1px solid #f3e7d9}
    .foot-links{display:flex;gap:14px}
    .foot a{text-decoration:none;color:inherit;opacity:.8}

    #nav-toggle{display:none}
    @media (max-width: 1200px){
      .kpis{grid-template-columns:repeat(3,1fr)}
      .grid-3{grid-template-columns:1fr 1fr}
    }
    @media (max-width: 992px){
      .menu-btn{display:grid}
      .sidebar{transform:translateX(-100%)}
      #nav-toggle:checked ~ .sidebar{transform:translateX(0)}
      .page{margin-left:0}
      .topbar{grid-template-columns:1fr 1fr auto}
      .kpis{grid-template-columns:repeat(2,1fr)}
    }
    @media (max-width: 640px){
      .topbar{grid-template-columns:auto 1fr auto}
      .kpis{grid-template-columns:1fr}
      .grid-2,.grid-3{grid-template-columns:1fr}
      .li,.pet{grid-template-columns:56px 1fr auto}
      .search{padding:6px 8px}
      .hero-text h1{font-size:26px}
    }
  </style>
</head>
<body class="bg-app">
  <input id="nav-toggle" type="checkbox" hidden>
<?php
// Header/Sidebar include: make sure these files DO NOT call header() / session_start() again
if (file_exists(__DIR__.'/header.php'))  include __DIR__.'/header.php';
if (file_exists(__DIR__.'/sidebar.php')) include __DIR__.'/sidebar.php';
?>

  <main class="page">
    <section class="hero">
      <div class="hero-text">
        <h1>Welcome back, <span><?= $displayName ?></span></h1>
        <p>Track pets, appointments, adoptions, and learning—everything in one clean dashboard.</p>
        <div class="quick">
          <a class="qbtn" href="mypet.php"><i class="bi bi-emoji-smile"></i>Add Pet</a>
          <a class="qbtn" href="appointement.php"><i class="bi bi-calendar-plus"></i>Book Vet</a>
          <a class="qbtn" href="addopt.php"><i class="bi bi-heart"></i>Find Adoption</a>
          <a class="qbtn" href="HealthRecords.php"><i class="bi bi-file-earmark-arrow-up"></i>Upload Report</a>
        </div>
      </div>
    </section>

    <section class="kpis">
      <div class="kpi">
        <div class="ico"><i class="bi bi-emoji-smile"></i></div>
        <div class="meta">
          <b><?= (int)$petCount ?></b>
          <span>My Pets</span>
        </div>
        <div class="spark s1"></div>
      </div>
      <div class="kpi">
        <div class="ico"><i class="bi bi-calendar2-check"></i></div>
        <div class="meta">
          <b><?= (int)$upcomingCount ?></b>
          <span>Upcoming Appointments</span>
        </div>
        <div class="spark s2"></div>
      </div>
      <div class="kpi">
        <div class="ico"><i class="bi bi-prescription2"></i></div>
        <div class="meta">
          <b><?= (int)$vaccinesDue ?></b>
          <span>Vaccines Due</span>
        </div>
        <div class="spark s3"></div>
      </div>
      <div class="kpi">
        <div class="ico"><i class="bi bi-heart"></i></div>
        <div class="meta">
          <b><?= (int)$adoptionCount ?></b>
          <span>Adoption Requests</span>
        </div>
        <div class="spark s4"></div>
      </div>
      <div class="kpi">
        <div class="ico"><i class="bi bi-bookmark-heart"></i></div>
        <div class="meta">
          <b><?= (int)$wishlistCount ?></b>
          <span>Wishlist Items</span>
        </div>
        <div class="spark s5"></div>
      </div>
    </section>

    <section class="grid-2">
      <div class="card">
        <div class="card-head">
          <h2>My Pets</h2>
          <div class="actions">
            <a class="qbtn" href="mypet.php"><i class="bi bi-plus-circle"></i>Add</a>
          </div>
        </div>
        <div class="pets">
          <?php if (!$pets): ?>
            <div class="muted">No pets yet.</div>
          <?php else: foreach ($pets as $p): ?>
            <div class="pet">
              <img src="<?= e($p['img'] ?? 'https://placehold.co/96x96?text=Pet') ?>" alt="">
              <div>
                <b><?= e($p['name']) ?></b>
                <span>
                  <?= e($p['type']) ?>
                  <?= $p['breed']? ' • '.e($p['breed']) : '' ?>
                  <?= $p['age']!==null? ' • '.(int)$p['age'].'y' : '' ?>
                  <?= $p['gender']? ' • '.e(ucfirst($p['gender'])) : '' ?>
                </span>
                <div class="tags">
                  <span class="chip ok"><i class="bi bi-shield-check"></i>Healthy</span>
                </div>
              </div>
              <div class="pet-actions">
                <a class="icon-btn" href="mypet.php"><i class="bi bi-pencil-square"></i></a>
                <a class="icon-btn" href="HealthRecords.php"><i class="bi bi-file-medical"></i></a>
                <a class="icon-btn danger" href="mypet.php"><i class="bi bi-trash"></i></a>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Upcoming Appointments</h2>
          <a class="qbtn" href="appointement.php"><i class="bi bi-calendar-plus"></i>Book</a>
        </div>
        <div class="timeline">
          <?php if (!$appts): ?>
            <div class="muted">No upcoming appointments.</div>
          <?php else: foreach ($appts as $a): ?>
            <div class="tl-item">
              <div class="dot ok"></div>
              <div class="tl-meta">
                <b><?= e($a['pet']) ?> • <?= e($a['vet']) ?></b>
                <span><?= e($a['when']) ?> • Clinic</span>
                <div class="tl-actions">
                  <a class="pill" href="appointement.php">Reschedule</a>
                  <a class="pill ghost" href="appointement.php">Cancel</a>
                </div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </section>

    <!-- Neeche ke sections abhi static rehne den; jab health/adoption modules finalize ho jayein to DB-bind kar denge -->
    <section class="grid-3">
      <div class="card">
        <div class="card-head">
          <h2>Adoption</h2>
          <a class="qbtn" href="addopt.php"><i class="bi bi-heart"></i>Browse</a>
        </div>
        <div class="list">
          <div class="muted">Your latest adoption requests will appear here.</div>
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Notifications</h2>
          <a class="qbtn"><i class="bi bi-check2-all"></i>Mark all read</a>
        </div>
        <div class="notif">
          <div class="muted">No notifications yet.</div>
        </div>
      </div>
    </section>

    <section class="grid-2">
      <div class="card">
        <div class="card-head">
          <h2>Health Records</h2>
          <a class="qbtn" href="HealthRecords.php"><i class="bi bi-upload"></i>Upload Report</a>
        </div>
        <div class="records">
          <div class="muted">Upload and manage your pet health records.</div>
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Care Guides & Support</h2>
          <a class="qbtn" href="caregide.php"><i class="bi bi-journal-text"></i>All Articles</a>
        </div>
        <div class="guides">
          <a class="guide" href="caregide.php">
            <div class="badge">Guide</div>
            <b>Vaccination Timeline for Puppies</b>
            <span>Checklist + reminders</span>
          </a>
          <a class="guide" href="caregide.php">
            <div class="badge">Blog</div>
            <b>Seasonal Allergies: What to Watch</b>
            <span>Symptoms & tips</span>
          </a>
          <a class="guide" href="caregide.php">
            <div class="badge">FAQ</div>
            <b>How to upload vet certificates?</b>
            <span>Step-by-step</span>
          </a>
        </div>
      </div>
    </section>

    <footer class="foot">
      <div>© FurShield</div>
      <div class="foot-links">
        <a>Privacy</a><a>Terms</a><a>Support</a>
      </div>
    </footer>
  </main>
</body>
</html>
