<?php
/* owners/OwnerProfile.php */
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

/* ---------- Helpers ---------- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qs(array $patch=[]){ $q=array_merge($_GET,$patch); foreach($q as $k=>$v) if($v===null) unset($q[$k]); $uri=strtok($_SERVER['REQUEST_URI'],'?'); return $uri.(count($q)?('?'.http_build_query($q)):''); }
function csrf($check=false){ if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); if($check && (($_POST['csrf']??'')!==$_SESSION['csrf'])){ http_response_code(403); exit('Bad CSRF'); } return $_SESSION['csrf']; }
$CSRF = csrf();

/* ---------- Current session (accept both keys) ---------- */
$sessionUser  = $_SESSION['user']  ?? [];
$sessionOwner = $_SESSION['owner'] ?? [];
$userId   = (int)($sessionUser['id'] ?? $sessionOwner['id'] ?? 0);
$userRole = (string)($sessionUser['role'] ?? ''); // may be empty if only $_SESSION['owner'] was set

if(!$userId){ http_response_code(401); exit('Login required'); }

/* ---------- Confirm role=owner from DB (also populates role if missing) ---------- */
$st = $conn->prepare("SELECT id, role, name, email, phone, pass_hash, status, created_at, approved_at, image FROM users WHERE id=? LIMIT 1");
$st->bind_param('i',$userId); $st->execute();
$user = $st->get_result()->fetch_assoc(); $st->close();

if(!$user){ http_response_code(404); exit('User not found'); }
if (strtolower((string)$user['role']) !== 'owner'){ http_response_code(403); exit('Access denied (owner only)'); }

/* Upgrade session shape so باقی پیجز بھی چلیں */
$_SESSION['user']['id']    = (int)$user['id'];
$_SESSION['user']['name']  = (string)$user['name'];
$_SESSION['user']['email'] = (string)$user['email'];
$_SESSION['user']['role']  = 'owner';
$_SESSION['owner']['id']   = (int)$user['id'];
$_SESSION['owner']['name'] = (string)$user['name'];
$_SESSION['owner']['email']= (string)$user['email'];

/* ---------- Paths (project root, not /owners) ---------- */
$projectRootFs = dirname(__DIR__);                   // …/your-root
$avatarDir     = $projectRootFs.'/uploads/avatars/';
if(!is_dir($avatarDir)) @mkdir($avatarDir, 0775, true);

/* Web base one level up from /owners */
$script  = $_SERVER['SCRIPT_NAME'] ?? '';
$webBase = rtrim(dirname(dirname($script)), '/');    // e.g. /adminpanel  OR ''
if($webBase==='/') $webBase='';

