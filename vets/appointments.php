<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('vet');

$uid = (int)($_SESSION['user']['id'] ?? 0);

function table_exists(mysqli $c, string $t): bool {
  $t = $c->real_escape_string($t);
  $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'");
  return $r && $r->num_rows>0;
}
function col_exists(mysqli $c, string $t, string $col): bool {
  $t = $c->real_escape_string($t); $col = $c->real_escape_string($col);
  $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
  return $r && $r->num_rows>0;
}
function pick_col(mysqli $c, string $t, array $cands): ?string {
  foreach($cands as $x){ if(col_exists($c,$t,$x)) return $x; }
  return null;
}

/* map vet profile id if vets.user_id exists */
$vetProfileId = null;
if (table_exists($conn,'vets') && col_exists($conn,'vets','user_id') && col_exists($conn,'vets','id')) {
  $q=$conn->prepare("SELECT id FROM vets WHERE user_id=? LIMIT 1");
  $q->bind_param("i",$uid); $q->execute(); $q->bind_result($vetProfileId); $q->fetch(); $q->close();
}
$whoA = $uid;
$whoB = $vetProfileId ?? -1;

/* appointments table + columns (auto-detect) */
$apptTab = table_exists($conn,'appointments') ? 'appointments' : null;
if (!$apptTab) { http_response_code(500); exit('appointments table missing'); }

$C_VET   = pick_col($conn,$apptTab,['vet_id','vet_user_id','vet','doctor_id','doctorId','assigned_vet','assigned_to']);
$C_TIME  = pick_col($conn,$apptTab,['scheduled_at','appointment_at','appointment_time','date_time','datetime','start_time','slot_time','time']);
$C_STAT  = pick_col($conn,$apptTab,['status','state']);
$C_PET   = pick_col($conn,$apptTab,['pet_id','petId']);
$C_OWNER = pick_col($conn,$apptTab,['owner_id','user_id','customer_id']);
$C_ID    = pick_col($conn,$apptTab,['id','appt_id','appointment_id']);

if (!$C_ID || !$C_TIME) { http_response_code(500); exit('appointments key/time columns missing'); }

/* POST actions */
$flash = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);

  if ($id>0) {
    if (in_array($action,['approve','reject','cancel','complete']) && $C_STAT) {
      $new = $action==='approve'?'approved':($action==='reject'?'rejected':($action==='cancel'?'cancelled':'completed'));
      $sql = "UPDATE `$apptTab` SET `$C_STAT`=? WHERE `$C_ID`=?".($C_VET?" AND `$C_VET` IN (?,?)":"");
      $st=$conn->prepare($sql);
      if ($C_VET) { $st->bind_param("siii",$new,$id,$whoA,$whoB); } else { $st->bind_param("si",$new,$id); }
      $ok = $st->execute(); $st->close();
      $flash = $ok ? "Updated to ".ucfirst($new) : "Update failed";
    }
    if ($action==='reschedule' && isset($_POST['when'])) {
      $when = trim((string)$_POST['when']);
      $ts = strtotime($when);
      if ($ts) {
        $dt = date('Y-m-d H:i:s',$ts);
        $sql = "UPDATE `$apptTab` SET `$C_TIME`=?".($C_STAT?", `$C_STAT`='rescheduled'":"")." WHERE `$C_ID`=?".($C_VET?" AND `$C_VET` IN (?,?)":"");
        $st=$conn->prepare($sql);
        if ($C_VET) { $st->bind_param("siii",$dt,$id,$whoA,$whoB); } else { $st->bind_param("si",$dt,$id); }
        $ok = $st->execute(); $st->close();
        $flash = $ok ? "Rescheduled" : "Reschedule failed";
      } else {
        $flash = "Invalid date/time";
      }
    }
  }
}

/* filters */
$view = $_GET['view'] ?? 'upcoming'; // upcoming | today | past | all
$status = $_GET['status'] ?? '';      // pending/approved/rescheduled/rejected/completed/cancelled
$search = trim((string)($_GET['q'] ?? ''));

/* WHERE builder */
$w=[]; $bind=[]; $bt='';
if ($C_VET) { $w[]="a.`$C_VET` IN (?,?)"; $bind[]=$whoA; $bind[]=$whoB; $bt.='ii'; }

if ($view==='today')                   $w[]="DATE(a.`$C_TIME`)=CURDATE()";
elseif ($view==='upcoming')            $w[]="a.`$C_TIME`>=NOW()";
elseif ($view==='past')                $w[]="a.`$C_TIME`<NOW()";

