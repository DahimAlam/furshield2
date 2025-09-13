<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('vet');

$uid = (int)($_SESSION['user']['id'] ?? 0);

function table_exists(mysqli $c, string $t): bool {
  $t = $c->real_escape_string($t);
  $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'");
  return $r && $r->num_rows>0;
}
function col_exists(mysqli $c, string $t, string $col): bool {
  $t = $c->real_escape_string($t); $col = $c->real_escape_string($col);
  $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
  return $r && $r->num_rows>0;
}
function pick_col(mysqli $c, string $t, array $cands): ?string {
  foreach($cands as $x){ if(col_exists($c,$t,$x)) return $x; }
  return null;
}

/* map vets.id if you link users->vets */
$vetProfileId = null;
if (table_exists($conn,'vets') && col_exists($conn,'vets','user_id') && col_exists($conn,'vets','id')) {
  $q=$conn->prepare("SELECT id FROM vets WHERE user_id=? LIMIT 1");
  $q->bind_param("i",$uid); $q->execute(); $q->bind_result($vetProfileId); $q->fetch(); $q->close();
}
$whoA=$uid; $whoB=$vetProfileId ?? -1;

/* ---- discover reviews table/columns ---- */
$tab = table_exists($conn,'vet_reviews') ? 'vet_reviews' : (table_exists($conn,'reviews') ? 'reviews' : null);
if (!$tab) { http_response_code(500); exit('reviews table missing'); }

$C_ID     = pick_col($conn,$tab,['id','review_id']);
$C_VET    = pick_col($conn,$tab,['vet_id','vet_user_id','vet_profile_id','doctor_id','doctorId','vet']);
$C_OWNER  = pick_col($conn,$tab,['owner_id','user_id','customer_id']);
$C_RATE   = pick_col($conn,$tab,['rating','stars','score']);
$C_TEXT   = pick_col($conn,$tab,['comment','review','message','text']);
$C_DATE   = pick_col($conn,$tab,['created_at','created','date']);

if (!$C_ID || !$C_RATE) { http_response_code(500); exit('reviews key columns missing'); }

/* ---- filters & pagination ---- */
$minStar = (int)($_GET['min'] ?? 0); if ($minStar < 0 || $minStar > 5) $minStar = 0;
$from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1,(int)($_GET['p'] ?? 1)); $limit=20; $offset = ($page-1)*$limit;

$w=[]; $bind=[]; $bt='';
if ($C_VET){ $w[]="r.`$C_VET` IN (?,?)"; $bind[]=$whoA; $bind[]=$whoB; $bt.='ii'; }
if ($minStar>0){ $w[]="ROUND(r.`$C_RATE`) >= ?"; $bind[]=$minStar; $bt.='i'; }
if ($from!==''){ $w[]="DATE(".($C_DATE ? "r.`$C_DATE`" : "NOW()").") >= ?"; $bind[]=$from; $bt.='s'; }
if ($to!==''){ $w[]="DATE(".($C_DATE ? "r.`$C_DATE`" : "NOW()").") <= ?"; $bind[]=$to; $bt.='s'; }
$where = $w?('WHERE '.implode(' AND ',$w)):'';

/* count + avg + distribution */
$avg=0.0; $total=0; $dist=array_fill(1,5,0);

$sql = "SELECT COALESCE(AVG(r.`$C_RATE`),0), COUNT(*) FROM `$tab` r $where";
$st=$conn->prepare($sql); if($bt) $st->bind_param($bt, ...$bind); $st->execute(); $st->bind_result($avg,$total); $st->fetch(); $st->close();

$sql = "SELECT CAST(ROUND(r.`$C_RATE`) AS UNSIGNED) star, COUNT(*) c FROM `$tab` r $where GROUP BY star";
$st=$conn->prepare($sql); if($bt) $st->bind_param($bt, ...$bind); $st->execute(); $res=$st->get_result();
while($x=$res->fetch_assoc()){ $s=(int)$x['star']; if($s>=1 && $s<=5) $dist[$s]=(int)$x['c']; }
$st->close();

/* fetch paged list */
$ownerJoin = ( $C_OWNER && table_exists($conn,'users') && col_exists($conn,'users','id') && col_exists($conn,'users','name') )
            ? "LEFT JOIN users u ON u.id = r.`$C_OWNER`" : "";
$ownerSel = $ownerJoin ? "COALESCE(u.name,'Anonymous')" : "'Anonymous'";
$textSel = $C_TEXT ? "r.`$C_TEXT`" : "''";
$dateSel = $C_DATE ? "r.`$C_DATE`" : "NOW()";
$rateSel = "r.`$C_RATE`";

$searchPost = $q!=='' ? " AND (".($C_TEXT ? "LOWER($textSel) LIKE ?" : "0")." ".($ownerJoin ? " OR LOWER(u.name) LIKE ?" : "").")" : "";
if ($q!==''){
  $qLike = '%'.mb_strtolower($q).'%';
  if ($C_TEXT && $ownerJoin){ $bind2 = array_merge($bind, [$qLike,$qLike]); $bt2=$bt.'ss'; }
  elseif ($C_TEXT){ $bind2 = array_merge($bind, [$qLike]); $bt2=$bt.'s'; }
  elseif ($ownerJoin){ $bind2 = array_merge($bind, [$qLike]); $bt2=$bt.'s'; }
  else { $bind2 = $bind; $bt2=$bt; }
} else { $bind2=$bind; $bt2=$bt; }

