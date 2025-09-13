<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__."/includes/db.php";
require_once __DIR__."/includes/auth.php";
if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

/* ---------- helpers (prefixed to avoid redeclare) ---------- */
if (!function_exists('fs_search_rows')) {
  function fs_search_rows(mysqli $c, string $sql, array $params=[], string $types=''){
    if (!$params){ $r=$c->query($sql); return $r? $r->fetch_all(MYSQLI_ASSOC):[]; }
    $st=$c->prepare($sql);
    if ($types==='') $types=str_repeat('s', count($params));
    $st->bind_param($types, ...$params);
    $st->execute(); $r=$st->get_result();
    return $r? $r->fetch_all(MYSQLI_ASSOC):[];
  }
}
if (!function_exists('fs_search_hascol')) {
  function fs_search_hascol(mysqli $c, string $t, string $col): bool {
    $t=$c->real_escape_string($t); $col=$c->real_escape_string($col);
    $q=$c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
    return $q && $q->num_rows>0;
  }
}
if (!function_exists('fs_search_firstcol')) {
  function fs_search_firstcol(mysqli $c, string $t, array $cands): ?string {
    foreach ($cands as $x){ if (fs_search_hascol($c,$t,$x)) return $x; }
    return null;
  }
}
if (!function_exists('fs_e')) {
  function fs_e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('fs_media')) {
  function fs_media($rel, string $folder, string $placeholder){
    $rel=trim((string)$rel);
    if ($rel==='') return $placeholder;
    if (str_starts_with($rel,'http://') || str_starts_with($rel,'https://')) return $rel;
    if ($rel[0]==='/') return $rel;
    if (str_starts_with($rel,'uploads/')) return BASE.'/'.ltrim($rel,'/');
    return BASE.'/uploads/'.$folder.'/'.$rel;
  }
}

/* ---------- input ---------- */
$q = trim($_GET['q'] ?? '');
$like = "%{$q}%";

/* ---------- columns that may vary ---------- */
$ad_img  = fs_search_firstcol($conn,'addoption',['avatar','image','image_path','photo','thumbnail','cover']) ?? 'avatar';
$ad_city = fs_search_hascol($conn,'addoption','city') ? 'city' : null;
$ad_spot = fs_search_hascol($conn,'addoption','spotlight') ? 'spotlight' : null;

$pr_img  = fs_search_firstcol($conn,'products',['image_path','image','cover']) ?? 'image';

$pets=$vets=$products=[];
$minLen = 2;

if (mb_strlen($q, 'UTF-8') >= $minLen) {
  /* Pets from addoption (shelter pets only, available) */
  $order = $ad_spot ? "ORDER BY a.`$ad_spot` DESC, a.id DESC" : "ORDER BY a.id DESC";
  $citySel = $ad_city ? ", COALESCE(a.`$ad_city`,'') AS city" : ", '' AS city";
  $sqlPets = "
    SELECT a.id, a.name, a.species, COALESCE(a.breed,'') AS breed, a.`$ad_img` AS image {$citySel}
    FROM addoption a
    JOIN users u ON u.id = a.shelter_id AND u.role='shelter'
    WHERE a.status='available' AND (a.name LIKE ? OR a.species LIKE ? OR a.breed LIKE ?)
    {$order}
    LIMIT 8
  ";
  $pets = fs_search_rows($conn, $sqlPets, [$like,$like,$like], 'sss');

  /* Vets */
  $sqlVets = "
    SELECT u.id, u.name, COALESCE(v.specialization,'Veterinarian') AS specialization
    FROM users u
    LEFT JOIN vets v ON v.user_id=u.id
    WHERE u.role='vet' AND (u.name LIKE ? OR v.specialization LIKE ?)
    ORDER BY u.id DESC
    LIMIT 8
  ";
  $vets = fs_search_rows($conn, $sqlVets, [$like,$like], 'ss');

  /* Products */
  $sqlProd = "
    SELECT id, name, price, `$pr_img` AS image
    FROM products
    WHERE name LIKE ?
    ORDER BY id DESC
    LIMIT 8
  ";
  $products = fs_search_rows($conn, $sqlProd, [$like], 's');
}

include __DIR__."/includes/header.php";
?>

