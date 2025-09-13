<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$ROOT = dirname(__DIR__); // C:\xampp\htdocs\furshield
require_once $ROOT . '/includes/db.php';
require_once $ROOT . '/includes/auth.php';

if (!defined('BASE')) define('BASE','/furshield');

/* Guard: Shelter only */
if (function_exists('require_role')) {
  require_role('shelter');
} else {
  if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== 'shelter')) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? BASE.'/');
    header("Location: ".BASE."/login.php?next={$next}");
    exit;
  }
}

$shelterId = (int)($_SESSION['user']['id'] ?? 0);
$conn->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function table_exists(mysqli $c, string $t): bool {
  $t = $c->real_escape_string($t);
  $r = $c->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}'");
  return $r && $r->num_rows > 0;
}
function col_exists(mysqli $c, string $t, string $col): bool {
  $t=$c->real_escape_string($t); $col=$c->real_escape_string($col);
  $r=$c->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='{$t}' AND column_name='{$col}'");
  return $r && $r->num_rows>0;
}
function pick_col(mysqli $c, string $t, array $cands): ?string {
  foreach ($cands as $x) if (col_exists($c,$t,$x)) return $x;
  return null;
}
function scalar(mysqli $c, string $sql, array $params = [], string $types = ''){
  if (!$params){ $r=$c->query($sql); if($r){ $row=$r->fetch_row(); return $row? $row[0]:null; } return null; }
  $st=$c->prepare($sql); if($types==='') $types=str_repeat('s',count($params));
  $st->bind_param($types, ...$params); $st->execute(); $res=$st->get_result();
  $val=null; if($res){ $row=$res->fetch_row(); $val=$row? $row[0]:null; }
  $st->close(); return $val;
}
function rows(mysqli $c, string $sql, array $params = [], string $types = ''): array {
  if (!$params){ $r=$c->query($sql); return $r? $r->fetch_all(MYSQLI_ASSOC):[]; }
  $st=$c->prepare($sql); if($types==='') $types=str_repeat('s',count($params));
  $st->bind_param($types, ...$params); $st->execute(); $res=$st->get_result();
  $out = $res? $res->fetch_all(MYSQLI_ASSOC):[];
  $st->close(); return $out;
}

/* ---------- Dynamic columns ---------- */
$PET_TBL = table_exists($conn,'pets') ? 'pets' : null;
if (!$PET_TBL) { die('Pets table not found'); }

$C_NAME   = pick_col($conn,$PET_TBL, ['name','pet_name','title']);
$C_SPEC   = pick_col($conn,$PET_TBL, ['species','type']);
$C_BREED  = pick_col($conn,$PET_TBL, ['breed']);
$C_GENDER = pick_col($conn,$PET_TBL, ['gender','sex']);
$C_AGE    = pick_col($conn,$PET_TBL, ['age_years','age']);
$C_STATUS = pick_col($conn,$PET_TBL, ['status','state']);
$C_AVATAR = pick_col($conn,$PET_TBL, ['avatar','image','photo','thumbnail']);
$C_SPOT   = pick_col($conn,$PET_TBL, ['spotlight','featured']);
$C_URGUNT = pick_col($conn,$PET_TBL, ['urgent_until']);
$C_SHELID = pick_col($conn,$PET_TBL, ['shelter_id','shelter','org_id']);

if (!$C_SHELID) { // fall back: if no shelter_id column, assume global
  $C_SHELID = null;
}

/* Requests table auto-detect */
$REQ_TBL = table_exists($conn,'request') ? 'request' : (table_exists($conn,'adoption_requests') ? 'adoption_requests' : null);

/* ---------- KPIs ---------- */
$whereShel = $C_SHELID ? " WHERE `$C_SHELID`=? " : " ";
$totalPetsListed = (int)scalar($conn, "SELECT COUNT(*) FROM `$PET_TBL`".$whereShel, $C_SHELID?[$shelterId]:[], $C_SHELID?'i':'') ?: 0;

$currentlyAvailable = 0;
if ($C_STATUS){
  $currentlyAvailable = (int)scalar(
    $conn,
    "SELECT COUNT(*) FROM `$PET_TBL`".($C_SHELID?" WHERE `$C_SHELID`=? AND ":" WHERE ")."`$C_STATUS` IN ('available','approved','ready')",
    $C_SHELID?[$shelterId]:[],
    $C_SHELID?'i':''
  ) ?: 0;
}

