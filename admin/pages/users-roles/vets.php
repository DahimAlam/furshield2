<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_role('admin');

if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

function refs(&$a){ $r=[]; foreach($a as $k=>$_){ $r[$k]=&$a[$k]; } return $r; }
function cols(mysqli $c,$t){ $t=$c->real_escape_string($t); $r=$c->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'"); $o=[]; while($r && ($x=$r->fetch_row())) $o[]=$x[0]; return $o; }
function pick($arr,$cands){ foreach($cands as $x){ if(in_array($x,$arr,true)) return $x; } return null; }

$uCols = cols($conn,'users');
$vCols = cols($conn,'vets');

$U_ID = pick($uCols,['id','user_id']);
$U_NAME = pick($uCols,['name','full_name']);
$U_MAIL = pick($uCols,['email','mail']);
$U_ROLE = pick($uCols,['role','user_role']);
$U_STAT = pick($uCols,['status','state']);
$U_CREATED = pick($uCols,['created_at','created','signup_at']);
$U_CITY = pick($uCols,['city']);
$U_COUNTRY = pick($uCols,['country']);

$V_USER = pick($vCols,['user_id','uid','userId']);
$V_SPEC = pick($vCols,['specialization','expertise','speciality','skills']);
$V_EXP  = pick($vCols,['experience_years','experience','years_experience','exp_years']);
$V_ADDR = pick($vCols,['clinic_address','address','clinic_addr','clinic']);
$V_CITY = pick($vCols,['city']);
$V_COUNTRY = pick($vCols,['country']);
$V_AVATAR = pick($vCols,['profile_image','avatar','photo','image']);
$V_CNIC = pick($vCols,['cnic_image','cnic_file','id_card_image']);
$V_LIC  = pick($vCols,['license_image','license_file','license_doc','license_pic','license']);
$V_STATUS = pick($vCols,['status','verification_status','state']);
$V_CREATED = pick($vCols,['created_at','created']);

if(!$U_ID || !$V_USER || !$U_NAME || !$U_MAIL){ http_response_code(500); exit; }

$status = $_GET['status'] ?? 'active';
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1,(int)($_GET['p'] ?? 1));
$limit = 25;
$offset = ($page-1)*$limit;

$conds = [];
$types = '';
$args = [];

if ($U_ROLE) $conds[] = "u.`$U_ROLE`='vet'";
if ($status !== 'any') {
  if ($U_STAT) { $conds[] = "u.`$U_STAT`=?"; $types.='s'; $args[]=$status; }
  elseif ($V_STATUS) { $conds[] = "v.`$V_STATUS`=?"; $types.='s'; $args[]=$status; }
}
if ($q !== '') {
  $like = '%'.mb_strtolower($q).'%';
  $s = [];
  $s[] = "LOWER(u.`$U_NAME`) LIKE ?";
  $s[] = "LOWER(u.`$U_MAIL`) LIKE ?";
  if ($V_SPEC) $s[] = "LOWER(v.`$V_SPEC`) LIKE ?";
  if ($V_CITY || $U_CITY) $s[] = "LOWER(".($V_CITY?"v.`$V_CITY`":"u.`$U_CITY`").") LIKE ?";
  if ($V_COUNTRY || $U_COUNTRY) $s[] = "LOWER(".($V_COUNTRY?"v.`$V_COUNTRY`":"u.`$U_COUNTRY`").") LIKE ?";
  $conds[] = '('.implode(' OR ',$s).')';
  $types .= str_repeat('s', count($s));
  for($i=0;$i<count($s);$i++) $args[]=$like;
}
$where = $conds ? 'WHERE '.implode(' AND ',$conds) : '';

$total = 0;
$sql = "SELECT COUNT(*) FROM users u JOIN vets v ON v.`$V_USER` = u.`$U_ID` $where";
$st = $conn->prepare($sql);
if ($types){ $b=refs($args); $st->bind_param($types, ...$b); }
$st->execute(); $st->bind_result($total); $st->fetch(); $st->close();

$cityExpr = $V_CITY ? "v.`$V_CITY`" : ($U_CITY ? "u.`$U_CITY`" : "NULL");
$countryExpr = $V_COUNTRY ? "v.`$V_COUNTRY`" : ($U_COUNTRY ? "u.`$U_COUNTRY`" : "NULL");
$specExpr = $V_SPEC ? "v.`$V_SPEC`" : "NULL";
$expExpr = $V_EXP ? "v.`$V_EXP`" : "0";
$addrExpr = $V_ADDR ? "v.`$V_ADDR`" : "''";
$avatarExpr = $V_AVATAR ? "v.`$V_AVATAR`" : "NULL";
$cnicExpr = $V_CNIC ? "v.`$V_CNIC`" : "NULL";
$licExpr = $V_LIC ? "v.`$V_LIC`" : "NULL";
$statExpr = $U_STAT ? "u.`$U_STAT`" : ($V_STATUS ? "v.`$V_STATUS`" : "'active'");
$createdExpr = $U_CREATED ? "u.`$U_CREATED`" : ($V_CREATED ? "v.`$V_CREATED`" : "NOW()");

