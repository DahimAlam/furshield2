<?php
if (session_status()===PHP_SESSION_NONE) session_start();
$ROOT=dirname(__DIR__,3);
$ADMIN=dirname(__DIR__,2);
require_once $ROOT.'/includes/db.php';
require_once $ROOT.'/includes/auth.php';
require_role('admin');
if(!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');
if(empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin']=bin2hex(random_bytes(16));

function t_exists($c,$t){$t=$c->real_escape_string($t);$r=$c->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}'");return $r&&$r->num_rows>0;}
function hascol($c,$t,$col){$t=$c->real_escape_string($t);$col=$c->real_escape_string($col);$r=$c->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='{$t}' AND column_name='{$col}'");return $r&&$r->num_rows>0;}
function pick($c,$t,$opts){foreach($opts as $x){if(hascol($c,$t,$x))return $x;}return null;}
function refs(&$a){$r=[];foreach($a as $k=>$_){$r[$k]=&$a[$k];}return $r;}
function media($p,$folder){$p=trim((string)$p);if($p==='')return null;if(str_starts_with($p,'http'))return $p;$p=ltrim(str_replace('\\','/',$p),'/');if(str_starts_with($p,'uploads/'))return BASE.'/'.$p;return BASE.'/uploads/'.$folder.'/'.$p;}

if(!t_exists($conn,'products')){http_response_code(500);exit;}

$P_ID   = pick($conn,'products',['id','product_id']);
$P_NAME = pick($conn,'products',['name','title']);
$P_PRICE= pick($conn,'products',['price','amount']);
$P_IMG  = pick($conn,'products',['image_path','image','cover']);
$P_STOCK= pick($conn,'products',['stock_qty','qty','stock']);
$P_ACTIVE = pick($conn,'products',['is_active','active','status']);
$P_DESC = pick($conn,'products',['description','body','summary','details','content']);
$P_SLUG = pick($conn,'products',['slug','permalink']);
$P_FEAT = hascol($conn,'products','featured') ? 'featured' : null;

$alert='';
function get_product($conn,$id,$P_ID,$P_NAME,$P_PRICE,$P_IMG,$P_STOCK,$P_ACTIVE,$P_DESC,$P_SLUG,$P_FEAT){
  $selName=$P_NAME?("`$P_NAME`"):"'—'";
  $selPrice=$P_PRICE?("`$P_PRICE`"):"0";
  $selImg=$P_IMG?("`$P_IMG`"):"NULL";
  $selStock=$P_STOCK?("`$P_STOCK`"):"NULL";
  $selActive=$P_ACTIVE?("`$P_ACTIVE`"):"NULL";
  $selDesc=$P_DESC?("`$P_DESC`"):"NULL";
  $selSlug=$P_SLUG?("`$P_SLUG`"):"NULL";
  $selFeat=$P_FEAT?("`$P_FEAT`"):"0";
  $sql="SELECT `$P_ID` id,$selName name,$selPrice price,$selImg image,$selStock stock,$selActive active,$selDesc description,$selSlug slug,$selFeat featured FROM products WHERE `$P_ID`=?";
  $st=$conn->prepare($sql);$st->bind_param("i",$id);$st->execute();$r=$st->get_result()->fetch_assoc();$st->close();return $r;
}

if($_SERVER['REQUEST_METHOD']==='POST' && hash_equals($_SESSION['csrf_admin'], $_POST['csrf']??'')){
  $mode=$_POST['mode']??'';
  $id=(int)($_POST['id']??0);
  $name=trim($_POST['name']??'');
  $price=(float)($_POST['price']??0);
  $stock=$_POST['stock']!==''
    ? (int)$_POST['stock']
    : null;
  $active=isset($_POST['active'])?1:0;
  $featured=isset($_POST['featured'])?1:0;
  $slug=trim($_POST['slug']??'');
  $desc=trim($_POST['description']??'');
  $img_current=trim($_POST['image_current']??'');
  $img=$img_current;

  if(!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])){
    $ext=strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
    if(in_array($ext,['jpg','jpeg','png','webp','gif'],true)){
      $dir=$ROOT.'/uploads/products'; if(!is_dir($dir)) @mkdir($dir,0777,true);
      $fname='prod_'.date('Ymd_His').'_' . substr(bin2hex(random_bytes(6)),0,12).'.'.$ext;
      $dest=$dir.'/'.$fname;
      if(move_uploaded_file($_FILES['image']['tmp_name'],$dest)){
        $img='uploads/products/'.$fname;
      }
    }
  }

  $cols=[];$vals=[];$types='';
  if($P_NAME){$cols[$P_NAME]=$name;$types.='s';}
  if($P_PRICE){$cols[$P_PRICE]=$price;$types.='d';}
  if($P_STOCK!==null && $P_STOCK!==''){ if($stock!==null){$cols[$P_STOCK]=$stock;$types.='i';} }
  if($P_DESC){$cols[$P_DESC]=$desc;$types.='s';}
  if($P_SLUG){$cols[$P_SLUG]=$slug;$types.='s';}
  if($P_IMG){$cols[$P_IMG]=$img;$types.='s';}
  if($P_FEAT){$cols[$P_FEAT]=$featured;$types.='i';}
  if($P_ACTIVE){
    if(in_array($P_ACTIVE,['is_active','active'],true)){$cols[$P_ACTIVE]=$active;$types.='i';}
    else {$cols[$P_ACTIVE]=$active? 'active':'inactive';$types.='s';}
  }

  if($mode==='create'){
    if(!$P_NAME || !$P_PRICE){ $alert='error'; }
    else{
      $names=array_map(function($k){return "`$k`";},array_keys($cols));
      $place=rtrim(str_repeat('?,',count($cols)),',');
      $sql="INSERT INTO products (".implode(',',$names).") VALUES ($place)";
      $st=$conn->prepare($sql); $vals=array_values($cols); $b=refs($vals); $st->bind_param($types,...$b);
      $ok=$st->execute(); $st->close(); $alert=$ok?'saved':'error';
    }
  } elseif($mode==='edit' && $id>0){
    $set=[]; foreach($cols as $k=>$v){ $set[]="`$k`=?"; }
    $sql="UPDATE products SET ".implode(',',$set)." WHERE `$P_ID`=?";
    $st=$conn->prepare($sql); $vals=array_values($cols); $types2=$types.'i'; $vals[]=$id; $b=refs($vals); $st->bind_param($types2,...$b);
    $ok=$st->execute(); $st->close(); $alert=$ok?'saved':'error';
  } elseif($mode==='delete' && $id>0){
    $st=$conn->prepare("DELETE FROM products WHERE `$P_ID`=?");$st->bind_param("i",$id);$ok=$st->execute();$st->close();$alert=$ok?'deleted':'error';
  }
  header("Location: ".$_SERVER['PHP_SELF'].'?alert='.$alert); exit;
}

$act=$_GET['action']??'list';

include $ADMIN.'/includes/header.php';
include $ADMIN.'/includes/sidebar.php';

function val($arr,$k,$d=''){return isset($arr[$k])?$arr[$k]:$d;}
?>
<main class="admin-main p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold text-dark m-0">Products</h1>
    <?php if($act==='list'){ ?>
      <a class="btn btn-warning" href="?action=create" style="background:var(--primary);border:none"><i class="bi bi-plus-lg me-1"></i>Add Product</a>
    <?php } else { ?>
      <a class="btn btn-outline-secondary" href="?"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <?php } ?>
  </div>

  <?php if(isset($_GET['alert'])): ?>
    <?php if($_GET['alert']==='saved'): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
    <?php if($_GET['alert']==='deleted'): ?><div class="alert alert-success">Deleted.</div><?php endif; ?>
    <?php if($_GET['alert']==='error'): ?><div class="alert alert-danger">Action failed.</div><?php endif; ?>
  <?php endif; ?>

  <?php if($act==='create' || $act==='edit'):
    $item = $act==='edit' && isset($_GET['id']) ? get_product($conn,(int)$_GET['id'],$P_ID,$P_NAME,$P_PRICE,$P_IMG,$P_STOCK,$P_ACTIVE,$P_DESC,$P_SLUG,$P_FEAT) : [];
    $imgPrev = media(val($item,'image'), 'products');
  ?>
    <div class="card shadow-sm rounded-4 border-0">
      <div class="card-body p-4">
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf_admin']; ?>">
          <input type="hidden" name="mode" value="<?php echo $act==='create'?'create':'edit'; ?>">
          <?php if($act==='edit'){ ?><input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>"><?php } ?>
          <div class="col-lg-8">
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" name="name" required class="form-control form-control-lg" value="<?php echo htmlspecialchars(val($item,'name')); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="6"><?php echo htmlspecialchars(val($item,'description')); ?></textarea>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="mb-3">
              <label class="form-label">Price</label>
              <input type="number" step="0.01" min="0" name="price" required class="form-control" value="<?php echo htmlspecialchars(val($item,'price',0)); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Stock</label>
              <input type="number" name="stock" class="form-control" value="<?php echo htmlspecialchars(val($item,'stock')); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Slug</label>
              <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars(val($item,'slug')); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Image</label>
              <div class="card border-0 shadow-sm mb-2" style="border-radius:var(--radius);overflow:hidden">
                <img src="<?php echo $imgPrev ?: (BASE.'/assets/img/placeholder.png'); ?>" style="width:100%;height:220px;object-fit:cover" alt="">
              </div>
              <input type="hidden" name="image_current" value="<?php echo htmlspecialchars(val($item,'image')); ?>">
              <input type="file" name="image" class="form-control">
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" name="active" id="active" <?php echo (int)val($item,'active',1)==1?'checked':''; ?>>
              <label class="form-check-label" for="active">Active</label>
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="featured" id="featured" <?php echo (int)val($item,'featured',0)==1?'checked':''; ?> <?php echo $P_FEAT? '':'disabled'; ?>>
              <label class="form-check-label" for="featured">Show on homepage (Top)</label>
            </div>
            <button class="btn btn-warning w-100" style="background:var(--primary);border:none">Save</button>
          </div>
        </form>
      </div>
    </div>

  <?php elseif($act==='view' && isset($_GET['id'])):
    $item=get_product($conn,(int)$_GET['id'],$P_ID,$P_NAME,$P_PRICE,$P_IMG,$P_STOCK,$P_ACTIVE,$P_DESC,$P_SLUG,$P_FEAT);
    $img=media(val($item,'image'),'products');
  ?>
    <div class="card shadow-sm rounded-4 border-0">
      <div class="row g-0">
        <div class="col-md-4">
          <img src="<?php echo $img?:BASE.'/assets/img/placeholder.png'; ?>" style="width:100%;height:100%;object-fit:cover;border-top-left-radius:var(--radius);border-bottom-left-radius:var(--radius)" alt="">
        </div>
        <div class="col-md-8">
          <div class="card-body p-4">
            <h3 class="mb-1"><?php echo htmlspecialchars(val($item,'name')); ?></h3>
            <div class="text-muted mb-2">$<?php echo number_format((float)val($item,'price',0),2); ?></div>
            <div class="mb-3"><?php echo nl2br(htmlspecialchars(val($item,'description'))); ?></div>
            <div class="d-flex gap-2">
              <a class="btn btn-outline-secondary" href="?action=edit&id=<?php echo (int)$item['id']; ?>">Edit</a>
              <form method="post" onsubmit="return confirm('Delete this product?');">
                <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf_admin']; ?>">
                <input type="hidden" name="mode" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                <button class="btn btn-outline-danger">Delete</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

  <?php else:
    $q=trim((string)($_GET['q']??''));$only=$_GET['only']??'all';
    if(!in_array($only,['all','featured','active','inactive'],true)) $only='all';
    $page=max(1,(int)($_GET['p']??1));$limit=24;$offset=($page-1)*$limit;

    $conds=[];$types='';$args=[];
    if($q!==''){
      $like='%'.mb_strtolower($q).'%'; $s=[];
      if($P_NAME)$s[]="LOWER(p.`$P_NAME`) LIKE ?";
      if($P_SLUG)$s[]="LOWER(p.`$P_SLUG`) LIKE ?";
      if($s){$conds[]='('.implode(' OR ',$s).')';$types.=str_repeat('s',count($s));for($i=0;$i<count($s);$i++)$args[]=$like;}
    }
    if($only==='featured' && $P_FEAT){$conds[]="p.`$P_FEAT`=1";}
    if($only==='active' && $P_ACTIVE){ if(in_array($P_ACTIVE,['is_active','active'],true)){$conds[]="p.`$P_ACTIVE`=1";} else {$conds[]="p.`$P_ACTIVE`='active'";} }
    if($only==='inactive' && $P_ACTIVE){ if(in_array($P_ACTIVE,['is_active','active'],true)){$conds[]="p.`$P_ACTIVE`=0";} else {$conds[]="p.`$P_ACTIVE`<>'active'";} }
    $where=$conds?('WHERE '.implode(' AND ',$conds)):'';

    $selName=$P_NAME?("p.`$P_NAME`"):"'—'";
    $selPrice=$P_PRICE?("p.`$P_PRICE`"):"0";
    $selImg=$P_IMG?("p.`$P_IMG`"):"NULL";
    $selStock=$P_STOCK?("p.`$P_STOCK`"):"NULL";
    $selActive=$P_ACTIVE?("p.`$P_ACTIVE`"):"NULL";
    $selFeat=$P_FEAT?("p.`$P_FEAT`"):"0";

    $st=$conn->prepare("SELECT COUNT(*) FROM products p $where"); if($types){$b=refs($args);$st->bind_param($types,...$b);} $st->execute();$st->bind_result($total);$st->fetch();$st->close();
    $pages=max(1,(int)ceil($total/$limit));

    $types2=$types.'ii';$args2=$args;$args2[]=$limit;$args2[]=$offset;
    $sql="SELECT p.`$P_ID` id,$selName name,$selPrice price,$selImg image,$selStock stock,$selActive active,$selFeat featured FROM products p $where ORDER BY p.`$P_ID` DESC LIMIT ? OFFSET ?";
    $st=$conn->prepare($sql);$b2=refs($args2);$st->bind_param($types2,...$b2);$st->execute();$res=$st->get_result();$rows=[];while($r=$res->fetch_assoc())$rows[]=$r;$st->close();
  ?>
    <div class="card shadow-sm rounded-4 border-0">
      <div class="card-body p-3">
        <form class="d-flex gap-2 mb-3" method="get">
          <input type="text" name="q" class="form-control" placeholder="Search name/slug" value="<?php echo htmlspecialchars($q); ?>">
          <select name="only" class="form-select">
            <option value="all"<?php if($only==='all')echo' selected';?>>All</option>
            <option value="featured"<?php if($only==='featured')echo' selected';?>>Homepage (Top)</option>
            <option value="active"<?php if($only==='active')echo' selected';?>>Active</option>
            <option value="inactive"<?php if($only==='inactive')echo' selected';?>>Inactive</option>
          </select>
          <button class="btn btn-primary">Apply</button>
          <a class="btn btn-outline-secondary" href="?">Reset</a>
        </form>

        <?php if(!$rows): ?>
          <div class="alert alert-info m-0">No products found.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle table-hover">
            <thead class="table-light">
              <tr>
                <th style="width:72px">Image</th>
                <th>Name</th>
                <th style="width:120px">Price</th>
                <th style="width:120px">Stock</th>
                <th style="width:140px">Status</th>
                <th style="width:140px">Homepage</th>
                <th style="width:280px" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rows as $r): $img=media($r['image']??'','products'); $isA=$r['active']; $isF=(int)($r['featured']??0); ?>
              <tr>
                <td><?php if($img){ ?><img src="<?php echo $img; ?>" alt="" width="60" height="60" style="object-fit:cover;border-radius:12px"><?php } else { ?><span class="badge bg-secondary">No Image</span><?php } ?></td>
                <td class="fw-semibold"><?php echo htmlspecialchars($r['name']); ?></td>
                <td>$<?php echo number_format((float)$r['price'],2); ?></td>
                <td><?php echo $r['stock']!==null?(int)$r['stock']:'—'; ?></td>
                <td>
                  <?php if($isA===null){ ?><span class="badge bg-light text-dark">N/A</span>
                  <?php } elseif(in_array($P_ACTIVE,['is_active','active'],true)?((int)$isA===1):($isA==='active')){ ?><span class="badge bg-success">Active</span>
                  <?php } else { ?><span class="badge bg-secondary">Inactive</span><?php } ?>
                </td>
                <td><?php if($P_FEAT){ echo $isF===1?'<span class="badge bg-primary">Shown</span>':'<span class="badge bg-light text-dark">Not Shown</span>'; } else { echo '<span class="badge bg-light text-dark">N/A</span>'; } ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="?action=view&id=<?php echo (int)$r['id']; ?>">View</a>
                  <a class="btn btn-sm btn-outline-primary" href="?action=edit&id=<?php echo (int)$r['id']; ?>">Edit</a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this product?');">
                    <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf_admin']; ?>">
                    <input type="hidden" name="mode" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                  <?php if($P_FEAT){ ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf_admin']; ?>">
                      <input type="hidden" name="mode" value="edit">
                      <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                      <input type="hidden" name="name" value="<?php echo htmlspecialchars($r['name'],ENT_QUOTES); ?>">
                      <input type="hidden" name="price" value="<?php echo (float)$r['price']; ?>">
                      <input type="hidden" name="stock" value="<?php echo (int)$r['stock']; ?>">
                      <input type="hidden" name="slug" value="">
                      <input type="hidden" name="description" value="">
                      <input type="hidden" name="image_current" value="<?php echo htmlspecialchars($r['image']??'',ENT_QUOTES); ?>">
                      <input type="hidden" name="active" value="<?php echo in_array($P_ACTIVE,['is_active','active'],true)?((int)$isA===1?1:0):($isA==='active'?1:0); ?>">
                      <input type="hidden" name="featured" value="<?php echo $isF?0:1; ?>">
                      <button class="btn btn-sm <?php echo $isF? 'btn-outline-primary':'btn-primary'; ?>"><?php echo $isF?'Remove Home':'Show on Home'; ?></button>
                    </form>
                  <?php } ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if($pages>1) $mk=function($p)use($q,$only){return '?q='.urlencode($q).'&only='.urlencode($only).'&p='.$p;}; ?>
        <div class="d-flex justify-content-end gap-2 mt-3">
          <a class="btn btn-outline-primary btn-sm<?php if($page<=1) echo ' disabled'; ?>" href="<?php echo $mk(max(1,$page-1)); ?>">Prev</a>
          <span class="btn btn-light btn-sm disabled">Page <?php echo $page; ?> / <?php echo $pages; ?></span>
          <a class="btn btn-outline-primary btn-sm<?php if($page>=$pages) echo ' disabled'; ?>" href="<?php echo $mk(min($pages,$page+1)); ?>">Next</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</main>
<?php include $ADMIN.'/includes/footer.php'; ?>
