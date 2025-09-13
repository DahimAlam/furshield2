<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('vet');

$uid = (int)($_SESSION['user']['id'] ?? 0);
if (!defined('BASE')) define('BASE','/furshield');

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
function jsonify_attach($v){
  if ($v===null || $v==='') return [];
  $arr = json_decode($v, true);
  if (is_array($arr)) return $arr;
  return [$v];
}

$vetProfileId = null;
if (table_exists($conn,'vets') && col_exists($conn,'vets','user_id') && col_exists($conn,'vets','id')) {
  $q=$conn->prepare("SELECT id FROM vets WHERE user_id=? LIMIT 1");
  $q->bind_param("i",$uid); $q->execute(); $q->bind_result($vetProfileId); $q->fetch(); $q->close();
}
$whoA=$uid; $whoB=$vetProfileId ?? -1;

$tab = table_exists($conn,'health_records') ? 'health_records' : (table_exists($conn,'medical_records') ? 'medical_records' : null);
if (!$tab) { http_response_code(500); exit('records table missing'); }

$C_ID     = pick_col($conn,$tab,['id','record_id']);
$C_VET    = pick_col($conn,$tab,['vet_id','doctor_id','created_by','added_by']);
$C_PET    = pick_col($conn,$tab,['pet_id','petId']);
$C_DATE   = pick_col($conn,$tab,['visit_date','date','created_at']);
$C_DIAG   = pick_col($conn,$tab,['diagnosis','note','notes','description']);
$C_TREAT  = pick_col($conn,$tab,['treatment','prescription','procedure']);
$C_ATTACH = pick_col($conn,$tab,['attachments','files','file','document','document_path']);

if (!$C_ID || !$C_PET || !$C_DATE) { http_response_code(500); exit('records key columns missing'); }

$uploadDir = dirname(__DIR__).'/uploads/records/';
$uploadUrl = BASE.'/uploads/records/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

$flash=null;

