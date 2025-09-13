<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('BASE')) define('BASE', '/furshield');
$root = dirname(__DIR__, 3);
require_once $root . '/includes/db.php';
require_once $root . '/includes/auth.php';
require_role('admin');
include $root . '/admin/includes/header.php';
include $root . '/admin/includes/sidebar.php';

function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function t_exists(mysqli $c,string $t):bool{ $db = $c->query("SELECT DATABASE()")->fetch_row()[0] ?? ''; $s=$c->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?"); if(!$s)return false; $s->bind_param("ss",$db,$t); $ok=$s->execute(); $cnt=0; if($ok){$s->bind_result($cnt);$s->fetch();} $s->close(); return $ok && $cnt>0; }
function hascol(mysqli $c,string $t,string $col):bool{ $db = $c->query("SELECT DATABASE()")->fetch_row()[0] ?? ''; $s=$c->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?"); if(!$s)return false; $s->bind_param("sss",$db,$t,$col); $ok=$s->execute(); $cnt=0; if($ok){$s->bind_result($cnt);$s->fetch();} $s->close(); return $ok && $cnt>0; }
function pick(array $a,array $keys,$d=''){ foreach($keys as $k){ if(isset($a[$k]) && $a[$k]!=='' && $a[$k]!==null) return $a[$k]; } return $d; }
function media(string $p,string $fb=''):string{ if(!$p){return $fb;} if(preg_match('~^https?://~i',$p)) return $p; if(str_starts_with($p,'/')) return $p; return BASE.'/'.ltrim($p,'/'); }
function ensure_home_settings(mysqli $c):bool{
  if(!t_exists($c,'homepage_settings')){
    $sql="CREATE TABLE IF NOT EXISTS homepage_settings (
      id TINYINT UNSIGNED PRIMARY KEY,
      hero_title VARCHAR(200) NULL,
      hero_subtitle VARCHAR(300) NULL,
      hero_image VARCHAR(255) NULL,
      cta_primary_text VARCHAR(100) NULL,
      cta_primary_url VARCHAR(255) NULL,
      cta_secondary_text VARCHAR(100) NULL,
      cta_secondary_url VARCHAR(255) NULL,
      adoption_mode ENUM('latest','featured') NULL,
      products_mode ENUM('latest','featured') NULL,
      updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if(!$c->query($sql)) return false;
  }
  $need = ['hero_title','hero_subtitle','hero_image','cta_primary_text','cta_primary_url','cta_secondary_text','cta_secondary_url','adoption_mode','products_mode','updated_at'];
  foreach($need as $col){
    if(!hascol($c,'homepage_settings',$col)){
      $map=[
        'hero_title'=>"ALTER TABLE homepage_settings ADD COLUMN hero_title VARCHAR(200) NULL",
        'hero_subtitle'=>"ALTER TABLE homepage_settings ADD COLUMN hero_subtitle VARCHAR(300) NULL",
        'hero_image'=>"ALTER TABLE homepage_settings ADD COLUMN hero_image VARCHAR(255) NULL",
        'cta_primary_text'=>"ALTER TABLE homepage_settings ADD COLUMN cta_primary_text VARCHAR(100) NULL",
        'cta_primary_url'=>"ALTER TABLE homepage_settings ADD COLUMN cta_primary_url VARCHAR(255) NULL",
        'cta_secondary_text'=>"ALTER TABLE homepage_settings ADD COLUMN cta_secondary_text VARCHAR(100) NULL",
        'cta_secondary_url'=>"ALTER TABLE homepage_settings ADD COLUMN cta_secondary_url VARCHAR(255) NULL",
        'adoption_mode'=>"ALTER TABLE homepage_settings ADD COLUMN adoption_mode ENUM('latest','featured') NULL",
        'products_mode'=>"ALTER TABLE homepage_settings ADD COLUMN products_mode ENUM('latest','featured') NULL",
        'updated_at'=>"ALTER TABLE homepage_settings ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
      ];
      if(isset($map[$col])) $c->query($map[$col]);
    }
  }
  $row=[]; $s=$c->prepare("SELECT id FROM homepage_settings WHERE id=1"); if($s){$s->execute(); $res=$s->get_result(); $row=$res?$res->fetch_assoc():[]; $s->close();}
  if(!$row){ $s=$c->prepare("INSERT INTO homepage_settings (id, hero_title, hero_subtitle, adoption_mode, products_mode) VALUES (1, 'Care that feels like home', 'Find vets, adopt loving pets, and shop essentials in one place.', 'latest','latest')"); if($s){$s->execute(); $s->close();} }
  return true;
}

$uploadsDir = $root . '/uploads/home/';
$uploadsUrl = BASE . '/uploads/home/';
if(!is_dir($uploadsDir)) @mkdir($uploadsDir,0775,true);

if(empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin']=bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_admin'];

$db_ready = ensure_home_settings($conn);
$notice = '';
$ok = true;

if($_SERVER['REQUEST_METHOD']==='POST'){
  $ok = hash_equals($csrf, $_POST['csrf'] ?? '');
  if(!$ok){ $notice = 'Security check failed.'; }
  if($ok && $db_ready){
    $hero_title = trim($_POST['hero_title'] ?? '');
    $hero_subtitle = trim($_POST['hero_subtitle'] ?? '');
    $cta_primary_text = trim($_POST['cta_primary_text'] ?? '');
    $cta_primary_url = trim($_POST['cta_primary_url'] ?? '');
    $cta_secondary_text = trim($_POST['cta_secondary_text'] ?? '');
    $cta_secondary_url = trim($_POST['cta_secondary_url'] ?? '');
    $adoption_mode = in_array($_POST['adoption_mode'] ?? '',['latest','featured'],true) ? $_POST['adoption_mode'] : 'latest';
    $products_mode = in_array($_POST['products_mode'] ?? '',['latest','featured'],true) ? $_POST['products_mode'] : 'latest';
    $imgPath = null;
    if(!empty($_FILES['hero_image']['name']) && is_uploaded_file($_FILES['hero_image']['tmp_name'])){
      $f = $_FILES['hero_image'];
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      $allowed = ['jpg','jpeg','png','webp','gif'];
      if(in_array($ext,$allowed,true)){
        $base = preg_replace('~[^a-z0-9]+~i','-', pathinfo($f['name'], PATHINFO_FILENAME));
        $name = trim($base,'-').'-'.bin2hex(random_bytes(4)).'.'.$ext;
        $target = $uploadsDir.$name;
        if(@move_uploaded_file($f['tmp_name'],$target)){
          $imgPath = '/uploads/home/'.$name;
        }
      }
    }
    $cols = [];
    if(hascol($conn,'homepage_settings','hero_title')) $cols['hero_title']=$hero_title;
    if(hascol($conn,'homepage_settings','hero_subtitle')) $cols['hero_subtitle']=$hero_subtitle;
    if($imgPath && hascol($conn,'homepage_settings','hero_image')) $cols['hero_image']=$imgPath;
    if(hascol($conn,'homepage_settings','cta_primary_text')) $cols['cta_primary_text']=$cta_primary_text;
    if(hascol($conn,'homepage_settings','cta_primary_url')) $cols['cta_primary_url']=$cta_primary_url;
    if(hascol($conn,'homepage_settings','cta_secondary_text')) $cols['cta_secondary_text']=$cta_secondary_text;
    if(hascol($conn,'homepage_settings','cta_secondary_url')) $cols['cta_secondary_url']=$cta_secondary_url;
    if(hascol($conn,'homepage_settings','adoption_mode')) $cols['adoption_mode']=$adoption_mode;
    if(hascol($conn,'homepage_settings','products_mode')) $cols['products_mode']=$products_mode;

    if(!empty($cols)){
      $fields = array_keys($cols);
      $place = implode(',', array_fill(0,count($fields),'?'));
      $updates = implode(',', array_map(fn($f)=>"$f=VALUES($f)", $fields));
      $types = str_repeat('s', count($fields));
      $sql = "INSERT INTO homepage_settings (id,".implode(',',$fields).") VALUES (1,$place) ON DUPLICATE KEY UPDATE $updates";
      $stmt = $conn->prepare($sql);
      if($stmt){
        $vals = array_values($cols);
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $stmt->close();
        $notice = 'Saved.';
      } else {
        $notice = 'Could not save.';
        $ok = false;
      }
    } else {
      $notice = 'Nothing to update.';
    }
  }
}

$settings = [
  'hero_title'=>'',
  'hero_subtitle'=>'',
  'hero_image'=>'',
  'cta_primary_text'=>'',
  'cta_primary_url'=>'',
  'cta_secondary_text'=>'',
  'cta_secondary_url'=>'',
  'adoption_mode'=>'latest',
  'products_mode'=>'latest'
];
if($db_ready){
  $s=$conn->prepare("SELECT hero_title,hero_subtitle,hero_image,cta_primary_text,cta_primary_url,cta_secondary_text,cta_secondary_url,adoption_mode,products_mode FROM homepage_settings WHERE id=1");
  if($s){ $s->execute(); $r=$s->get_result(); if($r && $r->num_rows){ $settings = array_merge($settings, $r->fetch_assoc()); } $s->close(); }
}

$previewImg = media($settings['hero_image'] ?? '', BASE.'/assets/placeholder/hero.webp');
?>
<main class="main p-4">
  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 fw-bold m-0">Homepage Settings</h1>
      <span class="badge rounded-pill px-3 py-2" style="background:var(--primary);color:#fff">Live</span>
    </div>

    <?php if($notice): ?>
      <div class="alert <?php echo $ok?'alert-success':'alert-danger'; ?> border-0 rounded-3" role="alert"><?php echo e($notice); ?></div>
    <?php endif; ?>
    <?php if(!$db_ready): ?>
      <div class="alert alert-warning border-0 rounded-3" role="alert">Storage is not ready. Changes will not persist.</div>
    <?php endif; ?>

    <form class="row g-4" method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>"/>

      <div class="col-12 col-xl-8">
        <div class="card border-0" style="border-radius:var(--radius);box-shadow:var(--shadow);background:var(--card)">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h2 class="h5 fw-bold m-0">Hero</h2>
              <i class="bi bi-image fs-4" style="color:var(--primary)"></i>
            </div>
            <div class="mb-3">
              <label class="form-label">Title</label>
              <input type="text" name="hero_title" class="form-control form-control-lg" value="<?php echo e($settings['hero_title']); ?>" placeholder="Care that feels like home" />
            </div>
            <div class="mb-3">
              <label class="form-label">Subtitle</label>
              <input type="text" name="hero_subtitle" class="form-control" value="<?php echo e($settings['hero_subtitle']); ?>" placeholder="Find vets, adopt loving pets, and shop essentials in one place." />
            </div>
            <div class="row g-3">
              <div class="col-12 col-md-7">
                <label class="form-label">Hero Image</label>
                <input class="form-control" type="file" name="hero_image" accept=".jpg,.jpeg,.png,.webp,.gif" />
                <div class="form-text">1920×900 recommended. JPG/PNG/WEBP.</div>
              </div>
              <div class="col-12 col-md-5">
                <div class="ratio ratio-21x9 rounded" style="border-radius:var(--radius);background:#f4f4f9;overflow:hidden">
                  <div style="background:url('<?php echo e($previewImg); ?>') center/cover no-repeat;width:100%;height:100%"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card border-0 mt-4" style="border-radius:var(--radius);box-shadow:var(--shadow);background:var(--card)">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h2 class="h5 fw-bold m-0">Call To Actions</h2>
              <i class="bi bi-bullseye fs-4" style="color:var(--primary)"></i>
            </div>
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <div class="mb-3">
                  <label class="form-label">Primary Button Text</label>
                  <input type="text" name="cta_primary_text" class="form-control" value="<?php echo e($settings['cta_primary_text']); ?>" placeholder="Find a Vet" />
                </div>
                <div class="mb-3">
                  <label class="form-label">Primary Button URL</label>
                  <input type="url" name="cta_primary_url" class="form-control" value="<?php echo e($settings['cta_primary_url']); ?>" placeholder="<?php echo e(BASE); ?>/vets.php" />
                </div>
              </div>
              <div class="col-12 col-lg-6">
                <div class="mb-3">
                  <label class="form-label">Secondary Button Text</label>
                  <input type="text" name="cta_secondary_text" class="form-control" value="<?php echo e($settings['cta_secondary_text']); ?>" placeholder="Adopt a Pet" />
                </div>
                <div class="mb-3">
                  <label class="form-label">Secondary Button URL</label>
                  <input type="url" name="cta_secondary_url" class="form-control" value="<?php echo e($settings['cta_secondary_url']); ?>" placeholder="<?php echo e(BASE); ?>/adopt.php" />
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="col-12 col-xl-4">
        <div class="card border-0 mb-4" style="border-radius:var(--radius);box-shadow:var(--shadow);background:var(--card)">
          <div class="card-body p-4">
            <h2 class="h6 fw-bold mb-3">Homepage Sections</h2>
            <div class="mb-3">
              <label class="form-label">Adoption Section</label>
              <div class="input-group">
                <label class="input-group-text"><i class="bi bi-heart"></i></label>
                <select class="form-select" name="adoption_mode">
                  <option value="latest" <?php echo ($settings['adoption_mode']??'latest')==='latest'?'selected':''; ?>>Latest</option>
                  <option value="featured" <?php echo ($settings['adoption_mode']??'latest')==='featured'?'selected':''; ?>>Featured</option>
                </select>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label">Products Section</label>
              <div class="input-group">
                <label class="input-group-text"><i class="bi bi-bag"></i></label>
                <select class="form-select" name="products_mode">
                  <option value="latest" <?php echo ($settings['products_mode']??'latest')==='latest'?'selected':''; ?>>Latest</option>
                  <option value="featured" <?php echo ($settings['products_mode']??'latest')==='featured'?'selected':''; ?>>Featured</option>
                </select>
              </div>
            </div>
            <div class="text-muted small">Products in Featured mode respect the product flag when available.</div>
          </div>
        </div>

        <div class="card border-0" style="border-radius:var(--radius);box-shadow:var(--shadow);background:var(--card)">
          <div class="card-body p-4">
            <div class="d-grid gap-2">
              <button class="btn btn-lg text-white" style="background:var(--primary);border-radius:14px">Save Changes</button>
              <a href="<?php echo e(BASE); ?>/" class="btn btn-lg" style="border-radius:14px;border:1px solid #eee">View Homepage</a>
            </div>
          </div>
        </div>
      </div>
    </form>

    <div class="row g-4 mt-1">
      <div class="col-12">
        <div class="card border-0" style="border-radius:var(--radius);box-shadow:var(--shadow);background:linear-gradient(120deg,#fff, #fff3e6)">
          <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(245,158,11,.15);color:var(--primary)"><i class="bi bi-lightning-charge-fill fs-5"></i></div>
              <div class="flex-grow-1">
                <div class="fw-semibold">Preview</div>
                <div class="text-muted small">A visual cue of the hero area using current settings</div>
              </div>
            </div>
            <div class="mt-3">
              <div class="p-4 p-md-5 rounded-4 position-relative overflow-hidden" style="background:url('<?php echo e($previewImg); ?>') center/cover no-repeat">
                <div class="position-absolute top-0 start-0 w-100 h-100" style="background:linear-gradient(180deg,rgba(0,0,0,.25),rgba(0,0,0,.45))"></div>
                <div class="position-relative" style="max-width:760px">
                  <h3 class="text-white fw-bold display-6 mb-3" style="text-shadow:0 6px 24px rgba(0,0,0,.25)"><?php echo e($settings['hero_title'] ?: 'Care that feels like home'); ?></h3>
                  <p class="text-white-50 fs-6 mb-4"><?php echo e($settings['hero_subtitle'] ?: 'Find vets, adopt loving pets, and shop essentials in one place.'); ?></p>
                  <div class="d-flex gap-2 flex-wrap">
                    <?php if(!empty($settings['cta_primary_text'])): ?>
                      <a href="#" class="btn btn-lg text-white" style="background:var(--primary);border-radius:14px"><?php echo e($settings['cta_primary_text']); ?></a>
                    <?php endif; ?>
                    <?php if(!empty($settings['cta_secondary_text'])): ?>
                      <a href="#" class="btn btn-lg" style="border-radius:14px;background:#ffffffcc;backdrop-filter:blur(6px)"><?php echo e($settings['cta_secondary_text']); ?></a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="d-flex gap-3 flex-wrap mt-3">
                <span class="badge rounded-pill" style="background:#fff;border:1px solid #eee">Adoption: <?php echo e(strtoupper($settings['adoption_mode'] ?? 'latest')); ?></span>
                <span class="badge rounded-pill" style="background:#fff;border:1px solid #eee">Products: <?php echo e(strtoupper($settings['products_mode'] ?? 'latest')); ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<style>
:root{--primary:#F59E0B;--accent:#EF4444;--bg:#FFF7ED;--text:#1F2937;--card:#FFFFFF;--radius:18px;--shadow:0 10px 30px rgba(0,0,0,.08)}
body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif}
h1,h2,h3,h4,h5,h6,p{font-family:Montserrat,Poppins,sans-serif}
.card .form-label{font-weight:600}
.form-control,.form-select{border-radius:14px;border-color:#eee}
.input-group-text{border-radius:14px;border-color:#eee;background:#fafafa}
.btn:focus{box-shadow:0 0 0 .2rem rgba(245,158,11,.25)}
</style>

<?php include $root . '/admin/includes/footer.php'; ?>