if ($C_STAT && $status!=='')           $w[]="a.`$C_STAT`=?";
if ($C_STAT && $status!=='') { $bind[]=$status; $bt.='s'; }

$wher = $w?('WHERE '.implode(' AND ',$w)):'';

/* select */
$selPet   = $C_PET   ? "a.`$C_PET` AS pet_ref"     : "NULL AS pet_ref";
$selOwner = $C_OWNER ? "a.`$C_OWNER` AS owner_ref" : "NULL AS owner_ref";
$selStat  = $C_STAT  ? "a.`$C_STAT` AS astatus"    : "'scheduled' AS astatus";

$sql="SELECT a.`$C_ID` AS id, a.`$C_TIME` AS atime, $selPet, $selOwner, $selStat
      FROM `$apptTab` a
      $wher
      ORDER BY a.`$C_TIME` ASC
      LIMIT 200";

$st=$conn->prepare($sql);
if ($bt) { $st->bind_param($bt, ...$bind); }
$st->execute(); $res=$st->get_result();
$rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; $st->close();

/* search by pet/owner name (client-side emulate by enriching names and then filtering in PHP) */
$petMap = []; $ownMap = [];
if ($C_PET && $rows) {
  $ids = array_values(array_unique(array_filter(array_map('intval', array_column($rows,'pet_ref')))));
  if ($ids) {
    $in = implode(',', $ids);
    if (table_exists($conn,'pets') && col_exists($conn,'pets','id') && col_exists($conn,'pets','name')) {
      $q=$conn->query("SELECT id,name FROM pets WHERE id IN ($in)");
      while($x=$q->fetch_assoc()) $petMap[(int)$x['id']]=$x['name'];
    }
  }
}
if ($C_OWNER && $rows) {
  $ids = array_values(array_unique(array_filter(array_map('intval', array_column($rows,'owner_ref')))));
  if ($ids) {
    $in = implode(',', $ids);
    if (table_exists($conn,'users') && col_exists($conn,'users','id') && col_exists($conn,'users','name')) {
      $q=$conn->query("SELECT id,name FROM users WHERE id IN ($in)");
      while($x=$q->fetch_assoc()) $ownMap[(int)$x['id']]=$x['name'];
    }
  }
}
foreach($rows as &$r){
  $r['pet_name']   = $petMap[(int)($r['pet_ref']??0)]   ?? '—';
  $r['owner_name'] = $ownMap[(int)($r['owner_ref']??0)] ?? '—';
}
unset($r);

/* apply text search */
if ($search!=='') {
  $s = mb_strtolower($search);
  $rows = array_values(array_filter($rows, function($r) use($s){
    return str_contains(mb_strtolower($r['pet_name']),' '.$s) ||
           str_contains(mb_strtolower($r['pet_name']),$s) ||
           str_contains(mb_strtolower($r['owner_name']),$s) ||
           str_contains(mb_strtolower($r['astatus']),$s);
  }));
}

/* calendar data (current month) */
$month = (int)($_GET['m'] ?? date('n'));
$year  = (int)($_GET['y'] ?? date('Y'));
$first = new DateTime("$year-$month-01");
$start = clone $first;
$start->modify('last sunday'); // start grid on Sunday
$last  = (clone $first)->modify('last day of this month');
$end   = (clone $last)->modify('next saturday');