$sql = "SELECT r.`$C_ID` AS id, $rateSel AS rating, $textSel AS comment, $dateSel AS created_at, $ownerSel AS owner_name
        FROM `$tab` r
        $ownerJoin
        $where".($searchPost ? $searchPost : '')."
        ORDER BY $dateSel DESC
        LIMIT $limit OFFSET $offset";
$st=$conn->prepare($sql); if($bt2) $st->bind_param($bt2, ...$bind2); $st->execute(); $res=$st->get_result();
$rows=[]; while($x=$res->fetch_assoc()) $rows[]=$x; $st->close();

$pages = max(1, (int)ceil($total/$limit));

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="main">
  <div class="container-fluid py-4">

    <div class="row g-3">
      <div class="col-12 col-xl-4">
        <div class="cardx p-3 h-100">
          <h5 class="mb-2">Overview</h5>
          <div class="d-flex align-items-center gap-3">
            <div class="display-6 fw-bold"><?php echo number_format((float)$avg,1); ?></div>
            <div class="stars-lg" aria-label="Average rating">
              <?php
                $full = floor($avg); $half = ($avg - $full) >= 0.5;
                for($i=1;$i<=5;$i++){
                  if($i <= $full) echo '<span class="star full">★</span>';
                  elseif($i===$full+1 && $half) echo '<span class="star half">★</span>';
                  else echo '<span class="star">☆</span>';
                }
              ?>
            </div>
          </div>
          <div class="text-muted small mb-2"><?php echo number_format($total); ?> reviews</div>
          <canvas id="distChart" height="140"></canvas>
        </div>
      </div>

      <div class="col-12 col-xl-8">
        <div class="cardx p-3">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h5 class="m-0">Reviews</h5>
            <form class="d-flex gap-2 align-items-end" method="get">
              <div>
                <label class="form-label small text-muted mb-1">Min rating</label>
                <select name="min" class="form-select">
                  <option value="0"<?php if($minStar===0) echo ' selected'; ?>>Any</option>
                  <?php for($i=5;$i>=1;$i--){ ?>
                    <option value="<?php echo $i; ?>"<?php if($minStar===$i) echo ' selected'; ?>><?php echo $i; ?>★ & up</option>
                  <?php } ?>
                </select>
              </div>
              <div>
                <label class="form-label small text-muted mb-1">From</label>
                <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control"/>
              </div>
              <div>
                <label class="form-label small text-muted mb-1">To</label>
                <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control"/>
              </div>
              <div>
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control" placeholder="owner / text"/>
              </div>
              <div>
                <button class="btn btn-primary">Apply</button>
              </div>
            </form>
          </div>

          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th style="width:110px">Rating</th>
                  <th>Review</th>
                  <th style="width:180px">Owner</th>
                  <th style="width:160px">Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if(!$rows){ ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">No reviews</td></tr>
                <?php } else { foreach($rows as $r){ ?>
                  <tr>
                    <td>
                      <div class="stars">
                        <?php $stars = (int)round($r['rating'] ?? 0);
                          for($i=1;$i<=5;$i++) echo $i <= $stars ? '<span class="star full">★</span>' : '<span class="star">☆</span>';
                        ?>
                      </div>
                      <div class="small text-muted"><?php echo number_format((float)$r['rating'],1); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($r['comment'] ?: ''); ?></td>
                    <td class="fw-semibold"><?php echo htmlspecialchars($r['owner_name']); ?></td>
                    <td class="nowrap"><?php echo date('d M Y, h:i A', strtotime($r['created_at'])); ?></td>
                  </tr>
                <?php } } ?>
              </tbody>
            </table>
          </div>

          <?php if($pages>1){ ?>
            <div class="d-flex justify-content-end gap-2 mt-3">
              <?php
                $mk = function($p) use($minStar,$from,$to,$q){
                  return '?min='.$minStar.'&from='.urlencode($from).'&to='.urlencode($to).'&q='.urlencode($q).'&p='.$p;
                };
              ?>
              <a class="btn btn-outline-primary btn-sm<?php if($page<=1) echo ' disabled'; ?>" href="<?php echo $mk(max(1,$page-1)); ?>">Prev</a>
              <span class="btn btn-light btn-sm disabled">Page <?php echo $page; ?> / <?php echo $pages; ?></span>
              <a class="btn btn-outline-primary btn-sm<?php if($page>=$pages) echo ' disabled'; ?>" href="<?php echo $mk(min($pages,$page+1)); ?>">Next</a>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
// distribution chart
const dist = <?php echo json_encode([$dist[1],$dist[2],$dist[3],$dist[4],$dist[5]]); ?>;
new Chart(document.getElementById('distChart'),{
  type:'bar',
  data:{ labels:['1★','2★','3★','4★','5★'], datasets:[{ label:'Count', data:dist }]},
  options:{plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
