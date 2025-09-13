<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qs(array $patch=[]){ $q = array_merge($_GET, $patch); foreach($q as $k=>$v) if($v===null) unset($q[$k]); return strtok($_SERVER['REQUEST_URI'],'?').(count($q)?('?'.http_build_query($q)):''); }
function jdesc($t){ $j=@json_decode((string)$t,true); return is_array($j)?$j:[]; }
function csrf($check=false){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); if($check && (($_POST['csrf']??'')!==$_SESSION['csrf'])){ http_response_code(403); exit('Bad CSRF'); } return $_SESSION['csrf']; }
$CSRF = csrf();

$ownerId = (int)($_SESSION['user']['id'] ?? $_SESSION['owner']['id'] ?? 0);
if (!$ownerId) { http_response_code(401); exit('Login required'); }

/* Filesystem + URL for /images/pet */
$projectRootFs = dirname(__DIR__);                 // .../ADMINPANEL
$uploadDir     = $projectRootFs.'/images/pet/';    // FS path
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

/* URL base = one level up from /owners */
$script   = $_SERVER['SCRIPT_NAME'] ?? '';
$baseUrl  = rtrim(dirname(dirname($script)), '/'); // /ADMINPANEL
if ($baseUrl === '/') $baseUrl = '';
$uploadUrl = $baseUrl.'/images/pet/';

function img_url($file,$dir,$url){ if(!$file) return ''; $p = $dir.$file; return is_file($p)?($url.$file):''; }
function up_photo($field,$dir){
  if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return [null,null];
  $tmp=$_FILES[$field]['tmp_name']; $info=@getimagesize($tmp); if(!$info) return [null,'Invalid image'];
  $ext=strtolower(image_type_to_extension($info[2],false)); if(!in_array($ext,['jpg','jpeg','png','webp'])) return [null,'Only JPG/PNG/WebP'];
  $name='pet_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
  if(!move_uploaded_file($tmp,$dir.$name)) return [null,'Upload failed'];
  return [$name,null];
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf(true);
  $act = $_POST['action'] ?? '';
  if ($act==='save') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $species = trim($_POST['species'] ?? '');
    $breed = trim($_POST['breed'] ?? '');
    $gender = $_POST['gender'] ?? 'Unknown';
    $ageVal = (int)($_POST['ageVal'] ?? 0);
    $ageUnit = in_array($_POST['ageUnit'] ?? 'y',['y','mo'],true)?$_POST['ageUnit']:'y';
    $desc = [
      'age_unit'=>$ageUnit,
      'next_vaccine'=>trim($_POST['nextVaccine'] ?? ''),
      'allergies'=>trim($_POST['allergies'] ?? ''),
      'vaccinated'=>(($_POST['vaccinated'] ?? '0')==='1')?1:0,
      'notes'=>trim($_POST['notes'] ?? '')
    ];
    $descJson = json_encode($desc, JSON_UNESCAPED_UNICODE);
    [$newFile,$upErr] = up_photo('photo',$uploadDir);

    if ($id>0) {
      $stmt=$conn->prepare("SELECT image FROM pets WHERE id=? AND user_id=? LIMIT 1");
      $stmt->bind_param('ii',$id,$ownerId); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
      $img = $row['image'] ?? null;
      if (!empty($_POST['remove_photo'])) { if($img && is_file($uploadDir.$img)) @unlink($uploadDir.$img); $img=null; }
      if ($newFile) { if($img && is_file($uploadDir.$img)) @unlink($uploadDir.$img); $img=$newFile; }
      $stmt=$conn->prepare("UPDATE pets SET name=?,species=?,breed=?,age=?,gender=?,image=?,description=? WHERE id=? AND user_id=?");
      $stmt->bind_param('ssssssiii',$name,$species,$breed,$ageVal,$gender,$img,$descJson,$id,$ownerId); $stmt->execute(); $stmt->close();
      header('Location: '.qs(['edit_id'=>null,'msg'=>'Updated'])); exit;
    } else {
      $stmt=$conn->prepare("INSERT INTO pets (user_id,name,species,breed,age,gender,image,description) VALUES (?,?,?,?,?,?,?,?)");
      $stmt->bind_param('isssssss',$ownerId,$name,$species,$breed,$ageVal,$gender,$newFile,$descJson); $stmt->execute(); $stmt->close();
      header('Location: '.qs(['msg'=>'Added'])); exit;
    }
  }
  if ($act==='delete_one') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt=$conn->prepare("SELECT image FROM pets WHERE id=? AND user_id=?"); $stmt->bind_param('ii',$id,$ownerId); $stmt->execute();
    $r=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($r) {
      if (!empty($r['image']) && is_file($uploadDir.$r['image'])) @unlink($uploadDir.$r['image']);
      $stmt=$conn->prepare("DELETE FROM pets WHERE id=? AND user_id=?"); $stmt->bind_param('ii',$id,$ownerId); $stmt->execute(); $stmt->close();
    }
    header('Location: '.qs(['msg'=>'Deleted'])); exit;
  }
}

