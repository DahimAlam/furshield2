<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';


require_role('vet');

if (!defined('BASE')) define('BASE','/furshield');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$uid = (int)($_SESSION['user']['id'] ?? 0);

/* ---------- helpers ---------- */
function rows(mysqli $c, string $sql, array $p=[], string $types=''){
  if(!$p){ $r=$c->query($sql); return $r? $r->fetch_all(MYSQLI_ASSOC):[]; }
  if($types==='') $types=str_repeat('s',count($p));
  $st=$c->prepare($sql); $st->bind_param($types, ...$p); $st->execute();
  $res=$st->get_result(); return $res? $res->fetch_all(MYSQLI_ASSOC):[];
}
function one(mysqli $c, string $sql, array $p=[], string $types=''){
  $a = rows($c,$sql,$p,$types); return $a ? $a[0] : null;
}
function col_exists(mysqli $c, string $t, string $col): bool{
  $t=$c->real_escape_string($t); $col=$c->real_escape_string($col);
  $r=$c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
  return $r && $r->num_rows>0;
}
function pickcol(mysqli $c, string $t, array $cands): ?string{
  foreach($cands as $x){ if(col_exists($c,$t,$x)) return $x; } return null;
}

/* ---------- 1) Vet profile (vets.user_id = session id) ---------- */
$vet = one($conn,
  "SELECT user_id AS vet_id, name, specialization, profile_image
     FROM vets WHERE user_id=? LIMIT 1",
  [$uid], 'i'
);
if(!$vet){ http_response_code(404); exit('Vet profile not found.'); }

/* ---------- 2) Quick stats ---------- */
$pending   = (int) (one($conn,"SELECT COUNT(*) c FROM appointments WHERE vet_id=? AND status='pending'",   [$uid],'i')['c'] ?? 0);
$approved  = (int) (one($conn,"SELECT COUNT(*) c FROM appointments WHERE vet_id=? AND status='approved'",  [$uid],'i')['c'] ?? 0);
$completed = (int) (one($conn,"SELECT COUNT(*) c FROM appointments WHERE vet_id=? AND status='completed'", [$uid],'i')['c'] ?? 0);

$PET_NAME_COL = pickcol($conn,'pets',['name','pet_name','title']);
$petNameExpr = $PET_NAME_COL ? "COALESCE(p.`$PET_NAME_COL`, CONCAT('Pet #',p.id))" : "CONCAT('Pet #',p.id)";

/* ---------- 3) Today + upcoming ---------- */
$today = rows($conn,
  "SELECT a.id, a.scheduled_at, a.status, $petNameExpr AS pet_name
     FROM appointments a
     LEFT JOIN pets p ON p.id=a.pet_id
    WHERE a.vet_id=? AND DATE(a.scheduled_at)=CURDATE()
    ORDER BY a.scheduled_at ASC",
  [$uid], 'i'
);

$upcoming = rows($conn,
  "SELECT a.id, a.scheduled_at, a.status, $petNameExpr AS pet_name
     FROM appointments a
     LEFT JOIN pets p ON p.id=a.pet_id
    WHERE a.vet_id=? AND a.scheduled_at>NOW() AND a.status IN ('pending','approved')
    ORDER BY a.scheduled_at ASC
    LIMIT 10",
  [$uid], 'i'
);

/* ---------- 4) Slot add / toggle ---------- */
$flash='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (isset($_POST['add_slot'])) {
    $dow  = trim($_POST['dow'] ?? '');          
    $date = trim($_POST['date'] ?? '');          
    $start= trim($_POST['start'] ?? '');         
    $end  = trim($_POST['end'] ?? '');           
    $act  = isset($_POST['active']) ? 1 : 0;

    if (($dow==='' && $date==='') || $start==='' || $end==='') {
      $flash = 'Please provide (Day of week OR Specific date) and start/end time.'; 
    } else {
      $dowVal = null;
      if ($dow!=='') {
        $d = (int)$dow;
        if ($d>=0 && $d<=6) $dowVal=$d;
        else if ($d>=1 && $d<=7) $dowVal=$d; 
        else $dowVal=null;
      }
      $dateVal = $date!=='' ? $date : null;

      $st = $conn->prepare(
        "INSERT INTO vet_availability (vet_id, dow, specific_date, start_time, end_time, is_active)
         VALUES (?,?,?,?,?,?)"
      );
      if ($dowVal===null) { $dowParam = null; $types = 'iisssi'; $st->bind_param($types, $uid, $dowParam, $dateVal, $start, $end, $act); }
      else {                 $types = 'iisssi'; $st->bind_param($types, $uid, $dowVal,  $dateVal, $start, $end, $act); }
      $st->execute();
      $flash = 'Slot added.';
    }
  }
  if (isset($_POST['toggle_slot'])) {
    $sid = (int)$_POST['slot_id'];
    $conn->query("UPDATE vet_availability SET is_active=1-is_active WHERE vet_id={$uid} AND id={$sid}"); // safe b/c ints
    $flash = 'Slot updated.';
  }
  if (isset($_POST['delete_slot'])) {
    $sid = (int)$_POST['slot_id'];
    $conn->query("DELETE FROM vet_availability WHERE vet_id={$uid} AND id={$sid}");
    $flash = 'Slot deleted.';
  }
}

