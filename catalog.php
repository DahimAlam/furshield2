<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

if (!function_exists('col_exists')) {
  function col_exists(mysqli $c, string $t, string $col): bool {
    $t = $c->real_escape_string($t); $col = $c->real_escape_string($col);
    $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
    return $r && $r->num_rows>0;
  }
}
if (!function_exists('pickcol')) {
  function pickcol(mysqli $c, string $t, array $cands): ?string {
    foreach($cands as $x){ if(col_exists($c,$t,$x)) return $x; }
    return null;
  }
}
if (!function_exists('media')) {
  function media($rel, $folder){
    if(!$rel) return BASE.'/assets/placeholder/product.jpg';
    $rel = trim((string)$rel);
    if (str_starts_with($rel,'http://') || str_starts_with($rel,'https://')) return $rel;
    if ($rel[0]==='/') return $rel;
    if (str_starts_with($rel,'uploads/')) return BASE.'/'.ltrim($rel,'/');
    return BASE.'/uploads/'.$folder.'/'.$rel;
  }
}

$t='products';
$C_ID   = pickcol($conn,$t,['id','product_id']);
$C_NAME = pickcol($conn,$t,['name','title']);
$C_PRICE= pickcol($conn,$t,['price','amount','unit_price']);
$C_SALE = pickcol($conn,$t,['sale_price','discount_price','price_sale']);
$C_IMG  = pickcol($conn,$t,['image_path','image','cover','thumb']);
$C_IMG2 = pickcol($conn,$t,['hover_image','image_hover','image2','image_alt']);
$C_CAT  = pickcol($conn,$t,['category','cat','type','collection']);
$C_BR   = pickcol($conn,$t,['brand','manufacturer']);
$C_DESC = pickcol($conn,$t,['short_desc','description','details','detail']);
$C_ACT  = pickcol($conn,$t,['is_active','active','visible','enabled']);
$C_FEAT = pickcol($conn,$t,['featured','is_featured']);
$C_NEW  = pickcol($conn,$t,['created_at','created','added_at','createdon','created_on']);
$C_STK  = pickcol($conn,$t,['stock','qty','quantity','in_stock']);
$C_POP  = pickcol($conn,$t,['views','sold','orders']); 

$q      = trim($_GET['q'] ?? '');
$cat    = trim($_GET['category'] ?? '');
$minp   = $_GET['min'] ?? '';
$maxp   = $_GET['max'] ?? '';
$sort   = $_GET['sort'] ?? 'new';
$page   = max(1, (int)($_GET['p'] ?? 1));
$pp     = 20; 
$off    = ($page-1)*$pp;

$w = []; $types=''; $vals=[];
if ($C_ACT) { $w[]="`$C_ACT`=1"; }
if ($q !== '') {
  $like = '%'.$q.'%';
  $parts=[];
  if ($C_NAME) $parts[]="`$C_NAME` LIKE ?";
  if ($C_DESC) $parts[]="`$C_DESC` LIKE ?";
  if ($C_BR)   $parts[]="`$C_BR` LIKE ?";
  if ($C_CAT)  $parts[]="`$C_CAT` LIKE ?";
  if ($parts){
    $w[]='('.implode(' OR ',$parts).')';
    $rep = count($parts);
    $types.=str_repeat('s',$rep);
    for($i=0;$i<$rep;$i++) $vals[]=$like;
  }
}
if ($cat !== '' && $C_CAT){
  $w[]="`$C_CAT`=?";
  $types.='s'; $vals[]=$cat;
}
if ($C_PRICE){
  if ($minp !== '' && is_numeric($minp)){ $w[]="`$C_PRICE`>=?"; $types.='d'; $vals[]=(float)$minp; }
  if ($maxp !== '' && is_numeric($maxp)){ $w[]="`$C_PRICE`<=?"; $types.='d'; $vals[]=(float)$maxp; }
}
$where = $w ? ('WHERE '.implode(' AND ',$w)) : '';

/* ---------- sort ---------- */
$ob = '';
switch ($sort) {
  case 'price_asc':  $ob = $C_PRICE ? "ORDER BY `$C_PRICE` ASC" : "ORDER BY $C_ID DESC"; break;
  case 'price_desc': $ob = $C_PRICE ? "ORDER BY `$C_PRICE` DESC" : "ORDER BY $C_ID DESC"; break;
  case 'popular':    $ob = $C_POP ? "ORDER BY `$C_POP` DESC, $C_ID DESC" : ($C_FEAT ? "ORDER BY `$C_FEAT` DESC, $C_ID DESC" : "ORDER BY $C_ID DESC"); break;
  case 'new':
  default:           $ob = $C_NEW ? "ORDER BY `$C_NEW` DESC" : "ORDER BY $C_ID DESC"; break;
}

