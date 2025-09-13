<?php
if (session_status()===PHP_SESSION_NONE) session_start();
$ROOT=realpath(dirname(__DIR__,3));
$ADMIN=realpath(dirname(__DIR__,2));
require_once $ROOT.'/includes/db.php';
require_once $ROOT.'/includes/auth.php';
require_role('admin');
if(!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

function t_exists(mysqli $c,$t){$t=$c->real_escape_string($t);$r=$c->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'");return $r&&$r->num_rows>0;}
function cols(mysqli $c,$t){$t=$c->real_escape_string($t);$r=$c->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'");$o=[];while($r&&($x=$r->fetch_row()))$o[]=$x[0];return $o;}
function pick($arr,$c){foreach($c as $x){if(in_array($x,$arr,true))return $x;}return null;}
function refs(&$a){$r=[];foreach($a as $k=>$_){$r[$k]=&$a[$k];}return $r;}
function media_url($root,$rel,$prefer='pet'){ $rel=trim((string)$rel); if($rel==='') return null; $clean=ltrim(str_replace('\\','/',$rel),'/'); if(stripos($clean,'furshield/')===0) $clean=substr($clean,10); $cands=[$clean]; if($prefer==='pet'){ $cands[]='uploads/pets/'.$clean; $cands[]='uploads/'.$clean; $cands[]='uploads/gallery/'.$clean; } else { $cands[]='uploads/'.$clean; } foreach($cands as $c){$fs=rtrim($root,'/').'/'.$c; if(is_file($fs)) return rtrim(BASE,'/').'/'.ltrim($c,'/'); } return null; }

$tab=null; foreach(['adoption_listings','adoptions','adoption','pets'] as $t){ if(t_exists($conn,$t)){ $tab=$t; break; } }
if(!$tab){ http_response_code(500); exit; }

$ac=cols($conn,$tab);
$A_ID=pick($ac,['id','listing_id','adoption_id','pet_id']);
$A_SHELTER=pick($ac,['shelter_id','shelter_user_id','shelter','shelterId']);
$A_NAME=pick($ac,['pet_name','name','title']);
$A_SPECIES=pick($ac,['species','type']);
$A_BREED=pick($ac,['breed']);
$A_AGE=pick($ac,['age','age_years']);
$A_SEX=pick($ac,['gender','sex']);
$A_STATUS=pick($ac,['status','state']);
$A_CITY=pick($ac,['city']);
$A_COUNTRY=pick($ac,['country']);
$A_CREATED=pick($ac,['created_at','created','created_on','posted_at']);
$A_IMAGE=pick($ac,['image','photo','picture','thumbnail','cover','img']);
$A_VIS=pick($ac,['is_published','published','show_on_site','visible','is_visible','display','is_active']);

$uc=t_exists($conn,'users')?cols($conn,'users'):[];
$U_ID=pick($uc,['id','user_id']);
$U_NAME=pick($uc,['name','full_name']);
$U_ROLE=pick($uc,['role','user_role']);

$sc=t_exists($conn,'shelters')?cols($conn,'shelters'):[];
$S_ID=pick($sc,['id','shelter_id']);
$S_NAME=pick($sc,['name','shelter_name','org_name','title']);
$S_CITY=pick($sc,['city']);
$S_COUNTRY=pick($sc,['country']);
$S_USER=pick($sc,['user_id','uid']);

if(!$A_ID){ http_response_code(500); exit; }

if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=(int)($_POST['id']??0);
  $act=trim($_POST['action']??'');
  if($id>0){
    if(in_array($act,['approve','rejected','adopted','inactive']) && $A_STATUS){
      $st=$conn->prepare("UPDATE `$tab` SET `$A_STATUS`=?, `$A_ID`=`$A_ID` WHERE `$A_ID`=?");
      $st->bind_param("si",$act,$id); $st->execute(); $st->close();
    } elseif(in_array($act,['show','hide'])){
      if($A_VIS){
        $val=$act==='show'?1:0;
        $st=$conn->prepare("UPDATE `$tab` SET `$A_VIS`=?, `$A_ID`=`$A_ID` WHERE `$A_ID`=?");
        $st->bind_param("ii",$val,$id); $st->execute(); $st->close();
      } elseif($A_STATUS){
        $val=$act==='show'?'approved':'inactive';
        $st=$conn->prepare("UPDATE `$tab` SET `$A_STATUS`=?, `$A_ID`=`$A_ID` WHERE `$A_ID`=?");
        $st->bind_param("si",$val,$id); $st->execute(); $st->close();
      }
    }
  }
  header("Location: ".$_SERVER['REQUEST_URI']); exit;
}

$from=$_GET['from']??'';
$to=$_GET['to']??'';
$stat=$_GET['status']??'any';
$vis=$_GET['vis']??'any';
$species=trim((string)($_GET['species']??''));
$shel=(int)($_GET['shelter']??0);
$q=trim((string)($_GET['q']??''));
$page=max(1,(int)($_GET['p']??1)); $limit=24; $offset=($page-1)*$limit;

$sName="'—'"; $sCity="'—'"; $sCountry="'—'"; $sJoin=''; $suJoin='';
if($A_SHELTER){
  if($S_ID && t_exists($conn,'shelters')){
    $sJoin="LEFT JOIN shelters s ON s.`$S_ID`=a.`$A_SHELTER`";
    $sName=$S_NAME?"COALESCE(s.`$S_NAME`,'—')":"'—'";
    $sCity=$S_CITY?"COALESCE(s.`$S_CITY`,'—')":"'—'";
    $sCountry=$S_COUNTRY?"COALESCE(s.`$S_COUNTRY`,'—')":"'—'";
  } elseif($U_ID && $U_ROLE){
    $suJoin="LEFT JOIN users su ON su.`$U_ID`=a.`$A_SHELTER` AND ".($U_ROLE?"su.`$U_ROLE`='shelter'":"1=1");
    $sName=$U_NAME?"COALESCE(su.`$U_NAME`,'—')":"'—'";
  }
}

$when=$A_CREATED?("a.`$A_CREATED`"):"NOW()";
$conds=[]; $types=''; $args=[];
if($from!==''){ $conds[]="DATE($when)>=?"; $types.='s'; $args[]=$from; }
if($to!==''){ $conds[]="DATE($when)<=?"; $types.='s'; $args[]=$to; }
if($stat!=='any' && $A_STATUS){ $conds[]="a.`$A_STATUS`=?"; $types.='s'; $args[]=$stat; }
if($vis!=='any'){
  if($A_VIS){ $conds[]="a.`$A_VIS`=?"; $types.='i'; $args[]=$vis==='shown'?1:0; }
  else { if($vis==='shown' && $A_STATUS){ $conds[]="a.`$A_STATUS` IN ('approved','adopted')"; } if($vis==='hidden' && $A_STATUS){ $conds[]="a.`$A_STATUS` IN ('pending','rejected','inactive')"; } }
}
if($species!=='' && $A_SPECIES){ $conds[]="LOWER(a.`$A_SPECIES`) LIKE ?"; $types.='s'; $args[]='%'.mb_strtolower($species).'%'; }
if($shel>0 && $A_SHELTER){ $conds[]="a.`$A_SHELTER`=?"; $types.='i'; $args[]=$shel; }
if($q!==''){
  $like='%'.mb_strtolower($q).'%'; $s=[];
  if($A_NAME)$s[]="LOWER(a.`$A_NAME`) LIKE ?";
  if($A_BREED)$s[]="LOWER(a.`$A_BREED`) LIKE ?";
  if($A_SPECIES)$s[]="LOWER(a.`$A_SPECIES`) LIKE ?";
  if($A_CITY)$s[]="LOWER(a.`$A_CITY`) LIKE ?";
  if($A_COUNTRY)$s[]="LOWER(a.`$A_COUNTRY`) LIKE ?";
  if($sName!=="'—'") $s[]="LOWER($sName) LIKE ?";
  if($s){ $conds[]='('.implode(' OR ',$s).')'; $types.=str_repeat('s',count($s)); for($i=0;$i<count($s);$i++) $args[]=$like; }
}
$where=$conds?('WHERE '.implode(' AND ',$conds)):'';

$total=0;
$sql="SELECT COUNT(*) FROM `$tab` a $sJoin $suJoin $where";
$st=$conn->prepare($sql); if($types){$b=refs($args);$st->bind_param($types,...$b);} $st->execute(); $st->bind_result($total); $st->fetch(); $st->close();

$name=$A_NAME?("a.`$A_NAME`"):"'—'";
$speciesX=$A_SPECIES?("a.`$A_SPECIES`"):"'—'";
$breed=$A_BREED?("a.`$A_BREED`"):"'—'";
$age=$A_AGE?("a.`$A_AGE`"):"'—'";
$sex=$A_SEX?("a.`$A_SEX`"):"'—'";
$city=$A_CITY?("a.`$A_CITY`"):$sCity;
$country=$A_COUNTRY?("a.`$A_COUNTRY`"):$sCountry;
$statusX=$A_STATUS?("a.`$A_STATUS`"):"'—'";
$image=$A_IMAGE?("a.`$A_IMAGE`"):"''";
$visX=$A_VIS?("a.`$A_VIS`"):"NULL";

$rows=[];
$sql="SELECT a.`$A_ID` id,$name name,$speciesX species,$breed breed,$age age,$sex sex,$city city,$country country,$statusX status,$image image,$visX vis,$sName shelter,$when created_at FROM `$tab` a $sJoin $suJoin $where ORDER BY $when DESC LIMIT ? OFFSET ?";
$types2=$types.'ii'; $args2=$args; $args2[]=$limit; $args2[]=$offset;
$st=$conn->prepare($sql); $b2=refs($args2); $st->bind_param($types2,...$b2); $st->execute(); $res=$st->get_result(); while($r=$res->fetch_assoc()) $rows[]=$r; $st->close();

$pages=max(1,(int)ceil($total/$limit));

$shelters=[];
if($S_ID && $S_NAME && t_exists($conn,'shelters')){ $r=$conn->query("SELECT `$S_ID` id, `$S_NAME` name FROM shelters ORDER BY `$S_NAME` ASC"); while($r&&($y=$r->fetch_assoc())) $shelters[]=$y; }
elseif($U_ID && $U_ROLE && $U_NAME){ $r=$conn->query("SELECT `$U_ID` id, `$U_NAME` name FROM users WHERE `$U_ROLE`='shelter' ORDER BY `$U_NAME` ASC"); while($r&&($y=$r->fetch_assoc())) $shelters[]=$y; }

include $ADMIN.'/includes/header.php';
include $ADMIN.'/includes/sidebar.php';
?>
<main class="admin-main p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold text-dark m-0">Pets (Adoption)</h1>
    <form class="d-flex flex-wrap gap-2 align-items-end" method="get">
      <div><label class="form-label small text-muted mb-1">From</label><input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control"></div>
      <div><label class="form-label small text-muted mb-1">To</label><input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control"></div>
      <div><label class="form-label small text-muted mb-1">Status</label><select name="status" class="form-select"><?php foreach(['any','pending','approved','adopted','rejected','inactive'] as $s){ ?><option value="<?php echo $s; ?>"<?php if($stat===$s) echo ' selected'; ?>><?php echo ucfirst($s); ?></option><?php } ?></select></div>
      <div><label class="form-label small text-muted mb-1">Visibility</label><select name="vis" class="form-select"><?php foreach(['any'=>'Any','shown'=>'Shown','hidden'=>'Hidden'] as $k=>$v){ ?><option value="<?php echo $k; ?>"<?php if($vis===$k) echo ' selected'; ?>><?php echo $v; ?></option><?php } ?></select></div>
      <div><label class="form-label small text-muted mb-1">Species</label><input type="text" name="species" value="<?php echo htmlspecialchars($species); ?>" class="form-control" placeholder="e.g., Dog"></div>
      <div><label class="form-label small text-muted mb-1">Shelter</label><select name="shelter" class="form-select"><option value="0">All</option><?php foreach($shelters as $sh){ ?><option value="<?php echo (int)$sh['id']; ?>"<?php if($shel===(int)$sh['id']) echo ' selected'; ?>><?php echo htmlspecialchars($sh['name']); ?></option><?php } ?></select></div>
      <div><label class="form-label small text-muted mb-1">Search</label><input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control" placeholder="name, breed, city"></div>
      <div><button class="btn btn-primary">Apply</button></div>
    </form>
  </div>

  <div class="card shadow-sm rounded-4 border-0">
    <div class="card-body p-3">
      <?php if(!$rows){ ?>
        <div class="alert alert-info m-0">No listings found.</div>
      <?php } else { ?>
      <div class="table-responsive">
        <table class="table align-middle table-hover">
          <thead class="table-light">
            <tr>
              <th style="width:72px">Image</th>
              <th>Name</th>
              <th>Species/Breed</th>
              <th>Age/Sex</th>
              <th>Shelter</th>
              <th>City</th>
              <th>Country</th>
              <th>Status</th>
              <th>Visibility</th>
              <th style="width:260px" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r): $img=media_url($ROOT,$r['image']??'','pet'); $s=strtolower((string)$r['status']); $vcol=$r['vis']; ?>
            <tr>
              <td><?php if($img){ ?><img src="<?php echo $img; ?>" alt="" width="60" height="60" style="object-fit:cover;border-radius:12px"><?php } else { ?><span class="badge bg-secondary">No Image</span><?php } ?></td>
              <td class="fw-semibold"><?php echo htmlspecialchars($r['name']?:'—'); ?><div class="small text-muted"><?php echo date('d M Y', strtotime($r['created_at'])); ?></div></td>
              <td><?php echo htmlspecialchars(($r['species']?:'—').($r['breed']?' • '.$r['breed']:'')); ?></td>
              <td><?php echo htmlspecialchars(($r['age']?:'—').($r['sex']?' • '.$r['sex']:'')); ?></td>
              <td><?php echo htmlspecialchars($r['shelter']?:'—'); ?></td>
              <td><?php echo htmlspecialchars($r['city']?:'—'); ?></td>
              <td><?php echo htmlspecialchars($r['country']?:'—'); ?></td>
              <td>
                <?php if($s==='approved'): ?><span class="badge bg-success">Approved</span>
                <?php elseif($s==='pending' || $s===''): ?><span class="badge bg-warning text-dark">Pending</span>
                <?php elseif($s==='adopted'): ?><span class="badge bg-info text-dark">Adopted</span>
                <?php elseif($s==='rejected'): ?><span class="badge bg-secondary">Rejected</span>
                <?php elseif($s==='inactive'): ?><span class="badge bg-dark">Inactive</span>
                <?php else: ?><span class="badge bg-light text-dark"><?php echo htmlspecialchars($r['status']); ?></span><?php endif; ?>
              </td>
              <td>
                <?php if($A_VIS!==null){ ?>
                  <?php if((int)$vcol===1){ ?><span class="badge bg-primary">Shown</span><?php } else { ?><span class="badge bg-secondary">Hidden</span><?php } ?>
                <?php } else { ?>
                  <?php if(in_array($s,['approved','adopted'])){ ?><span class="badge bg-primary">Shown</span><?php } else { ?><span class="badge bg-secondary">Hidden</span><?php } ?>
                <?php } ?>
              </td>
              <td class="text-end">
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-outline-success" name="action" value="approve">Approve</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-outline-secondary" name="action" value="rejected">Reject</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-outline-dark" name="action" value="adopted">Mark Adopted</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-outline-danger" name="action" value="inactive">Deactivate</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-primary" name="action" value="show">Show</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-outline-primary" name="action" value="hide">Hide</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if($pages>1){ ?>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <?php $mk=function($p)use($from,$to,$stat,$vis,$species,$shel,$q){return '?from='.urlencode($from).'&to='.urlencode($to).'&status='.urlencode($stat).'&vis='.urlencode($vis).'&species='.urlencode($species).'&shelter='.$shel.'&q='.urlencode($q).'&p='.$p;}; ?>
        <a class="btn btn-outline-primary btn-sm<?php if($page<=1) echo ' disabled'; ?>" href="<?php echo $mk(max(1,$page-1)); ?>">Prev</a>
        <span class="btn btn-light btn-sm disabled">Page <?php echo $page; ?> / <?php echo $pages; ?></span>
        <a class="btn btn-outline-primary btn-sm<?php if($page>=$pages) echo ' disabled'; ?>" href="<?php echo $mk(min($pages,$page+1)); ?>">Next</a>
      </div>
      <?php } ?>
      <?php } ?>
    </div>
  </div>
</main>
<?php include $ADMIN.'/includes/footer.php'; ?>
