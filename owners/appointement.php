<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

if (!isset($_SESSION['user']['id']) && !isset($_SESSION['owner']['id'])) { http_response_code(401); die('Login required'); }
$ownerId = (int)($_SESSION['user']['id'] ?? $_SESSION['owner']['id'] ?? 0);

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qs(array $patch=[]){ $q = array_merge($_GET, $patch); foreach($q as $k=>$v) if($v===null) unset($q[$k]); $uri = strtok($_SERVER['REQUEST_URI'],'?'); return $uri.(count($q)?('?'.http_build_query($q)):''); }

/* ----- Delete (single / bulk) ----- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (($_POST['action'] ?? '') === 'delete_one') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id=? AND owner_id=?");
    $stmt->bind_param('ii', $id, $ownerId);
    $stmt->execute(); $stmt->close();
    header('Location: '.qs(['msg'=>'Deleted'])); exit;
  }
  if (($_POST['action'] ?? '') === 'bulk_delete') {
    $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
    if ($ids) {
      $in  = implode(',', array_fill(0, count($ids), '?'));
      $types = str_repeat('i', count($ids)).'i';
      $sql = "DELETE FROM appointments WHERE id IN ($in) AND owner_id=?";
      $stmt = $conn->prepare($sql);
      $params = array_merge($ids, [$ownerId]);
      $stmt->bind_param($types, ...$params);
      $stmt->execute(); $stmt->close();
    }
    header('Location: '.qs(['msg'=>'Deleted'])); exit;
  }
}

/* ----- Filters (GET) ----- */
$uiStatus = $_GET['status'] ?? 'All';
$mapToDb  = ['All'=>null,'Pending'=>'pending','Confirmed'=>'approved','Completed'=>'completed','Cancelled'=>'cancelled'];
$dbStatus = $mapToDb[$uiStatus] ?? null;

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$q    = trim($_GET['q'] ?? '');

/* ----- Query ----- */
$w = ["a.owner_id=?"];
$p = [$ownerId];
$t = "i";

if ($dbStatus) { $w[] = "a.status=?"; $p[] = $dbStatus; $t .= "s"; }
if ($from !== '') { $w[] = "DATE(a.appointment_time) >= ?"; $p[] = $from; $t .= "s"; }
if ($to !== '')   { $w[] = "DATE(a.appointment_time) <= ?"; $p[] = $to;   $t .= "s"; }
if ($q !== '')    { $w[] = "(p.name LIKE ? OR v.name LIKE ? OR COALESCE(a.notes,'') LIKE ?)"; $like = "%$q%"; $p[]=$like; $p[]=$like; $p[]=$like; $t.="sss"; }

$sql = "
SELECT a.id, a.appointment_time, a.status, a.notes,
       p.name AS pet_name,
       v.name AS vet_name