/* ---------- count ---------- */
$sqlCount = "SELECT COUNT(*) c FROM `$t` $where";
$stc = $conn->prepare($sqlCount);
if($types) $stc->bind_param($types, ...$vals);
$stc->execute(); $res=$stc->get_result(); $total = (int)($res->fetch_assoc()['c'] ?? 0); $stc->close();
$pages = max(1, (int)ceil($total/$pp));

/* ---------- fetch ---------- */
$select = [];
$select[] = $C_ID ? "`$C_ID` AS id" : "id";
$select[] = $C_NAME ? "`$C_NAME` AS name" : "name";
$select[] = $C_PRICE? "`$C_PRICE` AS price" : "NULL AS price";
if ($C_SALE) $select[] = "`$C_SALE` AS sale_price";
$select[] = $C_IMG  ? "`$C_IMG` AS image" : "'' AS image";
$select[] = $C_IMG2 ? "`$C_IMG2` AS hover_image" : "'' AS hover_image";
if ($C_CAT)  $select[]="`$C_CAT` AS category";
if ($C_BR)   $select[]="`$C_BR` AS brand";
if ($C_DESC) $select[]="`$C_DESC` AS description";
if ($C_FEAT) $select[]="`$C_FEAT` AS featured";
if ($C_STK)  $select[]="`$C_STK` AS stock";
if ($C_NEW)  $select[]="`$C_NEW` AS created_at";

$sql = "SELECT ".implode(', ',$select)." FROM `$t` $where $ob LIMIT ? OFFSET ?";
$st = $conn->prepare($sql);
if ($types){
  $types2 = $types.'ii'; $vals2 = $vals; $vals2[] = $pp; $vals2[] = $off;
  $st->bind_param($types2, ...$vals2);
} else {
  $st->bind_param('ii', $pp, $off);
}
$st->execute(); $items = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

/* ---------- categories for filter ---------- */
$cats = [];
if ($C_CAT){
  $rs = $conn->query("SELECT DISTINCT `$C_CAT` AS c FROM `$t` ".($C_ACT? "WHERE `$C_ACT`=1 " : "")."ORDER BY c");
  if($rs){ while($r=$rs->fetch_assoc()){ if($r['c']!=='') $cats[]=$r['c']; } }
}

