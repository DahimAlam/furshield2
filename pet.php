<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/includes/db.php';
if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function e($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function hascol(mysqli $c, string $t, string $col): bool {
  $t = $c->real_escape_string($t);
  $col = $c->real_escape_string($col);
  $q = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
  return $q && $q->num_rows>0;
}
function media_pet($file){
  $file=(string)$file;
  if($file==='') return BASE.'/assets/placeholder/pet.jpg';
  if (preg_match('~^https?://~',$file)) return $file;
  if ($file[0]==='/') return $file;
  return BASE.'/uploads/pets/'.$file;
}

/* ---------- Input ---------- */
$id = (int)($_GET['id'] ?? 0);
if(!$id){ http_response_code(404); exit('Pet not found'); }

/* ---------- Build dynamic SELECT (safe if some columns are missing) ---------- */
$sel_created  = hascol($conn,'addoption','created_at') ? ', a.created_at' : ', NULL AS created_at';
$sel_updated  = hascol($conn,'addoption','updated_at') ? ', a.updated_at' : ', NULL AS updated_at';
$sel_spot     = hascol($conn,'addoption','spotlight')  ? ', a.spotlight'  : ', 0 AS spotlight';
$sel_urgent   = hascol($conn,'addoption','urgent_until') ? ', a.urgent_until' : ', NULL AS urgent_until';
$sel_city     = hascol($conn,'addoption','city') ? ', a.city' : ', NULL AS city';

$sql = "SELECT a.id,a.name,a.species,a.breed,a.gender,a.age,a.avatar,a.description,
               a.status,a.shelter_id
               {$sel_city}{$sel_created}{$sel_updated}{$sel_spot}{$sel_urgent},
               u.name AS shelter_name
        FROM addoption a
        LEFT JOIN users u ON u.id = a.shelter_id
        WHERE a.id=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i',$id);
$stmt->execute();
$pet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$pet){ http_response_code(404); exit('Pet not found'); }

/* ---------- Session / user ---------- */
$logged_in = !empty($_SESSION['user']);
$role      = $logged_in ? ($_SESSION['user']['role'] ?? '') : '';
$user_id   = $logged_in ? (int)$_SESSION['user']['id'] : 0;

/* ---------- Already applied? ---------- */
$already = 0;
if ($logged_in && $role==='owner' && $pet['status']==='available') {
  $q = $conn->prepare("SELECT COUNT(*) c FROM request WHERE applicant_id=? AND pet_id=? AND status IN ('pending','pre-approved')");
  $q->bind_param('ii',$user_id,$id);
  $q->execute();
  $already = (int)($q->get_result()->fetch_assoc()['c'] ?? 0);
  $q->close();
}

/* ---------- Some meta ---------- */
$postedOn = !empty($pet['created_at']) ? date('M d, Y', strtotime($pet['created_at'])) : null;
$appliedCount = 0;
$rc = $conn->query("SELECT COUNT(*) c FROM request WHERE pet_id=".(int)$id);
if($rc){ $appliedCount = (int)($rc->fetch_assoc()['c'] ?? 0); }

