<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('vet');

if (!defined('BASE')) define('BASE','/furshield');
$uid = (int)($_SESSION['user']['id'] ?? 0);

/* ---------- helpers ---------- */
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
function save_one($field, $dir, $prefix){
  if (!isset($_FILES[$field]) || $_FILES[$field]['error']!==UPLOAD_ERR_OK) return null;
  $ext=strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
  if (!$ext) $ext='bin';
  if (!is_dir($dir)) @mkdir($dir,0775,true);
  $name = $prefix.'_'.uniqid().'.'.$ext;
  $to = rtrim($dir,'/').'/'.$name;
  if (move_uploaded_file($_FILES[$field]['tmp_name'],$to)) return $name;
  return null;
}

/* ---------- schema bootstrap ---------- */
$tab = table_exists($conn,'vets') ? 'vets' : null;
if (!$tab) {
  $conn->query("CREATE TABLE IF NOT EXISTS vets(
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(120) NULL,
    specialization VARCHAR(255) NULL,
    experience_years INT NULL,
    clinic_address TEXT NULL,
    bio TEXT NULL,
    avatar VARCHAR(255) NULL,
    cnic_no VARCHAR(64) NULL,
    cnic_image VARCHAR(255) NULL,
    license_no VARCHAR(64) NULL,
    license_image VARCHAR(255) NULL,
    status VARCHAR(32) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id), INDEX(status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  $tab = 'vets';
}
/* ensure name column exists for legacy tables */
if (!col_exists($conn, 'vets', 'name')) {
  $conn->query("ALTER TABLE `vets` ADD COLUMN `name` VARCHAR(120) NULL AFTER `user_id`");
}

/* ---------- column mapping ---------- */
$C_ID   = pick_col($conn,$tab,['id','vet_id']);
$C_USER = pick_col($conn,$tab,['user_id','uid']);
$C_NAME = pick_col($conn,$tab,['name','full_name','display_name']);
$C_SPEC = pick_col($conn,$tab,['specialization','expertise','speciality','skills']);
$C_EXP  = pick_col($conn,$tab,['experience_years','years_experience','experience','exp_years']);
$C_ADDR = pick_col($conn,$tab,['clinic_address','address','clinic_addr','clinic']);
$C_BIO  = pick_col($conn,$tab,['bio','about','description','summary']);
$C_AVA  = pick_col($conn,$tab,['avatar','profile_image','photo','image']);
$C_CNIC = pick_col($conn,$tab,['cnic_no','cnic_number','cnic','national_id']);
$C_CIMG = pick_col($conn,$tab,['cnic_image','cnic_file','cnic_doc','id_card_image']);
$C_LIC  = pick_col($conn,$tab,['license_no','license_number','license','license_id']);
$C_LIMG = pick_col($conn,$tab,['license_image','license_file','license_doc','license_pic']);
$C_STAT = pick_col($conn,$tab,['status','verification_status','state']);

/* ---------- paths ---------- */
$uploadsAvatar = dirname(__DIR__).'/uploads/avatars/';
$uploadsDocs   = dirname(__DIR__).'/uploads/records/';
$avatarUrl = BASE.'/uploads/avatars/';
$docsUrl   = BASE.'/uploads/records/';

/* ---------- ensure a row for this user ---------- */
$vetRowId = null;
$hasRow = false;

if ($C_USER) {
  $chk = $conn->prepare("SELECT 1 FROM `$tab` WHERE `$C_USER`=? LIMIT 1");
  $chk->bind_param("i", $uid);
  $chk->execute();
  $chk->store_result();
  $hasRow = $chk->num_rows > 0;
  $chk->close();

  if ($hasRow && $C_ID) {
    $q = $conn->prepare("SELECT `$C_ID` FROM `$tab` WHERE `$C_USER`=? LIMIT 1");
    $q->bind_param("i", $uid);
    $q->execute();
    $q->bind_result($vetRowId);
    $q->fetch();
    $q->close();
  }

  if (!$hasRow) {
    $ins = $conn->prepare("INSERT INTO `$tab`(`$C_USER`) VALUES (?)");
    $ins->bind_param("i", $uid);
    try {
      $ins->execute();
      $vetRowId = $ins->insert_id ?: $vetRowId;
    } catch (mysqli_sql_exception $e) {
      if ($e->getCode() != 1062) { throw $e; }
    }
    $ins->close();
  }
}

/* ---------- handle post ---------- */
$flash=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name = trim($_POST['name'] ?? '');
  $spec = trim($_POST['specialization'] ?? '');
  $exp  = (int)($_POST['experience'] ?? 0);
  $addr = trim($_POST['clinic_address'] ?? '');
  $bio  = trim($_POST['bio'] ?? '');
  $cnicNo = trim($_POST['cnic_no'] ?? '');
  $licNo  = trim($_POST['license_no'] ?? '');

  $newAvatar = save_one('avatar', $uploadsAvatar, 'avatar');
  $newCnic   = save_one('cnic_img', $uploadsDocs, 'cnic');
  $newLic    = save_one('license_img', $uploadsDocs, 'license');

  $set=[]; $types=''; $vals=[];
  if ($C_NAME){ $set[]="`$C_NAME`=?"; $types.='s'; $vals[]=&$name; }
  if ($C_SPEC){ $set[]="`$C_SPEC`=?"; $types.='s'; $vals[]=&$spec; }
  if ($C_EXP){  $set[]="`$C_EXP`=?";  $types.='i'; $vals[]=&$exp; }
  if ($C_ADDR){ $set[]="`$C_ADDR`=?"; $types.='s'; $vals[]=&$addr; }
  if ($C_BIO){  $set[]="`$C_BIO`=?";  $types.='s'; $vals[]=&$bio; }
  if ($C_CNIC){ $set[]="`$C_CNIC`=?"; $types.='s'; $vals[]=&$cnicNo; }
  if ($C_LIC){  $set[]="`$C_LIC`=?";  $types.='s'; $vals[]=&$licNo; }
  if ($C_AVA && $newAvatar){ $set[]="`$C_AVA`=?"; $types.='s'; $vals[]=&$newAvatar; }
  if ($C_CIMG && $newCnic){  $set[]="`$C_CIMG`=?"; $types.='s'; $vals[]=&$newCnic; }
  if ($C_LIMG && $newLic){   $set[]="`$C_LIMG`=?"; $types.='s'; $vals[]=&$newLic; }

  if ($set){
    if ($vetRowId && $C_ID){
      $sql="UPDATE `$tab` SET ".implode(', ',$set)." WHERE `$C_ID`=?";
      $types.='i'; $vals[]=&$vetRowId;
      $st=$conn->prepare($sql); $st->bind_param($types, ...$vals); $ok=$st->execute(); $st->close();
    } elseif ($C_USER){
      $sql="UPDATE `$tab` SET ".implode(', ',$set)." WHERE `$C_USER`=?";
      $types.='i'; $vals[]=&$uid;
      $st=$conn->prepare($sql); $st->bind_param($types, ...$vals); $ok=$st->execute(); $st->close();
    } else { $ok=false; }
    $flash = $ok ? "Profile updated" : "Update failed";
  } else {
    $flash = "Nothing to update";
  }
}