$pendingRequests = 0;
$approvedRequests = 0;
$totalRequests = 0;
if ($REQ_TBL && col_exists($conn,$REQ_TBL,'status')){
  $pendingRequests = (int)scalar($conn, "SELECT COUNT(*) FROM `$REQ_TBL`".(col_exists($conn,$REQ_TBL,'shelter_id')?" WHERE `shelter_id`=? AND ":" WHERE ")."`status` IN ('pending','pre-approved')",
                                 col_exists($conn,$REQ_TBL,'shelter_id')?[$shelterId]:[],
                                 col_exists($conn,$REQ_TBL,'shelter_id')?'i':'') ?: 0;
  $approvedRequests = (int)scalar($conn, "SELECT COUNT(*) FROM `$REQ_TBL`".(col_exists($conn,$REQ_TBL,'shelter_id')?" WHERE `shelter_id`=? AND ":" WHERE ")."`status`='approved'",
                                   col_exists($conn,$REQ_TBL,'shelter_id')?[$shelterId]:[],
                                   col_exists($conn,$REQ_TBL,'shelter_id')?'i':'') ?: 0;
  $totalRequests = (int)scalar($conn, "SELECT COUNT(*) FROM `$REQ_TBL`".(col_exists($conn,$REQ_TBL,'shelter_id')?" WHERE `shelter_id`=?":""),
                                col_exists($conn,$REQ_TBL,'shelter_id')?[$shelterId]:[],
                                col_exists($conn,$REQ_TBL,'shelter_id')?'i':'') ?: 0;
}
$adoptionSuccessRate = $totalRequests>0 ? round(($approvedRequests/$totalRequests)*100) : 0;

/* ---------- Recent requests ---------- */
$recentReq = [];
if ($REQ_TBL){
  $colDate = col_exists($conn,$REQ_TBL,'request_date') ? 'request_date' : (col_exists($conn,$REQ_TBL,'created_at')?'created_at':'id');
  $recentReq = rows(
    $conn,
    "SELECT * FROM `$REQ_TBL`".
      (col_exists($conn,$REQ_TBL,'shelter_id')?" WHERE `shelter_id`=? ":" ").
      "ORDER BY `$colDate` DESC LIMIT 5",
    col_exists($conn,$REQ_TBL,'shelter_id')?[$shelterId]:[],
    col_exists($conn,$REQ_TBL,'shelter_id')?'i':''
  );
}

/* ---------- Urgent / Spotlight ---------- */
$urgentPets = [];
if ($C_SPOT || $C_URGUNT){
  $where = [];
  $params=[]; $types='';
  if ($C_SHELID){ $where[]="`$C_SHELID`=?"; $params[]=$shelterId; $types.='i'; }
  $urgentCond=[];
  if ($C_SPOT)   $urgentCond[] = "`$C_SPOT`=1";
  if ($C_URGUNT) $urgentCond[] = "(`$C_URGUNT` IS NOT NULL AND `$C_URGUNT`>=CURDATE())";
  $cond = $urgentCond ? '('.implode(' OR ',$urgentCond).')' : '1=0';
  $sql = "SELECT id, `$C_NAME` AS name, `$C_SPEC` AS species, ".($C_BREED?"`$C_BREED` AS breed":"'' AS breed")." FROM `$PET_TBL` ".
         (count($where)?('WHERE '.implode(' AND ',$where).' AND '.$cond):('WHERE '.$cond)).
         " ORDER BY id DESC LIMIT 5";
  $urgentPets = rows($conn,$sql,$params,$types);
}