$byDay = [];
for($d=(clone $start); $d<=$end; $d->modify('+1 day')){
  $byDay[$d->format('Y-m-d')] = [];
}
foreach($rows as $r){
  $date = date('Y-m-d', strtotime($r['atime']));
  if (isset($byDay[$date])) $byDay[$date][]=$r;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="main">
  <div class="container-fluid py-4">
    <?php if($flash){ ?>
      <div class="alert alert-warning border-0 shadow-sm mb-3"><?php echo htmlspecialchars($flash); ?></div>
    <?php } ?>

    <div class="cardx p-3 mb-3">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-12 col-md-3">
          <label class="form-label small text-muted">View</label>
          <select name="view" class="form-select">
            <option value="upcoming"<?php if($view==='upcoming') echo ' selected'; ?>>Upcoming</option>
            <option value="today"<?php if($view==='today') echo ' selected'; ?>>Today</option>
            <option value="past"<?php if($view==='past') echo ' selected'; ?>>Past</option>
            <option value="all"<?php if($view==='all') echo ' selected'; ?>>All</option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label small text-muted">Status</label>
          <select name="status" class="form-select">
            <option value=""<?php if($status==='') echo ' selected'; ?>>Any</option>
            <?php foreach(['pending','approved','rescheduled','rejected','completed','cancelled'] as $s){ ?>
              <option value="<?php echo $s; ?>"<?php if($status===$s) echo ' selected'; ?>><?php echo ucfirst($s); ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted">Search (pet/owner/status)</label>
          <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="e.g. Milo, Ahmad, approved"/>
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-primary">Apply</button>
        </div>
      </form>
    </div>

    <div class="row g-3">
      <div class="col-12 col-xl-7">
        <div class="cardx p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="m-0">Appointments</h5>
            <a class="btn btn-outline-primary btn-sm" href="availability.php">Edit Availability</a>
          </div>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Time</th><th>Pet</th><th>Owner</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if(!$rows){ ?>
                  <tr><td colspan="5" class="text-center text-muted py-4">No appointments</td></tr>
                <?php } else { foreach($rows as $r){ $dt=strtotime($r['atime']); ?>
                  <tr>
                    <td class="nowrap"><?php echo date('d M Y, h:i A', $dt); ?></td>
                    <td class="fw-semibold"><?php echo htmlspecialchars($r['pet_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['owner_name']); ?></td>
                    <td><span class="badge bg-soft-<?php echo htmlspecialchars($r['astatus']); ?>"><?php echo ucfirst($r['astatus']); ?></span></td>
                    <td class="text-end">
                      <div class="btn-group">
                        <form method="post">
                          <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"/>
                          <button name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                          <button name="action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                          <button name="action" value="cancel" class="btn btn-sm btn-outline-dark">Cancel</button>
                          <button name="action" value="complete" class="btn btn-sm btn-secondary">Complete</button>
                        </form>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#resModal"
                                data-id="<?php echo (int)$r['id']; ?>"
                                data-at="<?php echo date('Y-m-d\TH:i', $dt); ?>">Reschedule</button>
                      </div>
                    </td>
                  </tr>
                <?php } } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-5">
        <div class="cardx p-3 h-100">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="m-0">Calendar • <?php echo $first->format('M Y'); ?></h5>
            <div class="btn-group">
              <?php
                $pm = (clone $first)->modify('-1 month'); $nm=(clone $first)->modify('+1 month');
                $qs = fn($y,$m)=>'?view='.urlencode($view).'&status='.urlencode($status).'&q='.urlencode($search).'&y='.$y.'&m='.$m;
              ?>
              <a class="btn btn-sm btn-outline-primary" href="<?php echo $qs((int)$pm->format('Y'), (int)$pm->format('n')); ?>">&laquo;</a>
              <a class="btn btn-sm btn-outline-primary" href="<?php echo $qs((int)date('Y'), (int)date('n')); ?>">Today</a>
              <a class="btn btn-sm btn-outline-primary" href="<?php echo $qs((int)$nm->format('Y'), (int)$nm->format('n')); ?>">&raquo;</a>
            </div>
          </div>
          <div class="cal">
            <div class="cal-head">
              <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
            </div>
            <div class="cal-grid">
              <?php
                for($d=(clone $start); $d<=$end; $d->modify('+1 day')){
                  $key=$d->format('Y-m-d'); $inMonth = ($d->format('m')===$first->format('m'));
                  $items = $byDay[$key] ?? [];
                  echo '<div class="cal-day'.($inMonth?'':' cal-dim').'">';
                  echo '<div class="cal-date">'.(int)$d->format('j').'</div>';
                  if ($items) {
                    echo '<div class="cal-list">';
                    foreach(array_slice($items,0,4) as $it){
                      $t = date('h:i A', strtotime($it['atime']));
                      $st = htmlspecialchars($it['astatus']);
                      echo '<div class="cal-pill cal-'.$st.'">'.$t.' • '.htmlspecialchars($it['pet_name']).'</div>';
                    }
                    if (count($items)>4) echo '<div class="cal-more">+'.(count($items)-4).' more</div>';
                    echo '</div>';
                  }
                  echo '</div>';
                }
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Reschedule Modal -->
<div class="modal fade" id="resModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reschedule Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="res-id"/>
        <label class="form-label">New Date & Time</label>
        <input type="datetime-local" class="form-control" name="when" id="res-when" required/>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-dark" data-bs-dismiss="modal" type="button">Close</button>
        <button class="btn btn-primary" name="action" value="reschedule">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const resModal = document.getElementById('resModal');
resModal?.addEventListener('show.bs.modal', (e)=>{
  const btn = e.relatedTarget;
  document.getElementById('res-id').value = btn?.getAttribute('data-id') || '';
  document.getElementById('res-when').value = btn?.getAttribute('data-at') || '';
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