FROM appointments a
JOIN pets p ON p.id=a.pet_id
JOIN users v ON v.id=a.vet_id
WHERE ".implode(' AND ',$w)."
ORDER BY a.appointment_time DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($t, ...$p);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function chip($s){
  if ($s==='approved')  return ['ok','Confirmed'];
  if ($s==='pending')   return ['warn','Pending'];
  if ($s==='cancelled') return ['danger','Cancelled'];
  if ($s==='completed') return ['ok','Completed'];
  return ['warn', ucfirst($s)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • Appointments</title>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>

  <style>
    :root{
      --primary:#F59E0B; --accent:#EF4444; --bg:#FFF7ED; --text:#1F2937; --card:#FFFFFF;
      --muted:#6B7280; --border:#f1e6d7; --radius:18px; --shadow:0 10px 30px rgba(0,0,0,.08); --shadow-sm:0 6px 16px rgba(0,0,0,.06);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0}
    body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif;line-height:1.5}
    .page{margin-left:280px;padding:28px 24px 60px}
    .page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .page-title h1{margin:0;font-family:Montserrat,sans-serif;font-size:28px}
    .breadcrumbs{font-size:13px;color:var(--muted)}
    .tag{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid var(--border);font-size:12px}
    .card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .muted{color:var(--muted)}
    .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px}
    .stat{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid var(--border);font-weight:600}
    .input,.select{border:1px solid var(--border);background:#fff;border-radius:12px;padding:10px 12px;font-size:14px;outline:0}
    .input:focus,.select:focus{box-shadow:0 0 0 4px #ffe7c6;border-color:#f2cf97}
    .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:10px 14px;cursor:pointer;font-weight:600}
    .btn-primary{background:linear-gradient(135deg,var(--primary),#ffb444);color:#fff}
    .btn-ghost{background:#fff;border:1px solid var(--border);color:#92400e}
    .btn-danger{background:linear-gradient(135deg,#f87171,#ef4444);color:#fff}
    .table-wrap{overflow:auto;border:1px solid var(--border);border-radius:14px}
    table{width:100%;border-collapse:separate;border-spacing:0}
    thead th{position:sticky;top:0;background:#fff7ef;border-bottom:1px solid var(--border);text-align:left;font-size:13px;padding:12px;color:#92400e}
    tbody td{padding:12px;border-bottom:1px solid #f6efe4;font-size:14px;vertical-align:middle}
    tbody tr:hover{background:#fffdfa}
    .chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#fff;font-size:12px}
    .chip.ok{background:#ecfdf5;border-color:#bbf7d0;color:#047857}
    .chip.warn{background:#fff7ed;border-color:#fde68a;color:#b45309}
    .chip.danger{background:#fee2e2;border-color:#fecaca;color:#b91c1c}
    .icon-btn{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:#fff;border:1px solid var(--border);cursor:pointer}
    .icon-btn:hover{box-shadow:var(--shadow-sm);transform:translateY(-1px)}
    .empty{padding:24px;text-align:center;color:var(--muted)}
    .checkbox{width:18px;height:18px}
    @media (max-width: 640px){ .sidebar{transform:translateX(-100%)} .page{margin-left:0} }
  </style>
</head>
<body class="bg-app">

<?php if (file_exists(__DIR__.'/sidebar.php')) include("sidebar.php"); ?>

<main class="page">
  <div class="page-head">
    <div class="page-title">
      <div class="breadcrumbs">Owner • Appointments</div>
      <h1>Appointments</h1>
    </div>
    <span class="tag"><i class="bi bi-calendar2-check"></i> Manage</span>
  </div>

  <section class="card">
    <form class="toolbar" method="get" id="filters">
      <div class="stat"><i class="bi bi-calendar-week"></i> Total: <span id="count"><?= count($rows) ?></span></div>

      <select class="select" name="status">
        <?php foreach (['All','Confirmed','Pending','Completed','Cancelled'] as $opt): ?>
          <option value="<?= $opt ?>" <?= $uiStatus===$opt?'selected':''; ?>><?= $opt ?> Status</option>
        <?php endforeach; ?>
      </select>

      <input class="input" type="date" name="from" value="<?= e($from) ?>">
      <input class="input" type="date" name="to" value="<?= e($to) ?>">
      <input class="input" name="q" value="<?= e($q) ?>" placeholder="Search pet, vet, notes…">

      <button class="btn btn-ghost" type="button" id="clearFilters"><i class="bi bi-eraser"></i> Clear</button>
      <button class="btn btn-primary"><i class="bi bi-search"></i> Apply</button>
      <input type="hidden" name="t" value="<?= time() ?>">
    </form>

    <form method="post" id="bulkForm">
      <input type="hidden" name="action" value="bulk_delete">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:44px"><input type="checkbox" id="selectAll" class="checkbox"></th>
              <th>Date</th>
              <th>Time</th>
              <th>Pet</th>
              <th>Vet • Notes</th>
              <th>Status</th>
              <th style="width:140px">Actions</th>
            </tr>
          </thead>
          <tbody id="apptTbody">
            <?php if (!$rows): ?>
              <tr><td colspan="7" class="empty">No appointments yet.</td></tr>
            <?php else: foreach($rows as $r):
              $dt = new DateTime($r['appointment_time']);
              [$cls,$lbl] = chip($r['status']);
            ?>
              <tr data-id="<?= (int)$r['id'] ?>">
                <td><input type="checkbox" class="checkbox rowCheck" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
                <td><?= e($dt->format('Y-m-d')) ?></td>
                <td><?= e($dt->format('h:i A')) ?></td>
                <td><b><?= e($r['pet_name']) ?></b></td>
                <td><?= e($r['vet_name']) ?> • <span class="muted"><?= $r['notes']? e($r['notes']) : '—' ?></span></td>
                <td><span class="chip <?= $cls ?>"><i class="bi bi-circle-fill" style="font-size:8px"></i> <?= e($lbl) ?></span></td>
                <td>
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete this appointment?');">
                    <input type="hidden" name="action" value="delete_one">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="icon-btn" title="Delete"><i class="bi bi-trash3"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:10px;display:flex;justify-content:flex-end;gap:10px">
        <button type="submit" class="btn btn-danger" id="deleteSelected" <?= $rows? '' : 'disabled' ?>><i class="bi bi-trash3"></i> Delete Selected</button>
      </div>
    </form>
  </section>
</main>

<script>
const selectAll = document.getElementById('selectAll');
const tbody = document.getElementById('apptTbody');
const bulkBtn = document.getElementById('deleteSelected');
const clearBtn = document.getElementById('clearFilters');
const filters = document.getElementById('filters');

function updateBulk(){
  const checks = tbody.querySelectorAll('.rowCheck');
  const any = Array.from(checks).some(c => c.checked);
  bulkBtn.disabled = !any;
  if (checks.length) {
    selectAll.checked = Array.from(checks).every(c => c.checked);
  } else {
    selectAll.checked = false;
  }
}

tbody.addEventListener('change', (e)=>{
  if (!e.target.classList.contains('rowCheck')) return;
  updateBulk();
});

selectAll?.addEventListener('change', ()=>{
  const checks = tbody.querySelectorAll('.rowCheck');
  checks.forEach(c => c.checked = selectAll.checked);
  updateBulk();
});

clearBtn?.addEventListener('click', ()=>{
  filters.querySelector('[name="status"]').value = 'All';
  filters.querySelector('[name="from"]').value = '';
  filters.querySelector('[name="to"]').value = '';
  filters.querySelector('[name="q"]').value = '';
  filters.submit();
});

updateBulk();
</script>
</body>
</html>