/* ---------- fetch current ---------- */
$row=[];
if ($vetRowId && $C_ID){
  $q=$conn->prepare("SELECT * FROM `$tab` WHERE `$C_ID`=? LIMIT 1");
  $q->bind_param("i",$vetRowId); $q->execute(); $res=$q->get_result(); $row=$res->fetch_assoc()?:[]; $q->close();
} elseif ($C_USER){
  $q=$conn->prepare("SELECT * FROM `$tab` WHERE `$C_USER`=? LIMIT 1");
  $q->bind_param("i",$uid); $q->execute(); $res=$q->get_result(); $row=$res->fetch_assoc()?:[]; $q->close();
}

$avatarFile = $C_AVA ? ($row[$C_AVA] ?? '') : '';
$cnicFile   = $C_CIMG? ($row[$C_CIMG] ?? '') : '';
$licFile    = $C_LIMG? ($row[$C_LIMG] ?? '') : '';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="main">
  <div class="container-fluid py-4">
    <?php if($flash){ ?><div class="alert alert-warning border-0 shadow-sm mb-3"><?php echo htmlspecialchars($flash); ?></div><?php } ?>

    <div class="row g-3">
      <div class="col-12 col-lg-4">
        <div class="cardx p-3 text-center">
          <div class="mb-3">
            <div class="avatar-wrap mx-auto">
              <img src="<?php echo $avatarFile ? htmlspecialchars($avatarUrl.$avatarFile) : 'https://api.dicebear.com/7.x/initials/svg?seed='.urlencode($_SESSION['user']['email'] ?? 'V'); ?>" class="avatar-img" alt="Avatar"/>
            </div>
          </div>
          <div class="text-muted small mb-2">Verification Status</div>
          <div class="h6 mb-3">
            <?php echo htmlspecialchars($C_STAT ? (ucfirst((string)($row[$C_STAT] ?? 'pending'))) : 'N/A'); ?>
          </div>

          <form method="post" enctype="multipart/form-data" class="d-grid gap-2">
            <label class="form-label text-start m-0">Update Profile Photo</label>
            <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp" class="form-control"/>
            <button class="btn btn-primary mt-1">Save Photo</button>
          </form>

          <hr class="my-3"/>

          <div class="text-start small">
            <div class="fw-semibold mb-1">Documents</div>
            <div class="mb-2">CNIC:
              <?php if($cnicFile){ ?>
                <a class="link-primary" target="_blank" href="<?php echo htmlspecialchars($docsUrl.$cnicFile); ?>">View</a>
              <?php } else { echo '<span class="text-muted">—</span>'; } ?>
            </div>
            <div>License:
              <?php if($licFile){ ?>
                <a class="link-primary" target="_blank" href="<?php echo htmlspecialchars($docsUrl.$licFile); ?>">View</a>
              <?php } else { echo '<span class="text-muted">—</span>'; } ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-8">
        <div class="cardx p-3">
          <h5 class="mb-3">Professional Details</h5>
          <form method="post" enctype="multipart/form-data" class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name</label>
              <input type="text" name="name" class="form-control" placeholder="e.g., Dr. Ayesha Khan"
                     value="<?php echo htmlspecialchars($C_NAME ? (string)($row[$C_NAME] ?? '') : ''); ?>"/>
            </div>
            <div class="col-md-6">
              <label class="form-label">Specialization</label>
              <input type="text" name="specialization" class="form-control" placeholder="e.g., Surgery, Dermatology, Vaccination"
                     value="<?php echo htmlspecialchars($C_SPEC ? (string)($row[$C_SPEC] ?? '') : ''); ?>"/>
            </div>
            <div class="col-md-3">
              <label class="form-label">Experience (yrs)</label>
              <input type="number" min="0" max="60" name="experience" class="form-control"
                     value="<?php echo htmlspecialchars($C_EXP ? (string)($row[$C_EXP] ?? '0') : '0'); ?>"/>
            </div>
            <div class="col-md-12">
              <label class="form-label">Clinic Address</label>
              <textarea name="clinic_address" class="form-control" rows="2" placeholder="Street • City • Zip • Country"><?php
                echo htmlspecialchars($C_ADDR ? (string)($row[$C_ADDR] ?? '') : '');
              ?></textarea>
            </div>
            <div class="col-md-12">
              <label class="form-label">Bio</label>
              <textarea name="bio" class="form-control" rows="3" placeholder="Short professional bio"><?php
                echo htmlspecialchars($C_BIO ? (string)($row[$C_BIO] ?? '') : '');
              ?></textarea>
            </div>

            <div class="col-md-6">
              <label class="form-label">CNIC / National ID</label>
              <input type="text" name="cnic_no" class="form-control" placeholder="e.g., 42101-1234567-1"
                     value="<?php echo htmlspecialchars($C_CNIC ? (string)($row[$C_CNIC] ?? '') : ''); ?>"/>
            </div>
            <div class="col-md-6">
              <label class="form-label">Upload CNIC Image</label>
              <input type="file" name="cnic_img" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf"/>
            </div>

            <div class="col-md-6">
              <label class="form-label">License No.</label>
              <input type="text" name="license_no" class="form-control" placeholder="e.g., PV-123456"
                     value="<?php echo htmlspecialchars($C_LIC ? (string)($row[$C_LIC] ?? '') : ''); ?>"/>
            </div>
            <div class="col-md-6">
              <label class="form-label">Upload License</label>
              <input type="file" name="license_img" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf"/>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary">Save Changes</button>
              <a href="dashboard.php" class="btn btn-outline-dark">Back</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