/* ---------- Avatar upload helper ---------- */
function save_avatar($field, $dir){
  if(empty($_FILES[$field]['name']) || ($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return [null,null,'No file'];
  $tmp  = $_FILES[$field]['tmp_name'];
  $size = (int)$_FILES[$field]['size'];
  if($size > 5*1024*1024) return [null,null,'Image too large (max 5MB)'];

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $tmp) ?: '';
  finfo_close($finfo);

  $ok = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  if(!isset($ok[$mime])) return [null,null,'Only JPG/PNG/WebP allowed'];

  $ext  = $ok[$mime];
  $name = 'ava_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
  if(!move_uploaded_file($tmp, $dir.$name)) return [null,null,'Upload failed'];
  return [$name, $mime, null];
}

/* ---------- Actions (strictly for logged-in owner) ---------- */
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf(true);
  $action = $_POST['__act'] ?? '';

  if($action==='avatar_upload'){
    [$fname,$fmime,$err] = save_avatar('avatar',$avatarDir);
    if($err && !$fname){ header('Location: '.qs(['msg'=>$err])); exit; }

    // delete old
    if(!empty($user['image'])){
      $old = $projectRootFs.'/'.ltrim($user['image'],'/');
      if(is_file($old)) @unlink($old);
    }
    $rel = 'uploads/avatars/'.$fname;
    $st = $conn->prepare("UPDATE users SET image=? WHERE id=?");
    $st->bind_param('si',$rel,$userId); $st->execute(); $st->close();

    header('Location: '.qs(['msg'=>'Avatar updated'])); exit;
  }

  if($action==='avatar_remove'){
    if(!empty($user['image'])){
      $old = $projectRootFs.'/'.ltrim($user['image'],'/');
      if(is_file($old)) @unlink($old);
    }
    $st = $conn->prepare("UPDATE users SET image=NULL WHERE id=?");
    $st->bind_param('i',$userId); $st->execute(); $st->close();
    header('Location: '.qs(['msg'=>'Avatar removed'])); exit;
  }

  if($action==='save_profile'){
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if(!$full_name || !$email){
      header('Location: '.qs(['msg'=>'Name and email are required'])); exit;
    }

    // unique email except me
    $st = $conn->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
    $st->bind_param('si',$email,$userId); $st->execute();
    $exists = $st->get_result()->fetch_assoc(); $st->close();
    if($exists){ header('Location: '.qs(['msg'=>'Email already in use'])); exit; }

    // update basics
    $st = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=?");
    $st->bind_param('sssi',$full_name,$email,$phone,$userId); $st->execute(); $st->close();

    // password (optional)
    $curr = (string)($_POST['curr_pass'] ?? '');
    $new1 = (string)($_POST['new_pass'] ?? '');
    $new2 = (string)($_POST['new_pass2'] ?? '');
    if($curr!=='' || $new1!=='' || $new2!==''){
      $st = $conn->prepare("SELECT pass_hash FROM users WHERE id=? LIMIT 1");
      $st->bind_param('i',$userId); $st->execute();
      $ph = ($st->get_result()->fetch_assoc()['pass_hash'] ?? ''); $st->close();

      $ok = (strpos((string)$ph,'$2y$')===0) ? password_verify($curr,(string)$ph) : hash_equals((string)$ph,$curr);
      if(!$ok){ header('Location: '.qs(['msg'=>'Current password is incorrect'])); exit; }
      if(strlen($new1)<8){ header('Location: '.qs(['msg'=>'New password must be at least 8 characters'])); exit; }
      if($new1!==$new2){ header('Location: '.qs(['msg'=>'New passwords do not match'])); exit; }

      $hash = password_hash($new1, PASSWORD_BCRYPT);
      $st = $conn->prepare("UPDATE users SET pass_hash=? WHERE id=?");
      $st->bind_param('si',$hash,$userId); $st->execute(); $st->close();
      $_SESSION['user']['email']=$email; $_SESSION['user']['name']=$full_name;
      $_SESSION['owner']['email']=$email; $_SESSION['owner']['name']=$full_name;

      header('Location: '.qs(['msg'=>'Profile & password updated'])); exit;
    }

    $_SESSION['user']['email']=$email; $_SESSION['user']['name']=$full_name;
    $_SESSION['owner']['email']=$email; $_SESSION['owner']['name']=$full_name;

    header('Location: '.qs(['msg'=>'Profile updated'])); exit;
  }
}

/* ---------- Fresh user for render ---------- */
$st = $conn->prepare("SELECT id, role, name, email, phone, status, created_at, image FROM users WHERE id=? LIMIT 1");
$st->bind_param('i',$userId); $st->execute();
$user = $st->get_result()->fetch_assoc(); $st->close();

