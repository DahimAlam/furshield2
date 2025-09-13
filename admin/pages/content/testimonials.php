<?php
if (session_status()===PHP_SESSION_NONE) session_start();
$root = dirname(__DIR__,3);
require_once $root.'/includes/db.php';
require_once $root.'/includes/auth.php';
require_role('admin');
include $root.'/admin/includes/header.php';
include $root.'/admin/includes/sidebar.php';

if(empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin']=bin2hex(random_bytes(16));
$csrf=$_SESSION['csrf_admin'];

function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

$conn->query("CREATE TABLE IF NOT EXISTS testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NULL,
  role VARCHAR(150) NULL,
  rating TINYINT DEFAULT 5,
  message TEXT NULL,
  avatar VARCHAR(255) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$uploadsDir=$root.'/uploads/testimonials/';
if(!is_dir($uploadsDir))@mkdir($uploadsDir,0775,true);

$msg='';

if($_SERVER['REQUEST_METHOD']==='POST' && hash_equals($csrf,$_POST['csrf']??'')){
  $action=$_POST['action']??'';
  if(in_array($action,['add','edit'])){
    $name=trim($_POST['name']??'');
    $role=trim($_POST['role']??'');
    $rating=(int)($_POST['rating']??5);
    if($rating<1||$rating>5) $rating=5;
    $message=trim($_POST['message']??'');
    $status=(int)($_POST['is_active']??1);
    $avatar=null;
    if(!empty($_FILES['avatar']['name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])){
      $ext=strtolower(pathinfo($_FILES['avatar']['name'],PATHINFO_EXTENSION));
      if(in_array($ext,['jpg','jpeg','png','webp','gif'])){
        $nameFile='t-'.bin2hex(random_bytes(4)).'.'.$ext;
        if(move_uploaded_file($_FILES['avatar']['tmp_name'],$uploadsDir.$nameFile)){
          $avatar='/uploads/testimonials/'.$nameFile;
        }
      }
    }
    if($action==='add'){
      $st=$conn->prepare("INSERT INTO testimonials (name,role,rating,message,avatar,is_active) VALUES (?,?,?,?,?,?)");
      $st->bind_param("ssissi",$name,$role,$rating,$message,$avatar,$status);
      $st->execute();$st->close();
      $msg='Testimonial added.';
    }else{
      $id=(int)$_POST['id'];
      $sql="UPDATE testimonials SET name=?,role=?,rating=?,message=?,is_active=?".($avatar?",avatar=?":"")." WHERE id=?";
      $st=$conn->prepare($sql);
      if($avatar){
        $st->bind_param("ssisssi",$name,$role,$rating,$message,$status,$avatar,$id);
      }else{
        $st->bind_param("ssissi",$name,$role,$rating,$message,$status,$id);
      }
      $st->execute();$st->close();
      $msg='Testimonial updated.';
    }
  }
  if($action==='delete'){
    $id=(int)$_POST['id'];
    $st=$conn->prepare("DELETE FROM testimonials WHERE id=?");
    $st->bind_param("i",$id);$st->execute();$st->close();
    $msg='Testimonial deleted.';
  }
}

$page=max(1,(int)($_GET['p']??1));
$per=10;$off=($page-1)*$per;
$total=(int)($conn->query("SELECT COUNT(*) c FROM testimonials")->fetch_assoc()['c']??0);
$res=$conn->prepare("SELECT * FROM testimonials ORDER BY created_at DESC LIMIT ?,?");
$res->bind_param("ii",$off,$per);$res->execute();
$rows=$res->get_result()->fetch_all(MYSQLI_ASSOC);$res->close();
$pages=max(1,ceil($total/$per));
?>

<main class="main p-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h3 fw-bold">Manage Testimonials</h1>
      <button class="btn text-white" style="background:var(--primary);border-radius:14px" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> New Testimonial</button>
    </div>

    <?php if($msg): ?>
      <div class="alert alert-success rounded-3"><?php echo e($msg);?></div>
    <?php endif;?>

    <div class="card border-0" style="border-radius:var(--radius);box-shadow:var(--shadow);background:var(--card)">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light"><tr><th>ID</th><th>Avatar</th><th>Name</th><th>Role</th><th>Rating</th><th>Status</th><th>Created</th><th></th></tr></thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id'];?></td>
              <td><?php if(!empty($r['avatar'])): ?><img src="<?php echo e(BASE.$r['avatar']);?>" style="height:50px;width:50px;border-radius:50%"><?php endif;?></td>
              <td><?php echo e($r['name']);?></td>
              <td><?php echo e($r['role']);?></td>
              <td><?php for($i=1;$i<=5;$i++): ?><i class="bi <?php echo $i<=$r['rating']?'bi-star-fill text-warning':'bi-star';?>"></i><?php endfor;?></td>
              <td><span class="badge <?php echo $r['is_active']?'bg-success':'bg-secondary';?>"><?php echo $r['is_active']?'Active':'Hidden';?></span></td>
              <td><?php echo e($r['created_at']);?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $r['id'];?>">Edit</button>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf" value="<?php echo e($csrf);?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id'];?>">
                  <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this testimonial?')">Delete</button>
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
                    <div class="modal-header"><h5 class="modal-title">Edit Testimonial</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                      <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="<?php echo e($r['name']);?>"></div>
                      <div class="mb-3"><label class="form-label">Role</label><input type="text" name="role" class="form-control" value="<?php echo e($r['role']);?>"></div>
                      <div class="mb-3"><label class="form-label">Rating</label><select name="rating" class="form-select"><?php for($i=1;$i<=5;$i++):?><option value="<?php echo $i;?>" <?php if($i==$r['rating'])echo'selected';?>><?php echo $i;?></option><?php endfor;?></select></div>
                      <div class="mb-3"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="4"><?php echo e($r['message']);?></textarea></div>
                      <div class="mb-3"><label class="form-label">Avatar</label><input type="file" name="avatar" class="form-control"></div>
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
    <nav class="mt-3"><ul class="pagination">
      <?php for($i=1;$i<=$pages;$i++): ?><li class="page-item <?php echo $i==$page?'active':'';?>"><a class="page-link" href="?p=<?php echo $i;?>"><?php echo $i;?></a></li><?php endfor;?>
    </ul></nav>
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
        <div class="modal-header"><h5 class="modal-title">New Testimonial</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Role</label><input type="text" name="role" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Rating</label><select name="rating" class="form-select"><?php for($i=1;$i<=5;$i++):?><option value="<?php echo $i;?>"><?php echo $i;?></option><?php endfor;?></select></div>
          <div class="mb-3"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="4"></textarea></div>
          <div class="mb-3"><label class="form-label">Avatar</label><input type="file" name="avatar" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1">Active</option><option value="0">Hidden</option></select></div>
        </div>
        <div class="modal-footer"><button class="btn text-white" style="background:var(--primary)">Add</button></div>
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
