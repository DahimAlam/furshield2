<?php

if (session_status()===PHP_SESSION_NONE) session_start();

$ROOT  = dirname(__DIR__, 3);  
$ADMIN = dirname(__DIR__, 2);   

require_once $ROOT . '/includes/db.php';
require_once $ROOT . '/includes/auth.php';
require_role('admin');

if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

function cols(mysqli $c,$t){ $t=$c->real_escape_string($t); $r=$c->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'"); $o=[]; while($r && ($x=$r->fetch_row())) $o[]=$x[0]; return $o; }
function t_exists(mysqli $c,$t){ $t=$c->real_escape_string($t); $r=$c->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'"); return $r && $r->num_rows>0; }
function pick($arr,$cands){ foreach($cands as $x){ if(in_array($x,$arr,true)) return $x; } return null; }
function refs(&$a){ $r=[]; foreach($a as $k=>$_){ $r[$k]=&$a[$k]; } return $r; }

$tab = null; foreach(['appointments','bookings','appointment','schedules'] as $t){ if(t_exists($conn,$t)){ $tab=$t; break; } }
if(!$tab){ http_response_code(500); exit; }
$ac = cols($conn,$tab);
$A_ID   = pick($ac,['id','appointment_id','booking_id']);
$A_VET  = pick($ac,['vet_id','doctor_id','vet_user_id','vet']);
$A_OWNER= pick($ac,['owner_id','user_id','customer_id','client_id']);
$A_PET  = pick($ac,['pet_id','petId']);
$A_STAT = pick($ac,['status','state']);
$A_SCH  = pick($ac,['scheduled_at','appointment_at','datetime','start_at','slot_at']);
$A_DATE = pick($ac,['scheduled_date','date','for_date','appointment_date']);
$A_TIME = pick($ac,['scheduled_time','time','appointment_time','start_time','slot_time']);
$A_NOTE = pick($ac,['notes','note','message','reason','description']);
$A_CREATED = pick($ac,['created_at','created','created_on']);
$A_UPDATED = pick($ac,['updated_at','updated','updated_on']);

$uc = t_exists($conn,'users') ? cols($conn,'users') : [];
$U_ID = pick($uc,['id','user_id']);
$U_NAME = pick($uc,['name','full_name']);
$U_MAIL = pick($uc,['email','mail']);
$U_ROLE = pick($uc,['role','user_role']);
$U_STAT = pick($uc,['status','state']);

$pc = t_exists($conn,'pets') ? cols($conn,'pets') : [];
$P_ID = pick($pc,['id','pet_id']);
$P_NAME = pick($pc,['name','pet_name','title']);

if(!$A_ID){ http_response_code(500); exit; }

if($_SERVER['REQUEST_METHOD']==='POST'){
  $act = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  if($id>0){
    if(in_array($act,['approve','reject','cancel','complete','reschedule'])){
      if($act==='reschedule'){
        $dt = trim($_POST['when'] ?? '');
        if($dt===''){ $dt = date('Y-m-d H:i:s'); }
        if($A_SCH){
          $st=$conn->prepare("UPDATE `$tab` SET `$A_SCH`=?, ".($A_STAT?"`$A_STAT`='rescheduled', ":"").($A_UPDATED?"`$A_UPDATED`=NOW(),":"")." `$A_ID`=`$A_ID` WHERE `$A_ID`=?");
          $st->bind_param("si",$dt,$id);
        } elseif($A_DATE && $A_TIME){
          $d = substr($dt,0,10); $t = substr($dt,11,5).':00';
          $st=$conn->prepare("UPDATE `$tab` SET `$A_DATE`=?, `$A_TIME`=?, ".($A_STAT?"`$A_STAT`='rescheduled', ":"").($A_UPDATED?"`$A_UPDATED`=NOW(),":"")." `$A_ID`=`$A_ID` WHERE `$A_ID`=?");
          $st->bind_param("ssi",$d,$t,$id);
        } else {
          $st=$conn->prepare("UPDATE `$tab` SET ".($A_STAT?"`$A_STAT`='rescheduled', ":"").($A_UPDATED?"`$A_UPDATED`=NOW(),":"")." `$A_ID`=`$A_ID` WHERE `$A_ID`=?");
          $st->bind_param("i",$id);
        }
        $st->execute(); $st->close();
      } else {
        $st=$conn->prepare("UPDATE `$tab` SET ".($A_STAT?"`$A_STAT`=?," :"").($A_UPDATED?"`$A_UPDATED`=NOW(),":"")." `$A_ID`=`$A_ID` WHERE `$A_ID`=?");
        if($A_STAT){ $st->bind_param("si",$act,$id); } else { $st->bind_param("i",$id); }
        $st->execute(); $st->close();
      }
    }
  }
  header("Location: ".$_SERVER['REQUEST_URI']); exit;
}

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$stat = $_GET['status'] ?? 'any';
$vet = (int)($_GET['vet'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1,(int)($_GET['p'] ?? 1));
$limit=25; $offset=($page-1)*$limit;

$schedExpr = $A_SCH ? "a.`$A_SCH`" : ($A_DATE && $A_TIME ? "CONCAT(a.`$A_DATE`,' ',a.`$A_TIME`)" : ($A_DATE ? "CONCAT(a.`$A_DATE`,' 00:00:00')" : ($A_CREATED ? "a.`$A_CREATED`" : "NOW()")));
$ownerJoin = ($U_ID && $A_OWNER) ? "LEFT JOIN users ou ON ou.`$U_ID`=a.`$A_OWNER`" : "";
$vetJoin   = ($U_ID && $A_VET)   ? "LEFT JOIN users vu ON vu.`$U_ID`=a.`$A_VET`" : "";
$petJoin   = ($P_ID && $A_PET)   ? "LEFT JOIN pets p ON p.`$P_ID`=a.`$A_PET`"   : "";

$conds=[]; $types=''; $args=[];
if($from!==''){ $conds[]="DATE($schedExpr)>=?"; $types.='s'; $args[]=$from; }
if($to!==''){ $conds[]="DATE($schedExpr)<=?"; $types.='s'; $args[]=$to; }
if($stat!=='any' && $A_STAT){ $conds[]="a.`$A_STAT`=?"; $types.='s'; $args[]=$stat; }
if($vet>0 && $A_VET){ $conds[]="a.`$A_VET`=?"; $types.='i'; $args[]=$vet; }
if($q!==''){
  $like='%'.mb_strtolower($q).'%'; $s=[];
  if($P_NAME) $s[]="LOWER(p.`$P_NAME`) LIKE ?";
  if($U_NAME && $ownerJoin) $s[]="LOWER(ou.`$U_NAME`) LIKE ?";
  if($U_MAIL && $ownerJoin) $s[]="LOWER(ou.`$U_MAIL`) LIKE ?";
  if($U_NAME && $vetJoin)   $s[]="LOWER(vu.`$U_NAME`) LIKE ?";
  if($A_NOTE) $s[]="LOWER(a.`$A_NOTE`) LIKE ?";
  if($s){ $conds[]='('.implode(' OR ',$s).')'; $types.=str_repeat('s',count($s)); for($i=0;$i<count($s);$i++) $args[]=$like; }
}
$where = $conds?('WHERE '.implode(' AND ',$conds)):'';

$total=0;
$sql="SELECT COUNT(*) FROM `$tab` a $ownerJoin $vetJoin $petJoin $where";
$st=$conn->prepare($sql); if($types){ $b=refs($args); $st->bind_param($types,...$b); } $st->execute(); $st->bind_result($total); $st->fetch(); $st->close();

$selPet  = $P_NAME ? "COALESCE(p.`$P_NAME`,'—')" : "'—'";
$selOwner= ($U_NAME && $ownerJoin) ? "COALESCE(ou.`$U_NAME`,'—')" : "'—'";
$selOwnerMail = ($U_MAIL && $ownerJoin) ? "COALESCE(ou.`$U_MAIL`,'')" : "''";
$selVet  = ($U_NAME && $vetJoin) ? "COALESCE(vu.`$U_NAME`,'—')" : "'—'";
$selStat = $A_STAT ? "a.`$A_STAT`" : "'—'";
$selNote = $A_NOTE ? "a.`$A_NOTE`" : "''";
$selWhen = "$schedExpr";

$rows=[];
$sql="SELECT a.`$A_ID` id, $selWhen as when_at, $selStat as status, $selNote as notes, $selPet as pet_name, $selOwner as owner_name, $selOwnerMail as owner_email, $selVet as vet_name
      FROM `$tab` a $ownerJoin $vetJoin $petJoin
      $where
      ORDER BY $schedExpr ASC
      LIMIT ? OFFSET ?";
$types2=$types.'ii'; $args2=$args; $args2[]=$limit; $args2[]=$offset;
$st=$conn->prepare($sql); $b2=refs($args2); $st->bind_param($types2,...$b2); $st->execute(); $res=$st->get_result();
while($r=$res->fetch_assoc()) $rows[]=$r; $st->close();

$pages=max(1,(int)ceil($total/$limit));

$vets=[];
if($U_ID && $U_ROLE){
  $qr=$conn->prepare("SELECT `$U_ID` id, `$U_NAME` name FROM users WHERE `$U_ROLE`='vet' ".($U_STAT?"AND `$U_STAT`='active'":"")." ORDER BY `$U_NAME` ASC");
  $qr->execute(); $rs=$qr->get_result(); while($x=$rs->fetch_assoc()) $vets[]=$x; $qr->close();
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="admin-main p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold text-dark m-0">Appointments</h1>
    <form class="d-flex gap-2 align-items-end" method="get">
      <div>
        <label class="form-label small text-muted mb-1">From</label>
        <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control">
      </div>
      <div>
        <label class="form-label small text-muted mb-1">To</label>
        <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control">
      </div>
      <div>
        <label class="form-label small text-muted mb-1">Status</label>
        <select name="status" class="form-select">
          <?php foreach(['any','pending','approved','rescheduled','rejected','cancelled','completed'] as $s): ?>
            <option value="<?php echo $s; ?>"<?php if($stat===$s) echo ' selected'; ?>><?php echo ucfirst($s); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label small text-muted mb-1">Vet</label>
        <select name="vet" class="form-select">
          <option value="0">All</option>
          <?php foreach($vets as $v): ?>
            <option value="<?php echo (int)$v['id']; ?>"<?php if($vet===(int)$v['id']) echo ' selected'; ?>><?php echo htmlspecialchars($v['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label small text-muted mb-1">Search</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control" placeholder="pet, owner, vet, note">
      </div>
      <div>
        <button class="btn btn-primary">Apply</button>
      </div>
    </form>
  </div>

  <div class="card shadow-sm rounded-4 border-0">
    <div class="card-body p-3">
      <?php if(!$rows): ?>
        <div class="alert alert-info m-0">No appointments found.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle table-hover">
          <thead class="table-light">
            <tr>
              <th style="width:120px">When</th>
              <th>Pet</th>
              <th>Owner</th>
              <th>Vet</th>
              <th style="width:120px">Status</th>
              <th>Notes</th>
              <th style="width:230px" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r): ?>
            <tr>
              <td class="nowrap"><?php echo date('d M Y, h:i A', strtotime($r['when_at'])); ?></td>
              <td class="fw-semibold"><?php echo htmlspecialchars($r['pet_name']); ?></td>
              <td><?php echo htmlspecialchars($r['owner_name']); ?><div class="small text-muted"><?php echo htmlspecialchars($r['owner_email']); ?></div></td>
              <td><?php echo htmlspecialchars($r['vet_name']); ?></td>
              <td>
                <?php $s = strtolower((string)$r['status']); ?>
                <?php if($s==='approved'): ?><span class="badge bg-success">Approved</span>
                <?php elseif($s==='pending'): ?><span class="badge bg-warning text-dark">Pending</span>
                <?php elseif($s==='rescheduled'): ?><span class="badge bg-info text-dark">Rescheduled</span>
                <?php elseif(in_array($s,['rejected','cancelled'])): ?><span class="badge bg-secondary"><?php echo ucfirst($s); ?></span>
                <?php elseif($s==='completed'): ?><span class="badge bg-teal">Completed</span>
                <?php else: ?><span class="badge bg-light text-dark"><?php echo $r['status']?:'—'; ?></span><?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars($r['notes']); ?></td>
              <td class="text-end">
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-outline-success" name="action" value="approve">Approve</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-outline-secondary" name="action" value="reject">Reject</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-outline-danger" name="action" value="cancel">Cancel</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-outline-dark" name="action" value="complete">Complete</button>
                </form>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#resModal" data-id="<?php echo (int)$r['id']; ?>" data-when="<?php echo date('Y-m-d\TH:i', strtotime($r['when_at'])); ?>">Reschedule</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if($pages>1): ?>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <?php $mk=function($p)use($from,$to,$stat,$vet,$q){return '?from='.urlencode($from).'&to='.urlencode($to).'&status='.urlencode($stat).'&vet='.$vet.'&q='.urlencode($q).'&p='.$p;}; ?>
        <a class="btn btn-outline-primary btn-sm<?php if($page<=1) echo ' disabled'; ?>" href="<?php echo $mk(max(1,$page-1)); ?>">Prev</a>
        <span class="btn btn-light btn-sm disabled">Page <?php echo $page; ?> / <?php echo $pages; ?></span>
        <a class="btn btn-outline-primary btn-sm<?php if($page>=$pages) echo ' disabled'; ?>" href="<?php echo $mk(min($pages,$page+1)); ?>">Next</a>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<div class="modal fade" id="resModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reschedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="reschedule">
        <input type="hidden" name="id" id="res-id">
        <label class="form-label">When</label>
        <input type="datetime-local" name="when" id="res-when" class="form-control" required>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-dark" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const rm=document.getElementById('resModal');
rm?.addEventListener('show.bs.modal',e=>{
  const b=e.relatedTarget;
  document.getElementById('res-id').value=b?.getAttribute('data-id')||'';
  document.getElementById('res-when').value=b?.getAttribute('data-when')||'';
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
