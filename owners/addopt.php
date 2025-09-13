<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/db.php';
if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

$logged_in = !empty($_SESSION['user']);
$role      = $logged_in ? ($_SESSION['user']['role'] ?? '') : '';
$user_id   = $logged_in ? (int)$_SESSION['user']['id'] : 0;

function e($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function media_pet($file){ if(!$file) return BASE.'/assets/placeholder/pet.jpg'; if(str_starts_with($file,'http')) return $file; return BASE.'/uploads/pets/'.$file; }

/* ---- Filters (GET) ---- */
$fspecies = $_GET['species'] ?? 'All';
$fgender  = $_GET['gender']  ?? 'All';
$fage     = $_GET['age']     ?? 'All';   // <1 | 1-3 | >3 | All
$fcity    = $_GET['city']    ?? 'All';
$q        = trim($_GET['q']  ?? '');

/* ---- Filter options ---- */
$speciesOpts = [];
$res = $conn->query("SELECT DISTINCT species FROM addoption WHERE species IS NOT NULL AND species<>'' ORDER BY species ASC");
if($res) $speciesOpts = array_column($res->fetch_all(MYSQLI_ASSOC),'species');

$cities = [];
$res = $conn->query("SELECT DISTINCT city FROM addoption WHERE city IS NOT NULL AND city<>'' ORDER BY city ASC LIMIT 200");
if($res) $cities = array_column($res->fetch_all(MYSQLI_ASSOC),'city');

/* ---- Catalog (DB) ----
   Basic SQL filters for species / gender / city / status.
   Age is often free-text, so we’ll filter by age-range in PHP after fetch.
*/
$sql = "SELECT a.id,a.name,a.species,COALESCE(a.breed,'') AS breed,a.gender,a.age,COALESCE(a.city,'') AS city,a.avatar,a.status,
               u.name AS shelter_name
        FROM addoption a
        JOIN users u ON u.id = a.shelter_id AND u.role='shelter'
        WHERE a.status='available'";

$params = []; $types = '';
if($fspecies!=='All'){ $sql.=" AND a.species=?"; $params[]=$fspecies; $types.='s'; }
if($fgender!=='All'){  $sql.=" AND a.gender=?";  $params[]=$fgender;  $types.='s'; }
if($fcity!=='All'){    $sql.=" AND a.city=?";    $params[]=$fcity;    $types.='s'; }
if($q!==''){           $like = "%$q%"; $sql.=" AND (a.name LIKE ? OR a.breed LIKE ? OR u.name LIKE ?)"; $params[]=$like; $params[]=$like; $params[]=$like; $types.='sss'; }

$sql.=" ORDER BY a.spotlight DESC, a.id DESC LIMIT 60";
$stmt=$conn->prepare($sql);
if($params) $stmt->bind_param($types,...$params);
$stmt->execute();
$catalog = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ---- Age filter in PHP (parse age text to months) ---- */
function age_to_months($t){
  $t=strtolower((string)$t);
  if($t==='') return null;
  // catch patterns like "2 years", "1.5 yrs", "8 months", "10 mo"
  if(preg_match('/(\d+(?:\.\d+)?)\s*y/',$t,$m)){ return (int)round(((float)$m[1])*12); }
  if(preg_match('/(\d+(?:\.\d+)?)\s*m/',$t,$m)){ return (int)round((float)$m[1]); }
  if(is_numeric($t)) return (int)$t; // if stored just "2"
  return null;
}
if($fage!=='All'){
  $catalog = array_values(array_filter($catalog,function($p)use($fage){
    $mo = age_to_months($p['age'] ?? '');
    if($mo===null) return false;
    if($fage==='<1') return $mo<12;
    if($fage==='1-3') return $mo>=12 && $mo<=36;
    if($fage==='>3') return $mo>36;
    return true;
  }));
}

/* ---- My Requests (DB) ---- */
$requests = [];
if($logged_in && $role==='owner'){
  $stmt=$conn->prepare("
    SELECT r.id, r.status, r.created_at, r.updated_at, r.addoption_id,
           a.name AS pet_name, a.species, a.breed, a.city, s.name AS shelter_name, a.avatar
    FROM request r
    JOIN addoption a ON a.id = r.addoption_id
    JOIN users s ON s.id = r.shelter_id
    WHERE r.applicant_id = ?
    ORDER BY r.id DESC
  ");
  $stmt->bind_param('i',$user_id);
  $stmt->execute();
  $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

/* ---- Progress mapping ---- */
function progress_pct($status){
  $map = [
    'pending'=>20,'pre-approved'=>40,'home-visit'=>60,'approved'=>80,'adopted'=>100,
    'rejected'=>0,'cancelled'=>0
  ];
  $k = strtolower((string)$status);
  return $map[$k] ?? 20;
}
function status_chip($status){
  $s=strtolower($status);
  if($s==='approved')    return '<span class="chip ok"><i class="bi bi-check2-circle"></i> Approved</span>';
  if($s==='pre-approved')return '<span class="chip info"><i class="bi bi-patch-check"></i> Pre-approved</span>';
  if($s==='home-visit')  return '<span class="chip warn"><i class="bi bi-house-door"></i> Home-visit</span>';
  if($s==='adopted')     return '<span class="chip ok"><i class="bi bi-award"></i> Adopted</span>';
  if($s==='rejected')    return '<span class="chip danger"><i class="bi bi-x-circle"></i> Rejected</span>';
  if($s==='cancelled')   return '<span class="chip gray"><i class="bi bi-slash-circle"></i> Cancelled</span>';
  return '<span class="chip warn"><i class="bi bi-hourglass-split"></i> Pending</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • Adoption</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <style>
    :root{--primary:#F59E0B;--accent:#EF4444;--bg:#FFF7ED;--text:#1F2937;--card:#FFFFFF;--muted:#6B7280;--border:#f1e6d7;--radius:18px;--shadow:0 10px 30px rgba(0,0,0,.08);--shadow-sm:0 6px 16px rgba(0,0,0,.06)}
    *{box-sizing:border-box} html,body{height:100%} body{margin:0} body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif;line-height:1.5}
    .page{margin-left:280px;padding:28px 24px 60px}
    .page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .page-title h1{margin:0;font-family:Montserrat,sans-serif;font-size:28px}
    .breadcrumbs{font-size:13px;color:var(--muted)} .tag{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid var(--border);font-size:12px}
    .card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .card-head h2{margin:0;font-family:Montserrat,sans-serif;font-size:20px}
    .muted{color:var(--muted)} .nowrap{white-space:nowrap}
    .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px}
    .input,.select{border:1px solid var(--border);background:#fff;border-radius:12px;padding:10px 12px;font-size:14px;outline:0}
    .input:focus,.select:focus{box-shadow:0 0 0 4px #ffe7c6;border-color:#f2cf97}
    .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:10px 14px;cursor:pointer;font-weight:600}
    .btn-primary{background:linear-gradient(135deg,var(--primary),#ffb444);color:#fff}
    .btn-ghost{background:#fff;border:1px solid var(--border);color:#92400e}
    .btn-soft{background:#fff;border:1px solid var(--border)}
    .stat{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid var(--border);font-weight:600}
    .grid-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
    .pet-card{display:grid;grid-template-rows:auto 1fr auto;gap:8px;border:1px solid var(--border);border-radius:16px;background:#fff;overflow:hidden;box-shadow:var(--shadow-sm)}
    .pet-card .thumb{aspect-ratio:16/11;width:100%;object-fit:cover}
    .pet-card .content{padding:12px} .pet-card b{font-family:Montserrat,sans-serif}
    .meta{font-size:13px;color:var(--muted)}
    .chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
    .chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#fff;font-size:12px}
    .chip.ok{background:#ecfdf5;border-color:#bbf7d0;color:#047857}
    .chip.info{background:#eef2ff;border-color:#e0e7ff;color:#3730a3}
    .chip.warn{background:#fff7ed;border-color:#fde68a;color:#b45309}
    .chip.danger{background:#fee2e2;border-color:#fecaca;color:#b91c1c}
    .chip.gray{background:#f3f4f6;border-color:#e5e7eb;color:#374151}
    .card-actions{display:flex;gap:8px;align-items:center;padding:12px;border-top:1px dashed #f3e7d9}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:999px;background:linear-gradient(135deg,var(--primary),#ffb444);color:#fff;font-weight:600;font-size:13px;text-decoration:none}
    .pill.ghost{background:#fff;border:1px solid var(--border);color:#92400e}
    .table-wrap{overflow:auto;border:1px solid var(--border);border-radius:14px}
    table{width:100%;border-collapse:separate;border-spacing:0}
    thead th{position:sticky;top:0;background:#fff7ef;border-bottom:1px solid var(--border);text-align:left;font-size:13px;padding:12px;color:#92400e}
    tbody td{padding:12px;border-bottom:1px solid #f6efe4;font-size:14px;vertical-align:middle} tbody tr:hover{background:#fffdfa}
    .bar{height:8px;background:#f3efe7;border-radius:999px;overflow:hidden;border:1px solid var(--border)} .bar>i{display:block;height:100%;background:linear-gradient(90deg,#fbbf24,#f59e0b)}
    .icon-btn{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:#fff;border:1px solid var(--border);cursor:pointer}
    .icon-btn:hover{box-shadow:var(--shadow-sm);transform:translateY(-1px)}
    .modal{position:fixed;inset:0;background:rgba(0,0,0,.18);display:none;align-items:center;justify-content:center;padding:20px;z-index:999}
    .modal.show{display:flex}
    .dialog{max-width:860px;width:100%;background:#fff;border-radius:20px;box-shadow:var(--shadow);border:1px solid var(--border)}
    .dialog header{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
    .dialog .body{padding:16px}
    .close{border:0;background:#fff;border:1px solid var(--border);border-radius:10px;width:36px;height:36px;display:grid;place-items:center;cursor:pointer}
    .steps{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:10px}
    .step{display:flex;flex-direction:column;gap:6px;align-items:center;text-align:center}
    .circle{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;border:2px solid #e5e7eb;background:#fff;font-weight:700}
    .step.done .circle{border-color:#86efac;background:#ecfdf5;color:#065f46}
    .step.active .circle{border-color:#f59e0b;background:#fff7ed;color:#92400e}
    .step small{font-size:11px;color:#6b7280}
    .timeline{border-left:2px dashed #f0e7da;margin-left:18px;padding-left:14px;display:grid;gap:10px}
    .tl{position:relative}
    .tl::before{content:"";position:absolute;left:-20px;top:4px;width:10px;height:10px;border-radius:50%;background:#f59e0b}
    .tl .when{font-size:12px;color:#6b7280}
    .tl .what{font-weight:600}
    @media (max-width:1280px){.grid-cards{grid-template-columns:repeat(3,1fr)}}
    @media (max-width:860px){.grid-cards{grid-template-columns:repeat(2,1fr)}}
    @media (max-width:640px){.page{margin-left:0}.grid-cards{grid-template-columns:1fr}}
  </style>
</head>
<body class="bg-app">

<?php include __DIR__."/sidebar.php"; ?>

<main class="page">
  <div class="page-head">
    <div class="page-title">
      <div class="breadcrumbs">Owner • Adoption</div>
      <h1>Adoption</h1>
    </div>
    <span class="tag"><i class="bi bi-heart"></i> Find a Friend</span>
  </div>

  <section class="card">
    <div class="card-head">
      <h2>Browse Pets</h2>
      <div class="stat"><i class="bi bi-clipboard-heart"></i> Requests:
        <span><?= $logged_in && $role==='owner' ? count($requests) : 0 ?></span>
      </div>
    </div>

    <form class="toolbar" method="get">
      <select class="select" name="species">
        <option value="All">All Species</option>
        <?php foreach($speciesOpts as $sp){ ?>
          <option value="<?=e($sp)?>" <?= $fspecies===$sp?'selected':''; ?>><?=e($sp)?></option>
        <?php } ?>
      </select>

      <select class="select" name="gender">
        <option value="All">Any Gender</option>
        <option <?= $fgender==='Male'?'selected':''; ?>>Male</option>
        <option <?= $fgender==='Female'?'selected':''; ?>>Female</option>
      </select>

      <select class="select" name="age">
        <option value="All" <?= $fage==='All'?'selected':''; ?>>Any Age</option>
        <option value="<1"  <?= $fage==='<1'?'selected':''; ?>>Less than 1y</option>
        <option value="1-3" <?= $fage==='1-3'?'selected':''; ?>>1–3 years</option>
        <option value=">3"  <?= $fage==='>3'?'selected':''; ?>>Over 3y</option>
      </select>

      <select class="select" name="city">
        <option value="All">All Cities</option>
        <?php foreach($cities as $c){ ?>
          <option value="<?=e($c)?>" <?= $fcity===$c?'selected':''; ?>><?=e($c)?></option>
        <?php } ?>
      </select>

      <input class="input" name="q" value="<?=e($q)?>" placeholder="Search name, breed, shelter…"/>
      <button class="btn btn-ghost"><i class="bi bi-search"></i> Filter</button>
      <a class="btn btn-soft" href="adopt.php"><i class="bi bi-eraser"></i> Clear</a>
    </form>

    <div class="grid-cards">
      <?php if(!$catalog){ echo '<div class="muted" style="grid-column:1/-1;padding:16px">No pets found.</div>'; } ?>
      <?php foreach($catalog as $p){ ?>
        <div class="pet-card">
          <img class="thumb" src="<?=e(media_pet($p['avatar']))?>" alt="<?=e($p['name'])?>">
          <div class="content">
            <b><?=e($p['name'])?></b>
            <div class="meta"><?=e($p['species'])?> • <?=e($p['breed']?:'-')?> • <?=e($p['gender']?:'-')?> • <?=e($p['age']?:'-')?></div>
            <div class="meta"><i class="bi bi-geo-alt"></i> <?=e($p['city']?:'-')?> • <?=e($p['shelter_name']?:'Shelter')?></div>
          </div>
          <div class="card-actions">
            <?php
              $applyHref = BASE.'/pet.php?id='.(int)$p['id'].'#apply';
              if(!$logged_in){
                $applyHref = BASE.'/login.php?next='.urlencode(BASE.'/pet.php?id='.(int)$p['id'].'#apply');
              } elseif($role!=='owner'){
                $applyHref = BASE.'/pet.php?id='.(int)$p['id'];
              }
            ?>
            <a class="pill" href="<?=$applyHref?>"><i class="bi bi-heart"></i> Apply</a>
            <a class="pill ghost" href="<?=BASE.'/pet.php?id='.(int)$p['id']?>"><i class="bi bi-eye"></i> Details</a>
          </div>
        </div>
      <?php } ?>
    </div>
  </section>

  <section class="card" style="margin-top:16px">
    <div class="card-head">
      <h2>My Requests</h2>
      <div class="muted"><?= $logged_in && $role==='owner' ? '' : 'Login as owner to view your requests.' ?></div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Pet</th>
            <th>Species • Breed</th>
            <th>City • Shelter</th>
            <th class="nowrap">Applied On</th>
            <th>Progress</th>
            <th>Status</th>
            <th style="width:210px">Actions</th>
          </tr>
        </thead>
        <tbody id="reqTbody">
        <?php if(!($logged_in && $role==='owner')){ ?>
          <tr><td colspan="7" class="muted" style="text-align:center;padding:20px">Please login as Owner.</td></tr>
        <?php } elseif(!$requests){ ?>
          <tr><td colspan="7" class="muted" style="text-align:center;padding:20px">No adoption requests yet.</td></tr>
        <?php } else { foreach($requests as $r){
            $pct = progress_pct($r['status']);
        ?>
          <tr data-id="<?= (int)$r['id'] ?>"
              data-name="<?= e($r['pet_name']) ?>"
              data-status="<?= e($r['status']) ?>"
              data-created="<?= e(substr($r['created_at'],0,10)) ?>"
              data-updated="<?= e(substr($r['updated_at'],0,10)) ?>">
            <td><b><?=e($r['pet_name'])?></b></td>
            <td><?=e($r['species'])?><?= $r['breed'] ? ' • '.e($r['breed']) : '' ?></td>
            <td><?=e($r['city'])?> • <span class="muted"><?=e($r['shelter_name'])?></span></td>
            <td><?=e(date('Y-m-d', strtotime($r['created_at'])))?></td>
            <td>
              <div class="bar" style="width:160px"><i style="width: <?=$pct?>%"></i></div>
              <div class="muted" style="font-size:12px"><?=$pct?>% • Step <?= ($pct?ceil($pct/20):1) ?> of 5</div>
            </td>
            <td><?= status_chip($r['status']) ?></td>
            <td>
              <button class="icon-btn" data-action="track" title="Track"><i class="bi bi-geo"></i></button>
              <a class="icon-btn" title="View Pet" href="<?= BASE.'/pet.php?id='.(int)$r['addoption_id'] ?>"><i class="bi bi-eye"></i></a>
              <?php if (strtolower($r['status'])==='pending'){ ?>
                <a class="icon-btn" title="Cancel" href="<?= BASE.'/actions/adopt-cancel.php?id='.(int)$r['id'] ?>" onclick="return confirm('Cancel this request?')"><i class="bi bi-slash-circle"></i></a>
              <?php } ?>
            </td>
          </tr>
        <?php } } ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<div class="modal" id="trackModal" aria-hidden="true">
  <div class="dialog">
    <header>
      <strong>Adoption Tracking</strong>
      <button class="close" data-close><i class="bi bi-x"></i></button>
    </header>
    <div class="body">
      <div id="trackHeader" class="muted" style="margin-bottom:8px"></div>
      <div class="steps" id="stepper"></div>
      <div class="bar" style="margin:8px 0 2px"><i id="trackBar" style="width:0%"></i></div>
      <div id="trackPercent" class="muted" style="font-size:12px"></div>
      <div style="margin-top:14px;font-weight:600">Timeline</div>
      <div class="timeline" id="timeline"></div>
      <div id="nextHint" class="muted" style="margin-top:12px"></div>
    </div>
  </div>
</div>

<script>
(function(){
  const FLOW = ['Pending','Pre-approved','Home-visit','Approved','Adopted'];
  const STEP_LABEL = {'Pending':'Submitted','Pre-approved':'Pre-approved','Home-visit':'Home-visit','Approved':'Approved','Adopted':'Adopted'};
  const trackModal = document.getElementById('trackModal');
  const trackHeader = document.getElementById('trackHeader');
  const stepper = document.getElementById('stepper');
  const trackBar = document.getElementById('trackBar');
  const trackPercent = document.getElementById('trackPercent');
  const timeline = document.getElementById('timeline');
  const nextHint = document.getElementById('nextHint');

  function pct(status){
    const map = {'pending':20,'pre-approved':40,'home-visit':60,'approved':80,'adopted':100,'rejected':0,'cancelled':0};
    return map[(status||'').toLowerCase()] ?? 20;
  }
  function stepIndex(status){
    const i = FLOW.indexOf(status); return i<0?0:i;
  }
  function openTrack(row){
    const name = row.dataset.name;
    const status = row.dataset.status;
    const created = row.dataset.created;
    const updated = row.dataset.updated;

    const p = pct(status);
    trackHeader.textContent = `${name} • ${status}`;
    stepper.innerHTML = FLOW.map((s,i)=>{
      const cls = s===status ? 'step active' : (i<=stepIndex(status) ? 'step done' : 'step');
      return `<div class="${cls}"><div class="circle">${i+1}</div><small>${STEP_LABEL[s]}</small></div>`;
    }).join('');
    trackBar.style.width = p+'%';
    trackPercent.textContent = `${p}% complete • Step ${stepIndex(status)+1} of ${FLOW.length}`;
    const items = [];
    if (created) items.push({when: created, what: 'Submitted'});
    if (updated && updated!==created && p>20) items.push({when: updated, what: STEP_LABEL[status]||status});
    timeline.innerHTML = items.map(ev=>`<div class="tl"><div class="when">${ev.when}</div><div class="what">${ev.what}</div></div>`).join('') || '<div class="muted">No events yet.</div>';
    const i = stepIndex(status);
    const nxt = i<FLOW.length-1 ? FLOW[i+1] : null;
    nextHint.innerHTML = nxt ? `<i class="bi bi-lightbulb"></i> Next: <b>${STEP_LABEL[nxt]}</b>. Shelter will update this step.` : `<i class="bi bi-emoji-smile"></i> Process complete.`;
    trackModal.classList.add('show');
  }
  function closeTrack(){ trackModal.classList.remove('show'); }

  document.getElementById('reqTbody')?.addEventListener('click', (e)=>{
    const btn = e.target.closest('button.icon-btn');
    if(!btn) return;
    if(btn.dataset.action==='track'){
      const tr = btn.closest('tr'); if(tr) openTrack(tr);
    }
  });
  document.body.addEventListener('click',(e)=>{
    if(e.target.matches('[data-close]')) closeTrack();
    if(e.target===trackModal) closeTrack();
  });
})();
</script>
</body>
</html>