function save_files($field, $uploadDir){
  $out=[];
  if (!isset($_FILES[$field])) return $out;
  $f=$_FILES[$field];
  if (is_array($f['name'])){
    for($i=0;$i<count($f['name']);$i++){
      if ($f['error'][$i]!==UPLOAD_ERR_OK) continue;
      $name=$f['name'][$i];
      $ext=strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext,['pdf','jpg','jpeg','png','gif','webp'])) continue;
      $id=uniqid('rec_',true).'.'.$ext;
      $to=$uploadDir.$id;
      if (move_uploaded_file($f['tmp_name'][$i],$to)) $out[]=$id;
    }
  } else {
    if ($f['error']===UPLOAD_ERR_OK){
      $ext=strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if (in_array($ext,['pdf','jpg','jpeg','png','gif','webp'])){
        $id=uniqid('rec_',true).'.'.$ext;
        $to=$uploadDir.$id;
        if (move_uploaded_file($f['tmp_name'],$to)) $out[]=$id;
      }
    }
  }
  return $out;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['action'] ?? '';
  if ($act==='create') {
    $pet = (int)($_POST['pet_id'] ?? 0);
    $date = trim($_POST['visit_date'] ?? '');
    $diag = trim($_POST['diagnosis'] ?? '');
    $treat = trim($_POST['treatment'] ?? '');
    $ts = strtotime($date); $dt = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    $files = save_files('attachments', $uploadDir);
    $attachJson = $files ? json_encode($files, JSON_UNESCAPED_SLASHES) : null;

    $cols = "`$C_PET`, `$C_DATE`".($C_VET?", `$C_VET`":"").($C_DIAG?", `$C_DIAG`":"").($C_TREAT?", `$C_TREAT`":"").($C_ATTACH?", `$C_ATTACH`":"");
    $vals = "?, ?".($C_VET?", ?":"").($C_DIAG?", ?":"").($C_TREAT?", ?":"").($C_ATTACH?", ?":"");
    $sql = "INSERT INTO `$tab` ($cols) VALUES ($vals)";
    $st=$conn->prepare($sql);
    $types='is'; $bind=[&$pet,&$dt];
    if ($C_VET){ $types.='i'; $bind[]=&$whoA; }
    if ($C_DIAG){ $types.='s'; $bind[]=&$diag; }
    if ($C_TREAT){ $types.='s'; $bind[]=&$treat; }
    if ($C_ATTACH){ $types.='s'; $bind[]=&$attachJson; }
    $st->bind_param($types, ...$bind);
    $ok=$st->execute(); $st->close();
    $flash = $ok ? "Record added" : "Create failed";
  }

  if ($act==='update') {
    $id = (int)($_POST['id'] ?? 0);
    $date = trim($_POST['visit_date'] ?? '');
    $diag = trim($_POST['diagnosis'] ?? '');
    $treat = trim($_POST['treatment'] ?? '');
    $ts = strtotime($date); $dt = $ts ? date('Y-m-d', $ts) : date('Y-m-d');

    $set=[]; $types=''; $bind=[];
    $set[]="`$C_DATE`=?"; $types.='s'; $bind[]=&$dt;
    if ($C_DIAG){ $set[]="`$C_DIAG`=?"; $types.='s'; $bind[]=&$diag; }
    if ($C_TREAT){ $set[]="`$C_TREAT`=?"; $types.='s'; $bind[]=&$treat; }

    $more = save_files('attachments', $uploadDir);
    if ($C_ATTACH && $more){
      $cur=null;
      $q=$conn->prepare("SELECT `$C_ATTACH` FROM `$tab` WHERE `$C_ID`=?");
      $q->bind_param("i",$id); $q->execute(); $q->bind_result($cur); $q->fetch(); $q->close();
      $arr = array_values(array_unique(array_merge(jsonify_attach($cur), $more)));
      $j = json_encode($arr, JSON_UNESCAPED_SLASHES);
      $set[]="`$C_ATTACH`=?"; $types.='s'; $bind[]=&$j;
    }

    $sql="UPDATE `$tab` SET ".implode(', ',$set)." WHERE `$C_ID`=?".($C_VET?" AND `$C_VET` IN (?,?)":"");
    $types.='i'; $bind[]=&$id;
    if ($C_VET){ $types.='ii'; $bind[]=&$whoA; $bind[]=&$whoB; }

    $st=$conn->prepare($sql); $st->bind_param($types, ...$bind);
    $ok=$st->execute(); $st->close();
    $flash = $ok ? "Record updated" : "Update failed";
  }

  if ($act==='delete') {
    $id = (int)($_POST['id'] ?? 0);
    $sql="DELETE FROM `$tab` WHERE `$C_ID`=?".($C_VET?" AND `$C_VET` IN (?,?)":"");
    $st=$conn->prepare($sql);
    if ($C_VET){ $st->bind_param("iii",$id,$whoA,$whoB); } else { $st->bind_param("i",$id); }
    $ok=$st->execute(); $st->close();
    $flash = $ok ? "Record deleted" : "Delete failed";
  }
}

$view_pet = (int)($_GET['pet'] ?? 0);
$view_from = $_GET['from'] ?? '';
$view_to = $_GET['to'] ?? '';
$w=[]; $bind=[]; $bt='';
if ($C_VET){ $w[]="r.`$C_VET` IN (?,?)"; $bind[]=$whoA; $bind[]=$whoB; $bt.='ii'; }
if ($view_pet>0){ $w[]="r.`$C_PET`=?"; $bind[]=$view_pet; $bt.='i'; }
if ($view_from!==''){ $w[]="DATE(r.`$C_DATE`)>=?"; $bind[]=$view_from; $bt.='s'; }
if ($view_to!==''){ $w[]="DATE(r.`$C_DATE`)<=?"; $bind[]=$view_to; $bt.='s'; }
$where = $w?('WHERE '.implode(' AND ',$w)):'';

$selDiag = $C_DIAG ? "r.`$C_DIAG` AS diagnosis" : "NULL AS diagnosis";
$selTreat= $C_TREAT? "r.`$C_TREAT` AS treatment" : "NULL AS treatment";
$selAtt  = $C_ATTACH? "r.`$C_ATTACH` AS attachments" : "NULL AS attachments";

