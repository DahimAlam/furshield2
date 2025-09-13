<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/includes/db.php';
if(!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

function t_exists($c,$t){$t=$c->real_escape_string($t);$r=$c->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}'");return $r&&$r->num_rows>0;}
function hascol($c,$t,$col){$t=$c->real_escape_string($t);$col=$c->real_escape_string($col);$r=$c->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='{$t}' AND column_name='{$col}'");return $r&&$r->num_rows>0;}
function pick($c,$t,$opts){foreach($opts as $x){if(hascol($c,$t,$x))return $x;}return null;}
function media($p,$folder){$p=trim((string)$p);if($p==='')return null;if(strpos($p,'http')===0)return $p;$p=ltrim(str_replace('\\','/',$p),'/');if(strpos($p,'uploads/')===0)return BASE.'/'.$p;return BASE.'/uploads/'.$folder.'/'.$p;}
function rowx($c,$sql,$types='',$args=[]){$st=$c->prepare($sql);if($types){$refs=[];foreach($args as $k=>$_){$refs[$k]=&$args[$k];}$st->bind_param($types,...$refs);} $st->execute();$r=$st->get_result()->fetch_assoc();$st->close();return $r;}
function rowsx($c,$sql,$types='',$args=[]){$st=$c->prepare($sql);if($types){$refs=[];foreach($args as $k=>$_){$refs[$k]=&$args[$k];}$st->bind_param($types,...$refs);} $st->execute();$rs=$st->get_result();$out=[];while($x=$rs->fetch_assoc())$out[]=$x;$st->close();return $out;}

if(!t_exists($conn,'products')){http_response_code(404);exit('Not found');}

$P_ID   = pick($conn,'products',['id','product_id']);
$P_NAME = pick($conn,'products',['name','title']);
$P_PRICE= pick($conn,'products',['price','amount']);
$P_IMG  = pick($conn,'products',['image_path','image','cover']);
$P_DESC = pick($conn,'products',['description','body','summary','details','content']);
$P_STOCK= pick($conn,'products',['stock_qty','qty','stock']);
$P_ACTIVE = pick($conn,'products',['is_active','active','status']);
$P_SLUG = pick($conn,'products',['slug','permalink']);
$P_CAT = pick($conn,'products',['category_id','category','type','brand_id','brand']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

$selName=$P_NAME?("`$P_NAME`"):"'—'";
$selPrice=$P_PRICE?("`$P_PRICE`"):"0";
$selImg=$P_IMG?("`$P_IMG`"):"NULL";
$selDesc=$P_DESC?("`$P_DESC`"):"NULL";
$selStock=$P_STOCK?("`$P_STOCK`"):"NULL";
$selActive=$P_ACTIVE?("`$P_ACTIVE`"):"NULL";
$selSlug=$P_SLUG?("`$P_SLUG`"):"NULL";
$selCat=$P_CAT?("`$P_CAT`"):"NULL";

if($slug!=='' && $P_SLUG){
  $prod=rowx($conn,"SELECT `$P_ID` id,$selName name,$selPrice price,$selImg image,$selDesc description,$selStock stock,$selActive active,$selSlug slug,$selCat cat FROM products WHERE `$P_SLUG`=? LIMIT 1","s",[$slug]);
} elseif($id>0){
  $prod=rowx($conn,"SELECT `$P_ID` id,$selName name,$selPrice price,$selImg image,$selDesc description,$selStock stock,$selActive active,$selSlug slug,$selCat cat FROM products WHERE `$P_ID`=? LIMIT 1","i",[$id]);
} else {
  $prod=null;
}

if(!$prod){http_response_code(404);include __DIR__.'/includes/header.php';?>
<main class="container py-5"><div class="alert alert-warning">Product not found.</div></main>
<?php include __DIR__.'/includes/footer.php'; exit; }

$isActive=true;
if($P_ACTIVE!==null){
  if(in_array($P_ACTIVE,['is_active','active'],true)) $isActive=((int)$prod['active']===1);
  else $isActive=(strtolower((string)$prod['active'])==='active');
}
$inStock = $P_STOCK!==null ? ((int)$prod['stock']>0) : true;
$img=media($prod['image']??'','products');
$page_title=$prod['name'].' • FurShield';

include __DIR__.'/includes/header.php';
?>
<main class="py-5" style="background:var(--bg);color:var(--text)">
  <div class="container">
    <nav class="mb-3 small">
      <a href="<?php echo BASE; ?>" class="text-decoration-none">Home</a>
      <span class="mx-2">/</span>
      <a href="<?php echo BASE.'/catalog.php'; ?>" class="text-decoration-none">Products</a>
      <span class="mx-2">/</span>
      <span class="text-muted"><?php echo htmlspecialchars($prod['name']); ?></span>
    </nav>

    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card border-0 shadow" style="border-radius:var(--radius);overflow:hidden">
          <img src="<?php echo $img?:BASE.'/assets/img/placeholder.png'; ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" style="width:100%;height:480px;object-fit:cover">
        </div>
      </div>

      <div class="col-lg-6">
        <div class="sticky-top" style="top:90px">
          <h1 class="h3 mb-2"><?php echo htmlspecialchars($prod['name']); ?></h1>
          <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <div class="h3 m-0" style="color:var(--accent)">$<?php echo number_format((float)$prod['price'],2); ?></div>
            <?php if($P_STOCK!==null): ?>
              <?php if($inStock): ?>
                <span class="badge bg-success">In stock</span>
              <?php else: ?>
                <span class="badge bg-secondary">Out of stock</span>
              <?php endif; ?>
            <?php endif; ?>
            <?php if(!$isActive): ?><span class="badge bg-dark">Inactive</span><?php endif; ?>
          </div>

          <div class="d-flex align-items-center gap-2 mb-4">
            <div class="input-group" style="max-width:160px">
              <button class="btn btn-outline-secondary" type="button" id="qtyMinus">−</button>
              <input type="number" class="form-control text-center" id="qty" value="1" min="1" <?php echo $P_STOCK!==null && $inStock ? 'max="'.(int)$prod['stock'].'"' : ''; ?>>
              <button class="btn btn-outline-secondary" type="button" id="qtyPlus">+</button>
            </div>
            <span class="small text-muted"><?php echo $P_STOCK!==null ? ((int)$prod['stock'].' available') : ''; ?></span>
          </div>

          <div class="d-flex flex-wrap gap-2 mb-4">
            <form method="post" action="<?php echo BASE.'/actions/cart-add.php'; ?>" class="d-inline">
              <input type="hidden" name="id" value="<?php echo (int)$prod['id']; ?>">
              <input type="hidden" name="qty" id="qtyAddCart" value="1">
              <button class="btn btn-lg btn-warning" style="background:var(--primary);border:none" <?php echo (!$isActive||!$inStock)?'disabled':''; ?>>
                <i class="bi bi-bag-plus me-1"></i>Add to Cart
              </button>
            </form>

       

            <a class="btn btn-lg btn-outline-danger" href="<?php echo BASE.'/actions/wishlist-add.php?id='.(int)$prod['id']; ?>">
              <i class="bi bi-heart me-1"></i>Wishlist
            </a>
          </div>

          <div class="card border-0 shadow-sm" style="border-radius:var(--radius)">
            <div class="card-body">
              <h6 class="mb-2">Product details</h6>
              <div class="text-secondary mb-2"><?php echo nl2br(htmlspecialchars((string)$prod['description'])); ?></div>
              <div class="row small text-muted">
                <?php if($P_CAT && $prod['cat']!==''): ?><div class="col-6 mb-1">Category: <span class="text-dark"><?php echo htmlspecialchars($prod['cat']); ?></span></div><?php endif; ?>
                <?php if($P_SLUG && $prod['slug']!==''): ?><div class="col-6 mb-1">SKU/Slug: <span class="text-dark"><?php echo htmlspecialchars($prod['slug']); ?></span></div><?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php
      $relWhere=''; $types=''; $args=[];
      if($P_CAT && $prod['cat']!==''){ $relWhere="WHERE p.`$P_CAT`=? AND p.`$P_ID`<>?"; $types='si'; $args=[(string)$prod['cat'],(int)$prod['id']]; }
      else { $relWhere="WHERE p.`$P_ID`<>?"; $types='i'; $args=[(int)$prod['id']]; }
      if($P_ACTIVE){ if(in_array($P_ACTIVE,['is_active','active'],true)){$relWhere.=" AND p.`$P_ACTIVE`=1";} else {$relWhere.=" AND p.`$P_ACTIVE`='active'";} }
      $selNameR=$P_NAME?("p.`$P_NAME`"):"'—'";
      $selPriceR=$P_PRICE?("p.`$P_PRICE`"):"0";
      $selImgR=$P_IMG?("p.`$P_IMG`"):"NULL";
      $rel=rowsx($conn,"SELECT p.`$P_ID` id,$selNameR name,$selPriceR price,$selImgR image FROM products p $relWhere ORDER BY p.`$P_ID` DESC LIMIT 4",$types,$args);
    ?>

    <?php if($rel): ?>
    <hr class="my-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h5 m-0">You may also like</h2>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo BASE.'/catalog.php'; ?>">View all</a>
    </div>
    <div class="row g-4">
      <?php foreach($rel as $r): $rimg=media($r['image']??'','products'); ?>
      <div class="col-6 col-md-3">
        <div class="card h-100 shadow-sm" style="border-radius:var(--radius)">
          <img src="<?php echo $rimg?:BASE.'/assets/img/placeholder.png'; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($r['name']); ?>" style="height:200px;object-fit:cover">
          <div class="card-body d-flex flex-column">
            <h6 class="fw-semibold mb-1"><?php echo htmlspecialchars($r['name']); ?></h6>
            <div class="text-muted mb-2">$<?php echo number_format((float)$r['price'],2); ?></div>
            <div class="mt-auto d-flex gap-2">
              <a href="<?php echo BASE.'/product-details.php?id='.(int)$r['id']; ?>" class="btn btn-sm btn-outline-secondary flex-grow-1">View</a>
              <a href="<?php echo BASE.'/actions/cart-add.php?id='.(int)$r['id']; ?>" class="btn btn-sm btn-warning flex-grow-1" style="background:var(--primary);border:none">Add</a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>

<script>
(function(){
  var minus=document.getElementById('qtyMinus');
  var plus=document.getElementById('qtyPlus');
  var qty=document.getElementById('qty');
  var add=document.getElementById('qtyAddCart');
  var buy=document.getElementById('qtyBuyNow');
  function sync(){ add.value=qty.value; buy.value=qty.value; }
  if(minus){ minus.addEventListener('click',function(){ var v=parseInt(qty.value||'1',10); var min=parseInt(qty.min||'1',10); if(v>min){ qty.value=v-1; sync(); } }); }
  if(plus){ plus.addEventListener('click',function(){ var v=parseInt(qty.value||'1',10); var max=parseInt(qty.max||'0',10); if(max>0){ if(v<max){ qty.value=v+1; sync(); } } else { qty.value=v+1; sync(); } }); }
  if(qty){ qty.addEventListener('input',sync); }
  sync();
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