$editId = (int)($_GET['edit_id'] ?? 0);
$edit = null; $descEdit=[];
if ($editId>0) {
  $stmt=$conn->prepare("SELECT * FROM pets WHERE id=? AND user_id=? LIMIT 1");
  $stmt->bind_param('ii',$editId,$ownerId); $stmt->execute(); $edit=$stmt->get_result()->fetch_assoc(); $stmt->close();
  $descEdit = jdesc($edit['description'] ?? null);
}
$rows=[];
$stmt=$conn->prepare("SELECT * FROM pets WHERE user_id=? ORDER BY created_at DESC");
$stmt->bind_param('i',$ownerId); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • My Pets</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <style>
    :root{--primary:#F59E0B;--accent:#EF4444;--bg:#FFF7ED;--text:#1F2937;--card:#FFFFFF;--muted:#6B7280;--border:#f1e6d7;--radius:18px;--shadow:0 10px 30px rgba(0,0,0,.08);--ring:#ffe7c6}
    *{box-sizing:border-box}
    body{margin:0}
    body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif;line-height:1.5}
    .page{margin-left:280px;padding:28px 24px 80px}
    .h1{font:800 28px Montserrat;margin:0 0 14px}
    .breadcrumbs{font-size:13px;color:var(--muted);margin-bottom:6px}
    .card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow)}
    .form-card{padding:22px;margin-bottom:16px}
    .table-card{padding:18px}
    .grid{display:grid;gap:14px}
    .grid.cols-2{grid-template-columns:1fr 1fr}
    .grid.cols-3{grid-template-columns:repeat(3,1fr)}
    .label{font:600 12px Poppins;color:#92400e}
    .input,select,textarea{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:14px;background:#fff;font:500 14px Poppins;outline:0}
    .input:focus,select:focus,textarea:focus{box-shadow:0 0 0 4px var(--ring)}
    .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:12px 16px;cursor:pointer;font-weight:700}
    .btn-primary{background:linear-gradient(135deg,var(--primary),#ffb444);color:#fff}
    .btn-ghost{background:#fff;border:1px solid var(--border);color:#92400e}
    .btn-danger{background:linear-gradient(135deg,#f87171,#ef4444);color:#fff}
    .toolbar{display:flex;gap:10px;justify-content:flex-end;margin-top:6px}
    .thumb{width:60px;height:60px;border-radius:14px;object-fit:cover;border:1px solid var(--border);background:#f8fafc}
    .table-wrap{overflow:auto;border:1px solid var(--border);border-radius:14px}
    table{width:100%;border-collapse:separate;border-spacing:0}
    thead th{position:sticky;top:0;background:#fff7ef;border-bottom:1px solid var(--border);text-align:left;font-size:13px;padding:12px;color:#92400e}
    tbody td{padding:12px;border-bottom:1px solid #f6efe4;font-size:14px;vertical-align:middle}
    tbody tr:hover{background:#fffdfa}
    .actions{display:flex;gap:8px}
    .icon-btn{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:#fff;border:1px solid var(--border);cursor:pointer}
    .flash{margin-bottom:12px;padding:10px 12px;border:1px solid #c7d2fe;background:#eef2ff;border-radius:12px}

    /* Uploader (VIP) */
    .uploader{border:2px dashed #f2cf97;border-radius:16px;background:#fff7ef;padding:14px;display:flex;gap:14px;align-items:center;transition:.2s}
    .uploader:hover{box-shadow:var(--shadow)}
    .uploader .prev{width:96px;height:96px;border-radius:14px;border:1px solid var(--border);background:#fff;display:grid;place-items:center;overflow:hidden}
    .uploader .prev img{width:100%;height:100%;object-fit:cover}
    .uploader .meta{flex:1}
    .uploader .meta b{font-family:Montserrat;font-size:14px}
    .uploader .meta p{margin:4px 0 0;color:#8a5a24;font-size:12px}
    .uploader .actions{display:flex;gap:8px}
    .btn-soft{background:#fff;border:1px solid var(--border);color:#92400e}
    .drop{position:relative}
    .drop input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#fff;font-size:12px}
    @media(max-width:860px){.grid.cols-3{grid-template-columns:1fr 1fr}.page{margin-left:0}}
    @media(max-width:640px){.grid.cols-2,.grid.cols-3{grid-template-columns:1fr}}
  </style>
</head>
<body class="bg-app">

<?php if (file_exists(__DIR__.'/sidebar.php')) include __DIR__.'/sidebar.php'; ?>

<main class="page">
  <div class="breadcrumbs">Owner • Pets</div>
  <h1 class="h1">My Pets</h1>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="flash"><i class="bi bi-info-circle"></i> <?= e($_GET['msg']) ?></div>
  <?php endif; ?>

  <section class="card form-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div style="font:800 18px Montserrat"><?= $edit ? ('Edit — '.e($edit['name'] ?? ('#'.$editId))) : 'Add Pet' ?></div>
      <?php if ($edit): ?>
        <a class="btn btn-ghost" href="<?= e(qs(['edit_id'=>null])) ?>"><i class="bi bi-x-circle"></i> Reset</a>
      <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data" id="petForm">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : '' ?>">

      <div class="grid cols-3">
        <div>
          <label class="label">Name</label>
          <input class="input" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="Max">
        </div>
        <div>
          <label class="label">Species</label>
          <input class="input" name="species" required value="<?= e($edit['species'] ?? '') ?>" placeholder="Dog / Cat / Rabbit / etc.">
        </div>
        <div>
          <label class="label">Breed</label>
          <input class="input" name="breed" value="<?= e($edit['breed'] ?? '') ?>" placeholder="Labrador">
        </div>

        <div>
          <label class="label">Gender</label>
          <?php $g = $edit['gender'] ?? 'Unknown'; ?>
          <select class="input" name="gender">
            <option <?= $g==='Male'?'selected':''; ?>>Male</option>
            <option <?= $g==='Female'?'selected':''; ?>>Female</option>
            <option <?= $g==='Unknown'?'selected':''; ?>>Unknown</option>
          </select>
        </div>

        <div>
          <label class="label">Age</label>
          <div style="display:flex;gap:8px">
            <input class="input" type="number" min="0" name="ageVal" value="<?= e((string)($edit['age'] ?? 0)) ?>" placeholder="0" style="max-width:140px">
            <?php $u = $descEdit['age_unit'] ?? 'y'; ?>
            <select class="input" name="ageUnit" style="max-width:140px">
              <option value="y" <?= $u==='y'?'selected':''; ?>>Years</option>
              <option value="mo" <?= $u==='mo'?'selected':''; ?>>Months</option>
            </select>
          </div>
        </div>

        <div>
          <label class="label">Next Vaccine</label>
          <input class="input" name="nextVaccine" value="<?= e($descEdit['next_vaccine'] ?? '') ?>" placeholder="2025-12-01 / Rabies booster">
        </div>

        <div>
          <label class="label">Allergies</label>
          <input class="input" name="allergies" value="<?= e($descEdit['allergies'] ?? '') ?>" placeholder="Chicken protein">
        </div>

        <div>
          <label class="label">Vaccinated?</label>
          <?php $v = !empty($descEdit['vaccinated']); ?>
          <select class="input" name="vaccinated">
            <option value="1" <?= $v?'selected':''; ?>>Yes</option>
            <option value="0" <?= !$v?'selected':''; ?>>No</option>
          </select>
        </div>

        <div style="grid-column:1/-1">
          <label class="label">Notes</label>
          <textarea class="input" name="notes" rows="2" placeholder="Temperament, medical notes…"><?= e($descEdit['notes'] ?? '') ?></textarea>
        </div>

        <!-- VIP Uploader -->
        <div style="grid-column:1/-1">
          <label class="label">Photo</label>
          <?php $currentImg = $edit && !empty($edit['image']) ? img_url($edit['image'],$uploadDir,$uploadUrl) : ''; ?>
          <div class="uploader" id="uploader">
            <div class="prev" id="prevBox">
              <?php if($currentImg): ?>
                <img id="preview" src="<?= e($currentImg) ?>" alt="preview">
              <?php else: ?>
                <i class="bi bi-image" style="font-size:28px;color:#a16207"></i>
              <?php endif; ?>
            </div>
            <div class="meta">
              <b>Drag & drop image here</b>
              <p>JPG, PNG, or WebP • Max ~5MB (recommended)</p>
              <div class="pill"><i class="bi bi-folder"></i> Save path: <code>/images/pet/</code></div>
            </div>
            <div class="actions">
              <label class="btn btn-soft drop">
                <i class="bi bi-upload"></i> Choose…
                <input type="file" name="photo" id="photo" accept="image/*">
              </label>
              <button type="button" class="btn btn-ghost" id="clearPhoto"><i class="bi bi-x-circle"></i> Clear</button>
            </div>
          </div>
          <?php if ($currentImg): ?>
            <div style="margin-top:8px">
              <label class="pill"><input type="checkbox" name="remove_photo" value="1" id="removeChk"> Remove current photo</label>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="toolbar">
        <?php if ($edit): ?>
          <a class="btn btn-ghost" href="<?= e(qs(['edit_id'=>null])) ?>"><i class="bi bi-arrow-counterclockwise"></i> Cancel</a>
          <button class="btn btn-primary"><i class="bi bi-save2"></i> Update Pet</button>
        <?php else: ?>
          <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Pet</button>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="card table-card">
    <div style="font:800 18px Montserrat;margin-bottom:12px">Your Pets</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Photo</th>
            <th>Name</th>
            <th>Species</th>
            <th>Breed</th>
            <th>Gender</th>
            <th>Age</th>
            <th>Vaccinated</th>
            <th>Next Vaccine</th>
            <th>Added</th>
            <th style="width:130px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows): foreach($rows as $r):
            $d = jdesc($r['description'] ?? null);
            $age = (int)($r['age'] ?? 0);
            $ageStr = $age>0 ? ($age.' '.(($d['age_unit'] ?? 'y')==='mo'?'mo':'y')) : '—';
            $vacc = !empty($d['vaccinated']) ? 'Yes' : 'No';
            $img = img_url($r['image'] ?? '', $uploadDir, $uploadUrl);
            $pid = (int)$r['id'];
          ?>
          <tr>
            <td><?php if($img): ?><img class="thumb" src="<?= e($img) ?>" alt="pet"><?php else: ?>—<?php endif; ?></td>
            <td><?= e($r['name']) ?></td>
            <td><?= e($r['species'] ?: '—') ?></td>
            <td><?= e($r['breed'] ?: '—') ?></td>
            <td><?= e($r['gender'] ?: '—') ?></td>
            <td><?= e($ageStr) ?></td>
            <td><?= e($vacc) ?></td>
            <td><?= e($d['next_vaccine'] ?? '—') ?></td>
            <td><?= e($r['created_at'] ?? '—') ?></td>
            <td class="actions">
              <a class="icon-btn" title="Edit" href="<?= e(qs(['edit_id'=>$pid])) ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this pet?');">
                <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                <input type="hidden" name="action" value="delete_one">
                <input type="hidden" name="id" value="<?= $pid ?>">
                <button class="icon-btn" title="Delete" style="border-color:#fecaca"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="10" style="text-align:center;color:#6b7280">No pets yet. Add from the form above.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script>
/* VIP uploader interactions: preview + clear + drag&drop */
(() => {
  const input = document.getElementById('photo');
  const prevBox = document.getElementById('prevBox');
  const clearBtn = document.getElementById('clearPhoto');
  const removeChk = document.getElementById('removeChk');

  function setPreview(file){
    if(!file){ 
      prevBox.innerHTML = '<i class="bi bi-image" style="font-size:28px;color:#a16207"></i>';
      if (removeChk) removeChk.checked = false;
      return;
    }
    const url = URL.createObjectURL(file);
    prevBox.innerHTML = '<img id="preview" alt="preview">';
    prevBox.querySelector('#preview').src = url;
    if (removeChk) removeChk.checked = false; // new image overrides remove
  }

  input?.addEventListener('change', (e)=>{
    const f = e.target.files?.[0];
    if (f) setPreview(f);
  });

  clearBtn?.addEventListener('click', ()=>{
    if (input) { input.value = ''; }
    setPreview(null);
  });

  // Drag & drop on the whole uploader box
  const uploader = document.getElementById('uploader');
  if (uploader) {
    ['dragenter','dragover'].forEach(ev=>uploader.addEventListener(ev,(e)=>{e.preventDefault();uploader.style.boxShadow='0 0 0 4px var(--ring)';}));
    ['dragleave','drop'].forEach(ev=>uploader.addEventListener(ev,(e)=>{e.preventDefault();uploader.style.boxShadow='';}));
    uploader.addEventListener('drop',(e)=>{
      const file = e.dataTransfer.files?.[0];
      if (file && input) { input.files = e.dataTransfer.files; setPreview(file); }
    });
  }
})();
</script>
</body>
</html>