$sql="SELECT r.`$C_ID` AS id, r.`$C_PET` AS pet_id, r.`$C_DATE` AS visit_date, $selDiag, $selTreat, $selAtt
      FROM `$tab` r
      $where
      ORDER BY r.`$C_DATE` DESC, r.`$C_ID` DESC
      LIMIT 300";
$st=$conn->prepare($sql); if($bt) $st->bind_param($bt, ...$bind); $st->execute(); $res=$st->get_result();
$rows=[]; while($x=$res->fetch_assoc()) $rows[]=$x; $st->close();

$petMap=[];
if (table_exists($conn,'pets') && col_exists($conn,'pets','id') && col_exists($conn,'pets','name')) {
  $ids = array_values(array_unique(array_filter(array_map('intval', array_column($rows,'pet_id')))));
  if ($ids){
    $in = implode(',', $ids);
    $qr = $conn->query("SELECT id,name FROM pets WHERE id IN ($in)");
    while($p=$qr->fetch_assoc()) $petMap[(int)$p['id']]=$p['name'];
  }
}
$ownerMap=[];
if (table_exists($conn,'appointments') && col_exists($conn,'appointments','owner_id') && col_exists($conn,'appointments','pet_id')) {
  $qr=$conn->query("SELECT pet_id, MAX(owner_id) owner_id FROM appointments GROUP BY pet_id");
  while($r=$qr->fetch_assoc()) $ownerMap[(int)$r['pet_id']] = (int)$r['owner_id'];
}
$ownerNameMap=[];
if ($ownerMap && table_exists($conn,'users') && col_exists($conn,'users','id') && col_exists($conn,'users','name')) {
  $ids = array_values(array_unique(array_filter(array_map('intval', array_values($ownerMap)))));
  if ($ids){
    $in = implode(',', $ids);
    $qr=$conn->query("SELECT id,name FROM users WHERE id IN ($in)");
    while($u=$qr->fetch_assoc()) $ownerNameMap[(int)$u['id']]=$u['name'];
  }
}
foreach($rows as &$r){
  $r['pet_name'] = $petMap[(int)$r['pet_id']] ?? '—';
  $oid = $ownerMap[(int)$r['pet_id']] ?? 0;
  $r['owner_name'] = $ownerNameMap[$oid] ?? '—';
  $r['files'] = jsonify_attach($r['attachments'] ?? null);
}
unset($r);