$avatarUrl = $user['image']
  ? (($webBase ? $webBase : '').'/'.ltrim((string)$user['image'],'/'))
  : 'https://i.pravatar.cc/160?u='.$userId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • Owner Profile</title>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>

  <style>
    :root{--primary:#F59E0B;--accent:#EF4444;--bg:#FFF7ED;--text:#1F2937;--card:#FFFFFF;--muted:#6B7280;--border:#f1e6d7;--radius:18px;--shadow:0 10px 30px rgba(0,0,0,.08);--shadow-sm:0 6px 16px rgba(0,0,0,.06)}
    *{box-sizing:border-box} html,body{height:100%} body{margin:0} body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif;line-height:1.5}
    .sidebar{position:fixed;left:0;top:0;height:100vh;width:280px;background:#fff;border-right:1px solid #f3e7d9;box-shadow:var(--shadow);padding:18px 16px;display:flex;flex-direction:column;z-index:45;transition:transform .35s ease}
    .sidebar-head{display:flex;align-items:center;gap:12px;margin-top:24px;margin-bottom:18px}
    .avatar img{width:52px;height:52px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.08)}
    .info .nm{font-weight:700} .info .sub{color:var(--muted);font-size:12px}
    .nav{display:flex;flex-direction:column;gap:6px;margin-top:8px}
    .nav a{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;color:inherit;text-decoration:none;border:1px solid transparent}
    .nav a:hover{background:#fff7ef;border-color:var(--border)}
    .nav a.active{background:linear-gradient(135deg,#fff7ef,#fff);border-color:var(--border);box-shadow:var(--shadow-sm)}
    .nav a.danger{color:#b42318} .nav i{width:22px;text-align:center}
    .page{margin-left:280px;padding:28px 24px 60px}
    .card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
    .card-head h2{margin:0;font-family:Montserrat,sans-serif;font-size:20px}
    .muted{color:var(--muted)}
    .page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .page-title h1{margin:0;font-family:Montserrat,sans-serif;font-size:28px}
    .breadcrumbs{font-size:13px;color:var(--muted)}
    .tag{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid var(--border);font-size:12px}
    .profile-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:20px}
    .section{display:flex;flex-direction:column;gap:16px}
    form{display:flex;flex-direction:column;gap:16px}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .field{display:flex;flex-direction:column;gap:6px}
    .field label{font-weight:600;font-size:14px}
    .req{color:#b91c1c}
    .input, .select, .textarea{border:1px solid var(--border);background:#fff;border-radius:12px;padding:12px 12px;font-size:14px;outline:0}
    .input:focus, .select:focus, .textarea:focus{box-shadow:0 0 0 4px #ffe7c6;border-color:#f2cf97}
    .textarea{min-height:96px;resize:vertical}
    .help{font-size:12px;color:var(--muted)}
    .avatar-card{display:grid;grid-template-columns:96px 1fr;gap:14px;align-items:center;border:1px dashed var(--border);border-radius:14px;padding:14px;background:#fffdf7}
    .avatar-card .photo{width:96px;height:96px;border-radius:50%;overflow:hidden;border:4px solid #fff;box-shadow:0 6px 16px rgba(0,0,0,.08)}
    .avatar-card .photo img{width:100%;height:100%;object-fit:cover}
    .avatar-actions{display:flex;gap:10px;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:10px 14px;cursor:pointer;font-weight:600}
    .btn-primary{background:linear-gradient(135deg,var(--primary),#ffb444);color:#fff}
    .btn-ghost{background:#fff;border:1px solid var(--border);color:#92400e}
    .btn-danger{background:linear-gradient(135deg,#f87171,#ef4444);color:#fff}
    .file-input{position:relative;overflow:hidden}
    .file-input input{position:absolute;inset:0;opacity:0;cursor:pointer}
    .pref-list{display:flex;flex-direction:column;gap:10px}
    .pref{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;border:1px solid var(--border);border-radius:12px;padding:10px;background:#fff}
    .switch{--w:44px;--h:24px;position:relative;width:var(--w);height:var(--h);background:#e5e7eb;border-radius:999px;transition:.25s}
    .switch::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:.25s}
    input[type="checkbox"].toggle:checked + .switch{background:#fbbf24}
    input[type="checkbox"].toggle:checked + .switch::after{transform:translateX(20px)}
    .check{display:flex;align-items:center;gap:8px}
    .note{background:#fff7ef;border:1px solid var(--border);border-radius:12px;padding:10px;color:#92400e;font-size:13px}
    .bar{position:sticky;bottom:0;display:flex;justify-content:flex-end;gap:10px;background:linear-gradient(180deg,transparent, #fffdf7 40%);padding-top:10px}
    .summary{display:grid;grid-template-columns:1fr;gap:12px}
    .row{display:flex;justify-content:space-between;align-items:center;font-size:14px;border-bottom:1px dashed #f3e7d9;padding:6px 0}
    .row:last-child{border-bottom:0}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid var(--border);font-size:12px}
    @media (max-width: 1024px){ .profile-grid{grid-template-columns:1fr} }
    @media (max-width: 640px){
      .sidebar{transform:translateX(-100%)}
      .page{margin-left:0}
      .form-grid{grid-template-columns:1fr}
      .avatar-card{grid-template-columns:1fr}
      .bar{justify-content:center}
    }
  </style>
</head>
<body class="bg-app">

<?php if (file_exists(__DIR__.'/sidebar.php')) include __DIR__."/sidebar.php"; ?>

  <main class="page">
    <div class="page-head">
      <div class="page-title">
        <div class="breadcrumbs">Owner • Account</div>
        <h1>Profile</h1>
      </div>
      <span class="tag"><i class="bi bi-person-check"></i> <?= e($user['role']) ?></span>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
      <div class="card" style="background:#eef2ff;border-color:#c7d2fe;margin-bottom:12px">
        <i class="bi bi-info-circle"></i> <?= e($_GET['msg']) ?>
      </div>
    <?php endif; ?>

    <div class="profile-grid">
      <!-- Left: Forms -->
      <section class="section">
        <!-- Avatar -->
        <div class="card">
          <div class="card-head">
            <h2>Avatar</h2>
            <span class="muted">Update your profile photo</span>
          </div>

          <form class="avatar-card" id="avatarForm" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
            <input type="hidden" name="__act" id="__act" value="">
            <div class="photo">
              <img src="<?= e($avatarUrl) ?>" alt="Current avatar">
            </div>
            <div>
              <div class="avatar-actions">
                <label class="btn btn-primary file-input">
                  <i class="bi bi-cloud-upload"></i> Upload New
                  <input type="file" name="avatar" id="avatarFile" accept="image/*"
                         onchange="document.getElementById('__act').value='avatar_upload'; this.form.submit();">
                </label>
                <button class="btn btn-ghost" type="button" disabled title="Coming soon">
                  <i class="bi bi-arrows-angle-contract"></i> Crop
                </button>
                <button class="btn btn-danger" type="submit" onclick="document.getElementById('__act').value='avatar_remove'">
                  <i class="bi bi-trash3"></i> Remove
                </button>
              </div>
              <div class="help">JPG/PNG/WebP up to 5MB • 1:1 recommended</div>
            </div>
          </form>
        </div>

        <!-- Basic info + Password -->
        <form class="card" method="post">
          <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
          <input type="hidden" name="__act" value="save_profile">

          <div class="card-head">
            <h2>Basic Information</h2>
            <span class="muted">Name & contact details</span>
          </div>

          <div class="form-grid">
            <div class="field">
              <label>Full Name <span class="req">*</span></label>
              <input class="input" type="text" name="full_name" value="<?= e($user['name'] ?? '') ?>" required>
            </div>
            <div class="field">
              <label>Email <span class="req">*</span></label>
              <input class="input" type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required>
            </div>
            <div class="field">
              <label>Phone</label>
              <input class="input" type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+92 3XX XXXXXXX">
            </div>
            <div class="field">
              <label>Role</label>
              <input class="input" type="text" value="<?= e($user['role']) ?>" disabled>
            </div>
          </div>

          <div class="card-head" style="margin-top:6px">
            <h2>Security</h2>
            <span class="muted">Change your password</span>
          </div>
          <div class="form-grid">
            <div class="field">
              <label>Current Password</label>
              <input class="input" type="password" name="curr_pass" placeholder="••••••••">
            </div>
            <div class="field">
              <label>New Password</label>
              <input class="input" type="password" name="new_pass" placeholder="At least 8 characters">
            </div>
            <div class="field">
              <label>Confirm New Password</label>
              <input class="input" type="password" name="new_pass2" placeholder="Re-enter password">
            </div>
            <div class="field" style="grid-column:1/-1">
              <div class="note"><i class="bi bi-shield-lock"></i> Tip: Use a mix of letters, numbers and symbols.</div>
            </div>
          </div>

          <div class="bar">
            <button class="btn btn-ghost" type="reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save Changes</button>
          </div>
        </form>
      </section>

      <!-- Right: Summary -->
      <aside class="section">
        <div class="card">
          <div class="card-head">
            <h2>Profile Summary</h2>
            <span class="muted">Quick snapshot</span>
          </div>
          <div class="summary">
            <div class="row"><span>Name</span><b><?= e($user['name'] ?? '-') ?></b></div>
            <div class="row"><span>Email</span><b><?= e($user['email'] ?? '-') ?></b></div>
            <div class="row"><span>Phone</span><b><?= e($user['phone'] ?? '-') ?></b></div>
            <div class="row"><span>Role</span><b><span class="pill"><i class="bi bi-person-badge"></i> <?= e($user['role']) ?></span></b></div>
            <div class="row"><span>Status</span><b><span class="pill"><?= e($user['status'] ?? 'pending') ?></span></b></div>
            <div class="row"><span>Joined</span><b><?= e(substr((string)$user['created_at'],0,10)) ?></b></div>
          </div>
        </div>

        <div class="card">
          <div class="card-head">
            <h2>Owner Tips</h2>
            <span class="muted">Keep things up-to-date</span>
          </div>
          <ul style="margin:0;padding-left:18px">
            <li>Add an alternate phone for vet emergencies.</li>
            <li>Turn on SMS for vaccine reminders.</li>
            <li>Keep address current for home-visit grooming.</li>
          </ul>
        </div>

        <div class="card">
          <div class="card-head">
            <h2>Need Help?</h2>
            <span class="muted">We’re here for you</span>
          </div>
          <div class="summary">
            <div class="row"><span><i class="bi bi-envelope"></i> Email</span><b>support@furshield.app</b></div>
            <div class="row"><span><i class="bi bi-life-preserver"></i> FAQ</span><b><span class="pill">Care Guides</span></b></div>
            <div class="row"><span><i class="bi bi-clock-history"></i> Hours</span><b>9am–6pm PKT</b></div>
          </div>
        </div>
      </aside>
    </div>
  </main>

<script>
  // No extra JS needed.
</script>
</body>
</html>