/* ---------- Similar pets (same species, available) ---------- */
$similar = [];
if(!empty($pet['species'])){
  $stmt = $conn->prepare("SELECT id,name,species,COALESCE(breed,'') AS breed,COALESCE(city,'') AS city,avatar
                          FROM addoption
                          WHERE status='available' AND species=? AND id<>?
                          ORDER BY id DESC
                          LIMIT 4");
  $stmt->bind_param('si',$pet['species'],$id);
  $stmt->execute();
  $similar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

/* ---------- Timeline step ---------- */
/*
  1 Apply
  2 Review by shelter
  3 Meet & home-check
  4 Decision
  5 Pickup/Adopted
*/
$currentStep = 0;
if ($pet['status']!=='available') {
  $currentStep = 5; // already adopted or pending on listing
} else {
  $currentStep = ($already>0) ? 2 : 1;
}
?>
<?php include __DIR__.'/includes/header.php'; ?>

<style>
:root{
  --primary:#F59E0B; --accent:#EF4444; --bg:#FFF7ED; --text:#1F2937;
  --ring:#f0e7da; --card:#fff; --muted:#6B7280; --radius:16px;
}
.page-wrap{background:var(--bg);color:var(--text)}
.pet-hero{display:grid;grid-template-columns:1fr 1.2fr;gap:24px}
@media(max-width:992px){.pet-hero{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--ring);border-radius:var(--radius);box-shadow:0 10px 28px rgba(0,0,0,.06)}
.badge-soft{background:#fff;border:1px solid var(--ring);border-radius:999px;padding:6px 10px;font-weight:600}
.keyfacts{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
@media(max-width:768px){.keyfacts{grid-template-columns:repeat(2,1fr)}}
.fact{background:#fff;border:1px solid var(--ring);border-radius:12px;padding:10px}
.fact small{display:block;color:var(--muted);font-weight:600}
.fact b{display:block;font-family:Montserrat,system-ui,sans-serif}
.timeline{display:flex;align-items:center;gap:12px;overflow:auto}
.step{display:flex;align-items:center;gap:10px}
.dot{width:34px;height:34px;border-radius:999px;border:2px solid #e9dfcd;display:grid;place-items:center;font-weight:800;color:#b45309;background:#fff}
.step.active .dot{border-color:#fbbf24;background:#fff7e0}
.step.done .dot{border-color:#34d399;background:#ecfdf5;color:#065f46}
.pipe{height:2px;flex:1;background:#e9dfcd}
.pipe.active{background:linear-gradient(90deg,#34d399,#fbbf24)}
.shelter-card{display:flex;gap:12px;align-items:center}
.shelter-logo{width:56px;height:56px;border-radius:14px;background:#fff4e2;display:grid;place-items:center;color:#b45309}
.similar-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
@media(max-width:992px){.similar-grid{grid-template-columns:repeat(2,1fr)}}
.pet-card{border:1px solid var(--ring);border-radius:16px;overflow:hidden;transition:.2s}
.pet-card:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(0,0,0,.09)}
.thumb{aspect-ratio:4/3;background:#f6f6f6}
.thumb img{width:100%;height:100%;object-fit:cover}
.btn{border-radius:12px}
.btn-primary{background:linear-gradient(90deg,#fbbf24,#f59e0b);border:none;color:#111}
.btn-outline{background:#fff;border:1px solid var(--ring)}
.note{color:var(--muted);font-size:.92rem}
</style>

<main class="page-wrap py-4">
  <div class="container">
    <!-- HERO -->
    <div class="pet-hero">
      <div class="card p-2">
        <img class="img-fluid rounded" src="<?=e(media_pet($pet['avatar']))?>" alt="<?=e($pet['name'])?>">
      </div>

      <div class="card p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between">
          <h1 class="h3 m-0"><?=e($pet['name'])?></h1>
          <span class="badge-soft"><?=e(ucfirst($pet['status']))?></span>
        </div>
        <div class="mt-2 note">
          <?=e($pet['species'])?><?php if($pet['breed']) echo ' • '.e($pet['breed']); ?>
          <?php if(!empty($pet['city'])) echo ' • '.e($pet['city']); ?>
          <?php if($postedOn) echo ' • Posted '.$postedOn; ?>
        </div>

        <!-- Key facts -->
        <div class="keyfacts mt-3">
          <div class="fact"><small>Species</small><b><?=e($pet['species'])?></b></div>
          <div class="fact"><small>Breed</small><b><?=e($pet['breed']?:'—')?></b></div>
          <div class="fact"><small>Age</small><b><?=e($pet['age']?:'—')?></b></div>
          <div class="fact"><small>Gender</small><b><?=e($pet['gender']?:'—')?></b></div>
          <div class="fact"><small>City</small><b><?=e($pet['city']?:'—')?></b></div>
          <div class="fact"><small>Applications</small><b><?=number_format($appliedCount)?></b></div>
        </div>

        <hr>

        <!-- Shelter -->
        <div class="shelter-card">
          <div class="shelter-logo"><i class="bi bi-shield-heart"></i></div>
          <div>
            <div class="fw-semibold">Shelter</div>
            <div class="note"><?=e($pet['shelter_name'] ?? 'Unknown')?></div>
          </div>
        </div>

        <!-- Actions -->
        <div class="mt-3 d-flex gap-2">
          <?php if($pet['status']!=='available'): ?>
            <div class="alert alert-warning w-100 m-0">This pet is not available for new applications.</div>
          <?php else: ?>
            <?php if(!$logged_in): ?>
              <a class="btn btn-primary" href="<?=BASE.'/login.php?next='.urlencode(BASE.'/pet.php?id='.$pet['id'].'#apply')?>"><i class="bi bi-box-arrow-in-right me-1"></i> Login to Apply</a>
            <?php elseif($role!=='owner'): ?>
              <div class="alert alert-info m-0">Only owners can apply for adoption.</div>
            <?php elseif($already>0): ?>
              <div class="alert alert-success m-0">You already have a pending application for this pet.</div>
            <?php else: ?>
              <a class="btn btn-primary" href="#apply"><i class="bi bi-check2-circle me-1"></i> Apply to Adopt</a>
              <a class="btn btn-outline" href="<?=BASE.'/adopt.php?species='.urlencode($pet['species'])?>"><i class="bi bi-search-heart me-1"></i> More like this</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- About -->
    <div class="row g-3 mt-3">
      <div class="col-lg-8">
        <div class="card p-3 p-md-4">
          <h2 class="h5">About this pet</h2>
          <p class="mb-0"><?=nl2br(e($pet['description'] ?: 'No description provided.'))?></p>
        </div>

        <!-- Adoption timeline -->
        <div class="card p-3 p-md-4 mt-3">
          <h2 class="h5 mb-3">Adoption Timeline</h2>
          <?php
            // Helper to mark steps
            $s = $currentStep;
            $cls = function($n,$s){
              if($s>$n) return 'done';
              if($s==$n) return 'active';
              return '';
            };
            $pipe = function($n,$s){
              return ($s>$n) ? 'pipe active' : 'pipe';
            };
          ?>
          <div class="timeline">
            <div class="step <?=$cls(1,$s)?>"><span class="dot">1</span><span>Apply</span></div>
            <div class="<?=$pipe(1,$s)?>"></div>
            <div class="step <?=$cls(2,$s)?>"><span class="dot">2</span><span>Review</span></div>
            <div class="<?=$pipe(2,$s)?>"></div>
            <div class="step <?=$cls(3,$s)?>"><span class="dot">3</span><span>Meet / Home-check</span></div>
            <div class="<?=$pipe(3,$s)?>"></div>
            <div class="step <?=$cls(4,$s)?>"><span class="dot">4</span><span>Decision</span></div>
            <div class="<?=$pipe(4,$s)?>"></div>
            <div class="step <?=$cls(5,$s)?>"><span class="dot">5</span><span>Pickup / Adopted</span></div>
          </div>
          <div class="note mt-2">
            Tip: Shelter aapse call / message se follow-up karega. Meet & greet ke baad final decision hota hai.
          </div>
        </div>

        <!-- Apply form -->
        <?php if($logged_in && $role==='owner' && $pet['status']==='available' && !$already): ?>
        <div id="apply" class="card p-3 p-md-4 mt-3">
          <h2 class="h5 mb-2">Adoption Application</h2>
          <form method="post" action="<?=BASE.'/actions/adopt-apply.php'?>">
            <input type="hidden" name="csrf" value="<?=$csrf?>">
            <input type="hidden" name="pet_id" value="<?=$pet['id']?>">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" required>
              </div>
              <div class="col-md-8">
                <label class="form-label">Message for shelter (optional)</label>
                <input class="form-control" name="message" placeholder="Anything the shelter should know?">
              </div>
              <div class="col-12">
                <label class="form-label">Experience / Home setup</label>
                <textarea class="form-control" name="experience" rows="3" placeholder="Past experience, other pets at home, yard, schedule, etc."></textarea>
              </div>
              <div class="col-12">
                <button class="btn btn-primary"><i class="bi bi-send-check me-1"></i> Submit Application</button>
              </div>
            </div>
          </form>
        </div>
        <?php endif; ?>
      </div>

      <!-- Side panel -->
      <div class="col-lg-4">
        <div class="card p-3 p-md-4">
          <h2 class="h6 mb-2">Why Adopt via FurShield?</h2>
          <ul class="mb-0 note">
            <li>Verified shelters & responsible process</li>
            <li>Clear steps, fast follow-ups</li>
            <li>Happy matches & success stories</li>
          </ul>
        </div>

        <?php if(!empty($similar)): ?>
        <div class="card p-3 p-md-4 mt-3">
          <h2 class="h6 mb-3">Similar Pets</h2>
          <div class="similar-grid">
            <?php foreach($similar as $sp): ?>
            <article class="pet-card">
              <a href="<?=BASE.'/pet.php?id='.$sp['id']?>">
                <div class="thumb"><img src="<?=e(media_pet($sp['avatar']))?>" alt="<?=e($sp['name'])?>"></div>
              </a>
              <div class="p-2">
                <div class="fw-semibold text-truncate" title="<?=e($sp['name'])?>"><?=e($sp['name'])?></div>
                <div class="note text-truncate"><?=e($sp['species'])?><?php if($sp['breed']) echo ' • '.e($sp['breed']); ?></div>
                <?php if(!empty($sp['city'])): ?><div class="note"><?=e($sp['city'])?></div><?php endif; ?>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__.'/includes/footer.php'; ?>