$slots = rows($conn,
  "SELECT id, dow, specific_date, start_time, end_time, is_active, created_at
     FROM vet_availability
    WHERE vet_id=? 
    ORDER BY specific_date IS NULL, specific_date, dow, start_time",
  [$uid], 'i'
);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="main">
  <div class="container-fluid py-4">

    <?php if ($flash) { ?>
      <div class="alert alert-warning border-0 shadow-sm mb-3"><?php echo htmlspecialchars($flash); ?></div>
    <?php } ?>

    <!-- Header -->
    <div class="row g-3">
      <div class="col-12 col-xl-4">
        <div class="cardx p-3 d-flex align-items-center">
          <img src="<?php echo !empty($vet['profile_image'])? BASE.'/uploads/avatars/'.$vet['profile_image'] : BASE.'/assets/placeholder/vet.jpg'; ?>"
               class="rounded-circle me-3" style="width:64px;height:64px;object-fit:cover" alt="">
          <div>
            <div class="h5 m-0"><?php echo htmlspecialchars($vet['name'] ?: 'Veterinarian'); ?></div>
            <div class="text-muted small"><?php echo htmlspecialchars($vet['specialization'] ?: ''); ?></div>
          </div>
        </div>
      </div>
      <div class="col-12 col-xl-8">
        <div class="row g-3">
          <div class="col-4">
            <div class="cardx p-3 text-center">
              <div class="text-muted small">Pending</div>
              <div class="h4 m-0"><?php echo $pending; ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="cardx p-3 text-center">
              <div class="text-muted small">Approved</div>
              <div class="h4 m-0"><?php echo $approved; ?></div>
            </div>
          </div>
          <div class="col-4">
            <div class="cardx p-3 text-center">
              <div class="text-muted small">Completed</div>
              <div class="h4 m-0"><?php echo $completed; ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Today -->
    <div class="row g-3 mt-1">
      <div class="col-12 col-xl-6">
        <div class="cardx p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="m-0">Today’s Appointments</h5>
          </div>
          <?php if ($today) { ?>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Time</th><th>Pet</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($today as $a) { ?>
                  <tr>
                    <td><?php echo date('h:i A', strtotime($a['scheduled_at'])); ?></td>
                    <td><?php echo htmlspecialchars($a['pet_name']); ?></td>
                    <td><span class="badge bg-<?php
                      echo $a['status']==='approved'?'success':($a['status']==='pending'?'warning text-dark':'secondary'); ?>">
                      <?php echo htmlspecialchars(ucfirst($a['status'])); ?>
                    </span></td>
                  </tr>
                <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <div class="alert alert-light border">No appointments today.</div>
          <?php } ?>
        </div>
      </div>

      <div class="col-12 col-xl-6">
        <div class="cardx p-3">
          <h5 class="mb-2">Upcoming</h5>
          <?php if ($upcoming) { ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($upcoming as $u) { ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($u['pet_name']); ?></div>
                    <div class="small text-muted"><?php echo date('D, M d • h:i A', strtotime($u['scheduled_at'])); ?></div>
                  </div>
                  <span class="badge bg-<?php
                    echo $u['status']==='approved'?'success':($u['status']==='pending'?'warning text-dark':'secondary'); ?>">
                    <?php echo htmlspecialchars(ucfirst($u['status'])); ?>
                  </span>
                </li>
              <?php } ?>
            </ul>
          <?php } else { ?>
            <div class="alert alert-light border">Nothing upcoming yet.</div>
          <?php } ?>
        </div>
      </div>
    </div>

    <!-- Availability -->
    <div class="row g-3 mt-1">
      <div class="col-12 col-xl-5">
        <div class="cardx p-3">
          <h5 class="mb-3">Add Availability</h5>
          <form method="post" class="row g-2">
            <div class="col-12">
              <label class="form-label">Day of Week (0=Sun .. 6=Sat or 1..7)</label>
              <input type="number" name="dow" class="form-control" min="0" max="7" placeholder="Leave empty if setting a specific date">
            </div>
            <div class="col-12">
              <label class="form-label">Specific Date (optional)</label>
              <input type="date" name="date" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label">Start</label>
              <input type="time" name="start" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label">End</label>
              <input type="time" name="end" class="form-control" required>
            </div>
            <div class="col-12 form-check mt-1">
              <input class="form-check-input" type="checkbox" id="act" name="active" checked>
              <label class="form-check-label" for="act">Active</label>
            </div>
            <div class="col-12">
              <button class="btn btn-primary" name="add_slot" value="1">Add Slot</button>
            </div>
          </form>
        </div>
      </div>

      <div class="col-12 col-xl-7">
        <div class="cardx p-3">
          <h5 class="mb-3">Your Availability</h5>
          <?php if ($slots) { ?>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>#</th><th>DOW</th><th>Date</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($slots as $s) { ?>
                  <tr>
                    <td><?php echo (int)$s['id']; ?></td>
                    <td><?php echo $s['specific_date'] ? '—' : htmlspecialchars((string)$s['dow']); ?></td>
                    <td><?php echo $s['specific_date'] ? htmlspecialchars($s['specific_date']) : '—'; ?></td>
                    <td><?php echo htmlspecialchars(substr($s['start_time'],0,5)); ?></td>
                    <td><?php echo htmlspecialchars(substr($s['end_time'],0,5)); ?></td>
                    <td><?php echo $s['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Off</span>'; ?></td>
                    <td class="d-flex gap-1">
                      <form method="post">
                        <input type="hidden" name="slot_id" value="<?php echo (int)$s['id']; ?>">
                        <button class="btn btn-sm btn-outline-primary" name="toggle_slot" value="1">Toggle</button>
                      </form>
                      <form method="post" onsubmit="return confirm('Delete this slot?')">
                        <input type="hidden" name="slot_id" value="<?php echo (int)$s['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger" name="delete_slot" value="1">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <div class="alert alert-light border">No availability added yet.</div>
          <?php } ?>
        </div>
      </div>
    </div>

  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