<div class="container">
  <div class="d-flex align-items-end justify-content-between mb-3">
    <h2 class="h5 m-0">Search Results <?php if($q!=='') echo 'for “'.fs_e($q).'”'; ?></h2>
    <form action="" method="get" class="d-flex gap-2">
      <input name="q" class="form-control" value="<?php echo fs_e($q); ?>" placeholder="Search pets, vets, products…" />
      <button class="btn btn-primary">Search</button>
    </form>
  </div>

  <?php if ($q==='' || mb_strlen($q,'UTF-8')<$minLen): ?>
    <div class="alert alert-warning">Type at least <?php echo $minLen; ?> characters to search.</div>
  <?php else: ?>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card card-soft p-3 h-100">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="m-0">Adoptable Pets</h6>
          <span class="badge bg-warning-subtle text-dark"><?php echo count($pets); ?></span>
        </div>
        <?php if(!$pets): ?>
          <div class="text-muted small">No pets found.</div>
        <?php else: ?>
          <?php foreach($pets as $p): ?>
            <?php
              $img = fs_media($p['image'],'pets', BASE.'/assets/placeholder/pet.jpg');
              $meta = trim(($p['species']??'').' • '.($p['breed']??''), ' •');
              $city = $p['city'] ?? '';
            ?>
            <a class="d-flex align-items-center gap-2 text-decoration-none mb-2" href="<?php echo BASE; ?>/pet.php?id=<?php echo (int)$p['id']; ?>">
              <img src="<?php echo fs_e($img); ?>"
                   alt=""
                   class="rounded"
                   style="width:56px;height:56px;object-fit:cover;background:#f2f2f2"
                   onerror="this.src='<?php echo BASE; ?>/assets/placeholder/pet.jpg'">
              <div class="flex-grow-1">
                <div class="small fw-semibold text-truncate"><?php echo fs_e($p['name']); ?></div>
                <div class="small text-muted text-truncate"><?php echo fs_e($meta); ?></div>
                <?php if($city!==''): ?>
                  <div class="xsmall text-muted"><i class="bi bi-geo-alt me-1"></i><?php echo fs_e($city); ?></div>
                <?php endif; ?>
              </div>
              <i class="bi bi-arrow-right-short text-muted"></i>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card card-soft p-3 h-100">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="m-0">Vets</h6>
          <span class="badge bg-warning-subtle text-dark"><?php echo count($vets); ?></span>
        </div>
        <?php if(!$vets): ?>
          <div class="text-muted small">No vets found.</div>
        <?php else: ?>
          <?php foreach($vets as $v): ?>
            <a class="d-flex align-items-center gap-2 text-decoration-none mb-2" href="<?php echo BASE; ?>/vet-profile.php?id=<?php echo (int)$v['id']; ?>">
              <img src="<?php echo BASE; ?>/assets/img/vet.png"
                   alt=""
                   class="rounded"
                   style="width:56px;height:56px;object-fit:cover;background:#f2f2f2">
              <div class="flex-grow-1">
                <div class="small fw-semibold text-truncate"><?php echo fs_e($v['name']); ?></div>
                <div class="small text-muted text-truncate"><?php echo fs_e($v['specialization'] ?? 'Veterinarian'); ?></div>
              </div>
              <i class="bi bi-arrow-right-short text-muted"></i>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card card-soft p-3 h-100">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="m-0">Products</h6>
          <span class="badge bg-warning-subtle text-dark"><?php echo count($products); ?></span>
        </div>
        <?php if(!$products): ?>
          <div class="text-muted small">No products found.</div>
        <?php else: ?>
          <?php foreach($products as $p): ?>
            <?php $pimg = fs_media($p['image'],'products', BASE.'/assets/placeholder/product.jpg'); ?>
            <a class="d-flex align-items-center gap-2 text-decoration-none mb-2" href="<?php echo BASE; ?>/product-detail.php?id=<?php echo (int)$p['id']; ?>">
              <img src="<?php echo fs_e($pimg); ?>"
                   alt=""
                   class="rounded"
                   style="width:56px;height:56px;object-fit:cover;background:#f2f2f2"
                   onerror="this.src='<?php echo BASE; ?>/assets/placeholder/product.jpg'">
              <div class="flex-grow-1">
                <div class="small fw-semibold text-truncate"><?php echo fs_e($p['name']); ?></div>
                <div class="small text-muted">$<?php echo number_format((float)$p['price'], 2); ?></div>
              </div>
              <i class="bi bi-arrow-right-short text-muted"></i>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__."/includes/footer.php"; ?>