$vets = [];
$sql = "
  SELECT
    u.`$U_ID` AS id,
    u.`$U_NAME` AS name,
    u.`$U_MAIL` AS email,
    $createdExpr AS created_at,
    $statExpr AS status,
    $specExpr AS specialization,
    $expExpr AS experience_years,
    $addrExpr AS clinic_address,
    $cityExpr AS city,
    $countryExpr AS country,
    $avatarExpr AS profile_image,
    $cnicExpr AS cnic_image,
    $licExpr AS license_image
  FROM users u
  JOIN vets v ON v.`$V_USER` = u.`$U_ID`
  $where
  ORDER BY $createdExpr DESC
  LIMIT ? OFFSET ?
";
$types2 = $types.'ii';
$args2 = $args; $args2[]=$limit; $args2[]=$offset;
$st = $conn->prepare($sql);
$b2 = refs($args2);
$st->bind_param($types2, ...$b2);
$st->execute();
$res = $st->get_result();
while($r=$res->fetch_assoc()) $vets[]=$r;
$st->close();

$pages = max(1,(int)ceil($total/$limit));

$root = rtrim(str_replace('\\','/', realpath(__DIR__.'/../../../')), '/').'/';

function resolveMedia($rel,$prefer){
  global $root;
  $rel = trim((string)$rel);
  if($rel==='') return [null,null];
  $clean = ltrim(str_replace('\\','/',$rel),'/');
  if (strpos($clean,'furshield/')===0) $clean = substr($clean,10);
  $cands = [$clean];
  if ($prefer==='avatar') {
    $cands[] = 'uploads/avatars/'.$clean;
    $cands[] = 'uploads/'.$clean;
    $cands[] = 'uploads/records/'.$clean;
  } else {
    $cands[] = 'uploads/records/'.$clean;
    $cands[] = 'uploads/'.$clean;
    $cands[] = 'uploads/avatars/'.$clean;
  }
  foreach($cands as $c){
    $fs = $root.$c;
    if (is_file($fs)) return [rtrim(BASE,'/').'/'.ltrim($c,'/'), $fs];
  }
  return [null,null];
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="admin-main p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold text-dark m-0">Vets</h1>
    <form class="d-flex gap-2" method="get">
      <select name="status" class="form-select">
        <?php foreach(['active','pending','suspended','rejected','any'] as $s): ?>
          <option value="<?php echo $s; ?>"<?php if(($status?:'')===$s) echo ' selected'; ?>><?php echo ucfirst($s); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="q" class="form-control" placeholder="Search name, email, specialization, city…" value="<?php echo htmlspecialchars($q); ?>"/>
      <button class="btn btn-primary">Apply</button>
    </form>
  </div>

  <div class="card shadow-sm rounded-4 border-0">
    <div class="card-body p-4">
      <?php if(!$vets): ?>
        <div class="alert alert-info mb-0">No vets found.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle table-hover">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Profile</th>
                <th>Name</th>
                <th>Email</th>
                <th>Specialization</th>
                <th>Experience</th>
                <th>Clinic</th>
                <th>City</th>
                <th>Country</th>
                <th>CNIC</th>
                <th>License</th>
                <th>Status</th>
                <th>Created At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($vets as $i=>$v): ?>
              <?php [$avatarUrl,] = resolveMedia($v['profile_image'],'avatar'); ?>
              <?php [$cnicUrl,]  = resolveMedia($v['cnic_image'],'doc'); ?>
              <?php [$licUrl,]   = resolveMedia($v['license_image'],'doc'); ?>
              <tr>
                <td><?php echo ($page-1)*$limit + $i + 1; ?></td>
                <td>
                  <?php if($avatarUrl): ?>
                    <img src="<?php echo $avatarUrl; ?>" alt="Profile" class="rounded-circle" width="45" height="45">
                  <?php else: ?>
                    <span class="badge bg-secondary">No Image</span>
                  <?php endif; ?>
                </td>
                <td class="fw-semibold"><?php echo htmlspecialchars($v['name']); ?></td>
                <td><?php echo htmlspecialchars($v['email']); ?></td>
                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($v['specialization'] ?: '—'); ?></span></td>
                <td><?php echo (int)$v['experience_years']; ?> yrs</td>
                <td><?php echo htmlspecialchars($v['clinic_address'] ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($v['city'] ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($v['country'] ?: '—'); ?></td>
                <td>
                  <?php if($cnicUrl): ?>
                    <a href="<?php echo $cnicUrl; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                  <?php else: ?>
                    <span class="badge bg-secondary">Not Uploaded</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if($licUrl): ?>
                    <a href="<?php echo $licUrl; ?>" target="_blank" class="btn btn-sm btn-outline-dark">View</a>
                  <?php else: ?>
                    <span class="badge bg-secondary">Not Uploaded</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if(($v['status'] ?? '') === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($v['status'] ?? '—'); ?></span>
                  <?php endif; ?>
                </td>
                <td class="nowrap"><?php echo $v['created_at'] ? date("d M Y", strtotime($v['created_at'])) : '—'; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if($pages>1): ?>
          <div class="d-flex justify-content-end gap-2 mt-3">
            <?php $mk=function($p)use($status,$q){return '?status='.urlencode($status).'&q='.urlencode($q).'&p='.$p;}; ?>
            <a class="btn btn-outline-primary btn-sm<?php if($page<=1) echo ' disabled'; ?>" href="<?php echo $mk(max(1,$page-1)); ?>">Prev</a>
            <span class="btn btn-light btn-sm disabled">Page <?php echo $page; ?> / <?php echo $pages; ?></span>
            <a class="btn btn-outline-primary btn-sm<?php if($page>=$pages) echo ' disabled'; ?>" href="<?php echo $mk(min($pages,$page+1)); ?>">Next</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