/* ---------- Pets grid (some latest by shelter) ---------- */
$petList = [];
{
  $sel = "id, ".
         ($C_NAME?"`$C_NAME` AS name":"'' AS name").",".
         ($C_SPEC?"`$C_SPEC` AS species":"'' AS species").",".
         ($C_BREED?"`$C_BREED` AS breed":"'' AS breed").",".
         ($C_GENDER?"`$C_GENDER` AS gender":"'' AS gender").",".
         ($C_AGE?"`$C_AGE` AS age":"NULL AS age").",".
         ($C_STATUS?"`$C_STATUS` AS status":"'' AS status").",".
         ($C_AVATAR?"`$C_AVATAR` AS avatar":"'' AS avatar");
  $sql = "SELECT $sel FROM `$PET_TBL`".
         ($C_SHELID?" WHERE `$C_SHELID`=?":"").
         " ORDER BY id DESC LIMIT 9";
  $petList = rows($conn,$sql,$C_SHELID?[$shelterId]:[], $C_SHELID?'i':'');
}

/* ---------- Adoption Applications list ---------- */
$appList = [];
if ($REQ_TBL){
  $colDate = col_exists($conn,$REQ_TBL,'request_date') ? 'request_date' : (col_exists($conn,$REQ_TBL,'created_at')?'created_at':'id');
  $sql = "SELECT id, applicant_name, ".(col_exists($conn,$REQ_TBL,'pet_name')?'pet_name':'NULL AS pet_name').", ".
         (col_exists($conn,$REQ_TBL,'breed')?'breed':'NULL AS breed').", ".
         "$colDate AS d, ".
         (col_exists($conn,$REQ_TBL,'notes')?'notes':'NULL AS notes').", ".
         "status ".
         "FROM `$REQ_TBL`".
         (col_exists($conn,$REQ_TBL,'shelter_id')?" WHERE `shelter_id`=? ":" ").
         "ORDER BY $colDate DESC LIMIT 10";
  $appList = rows($conn,$sql, col_exists($conn,$REQ_TBL,'shelter_id')?[$shelterId]:[], col_exists($conn,$REQ_TBL,'shelter_id')?'i':'');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FurShield • Shelter Admin Dashboard</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

  <style>
    :root{
      --primary:#F59E0B; --accent:#EF4444; --bg:#FFF7ED; --text:#1F2937; --muted:#6B7280; --card:#FFFFFF; --ring:#f0e7da; --radius:18px; --shadow:0 10px 30px rgba(0,0,0,.08);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:var(--text);font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
    h1,h2,h3,h4,h5,h6{font-family:Montserrat, Poppins, sans-serif;margin:0 0 .5rem}
    a{text-decoration:none;color:inherit}
    .app{min-height:100vh;display:grid;grid-template-columns:280px 1fr;grid-template-rows:auto 1fr}
    aside{grid-row:1/3;background:linear-gradient(180deg,#fff, #fff8ef);border-right:1px solid var(--ring);padding:20px;position:sticky;top:0;height:100vh}
    header{grid-column:2;background:#fff;border-bottom:1px solid var(--ring);padding:14px 20px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:10}
    main{grid-column:2;padding:24px}
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}
    .brand .logo{width:44px;height:44px;border-radius:12px;background:radial-gradient(60% 60% at 30% 30%, #ffd796, #ffb84d 45%, #f59e0b 100%);display:grid;place-items:center;color:#fff;box-shadow:var(--shadow)}
    .brand h2{font-size:1.25rem;line-height:1.1}
    .brand small{display:block;color:var(--muted);font-weight:500}
    .menu{display:flex;flex-direction:column;gap:10px}
    .menu h4{font-size:.85rem;color:var(--muted);margin:16px 0 6px;text-transform:uppercase;letter-spacing:.06em}
    .menu a{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;border:1px solid transparent}
    .menu a:hover{background:#fff;border-color:var(--ring)}
    .menu a.active{background:linear-gradient(180deg,#fff,#fff3e0);border-color:#ffd9a3;box-shadow:inset 0 0 0 1px #ffe5bf}
    .menu i.bi{font-size:1.1rem;color:var(--primary)}
    .search{flex:1;display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--ring);border-radius:12px;padding:8px 12px}
    .search input{border:0;outline:0;width:100%;font:inherit}
    .badge{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid var(--ring);border-radius:999px;padding:8px 12px}
    .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;border:1px solid var(--ring);padding:10px 14px;background:#fff}
    .btn-primary{border:0;background:linear-gradient(90deg,#fbbf24,#f59e0b);color:#111;box-shadow:0 6px 16px rgba(245,158,11,.25)}
    .btn-outline{background:#fff;border:1px solid var(--ring)}
    .grid{display:grid;gap:16px}
    .kpis{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    .card{background:var(--card);border:1px solid var(--ring);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px}
    .kpi .icon{width:42px;height:42px;border-radius:12px;background:#fff4e2;display:grid;place-items:center;color:#b45309}
    .kpi h3{font-size:.95rem;color:var(--muted);font-weight:500}
    .kpi .val{font-family:Montserrat;font-size:1.8rem}
    section[hidden]{display:none!important}
    .sections > section{display:none}
    .sections > section:target{display:block}
    .sections > section:first-of-type{display:block}
    table{width:100%;border-collapse:separate;border-spacing:0 10px}
    th{font-size:.85rem;text-align:left;color:var(--muted);font-weight:600;padding:0 12px}
    td{background:#fff;border:1px solid var(--ring);border-left:4px solid #ffe1b0;padding:12px;vertical-align:middle}
    tr td:first-child{border-radius:12px 0 0 12px}
    tr td:last-child{border-radius:0 12px 12px 0}
    .status{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:.8rem;font-weight:600}
    .st-available{background:#ecfdf5;color:#065f46}
    .st-pending{background:#fff7ed;color:#9a3412}
    .st-approved{background:#ecfdf5;color:#065f46}
    .st-pre-approved{background:#eef2ff;color:#3730a3}
    .st-adopted{background:#eef2ff;color:#3730a3}
    .st-urgent{background:#fee2e2;color:#991b1b}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid var(--ring);font-size:.85rem}
    .row{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
    .col-6{grid-column:span 6}
    .col-4{grid-column:span 4}
    .col-12{grid-column:span 12}
    label{font-size:.88rem;font-weight:600;margin-bottom:6px;display:block}
    input, select, textarea{width:100%;padding:10px 12px;border:1px solid var(--ring);border-radius:12px;background:#fff;outline:none}
    textarea{min-height:110px;resize:vertical}
    .modal{position:fixed;inset:0;background:rgba(0,0,0,.18);display:none;align-items:center;justify-content:center;padding:20px}
    .modal:target{display:flex}
    .dialog{max-width:860px;width:100%;background:#fff;border-radius:20px;box-shadow:var(--shadow);border:1px solid var(--ring)}
    .dialog header{position:relative;border-bottom:1px solid var(--ring)}
    .dialog .body{padding:20px}
    .close{position:absolute;right:16px;top:14px}
    .pet-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px}
    .pet{background:#fff;border:1px solid var(--ring);border-radius:16px;overflow:hidden;box-shadow:var(--shadow)}
    .pet .img{background:#fff4e2;height:150px;display:grid;place-items:center;font-size:42px;color:#b45309}
    .pet .body{padding:12px}
    .pet .body h4{font-size:1rem;margin:0 0 6px}
    .pet .meta{display:flex;flex-wrap:wrap;gap:8px}
    .bar{height:10px;background:#fff;border:1px solid var(--ring);border-radius:999px;overflow:hidden}
    .bar > i{display:block;height:100%;background:linear-gradient(90deg,#fbbf24,#f59e0b)}
    .donut{--val:<?php echo (int)$adoptionSuccessRate; ?>;--size:96;--thickness:12;aspect-ratio:1/1;width:var(--size);border-radius:50%;background:
      conic-gradient(#f59e0b calc(var(--val)*1%), #ffeccc 0); display:grid;place-items:center}
    .donut::after{content:attr(data-label);font:700 1.05rem Montserrat;color:#b45309}
    .flex{display:flex;gap:12px;align-items:center}
    .between{justify-content:space-between}
    .muted{color:var(--muted)}
    .mt-2{margin-top:.5rem}
    .mt-3{margin-top:.75rem}
    .mt-4{margin-top:1rem}
    #menu-toggle{display:none}
    .mobilebar{display:none}
    @media (max-width: 1024px){
      .app{grid-template-columns:1fr}
      aside{position:fixed;left:0;top:0;bottom:0;transform:translateX(-100%);transition:.35s ease;z-index:999}
      #menu-toggle:checked ~ aside{transform:translateX(0)}
      header{grid-column:1;padding:12px}
      main{grid-column:1;padding:16px}
      .mobilebar{display:flex;align-items:center;gap:10px}
    }
  </style>
</head>
<body>
  <div class="app">
    <?php include __DIR__."/head.php"; ?>
    <?php include __DIR__."/sidebar.php"; ?>

    <main>
      <div class="sections">
        <!-- DASHBOARD -->
        <section id="dashboard">
          <div class="grid kpis">
            <div class="card kpi">
              <div class="flex between">
                <div>
                  <h3>Total Pets Listed</h3>
                  <div class="val"><?php echo number_format($totalPetsListed); ?></div>
                  <div class="muted mt-2">All-time</div>
                </div>
                <div class="icon"><i class="bi bi-bounding-box"></i></div>
              </div>
            </div>
            <div class="card kpi">
              <div class="flex between">
                <div>
                  <h3>Currently Available</h3>
                  <div class="val"><?php echo number_format($currentlyAvailable); ?></div>
                  <div class="muted mt-2">Ready for adoption</div>
                </div>
                <div class="icon"><i class="bi bi-heart"></i></div>
              </div>
            </div>
            <div class="card kpi">
              <div class="flex between">
                <div>
                  <h3>Pending Requests</h3>
                  <div class="val"><?php echo number_format($pendingRequests); ?></div>
                  <div class="muted mt-2">Awaiting review</div>
                </div>
                <div class="icon"><i class="bi bi-hourglass-split"></i></div>
              </div>
            </div>
            <div class="card kpi">
              <div class="flex between">
                <div>
                  <h3>Adoption Success</h3>
                  <div class="val"><?php echo (int)$adoptionSuccessRate; ?>%</div>
                  <div class="muted mt-2">Based on requests</div>
                </div>
                <div class="icon"><i class="bi bi-emoji-smile"></i></div>
              </div>
            </div>
          </div>

          <div class="grid mt-4" style="grid-template-columns:2fr 1fr">
            <div class="card">
              <div class="flex between">
                <h3>Recent Adoption Requests</h3>
                <a class="btn btn-outline" href="#requests"><i class="bi bi-arrow-right"></i> View all</a>
              </div>
              <div class="mt-3">
                <table>
                  <thead>
                    <tr><th>Pet</th><th>Owner</th><th>Date</th><th>Status</th></tr>
                  </thead>
                  <tbody>
                    <?php if(!$recentReq): ?>
                      <tr><td colspan="4" class="muted" style="text-align:center">No requests yet</td></tr>
                    <?php else: foreach($recentReq as $r): ?>
                      <tr>
                        <td><strong><?php echo e($r['pet_name'] ?? '—'); ?></strong><?php if(!empty($r['breed'])) echo ' • '.e($r['breed']); ?></td>
                        <td><?php echo e($r['applicant_name'] ?? '—'); ?></td>
                        <td><?php echo !empty($r['request_date']) ? date('d M Y', strtotime($r['request_date'])) : (!empty($r['created_at']) ? date('d M Y', strtotime($r['created_at'])) : '—'); ?></td>
                        <?php $st = strtolower($r['status'] ?? 'pending'); ?>
                        <td><span class="status <?php echo 'st-'.($st==='pre-approved'?'pre-approved':$st); ?>">
                          <i class="bi bi-<?php echo $st==='approved'?'check2-circle':($st==='pre-approved'?'stars':($st==='rejected'?'x-circle':'clock')); ?>"></i>
                          <?php echo ucwords($r['status'] ?? 'pending'); ?>
                        </span></td>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card" style="display:grid;place-items:center">
              <div class="donut" data-label="<?php echo (int)$adoptionSuccessRate; ?>%"></div>
              <div class="mt-2 muted">Adoption Success Rate</div>
            </div>
          </div>
        </section>

        <!-- PETS -->
        <section id="pets" hidden>
          <div class="flex between">
            <h3>Pets Management</h3>
            <a class="btn btn-primary" href="#add-pet"><i class="bi bi-plus-lg"></i> Add Pet</a>
          </div>

          <div class="pet-grid mt-3">
            <?php if(!$petList): ?>
              <div class="muted">No pets listed yet.</div>
            <?php else: foreach ($petList as $p): ?>
              <article class="pet">
                <div class="img">
                  <?php if(!empty($p['avatar'])): ?>
                    <img src="<?php echo e(BASE.'/uploads/pets/'.$p['avatar']); ?>" alt="<?php echo e($p['name']); ?>" style="width:100%;height:100%;object-fit:cover">
                  <?php else: ?>
                    <i class="bi bi-image"></i>
                  <?php endif; ?>
                </div>
                <div class="body">
                  <h4><?php echo e($p['name']); ?><?php if(!empty($p['species'])) echo ' • '.e($p['species']); ?></h4>
                  <div class="meta mt-2">
                    <?php if(!empty($p['gender'])): ?><span class="pill"><i class="bi bi-gender-ambiguous"></i> <?php echo e($p['gender']); ?></span><?php endif; ?>
                    <?php if(isset($p['age']) && $p['age']!==''): ?><span class="pill"><i class="bi bi-calendar3"></i> <?php echo e($p['age']); ?> yrs</span><?php endif; ?>
                    <?php if(!empty($p['status'])): ?>
                      <?php $ps = strtolower($p['status']); ?>
                      <span class="status <?php echo 'st-'.($ps==='pre-approved'?'pre-approved':$ps); ?>">
                        <i class="bi bi-<?php echo $ps==='available'?'heart':($ps==='adopted'?'award':($ps==='pending'?'clock':'heart')); ?>"></i> <?php echo ucwords($p['status']); ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="mt-3 flex">
                    <a class="btn btn-outline" href="#edit-pet"><i class="bi bi-pencil"></i> Edit</a>
                    <a class="btn btn-outline" href="#upload-img"><i class="bi bi-cloud-arrow-up"></i> Upload Images</a>
                    <a class="btn" href="#urgent"><i class="bi bi-lightning"></i> Mark Urgent</a>
                  </div>
                </div>
              </article>
            <?php endforeach; endif; ?>
          </div>

          <?php if($urgentPets): ?>
          <div class="card mt-4">
            <h3>Urgent / Spotlight</h3>
            <div class="mt-3">
              <table>
                <thead><tr><th>Pet</th><th>Type</th><th>Reason</th><th>Requested On</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach($urgentPets as $u): ?>
                  <tr>
                    <td><strong><?php echo e($u['name']); ?></strong><?php if(!empty($u['breed'])) echo ' • '.e($u['breed']); ?></td>
                    <td><?php echo ($C_SPOT && (int)scalar($conn,"SELECT `$C_SPOT` FROM `$PET_TBL` WHERE id=?",[$u['id']],"i")===1) ? 'Spotlight' : 'Urgent'; ?></td>
                    <td><?php echo $C_URGUNT ? 'Time-bound urgency' : 'High visibility'; ?></td>
                    <td><?php echo date('d M Y'); ?></td>
                    <td><span class="status st-pending"><i class="bi bi-clock"></i> Waiting</span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>
        </section>

        <!-- ADOPTION REQUESTS -->
        <section id="requests" hidden>
          <div class="card">
            <div class="flex between">
              <h3>Adoption Applications</h3>
              <div class="flex">
                <span class="pill"><i class="bi bi-filter"></i> Filter</span>
                <span class="pill"><i class="bi bi-funnel"></i> Pending</span>
              </div>
            </div>
            <div class="mt-3">
              <table>
                <thead>
                  <tr><th>Pet</th><th>Owner</th><th>Submitted</th><th>Notes</th><th>Decision</th></tr>
                </thead>
                <tbody>
                  <?php if(!$appList): ?>
                    <tr><td colspan="5" class="muted" style="text-align:center">No applications found.</td></tr>
                  <?php else: foreach($appList as $r): ?>
                    <tr>
                      <td><strong><?php echo e($r['pet_name'] ?? '—'); ?></strong><?php if(!empty($r['breed'])) echo ' • '.e($r['breed']); ?></td>
                      <td><?php echo e($r['applicant_name'] ?? '—'); ?></td>
                      <td><?php echo !empty($r['d']) ? date('d M Y', strtotime($r['d'])) : '—'; ?></td>
                      <td><?php echo e($r['notes'] ?? ''); ?></td>
                      <td>
                        <span class="flex">
                          <?php if ($REQ_TBL): ?>
                            <a class="btn" href="<?php echo e('update-request.php?id='.$r['id'].'&status=rejected'); ?>"><i class="bi bi-x"></i> Reject</a>
                            <a class="btn btn-primary" href="<?php echo e('update-request.php?id='.$r['id'].'&status=approved'); ?>"><i class="bi bi-check2"></i> Approve</a>
                          <?php else: ?>
                            <span class="muted">Actions unavailable</span>
                          <?php endif; ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
              <div class="muted mt-2">On approval, system should set pet status = Adopted (implement in update-request.php).</div>
            </div>
          </div>
        </section>

        <!-- PROFILE (static form placeholder – hook with your update endpoint) -->
        <section id="profile" hidden>
          <div class="card">
            <h3>Update Shelter Information</h3>
            <div class="row mt-3">
              <div class="col-6">
                <label>Shelter Name</label>
                <input type="text" placeholder="<?php echo e($_SESSION['user']['full_name'] ?? 'Happy Tails Shelter'); ?>" />
              </div>
              <div class="col-6">
                <label>Registration ID</label>
                <input type="text" placeholder="REG-PAK-00123" />
              </div>
              <div class="col-6">
                <label>Address</label>
                <input type="text" placeholder="Street, City, Province" />
              </div>
              <div class="col-3">
                <label>Capacity</label>
                <input type="number" placeholder="120" />
              </div>
              <div class="col-3">
                <label>Contact</label>
                <input type="text" placeholder="<?php echo e($_SESSION['user']['phone'] ?? '0300-0000000'); ?>" />
              </div>
              <div class="col-12 mt-2">
                <button class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
              </div>
            </div>
          </div>
        </section>

        <!-- REPORTS (simple visual snapshot) -->
        <section id="reports" hidden>
          <div class="grid" style="grid-template-columns:2fr 1fr">
            <div class="card">
              <h3>Inventory Snapshot</h3>
              <?php
                $countPending = (int)$pendingRequests;
                $countAdopted = $C_STATUS ? (int)scalar($conn,"SELECT COUNT(*) FROM `$PET_TBL`".($C_SHELID?" WHERE `$C_SHELID`=? AND ":" WHERE ")."`$C_STATUS`='adopted'",$C_SHELID?[$shelterId]:[],$C_SHELID?'i':'') : 0;
                $countUrgent  = ($C_SPOT||$C_URGUNT) ? (int)scalar($conn,"SELECT COUNT(*) FROM `$PET_TBL`".($C_SHELID?" WHERE `$C_SHELID`=? AND ":" WHERE ")."(".($C_SPOT?"`$C_SPOT`=1":"0").($C_URGUNT? " OR (`$C_URGUNT` IS NOT NULL AND `$C_URGUNT`>=CURDATE())":"").")",$C_SHELID?[$shelterId]:[],$C_SHELID?'i':'') : 0;
              ?>
              <div class="mt-3 grid" style="grid-template-columns:1fr 1fr;gap:16px">
                <div>
                  <div class="flex between"><span class="muted">Available</span><span><?php echo $currentlyAvailable; ?></span></div>
                  <div class="bar mt-2"><i style="width:<?php echo $totalPetsListed? max(6, round($currentlyAvailable*100/$totalPetsListed)):0; ?>%"></i></div>
                </div>
                <div>
                  <div class="flex between"><span class="muted">Pending</span><span><?php echo $countPending; ?></span></div>
                  <div class="bar mt-2"><i style="width:<?php echo $totalRequests? max(6, round($countPending*100/max(1,$totalRequests))):0; ?>%"></i></div>
                </div>
                <div>
                  <div class="flex between"><span class="muted">Adopted</span><span><?php echo $countAdopted; ?></span></div>
                  <div class="bar mt-2"><i style="width:<?php echo $totalPetsListed? max(6, round($countAdopted*100/$totalPetsListed)):0; ?>%"></i></div>
                </div>
                <div>
                  <div class="flex between"><span class="muted">Urgent</span><span><?php echo $countUrgent; ?></span></div>
                  <div class="bar mt-2"><i style="width:<?php echo $totalPetsListed? max(6, round($countUrgent*100/$totalPetsListed)):0; ?>%"></i></div>
                </div>
              </div>
            </div>
            <div class="card" style="display:grid;place-items:center">
              <div class="donut" data-label="<?php echo (int)$adoptionSuccessRate; ?>%"></div>
              <div class="mt-2 muted">Adoption Success Rate</div>
            </div>
          </div>
        </section>

        <!-- URGENT (anchor target for quick action) -->
        <section id="urgent" hidden>
          <div class="card">
            <div class="flex between">
              <h3>Urgent / Spotlight Requests</h3>
              <span class="pill"><i class="bi bi-shield-check"></i> Admin approval may be required</span>
            </div>
            <div class="mt-3">
              <?php if(!$urgentPets): ?>
                <div class="muted">No urgent/spotlight items right now.</div>
              <?php else: ?>
                <table>
                  <thead><tr><th>Pet</th><th>Type</th><th>Reason</th><th>Requested On</th><th>Status</th></tr></thead>
                  <tbody>
                  <?php foreach($urgentPets as $u): ?>
                    <tr>
                      <td><strong><?php echo e($u['name']); ?></strong><?php if(!empty($u['breed'])) echo ' • '.e($u['breed']); ?></td>
                      <td><?php echo ($C_SPOT && (int)scalar($conn,"SELECT `$C_SPOT` FROM `$PET_TBL` WHERE id=?",[$u['id']],"i")===1) ? 'Spotlight' : 'Urgent'; ?></td>
                      <td><?php echo $C_URGUNT ? 'Time-bound urgency' : 'High visibility'; ?></td>
                      <td><?php echo date('d M Y'); ?></td>
                      <td><span class="status st-pending"><i class="bi bi-clock"></i> Waiting</span></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <!-- COMMS: placeholder (wire to your messaging system if you have one) -->
        <section id="comms" hidden>
          <div class="grid" style="grid-template-columns:1.8fr 1fr">
            <div class="card">
              <h3>Inbox</h3>
              <div class="mt-3 muted">Connect this with your messages table to populate.</div>
            </div>
            <div class="card">
              <h3>Compose</h3>
              <div class="mt-3">
                <label>To</label>
                <input type="email" placeholder="owner@example.com" />
                <label class="mt-3">Message</label>
                <textarea placeholder="Write your message…"></textarea>
                <div class="mt-3 flex between">
                  <span class="muted">Send updates, reminders, approvals</span>
                  <button class="btn btn-primary"><i class="bi bi-send"></i> Send</button>
                </div>
              </div>
            </div>
          </div>
        </section>

      </div>
    </main>
  </div>

  <!-- Add Pet (Modal – still UI placeholder) -->
  <div class="modal" id="add-pet">
    <div class="dialog">
      <header class="flex between">
        <h3>Add New Pet</h3>
        <a class="btn close" href="#pets"><i class="bi bi-x"></i></a>
      </header>
      <div class="body">
        <div class="row">
          <div class="col-6">
            <label>Pet Name</label>
            <input type="text" placeholder="Luna" />
          </div>
          <div class="col-3">
            <label>Species</label>
            <select>
              <option>Dog</option><option>Cat</option><option>Bird</option><option>Other</option>
            </select>
          </div>
          <div class="col-3">
            <label>Breed</label>
            <input type="text" placeholder="Husky" />
          </div>
          <div class="col-4">
            <label>Age</label>
            <input type="text" placeholder="2 years" />
          </div>
          <div class="col-4">
            <label>Gender</label>
            <select><option>Female</option><option>Male</option></select>
          </div>
          <div class="col-4">
            <label>Status</label>
            <select>
              <option>Available</option>
              <option>Pending</option>
              <option>Adopted</option>
            </select>
          </div>
          <div class="col-12">
            <label>Health Notes</label>
            <textarea placeholder="Vaccinated, dewormed…"></textarea>
          </div>
          <div class="col-12">
            <label>Upload Photos (multiple)</label>
            <input type="file" multiple />
          </div>
          <div class="col-12 mt-2">
            <a class="btn" href="#pets"><i class="bi bi-x"></i> Cancel</a>
            <a class="btn btn-primary" href="#pets"><i class="bi bi-check2"></i> Save Pet</a>
          </div>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
