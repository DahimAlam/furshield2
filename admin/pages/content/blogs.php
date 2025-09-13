<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$root = dirname(__DIR__, 3);
require_once $root.'/includes/db.php';
require_once $root.'/includes/auth.php';
require_role('admin');
include $root.'/admin/includes/header.php';
include $root.'/admin/includes/sidebar.php';

if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin']=bin2hex(random_bytes(16));
$csrf=$_SESSION['csrf_admin'];

function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function t_exists($c,$t){
  $db = $c->query("SELECT DATABASE()")->fetch_row()[0] ?? '';
  $sql = "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema=? AND table_name=?";
  $s = $c->prepare($sql);
  if(!$s) return false;
  $s->bind_param("ss",$db,$t);
  $s->execute();
  $res = $s->get_result();
  $row = $res->fetch_assoc();
  $s->close();
  return (int)($row['cnt'] ?? 0) > 0;
}

function hascol($c,$t,$col){
  $db = $c->query("SELECT DATABASE()")->fetch_row()[0] ?? '';
  $sql = "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?";
  $s = $c->prepare($sql);
  if(!$s) return false;
  $s->bind_param("sss",$db,$t,$col);
  $s->execute();
  $res = $s->get_result();
  $row = $res->fetch_assoc();
  $s->close();
  return (int)($row['cnt'] ?? 0) > 0;
}


$conn->query("CREATE TABLE IF NOT EXISTS blogs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NULL,
  content TEXT NULL,
  image VARCHAR(255) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$uploadsDir=$root.'/uploads/blo/';
$uploadsUrl=BASE.'/uploads/blo/';
if(!is_dir($uploadsDir))@mkdir($uploadsDir,0775,true);

$msg='';$ok=true;

if($_SERVER['REQUEST_METHOD']==='POST' && hash_equals($csrf,$_POST['csrf']??'')){
  $action=$_POST['action']??'';
  if($action==='add'||$action==='edit'){
    $title=trim($_POST['title']??'');
    $content=trim($_POST['content']??'');
    $status=($_POST['is_active']??'1')==='1'?1:0;
    $imgPath=null;
    if(!empty($_FILES['image']['name'])&&is_uploaded_file($_FILES['image']['tmp_name'])){
      $ext=strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
      if(in_array($ext,['jpg','jpeg','png','webp','gif'])){
        $name='blog-'.bin2hex(random_bytes(4)).'.'.$ext;
        if(move_uploaded_file($_FILES['image']['tmp_name'],$uploadsDir.$name)){
          $imgPath='/uploads/blo/'.$name;
        }
      }
    }
    if($action==='add'){
      $stmt=$conn->prepare("INSERT INTO blogs (title,content,image,is_active) VALUES (?,?,?,?)");
      $stmt->bind_param("sssi",$title,$content,$imgPath,$status);
      $stmt->execute();$stmt->close();
      $msg='Blog added.';
    }else{
      $id=(int)$_POST['id'];
      $stmt=$conn->prepare("UPDATE blogs SET title=?,content=?,is_active=?".($imgPath?",image=?":"")." WHERE id=?");
      if($imgPath){$stmt->bind_param("ssisi",$title,$content,$status,$imgPath,$id);}
      else{$stmt->bind_param("ssii",$title,$content,$status,$id);}
      $stmt->execute();$stmt->close();
      $msg='Blog updated.';
    }
  }
  if($action==='delete'){
    $id=(int)$_POST['id'];
    $stmt=$conn->prepare("DELETE FROM blogs WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();$stmt->close();
    $msg='Blog deleted.';
  }
}

$page=max(1,(int)($_GET['p']??1));
$per=10;$off=($page-1)*$per;
$total=(int)($conn->query("SELECT COUNT(*) c FROM blogs")->fetch_assoc()['c']??0);
$res=$conn->prepare("SELECT * FROM blogs ORDER BY created_at DESC LIMIT ?,?");
$res->bind_param("ii",$off,$per);$res->execute();
$rows=$res->get_result()->fetch_all(MYSQLI_ASSOC);$res->close();
$pages=max(1,ceil($total/$per));
?>

<main class="main p-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h3 fw-bold">Manage Blogs</h1>
      <button class="btn text-white" style="background:var(--primary);border-radius:14px" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> New Blog</button>
    </div>

    <?php if($msg): ?>
      <div class="alert alert-success rounded-3"><?php echo e($msg);?></div>
    <?php endif;?>

    <div class="card border-0" style="border-radius:var(--radius);box-shadow:var(--shadow);background:var(--card)">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr><th>ID</th><th>Title</th><th>Image</th><th>Status</th><th>Created</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id'];?></td>
              <td><?php echo e($r['title']);?></td>
              <td><?php if(!empty($r['image'])): ?><img src="<?php echo e(BASE.$r['image']);?>" style="height:50px;border-radius:6px"><?php endif;?></td>
              <td><span class="badge <?php echo $r['is_active']?'bg-success':'bg-secondary';?>"><?php echo $r['is_active']?'Active':'Hidden';?></span></td>
              <td><?php echo e($r['created_at']);?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $r['id'];?>">Edit</button>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf" value="<?php echo e($csrf);?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id'];?>">
                  <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this blog?')">Delete</button>
                </form>
              </td>
            </tr>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?php echo $r['id'];?>" tabindex="-1">
              <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content rounded-4">
                  <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?php echo e($csrf);?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo (int)$r['id'];?>">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Blog</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" value="<?php echo e($r['title']);?>"></div>
                      <div class="mb-3"><label class="form-label">Content</label><textarea name="content" class="form-control" rows="5"><?php echo e($r['content']);?></textarea></div>
                      <div class="mb-3"><label class="form-label">Image</label><input type="file" name="image" class="form-control"></div>
                      <div class="mb-3"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1" <?php if($r['is_active'])echo'selected';?>>Active</option><option value="0" <?php if(!$r['is_active'])echo'selected';?>>Hidden</option></select></div>
                    </div>
                    <div class="modal-footer"><button class="btn text-white" style="background:var(--primary)">Save</button></div>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach;?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if($pages>1): ?>
    <nav class="mt-3">
      <ul class="pagination">
        <?php for($i=1;$i<=$pages;$i++): ?>
          <li class="page-item <?php echo $i==$page?'active':'';?>"><a class="page-link" href="?p=<?php echo $i;?>"><?php echo $i;?></a></li>
        <?php endfor;?>
      </ul>
    </nav>
    <?php endif;?>

  </div>
</main>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content rounded-4">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?php echo e($csrf);?>">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title">New Blog</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Content</label><textarea name="content" class="form-control" rows="5"></textarea></div>
          <div class="mb-3"><label class="form-label">Image</label><input type="file" name="image" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1">Active</option><option value="0">Hidden</option></select></div>
        </div>
        <div class="modal-footer"><button class="btn text-white" style="background:var(--primary)">Add Blog</button></div>
      </form>
    </div>
  </div>
</div>

<style>
:root{--primary:#F59E0B;--accent:#EF4444;--bg:#FFF7ED;--text:#1F2937;--card:#FFFFFF;--radius:18px;--shadow:0 10px 30px rgba(0,0,0,.08)}
body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif}
h1,h2,h3,h4,h5,h6,p{font-family:Montserrat,Poppins,sans-serif}
.table td,.table th{vertical-align:middle}
</style>

<?php include $root.'/admin/includes/footer.php'; ?>
