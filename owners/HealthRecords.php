<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

/* ===== Helpers ===== */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qs(array $patch=[]){ $q=array_merge($_GET,$patch); foreach($q as $k=>$v) if($v===null) unset($q[$k]); $uri=strtok($_SERVER['REQUEST_URI'],'?'); return $uri.(count($q)?('?'.http_build_query($q)):''); }
function csrf($check=false){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); if($check && (($_POST['csrf']??'')!==$_SESSION['csrf'])){ http_response_code(403); exit('Bad CSRF'); } return $_SESSION['csrf']; }
$CSRF = csrf();

/* Current owner (login) */
$ownerId = (int)($_SESSION['user']['id'] ?? $_SESSION['owner']['id'] ?? 0);
if(!$ownerId){ http_response_code(401); exit('Login required'); }

/* Upload path & URL */
$projectRootFs = dirname(__DIR__);                       // .../adminpanel
$uploadDir     = $projectRootFs.'/uploads/records/';
if(!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

$script  = $_SERVER['SCRIPT_NAME'] ?? '';
$baseUrl = rtrim(dirname(dirname($script)), '/');        // /adminpanel
if($baseUrl==='/') $baseUrl='';
$uploadUrl = $baseUrl.'/uploads/records/';

/* File upload */
function save_upload($field, $dir){
  if(empty($_FILES[$field]['name']) || ($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return [null,null,null,null];
  $tmp  = $_FILES[$field]['tmp_name'];
  $size = (int)$_FILES[$field]['size'];
  if($size > 10*1024*1024) return [null,null,null,'File too large (max 10MB)'];

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $tmp) ?: '';
  finfo_close($finfo);

  $ok = ['application/pdf','image/jpeg','image/png','image/webp','image/gif','image/svg+xml','image/heic'];
  if(!in_array($mime,$ok,true)) return [null,null,null,'Only PDF/JPG/PNG/WebP/GIF/SVG/HEIC allowed'];

  $extMap = [
    'application/pdf' => 'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',
    'image/gif'=>'gif','image/svg+xml'=>'svg','image/heic'=>'heic',
  ];
  $ext  = $extMap[$mime] ?? 'bin';
  $name = 'hr_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;

  if(!move_uploaded_file($tmp, $dir.$name)) return [null,null,null,'Upload failed'];
  return [$name, $mime, $size, null];
}

/* ===== Actions ===== */
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf(true);
  $act = $_POST['action'] ?? '';

  if($act==='save'){
    $id     = (int)($_POST['id'] ?? 0);                 // <-- EDIT support
    $pet_id = (int)($_POST['pet_id'] ?? 0);
    $type   = trim($_POST['record_type'] ?? '');       // e.g. "Vaccine", "Allergy Test"
    $title  = trim($_POST['title'] ?? '');
    $date   = trim($_POST['record_date'] ?? '');
    $notes  = trim($_POST['notes'] ?? '');
    $remove = isset($_POST['remove_file']) ? 1 : 0;

    if(!$pet_id || !$type || !$title || !$date){
      header('Location: '.qs(['msg'=>'Please fill required fields'])); exit;
    }

    // Check pet belongs to current owner (pets.user_id)
    $chk = $conn->prepare("SELECT id FROM pets WHERE id=? AND user_id=? LIMIT 1");
    $chk->bind_param('ii',$pet_id,$ownerId); $chk->execute();
    $okPet = $chk->get_result()->fetch_assoc(); $chk->close();
    if(!$okPet){ header('Location: '.qs(['msg'=>'Invalid pet'])); exit; }

    // Upload (if any)
    [$newName,$newMime,$newSize,$err] = save_upload('file',$uploadDir);
    if($err){ header('Location: '.qs(['msg'=>$err])); exit; }

    if($id>0){
      // --- UPDATE ---
      // Ensure record belongs to owner
      $cur = $conn->prepare("SELECT file_path,file_mime,file_size FROM health_records WHERE id=? AND owner_id=? LIMIT 1");
      $cur->bind_param('ii',$id,$ownerId); $cur->execute();
      $old = $cur->get_result()->fetch_assoc(); $cur->close();
      if(!$old){ header('Location: '.qs(['msg'=>'Record not found'])); exit; }

      $path = $old['file_path']; $mime = $old['file_mime']; $size = $old['file_size'];

      // Remove file if asked
      if($remove && $path){
        $abs = $projectRootFs.'/'.ltrim($path,'/');
        if(is_file($abs)) @unlink($abs);
        $path = $mime = null; $size = null;
      }

      // If new file uploaded, replace old
      if($newName){
        if(!empty($path)){
          $abs = $projectRootFs.'/'.ltrim($path,'/'); if(is_file($abs)) @unlink($abs);
        }
        $path = 'uploads/records/'.$newName;
        $mime = $newMime;
        $size = $newSize;
      }

      $up = $conn->prepare("UPDATE health_records
                            SET pet_id=?, record_type=?, title=?, record_date=?, notes=?, file_path=?, file_mime=?, file_size=?
                            WHERE id=? AND owner_id=?");
      $up->bind_param('issssssiii', $pet_id, $type, $title, $date, $notes, $path, $mime, $size, $id, $ownerId);
      $up->execute(); $up->close();

      header('Location: '.qs(['edit'=>null,'msg'=>'Updated'])); exit;
    } else {
      // --- INSERT ---
      $filePath = $newName ? ('uploads/records/'.$newName) : null;
      $stmt = $conn->prepare("INSERT INTO health_records (owner_id, pet_id, record_type, title, record_date, notes, file_path, file_mime, file_size) VALUES (?,?,?,?,?,?,?,?,?)");
      $stmt->bind_param('iiisssssi', $ownerId, $pet_id, $type, $title, $date, $notes, $filePath, $newMime, $newSize);
      $stmt->execute(); $stmt->close();

      header('Location: '.qs(['msg'=>'Added'])); exit;
    }
  }

  if($act==='delete_one'){
    $id = (int)($_POST['id'] ?? 0);
    $q = $conn->prepare("SELECT file_path FROM health_records WHERE id=? AND owner_id=?");
    $q->bind_param('ii',$id,$ownerId); $q->execute();
    $r = $q->get_result()->fetch_assoc(); $q->close();

    if($r){
      if(!empty($r['file_path'])){
        $abs = $projectRootFs.'/'.ltrim($r['file_path'],'/');
        if(is_file($abs)) @unlink($abs);
      }
      $d = $conn->prepare("DELETE FROM health_records WHERE id=? AND owner_id=?");
      $d->bind_param('ii',$id,$ownerId); $d->execute(); $d->close();
    }
    header('Location: '.qs(['msg'=>'Deleted'])); exit;
  }

  if($act==='bulk_delete'){
    $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
    if($ids){
      $in  = implode(',', array_fill(0,count($ids),'?'));
      $typ = str_repeat('i', count($ids)).'i';
      $params = array_merge($ids, [$ownerId]);

      // unlink files
      $sql = "SELECT file_path FROM health_records WHERE id IN ($in) AND owner_id=?";
      $st  = $conn->prepare($sql);
      $st->bind_param($typ, ...$params); $st->execute();
      $rs = $st->get_result();
      while($r = $rs->fetch_assoc()){
        if(!empty($r['file_path'])){
          $abs = $projectRootFs.'/'.ltrim($r['file_path'],'/');
          if(is_file($abs)) @unlink($abs);
        }
      }
      $st->close();

      // delete rows
      $sql = "DELETE FROM health_records WHERE id IN ($in) AND owner_id=?";
      $st  = $conn->prepare($sql);
      $st->bind_param($typ, ...$params); $st->execute(); $st->close();
    }
    header('Location: '.qs(['msg'=>'Deleted'])); exit;
  }
}

/* ===== Filters (GET) ===== */
$fltPet   = isset($_GET['pet']) ? (int)$_GET['pet'] : 0;   // 0 => All
$fltType  = $_GET['type']  ?? 'All';                      // label or All
$from     = trim($_GET['from'] ?? '');
$to       = trim($_GET['to'] ?? '');
$q        = trim($_GET['q'] ?? '');

/* Pets for current owner (dropdowns) */
$pets = [];
$ps = $conn->prepare("SELECT id, name FROM pets WHERE user_id=? ORDER BY name");
$ps->bind_param('i',$ownerId); $ps->execute();
$pets = $ps->get_result()->fetch_all(MYSQLI_ASSOC);
$ps->close();

/* ===== Edit fetch (if any) ===== */
$editId = (int)($_GET['edit'] ?? 0);
$edit   = null;
if($editId>0){
  $ep = $conn->prepare("SELECT * FROM health_records WHERE id=? AND owner_id=? LIMIT 1");
  $ep->bind_param('ii',$editId,$ownerId); $ep->execute();
  $edit = $ep->get_result()->fetch_assoc();
  $ep->close();
}

/* ===== Fetch rows ===== */
$w = ["hr.owner_id=?"];
$p = [$ownerId];
$t = "i";
if($fltPet>0){ $w[]="hr.pet_id=?"; $p[]=$fltPet; $t.="i"; }
if($fltType!=='All'){ $w[]="hr.record_type=?"; $p[]=$fltType; $t.="s"; }
if($from!==''){ $w[]="hr.record_date >= ?"; $p[]=$from; $t.="s"; }
if($to!==''){   $w[]="hr.record_date <= ?"; $p[]=$to;   $t.="s"; }
if($q!==''){    $w[]="(hr.title LIKE ? OR COALESCE(hr.notes,'') LIKE ? OR p.name LIKE ?)"; $like="%$q%"; $p[]=$like; $p[]=$like; $p[]=$like; $t.="sss"; }

$sql = "SELECT hr.*, p.name AS pet_name
        FROM health_records hr
        JOIN pets p ON p.id=hr.pet_id
        WHERE ".implode(' AND ',$w)."
        ORDER BY hr.record_date DESC, hr.id DESC";
$st = $conn->prepare($sql);
$st->bind_param($t, ...$p);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* UI helpers */
function human_size($b){ $u=['B','KB','MB','GB']; $i=0; $n=(int)$b; while($n>=1024 && $i<count($u)-1){ $n/=1024; $i++; } return ($n<10?number_format($n,1):number_format($n,0)).' '.$u[$i]; }
function chip_class($type){
  if($type==='Vaccine') return 'ok';
  if($type==='Lab Report' || $type==='X-Ray') return 'info';
  if($type==='Allergy Test' || $type==='Prescription') return 'warn';
  return '';
}
function file_chip($r){
  if(empty($r['file_path'])) return '<span class="chip"><i class="bi bi-dash-circle"></i> None</span>';
  $size = $r['file_size'] ? human_size((int)$r['file_size']) : '';
  $mime = (string)($r['file_mime'] ?? '');
  $kind = (strpos($mime,'pdf')!==false) ? 'PDF' : ((strpos($mime,'image')===0)?'Image':'File');
  return '<span class="chip"><i class="bi bi-paperclip"></i> '.$kind.($size?' • '.$size:'').'</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • Health Records</title>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>

  <style>
    :root{
      --primary:#F59E0B;
      --accent:#EF4444;
      --bg:#FFF7ED;
      --text:#1F2937;
      --card:#FFFFFF;
      --muted:#6B7280;
      --border:#f1e6d7;
      --radius:18px;
      --shadow:0 10px 30px rgba(0,0,0,.08);
      --shadow-sm:0 6px 16px rgba(0,0,0,.06);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0}
    body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif;line-height:1.5}

    /* Page shell */
    .page{margin-left:280px;padding:28px 24px 60px}

    /* Head */
    .page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .page-title h1{margin:0;font-family:Montserrat,sans-serif;font-size:28px}
    .breadcrumbs{font-size:13px;color:var(--muted)}
    .tag{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid var(--border);font-size:12px}

    /* Cards */
    .card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .card-head h2{margin:0;font-family:Montserrat,sans-serif;font-size:20px}
    .muted{color:var(--muted)}

    /* Form */
    form{display:flex;flex-direction:column;gap:14px}
    .form-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
    .field{display:flex;flex-direction:column;gap:6px}
    .field label{font-weight:600;font-size:14px}
    .input,.select,.textarea{border:1px solid var(--border);background:#fff;border-radius:12px;padding:10px 12px;font-size:14px;outline:0}
    .input:focus,.select:focus,.textarea:focus{box-shadow:0 0 0 4px #ffe7c6;border-color:#f2cf97}
    .textarea{min-height:80px;resize:vertical}
    .help{font-size:12px;color:var(--muted)}
    .req{color:#b91c1c}

    .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:10px 14px;cursor:pointer;font-weight:600}
    .btn-primary{background:linear-gradient(135deg,var(--primary),#ffb444);color:#fff}
    .btn-ghost{background:#fff;border:1px solid var(--border);color:#92400e}
    .btn-danger{background:linear-gradient(135deg,#f87171,#ef4444);color:#fff}

    /* Uploader */
    .uploader{display:grid;grid-template-columns:64px 1fr auto;gap:12px;align-items:center;border:1px dashed var(--border);border-radius:14px;padding:10px;background:#fffdf7}
    .file-ico{width:64px;height:64px;border-radius:12px;display:grid;place-items:center;background:#fff7ef;border:1px solid var(--border);font-size:26px;color:#b45309}
    .file-input{position:relative;overflow:hidden}
    .file-input input{position:absolute;inset:0;opacity:0;cursor:pointer}

    /* Toolbar */
    .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px;flex-direction: row;}
    .stat{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid var(--border);font-weight:600}

    /* Table */
    .table-wrap{overflow:auto;border:1px solid var(--border);border-radius:14px}
    table{width:100%;border-collapse:separate;border-spacing:0}
    thead th{position:sticky;top:0;background:#fff7ef;border-bottom:1px solid var(--border);text-align:left;font-size:13px;padding:12px;color:#92400e}
    tbody td{padding:12px;border-bottom:1px solid #f6efe4;font-size:14px;vertical-align:middle}
    tbody tr:hover{background:#fffdfa}
    .icon-btn{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:#fff;border:1px solid var(--border);cursor:pointer}
    .icon-btn:hover{box-shadow:var(--shadow-sm);transform:translateY(-1px)}
    .checkbox{width:18px;height:18px}
    .empty{padding:24px;text-align:center;color:var(--muted)}

    /* Chips */
    .chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#fff;font-size:12px}
    .chip.ok{background:#ecfdf5;border-color:#bbf7d0;color:#047857}
    .chip.info{background:#eef2ff;border-color:#e0e7ff;color:#3730a3}
    .chip.warn{background:#fff7ed;border-color:#fde68a;color:#b45309}

    /* Modal viewer */
    .modal{position:fixed;inset:0;display:none;place-items:center;background:rgba(0,0,0,.25);z-index:100}
    .modal.open{display:grid}
    .sheet{width:min(860px,94vw);height:min(80vh,780px);background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden}
    .sheet-head{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #f3e7d9}
    .sheet-head h3{margin:0;font-family:Montserrat,sans-serif;font-size:18px}
    .sheet-body{flex:1;overflow:auto;background:#fff}
    .close-x{background:#fff;border:1px solid var(--border);width:36px;height:36px;border-radius:10px;display:grid;place-items:center;cursor:pointer}
    .preview{width:100%;height:100%;border:0}
    .preview-img{max-width:100%;display:block;margin:auto;padding:10px}

    /* Responsive */
    @media (max-width: 1024px){ .form-grid{grid-template-columns:1fr 1fr} }
    @media (max-width: 640px){
      .page{margin-left:0}
      .form-grid{grid-template-columns:1fr}
    }
  </style>
</head>
<body class="bg-app">

<?php include("sidebar.php")?>

<main class="page">
  <div class="page-head">
    <div class="page-title">
      <div class="breadcrumbs">Owner • Health</div>
      <h1>Health Records</h1>
    </div>
    <span class="tag"><i class="bi bi-shield-plus"></i> Upload & Track</span>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="card" style="background:#eef2ff;border-color:#c7d2fe;margin-bottom:12px">
      <i class="bi bi-info-circle"></i> <?= e($_GET['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- Add / Edit Record -->
  <section class="card">
    <div class="card-head">
      <h2><?= $edit ? 'Edit Record' : 'Add Record' ?></h2>
      <span class="muted">Certificates, lab reports, vaccines, X-rays, prescriptions</span>
    </div>

    <form id="recForm" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : '' ?>">

      <div class="uploader">
        <div class="file-ico" id="fileIcon"><i class="bi bi-file-earmark-medical"></i></div>
        <div>
          <label class="btn btn-primary file-input">
            <i class="bi bi-cloud-upload"></i> Choose File
            <input type="file" name="file" id="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.svg,.heic"/>
          </label>
          <div class="help" id="fileHelp">Accepted: PDF, JPG, PNG (max ~10MB)</div>

          <?php if($edit && !empty($edit['file_path'])): ?>
            <div style="margin-top:8px;display:flex;align-items:center;gap:10px">
              <?php $href = $baseUrl.'/'.ltrim($edit['file_path'],'/'); ?>
              <a href="<?= e($href) ?>" target="_blank" class="chip"><i class="bi bi-paperclip"></i> Existing file</a>
              <label style="display:flex;align-items:center;gap:6px">
                <input type="checkbox" name="remove_file" value="1"> Remove file
              </label>
            </div>
          <?php endif; ?>
        </div>
        <div class="muted" id="fileMeta"><?= $edit && !empty($edit['file_path']) ? e(basename($edit['file_path'])) : 'No file selected' ?></div>
      </div>

      <div class="form-grid">
        <div class="field">
          <label>Pet <span class="req">*</span></label>
          <select class="select" name="pet_id" id="pet" required>
            <option value="">Select…</option>
            <?php
              $selPet = $edit ? (int)$edit['pet_id'] : $fltPet;
              foreach($pets as $p):
            ?>
              <option value="<?= (int)$p['id'] ?>" <?= ($selPet===(int)$p['id'])?'selected':''; ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Type <span class="req">*</span></label>
          <?php $selType = $edit ? (string)$edit['record_type'] : $fltType; ?>
          <select class="select" name="record_type" id="type" required>
            <option value="">Select…</option>
            <?php
              $TYPES = ['Vaccine','Allergy Test','Lab Report','X-Ray','Prescription','Other'];
              foreach($TYPES as $T):
            ?>
              <option <?= ($selType===$T)?'selected':''; ?>><?= e($T) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Title <span class="req">*</span></label>
          <input class="input" name="title" id="title" value="<?= e($edit['title'] ?? '') ?>" placeholder="e.g., DHPP Booster, CBC Report" required/>
        </div>
        <div class="field">
          <label>Date <span class="req">*</span></label>
          <input class="input" name="record_date" id="date" type="date" value="<?= e($edit['record_date'] ?? '') ?>" required/>
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Notes</label>
          <textarea class="textarea" name="notes" id="notes" placeholder="Allergy symptoms, dosage, vet name, etc."><?= e($edit['notes'] ?? '') ?></textarea>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap">
        <?php if($edit): ?>
          <a href="<?= e(qs(['edit'=>null])) ?>" class="btn btn-ghost"><i class="bi bi-arrow-counterclockwise"></i> Cancel</a>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save2"></i> Update Record</button>
        <?php else: ?>
          <button type="reset" class="btn btn-ghost"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add Record</button>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <!-- Filters & Table -->
  <section class="card" style="margin-top:16px">
    <div class="card-head">
      <h2>Records</h2>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div class="stat"><i class="bi bi-folder2-open"></i> Total: <span id="count"><?= count($rows) ?></span></div>
      </div>
    </div>

    <form class="toolbar" method="get" id="fltForm">
      <select class="select" name="pet" id="fltPet">
        <option value="0">All Pets</option>
        <?php foreach($pets as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= $fltPet===(int)$p['id']?'selected':''; ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="select" name="type" id="fltType">
        <option value="All" <?= $fltType==='All'?'selected':''; ?>>All Types</option>
        <option <?= $fltType==='Vaccine'?'selected':''; ?>>Vaccine</option>
        <option <?= $fltType==='Allergy Test'?'selected':''; ?>>Allergy Test</option>
        <option <?= $fltType==='Lab Report'?'selected':''; ?>>Lab Report</option>
        <option <?= $fltType==='X-Ray'?'selected':''; ?>>X-Ray</option>
        <option <?= $fltType==='Prescription'?'selected':''; ?>>Prescription</option>
        <option <?= $fltType==='Other'?'selected':''; ?>>Other</option>
      </select>
      <input class="input" type="date" name="from" id="fromDate" value="<?= e($from) ?>">
      <input class="input" type="date" name="to" id="toDate" value="<?= e($to) ?>">
      <input class="input" name="q" id="search" value="<?= e($q) ?>" placeholder="Search title, notes, pet…">
      <button type="button" class="btn btn-ghost" id="clearFilters"><i class="bi bi-eraser"></i> Clear</button>
      <button class="btn btn-primary"><i class="bi bi-search"></i> Apply</button>
    </form>

    <form method="post" id="bulkForm">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="bulk_delete">

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:44px"><input type="checkbox" id="selectAll" class="checkbox"></th>
              <th>Date</th>
              <th>Pet</th>
              <th>Type</th>
              <th>Title</th>
              <th>File</th>
              <th>Notes</th>
              <th style="width:140px">Actions</th>
            </tr>
          </thead>
          <tbody id="recTbody">
          <?php if(!$rows): ?>
            <tr><td colspan="8" class="empty">No health records yet.</td></tr>
          <?php else: foreach($rows as $r): ?>
            <tr data-id="<?= (int)$r['id'] ?>">
              <td><input type="checkbox" class="checkbox rowCheck" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
              <td><?= e($r['record_date']) ?></td>
              <td><b><?= e($r['pet_name']) ?></b></td>
              <td><span class="chip <?= chip_class($r['record_type']) ?>"><i class="bi bi-file-earmark-medical"></i> <?= e($r['record_type']) ?></span></td>
              <td><?= e($r['title']) ?></td>
              <td><?= file_chip($r) ?></td>
              <td><?= $r['notes'] ? e($r['notes']) : '—' ?></td>
              <td>
                <a class="icon-btn" href="<?= e(qs(['edit'=>(int)$r['id']])) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php if(!empty($r['file_path'])): ?>
                  <?php $href = $baseUrl.'/'.ltrim($r['file_path'],'/'); ?>
                  <a class="icon-btn" href="<?= e($href) ?>" target="_blank" title="View/Download"><i class="bi bi-eye"></i></a>
                <?php endif; ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this record?');">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
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

<!-- Viewer Modal (optional) -->
<div class="modal" id="viewer" aria-hidden="true">
  <div class="sheet">
    <div class="sheet-head">
      <h3 id="viewerTitle">Preview</h3>
      <button class="close-x" id="viewerClose"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sheet-body" id="viewerBody"></div>
  </div>
</div>

<script>
/* File meta (icon + name + size) */
(function(){
  const fileIn = document.getElementById('file');
  const fileIcon = document.getElementById('fileIcon');
  const fileMeta = document.getElementById('fileMeta');
  function humanSize(b){ const u=['B','KB','MB','GB']; let i=0,n=b||0; while(n>=1024&&i<u.length-1){n/=1024;i++;} return (n<10?n.toFixed(1):Math.round(n))+ ' ' + u[i]; }
  function kind(t){ if(!t) return ''; if(t.includes('pdf')) return 'PDF'; if(t.startsWith('image/')) return 'Image'; return 'File'; }
  fileIn?.addEventListener('change', ()=>{
    const f = fileIn.files?.[0];
    if(!f){ fileMeta.textContent='No file selected'; fileIcon.innerHTML='<i class="bi bi-file-earmark-medical"></i>'; return; }
    fileMeta.textContent = `${f.name} • ${kind(f.type)} • ${humanSize(f.size)}`;
    fileIcon.innerHTML = f.type.includes('pdf') ? '<i class="bi bi-file-earmark-pdf"></i>' :
                         f.type.startsWith('image/') ? '<i class="bi bi-image"></i>' : '<i class="bi bi-file-earmark"></i>';
  });
})();

/* Filters clear */
document.getElementById('clearFilters')?.addEventListener('click', ()=>{
  const f = document.getElementById('fltForm');
  f.querySelector('[name="pet"]').value = '0';
  f.querySelector('[name="type"]').value = 'All';
  f.querySelector('[name="from"]').value = '';
  f.querySelector('[name="to"]').value = '';
  f.querySelector('[name="q"]').value = '';
  f.submit();
});

/* Bulk select */
const tbody = document.getElementById('recTbody');
const selectAll = document.getElementById('selectAll');
const bulkBtn = document.getElementById('deleteSelected');
function updateBulk(){
  const checks = tbody.querySelectorAll('.rowCheck');
  const any = Array.from(checks).some(c=>c.checked);
  bulkBtn.disabled = !any;
  selectAll.checked = checks.length>0 && Array.from(checks).every(c=>c.checked);
}
tbody.addEventListener('change',(e)=>{ if(e.target.classList.contains('rowCheck')) updateBulk(); });
selectAll?.addEventListener('change',()=>{ const checks=tbody.querySelectorAll('.rowCheck'); checks.forEach(c=>c.checked=selectAll.checked); updateBulk(); });
updateBulk();
</script>
</body>
</html>