$petOptions=[];
if (table_exists($conn,'pets') && col_exists($conn,'pets','id') && col_exists($conn,'pets','name')){
  $q=$conn->query("SELECT id,name FROM pets ORDER BY name ASC");
  while($p=$q->fetch_assoc()) $petOptions[]=$p;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="main">
  <div class="container-fluid py-4">
    <?php if($flash){ ?><div class="alert alert-warning border-0 shadow-sm mb-3"><?php echo htmlspecialchars($flash); ?></div><?php } ?>

    <div class="cardx p-3 mb-3">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted">Pet</label>
          <select name="pet" class="form-select">
            <option value="0"<?php if($view_pet===0) echo ' selected'; ?>>All Pets</option>
            <?php foreach($petOptions as $p){ ?>
              <option value="<?php echo (int)$p['id']; ?>"<?php if($view_pet===(int)$p['id']) echo ' selected'; ?>>
                <?php echo htmlspecialchars($p['name']); ?>
              </option>
            <?php } ?>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small text-muted">From</label>
          <input type="date" name="from" value="<?php echo htmlspecialchars($view_from); ?>" class="form-control"/>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small text-muted">To</label>
          <input type="date" name="to" value="<?php echo htmlspecialchars($view_to); ?>" class="form-control"/>
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
            <h5 class="m-0">Medical Records</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#recCreate">Add Record</button>
          </div>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr><th>Date</th><th>Pet</th><th>Owner</th><th>Diagnosis</th><th>Treatment</th><th>Files</th><th class="text-end">Actions</th></tr></thead>
              <tbody>
                <?php if(!$rows){ ?>
                  <tr><td colspan="7" class="text-center text-muted py-4">No records</td></tr>
                <?php } else { foreach($rows as $r){ ?>
                  <tr>
                    <td class="nowrap"><?php echo date('d M Y', strtotime($r['visit_date'])); ?></td>
                    <td class="fw-semibold"><?php echo htmlspecialchars($r['pet_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['owner_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['diagnosis'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($r['treatment'] ?? ''); ?></td>
                    <td>
                      <?php if(!$r['files']){ echo '<span class="text-muted">—</span>'; }
                      else { foreach($r['files'] as $f){
                        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                        $label = in_array($ext,['pdf'])?'PDF':'Image';
                        echo '<a class="badge bg-light text-dark border me-1" target="_blank" href="'.htmlspecialchars($uploadUrl.$f).'">'.$label.'</a>';
                      }} ?>
                    </td>
                    <td class="text-end">
                      <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal" data-bs-target="#recEdit"
                          data-id="<?php echo (int)$r['id']; ?>"
                          data-date="<?php echo date('Y-m-d', strtotime($r['visit_date'])); ?>"
                          data-diag="<?php echo htmlspecialchars($r['diagnosis'] ?? '', ENT_QUOTES); ?>"
                          data-treat="<?php echo htmlspecialchars($r['treatment'] ?? '', ENT_QUOTES); ?>"
                          data-pet="<?php echo (int)$r['pet_id']; ?>">Edit</button>
                        <form method="post" onsubmit="return confirm('Delete this record?')">
                          <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"/>
                          <button class="btn btn-sm btn-danger" name="action" value="delete">Delete</button>
                        </form>
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
          <h5 class="mb-2">Upload Guidelines</h5>
          <ul class="mb-3">
            <li>Allowed: PDF, JPG, PNG, GIF, WEBP</li>
            <li>Multiple files supported; they append to existing attachments</li>
            <li>Owners will see these notes/files in their pet history</li>
          </ul>
          <div class="alert alert-light border">
            Keep sensitive data minimal. Ensure owner consent for sharing medical files.
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<div class="modal fade" id="recCreate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="post" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Medical Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="create"/>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Pet</label>
            <select name="pet_id" class="form-select" required>
              <?php foreach($petOptions as $p){ ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Visit Date</label>
            <input type="date" name="visit_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required/>
          </div>
          <div class="col-12">
            <label class="form-label">Diagnosis / Notes</label>
            <textarea name="diagnosis" class="form-control" rows="3" placeholder="e.g., Vaccination (Rabies), Skin allergy…"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Treatment / Prescription</label>
            <textarea name="treatment" class="form-control" rows="3" placeholder="e.g., Amoxicillin 250mg, 2× daily for 5 days…"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Attachments</label>
            <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp"/>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-dark" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="recEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="post" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Medical Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="update"/>
        <input type="hidden" name="id" id="e-id"/>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Pet</label>
            <select name="pet_id" id="e-pet" class="form-select" disabled>
              <?php foreach($petOptions as $p){ ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Visit Date</label>
            <input type="date" name="visit_date" id="e-date" class="form-control" required/>
          </div>
          <div class="col-12">
            <label class="form-label">Diagnosis / Notes</label>
            <textarea name="diagnosis" id="e-diag" class="form-control" rows="3"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Treatment / Prescription</label>
            <textarea name="treatment" id="e-treat" class="form-control" rows="3"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Add More Attachments</label>
            <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp"/>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-dark" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const recEdit = document.getElementById('recEdit');
recEdit?.addEventListener('show.bs.modal', (e)=>{
  const b = e.relatedTarget;
  document.getElementById('e-id').value   = b?.getAttribute('data-id')||'';
  document.getElementById('e-date').value = b?.getAttribute('data-date')||'';
  document.getElementById('e-diag').value = b?.getAttribute('data-diag')||'';
  document.getElementById('e-treat').value= b?.getAttribute('data-treat')||'';
  const pet = b?.getAttribute('data-pet')||'';
  const sel = document.getElementById('e-pet'); if (sel){ sel.value = pet; }
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