/* ---------- small helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$mk = function(array $patch){
  $q = array_merge($_GET, $patch);
  foreach($q as $k=>$v){ if($v===null || $v==='') unset($q[$k]); }
  $uri = strtok($_SERVER['REQUEST_URI'],'?');
  return $uri.(count($q)?('?'.http_build_query($q)):'');
};

include __DIR__ . '/includes/header.php';
?>
<style>
:root{
  --primary:#F59E0B; --accent:#EF4444; --bg:#FFF7ED; --text:#1F2937; --card:#fff;
  --radius:18px; --shadow:0 10px 30px rgba(0,0,0,.08);
}
.container, .container-fluid{max-width:100%; padding-left:clamp(12px,2.4vw,32px); padding-right:clamp(12px,2.4vw,32px)}
.row{ --bs-gutter-x: 1.2rem }

.catalog-hero{
  background:linear-gradient(180deg,#fff, rgba(255,247,237,.65));
  border-bottom:1px solid #f1e6d7;
}
.ch-badge{ background:#fff; border:1px solid #efe6d6; border-radius:999px; padding:.35rem .7rem; font-weight:700 }
.ch-title{ font-family:Montserrat, Poppins, sans-serif; font-weight:800; letter-spacing:.1px }

.filters{
  background:#fff; border:1px solid #eee; border-radius:16px; box-shadow:var(--shadow); padding:12px;
}
.filters .form-select, .filters .form-control{
  border-radius:12px; border:1px solid #e8e8e8; background:#fff
}

.product-card{
  border:1px solid #eee; border-radius:22px; overflow:hidden; background:#fff; box-shadow:var(--shadow);
  transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease;
}
.product-card:hover{ transform:translateY(-6px); box-shadow:0 20px 60px rgba(0,0,0,.14); border-color:#e9e3d6 }
.product-thumb{ position:relative; overflow:hidden; }
.product-thumb img{ width:100%; height:240px; object-fit:cover; display:block; transition:opacity .3s ease, transform .35s ease }
.product-thumb .hover{ position:absolute; inset:0; opacity:0 }
.product-card:hover .main{ transform:scale(1.05); opacity:0 }
.product-card:hover .hover{ opacity:1 }

.badges{ position:absolute; top:12px; left:12px; display:flex; gap:8px; }
.badges .pill{ background:rgba(255,255,255,.9); border:1px solid #eee; border-radius:999px; padding:.25rem .55rem; font-weight:700; font-size:.75rem }

.price{
  font-weight:800; letter-spacing:.2px;
}
.price del{ color:#9ca3af; font-weight:600; margin-left:6px }
.card-actions .btn{ border-radius:12px }

.pagination .page-link{ border-radius:10px; border:1px solid #eee }
.pagination .page-item.active .page-link{ background:var(--primary); border-color:var(--primary); color:#111 }
</style>

<div id="fsLoader" class="fs-loader is-hidden" aria-live="polite" aria-busy="true">
  <div class="fs-bar" id="fsLoaderBar"></div>
  <div class="fs-card">
    <div class="fs-logo"><i class="bi bi-shield-heart"></i></div>
    <div class="fs-brand">FurShield</div>
    <div class="fs-spin"><div class="fs-ring"></div></div>
    <div class="fs-sub">loading…</div>
  </div>
</div>

<main style="background:var(--bg); color:var(--text)">
  <section class="catalog-hero py-4">
    <div class="container-fluid">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="ch-badge">Catalog</span>
            <span class="text-muted">Showing <?php echo number_format(count($items)); ?> of <?php echo number_format($total); ?> items</span>
          </div>
          <h1 class="h3 ch-title m-0">Browse Products</h1>
        </div>
        <form class="d-flex align-items-center gap-2" method="get">
          <input type="text" name="q" value="<?php echo h($q); ?>" class="form-control" placeholder="Search products..." />
          <button class="btn btn-primary" style="background:var(--primary); border:none">Search</button>
          <?php if($q!=='' || $cat!=='' || $minp!=='' || $maxp!=='' || $sort!=='new'){ ?>
            <a href="<?php echo h($mk(['q'=>null,'category'=>null,'min'=>null,'max'=>null,'sort'=>null,'p'=>null])); ?>" class="btn btn-outline-secondary">Reset</a>
          <?php } ?>
        </form>
      </div>
    </div>
  </section>

  <section class="py-3">
    <div class="container-fluid">
      <div class="row g-3">
        <div class="col-12 col-lg-3">
          <div class="filters">
            <div class="mb-3">
              <label class="form-label fw-semibold">Category</label>
              <select class="form-select" onchange="location.href=this.value">
                <option value="<?php echo h($mk(['category'=>null,'p'=>null])); ?>">All</option>
                <?php foreach($cats as $c): ?>
                  <option value="<?php echo h($mk(['category'=>$c,'p'=>null])); ?>" <?php echo ($cat===$c?'selected':''); ?>>
                    <?php echo h($c); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <?php if($C_PRICE): ?>
            <div class="mb-3">
              <form method="get" class="row g-2">
                <input type="hidden" name="q" value="<?php echo h($q); ?>">
                <input type="hidden" name="category" value="<?php echo h($cat); ?>">
                <input type="hidden" name="sort" value="<?php echo h($sort); ?>">
                <div class="col-6">
                  <label class="form-label">Min</label>
                  <input type="number" step="0.01" name="min" value="<?php echo h($minp); ?>" class="form-control">
                </div>
                <div class="col-6">
                  <label class="form-label">Max</label>
                  <input type="number" step="0.01" name="max" value="<?php echo h($maxp); ?>" class="form-control">
                </div>
                <div class="col-12">
                  <button class="btn btn-outline-secondary w-100">Apply Price</button>
                </div>
              </form>
            </div>
            <?php endif; ?>

            <div class="mb-2">
              <label class="form-label fw-semibold">Sort by</label>
              <select class="form-select" onchange="location.href=this.value">
                <option value="<?php echo h($mk(['sort'=>'new','p'=>null])); ?>" <?php echo ($sort==='new'?'selected':''); ?>>Newest</option>
                <option value="<?php echo h($mk(['sort'=>'popular','p'=>null])); ?>" <?php echo ($sort==='popular'?'selected':''); ?>>Popular</option>
                <option value="<?php echo h($mk(['sort'=>'price_asc','p'=>null])); ?>" <?php echo ($sort==='price_asc'?'selected':''); ?>>Price: Low → High</option>
                <option value="<?php echo h($mk(['sort'=>'price_desc','p'=>null])); ?>" <?php echo ($sort==='price_desc'?'selected':''); ?>>Price: High → Low</option>
              </select>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-9">
          <?php if(!$items){ ?>
            <div class="alert alert-warning">No products found.</div>
          <?php } ?>

          <div class="row g-4 row-cols-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5">
            <?php foreach($items as $it): 
              $pid   = (int)($it['id'] ?? 0);
              $name  = (string)($it['name'] ?? '');
              $price = (float)($it['price'] ?? 0);
              $sale  = isset($it['sale_price']) && $it['sale_price']!==null ? (float)$it['sale_price'] : null;
              $img   = media($it['image'] ?? '', 'products');
              $hover = media($it['hover_image'] ?? '', 'products');
              $isFeat= !empty($it['featured']);
              $instk = !isset($it['stock']) || (is_numeric($it['stock']) ? ((int)$it['stock']>0) : (strtolower((string)$it['stock'])!=='0'));
            ?>
            <div class="col">
              <div class="product-card h-100 d-flex flex-column">
                <div class="product-thumb">
                  <div class="badges">
                    <?php if($isFeat){ ?><span class="pill">Featured</span><?php } ?>
                    <?php if(!$instk){ ?><span class="pill" style="background:#ffe6e6;border-color:#ffd1d1;color:#b91c1c">Out of stock</span><?php } ?>
                  </div>
                  <img src="<?php echo h($img); ?>" alt="<?php echo h($name); ?>" class="main" loading="lazy">
                  <?php if (!empty($it['hover_image'])) { ?>
                    <img src="<?php echo h($hover); ?>" alt="" class="hover" loading="lazy">
                  <?php } ?>
                </div>
                <div class="p-3 d-flex flex-column">
                  <div class="fw-semibold text-truncate" title="<?php echo h($name); ?>"><?php echo h($name); ?></div>
                  <div class="price mt-1 mb-3">
                    <?php if($sale && $sale>0 && $sale<$price){ ?>
                      $<?php echo number_format($sale,2); ?><del>$<?php echo number_format($price,2); ?></del>
                    <?php } else { ?>
                      $<?php echo number_format($price,2); ?>
                    <?php } ?>
                  </div>
                  <div class="mt-auto card-actions d-flex align-items-center gap-2">
                    <a href="<?php echo BASE.'/product-details.php?id='.$pid; ?>" class="btn btn-sm btn-outline-secondary flex-grow-1">View</a>
                    <a href="<?php echo BASE.'/actions/cart-add.php?id='.$pid; ?>" class="btn btn-sm btn-primary flex-grow-1" style="background:var(--primary);border:none" <?php echo $instk?'':'aria-disabled="true" tabindex="-1" class="btn btn-sm btn-secondary disabled"'; ?>>Add to Cart</a>
                    <a href="<?php echo BASE.'/actions/wishlist-add.php?id='.$pid; ?>" class="btn btn-sm btn-outline-danger" title="Wishlist"><i class="bi bi-heart"></i></a>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <?php if($pages>1): ?>
          <nav class="mt-4">
            <ul class="pagination justify-content-end">
              <?php
              $prev = max(1,$page-1); $next = min($pages,$page+1);
              ?>
              <li class="page-item <?php echo $page<=1?'disabled':''; ?>">
                <a class="page-link" href="<?php echo h($mk(['p'=>$prev])); ?>">Prev</a>
              </li>
              <?php
                $start = max(1, $page-2);
                $end   = min($pages, $page+2);
                if ($start>1){
                  echo '<li class="page-item"><a class="page-link" href="'.h($mk(['p'=>1])).'">1</a></li>';
                  if ($start>2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                }
                for($i=$start;$i<=$end;$i++){
                  $active = $i===$page ? ' active' : '';
                  echo '<li class="page-item'.$active.'"><a class="page-link" href="'.h($mk(['p'=>$i])).'">'.$i.'</a></li>';
                }
                if ($end<$pages){
                  if ($end<$pages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                  echo '<li class="page-item"><a class="page-link" href="'.h($mk(['p'=>$pages])).'">'.$pages.'</a></li>';
                }
              ?>
              <li class="page-item <?php echo $page>=$pages?'disabled':''; ?>">
                <a class="page-link" href="<?php echo h($mk(['p'=>$next])); ?>">Next</a>
              </li>
            </ul>
          </nav>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
