<?php
require_once("../includes/db.php");
require_once("../includes/auth.php");
require_role('admin');
include("includes/header.php");

$totUsers = (int)$conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$roleCounts = [];
$rcRes = $conn->query("SELECT role, COUNT(*) c FROM users GROUP BY role");
while($r=$rcRes->fetch_assoc()){ $roleCounts[$r['role']] = (int)$r['c']; }

$totPets = (int)$conn->query("SELECT COUNT(*) c FROM pets")->fetch_assoc()['c'];
$featPets = (int)$conn->query("SELECT COUNT(*) c FROM pets WHERE COALESCE(featured,0)=1")->fetch_assoc()['c'];

$totProducts = (int)$conn->query("SELECT COUNT(*) c FROM products")->fetch_assoc()['c'];
$featProducts = (int)$conn->query("SELECT COUNT(*) c FROM products WHERE COALESCE(featured,0)=1")->fetch_assoc()['c'];

$pendingVets = (int)$conn->query("SELECT COUNT(*) c FROM users WHERE role='vet' AND status='pending'")->fetch_assoc()['c'];
$pendingShelters = (int)$conn->query("SELECT COUNT(*) c FROM users WHERE role='shelter' AND status='pending'")->fetch_assoc()['c'];
$totApprovals = $pendingVets + $pendingShelters;

$signups = [];
$labels = [];
$start = new DateTime('-13 days'); $end = new DateTime('today');
$period = new DatePeriod($start, new DateInterval('P1D'), (clone $end)->modify('+1 day'));
foreach($period as $d){ $labels[] = $d->format('M d'); $signups[$d->format('Y-m-d')] = 0; }
$res = $conn->query("SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)");
while($r=$res->fetch_assoc()){ $signups[$r['d']] = (int)$r['c']; }
$signupValues = array_values($signups);

$topProducts = $conn->query("SELECT name, stock_qty FROM products ORDER BY stock_qty DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);

$featuredVets = $conn->query("
  SELECT u.id, u.name, v.specialization, v.experience_years
  FROM users u JOIN vets v ON v.user_id=u.id
  WHERE u.role='vet' AND u.status='active'
  ORDER BY v.experience_years DESC, u.created_at DESC LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

$pendingList = $conn->query("
  SELECT name, email, role, created_at
  FROM users
  WHERE status='pending' AND role IN ('vet','shelter')
  ORDER BY created_at ASC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$recentUsers = $conn->query("
  SELECT name, role, status, created_at
  FROM users
  ORDER BY created_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$latestPets = $conn->query("
  SELECT p.name, p.species, u.name AS owner_name
  FROM pets p JOIN users u ON u.id=p.user_id
  ORDER BY p.id DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$latestBlogs = $conn->query("
  SELECT title, created_at
  FROM blogs
  ORDER BY created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$chartPayload = [
  'labels' => $labels,
  'signups' => $signupValues,
  'roles' => [
    'owner' => $roleCounts['owner'] ?? 0,
    'vet' => $roleCounts['vet'] ?? 0,
    'shelter' => $roleCounts['shelter'] ?? 0,
    'admin' => $roleCounts['admin'] ?? 0,
  ],
];
?>
<div class="container-fluid p-4">
  <h3 class="mb-4"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</h3>

  <div class="row g-3">
    <div class="col-md-3">
      <div class="kpi-card">
        <i class="fa-solid fa-users icon"></i>
        <div class="label">Total Users</div>
        <div class="value"><?php echo $totUsers; ?></div>
        <div class="sub">Owners <?php echo $roleCounts['owner'] ?? 0; ?> • Vets <?php echo $roleCounts['vet'] ?? 0; ?> • Shelters <?php echo $roleCounts['shelter'] ?? 0; ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="kpi-card">
        <i class="fa-solid fa-paw icon"></i> 
        <div class="label">Pets</div>
        <div class="value"><?php echo $totPets; ?></div>
        <div class="sub"><?php echo $featPets; ?> featured</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="kpi-card">
        <i class="fa-solid fa-box icon"></i> 
        <div class="label">Products</div>
        <div class="value"><?php echo $totProducts; ?></div>
        <div class="sub"><?php echo $featProducts; ?> featured</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="kpi-card">
        <i class="fa-solid fa-circle-check icon"></i>
        <div class="label">Approvals</div>
        <div class="value"><?php echo $totApprovals; ?></div>
        <div class="sub"><?php echo $pendingVets; ?> vets • <?php echo $pendingShelters; ?> shelters</div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-8">
      <div class="chart-card">
        <h5 class="mb-3">Signups — Last 14 days</h5>
        <canvas id="signupChart"></canvas>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="chart-card">
        <h5 class="mb-3">User Roles</h5>
        <canvas id="rolesChart"></canvas>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-4">
      <div class="list-card">
        <h6>Top Products</h6>
        <ul class="list-group list-group-flush">
          <?php foreach($topProducts as $tp): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?php echo htmlspecialchars($tp['name']); ?></span>
              <span class="badge bg-secondary"><?php echo (int)$tp['stock_qty']; ?></span>
            </li>
          <?php endforeach; if(!$topProducts) echo "<li class='list-group-item text-muted'>No products</li>"; ?>
        </ul>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="list-card">
        <h6>Featured Vets</h6>
        <?php foreach($featuredVets as $v): ?>
          <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div>
              <div class="fw-semibold"><?php echo htmlspecialchars($v['name']); ?></div>
              <div class="small text-muted"><?php echo htmlspecialchars($v['specialization']); ?> • <?php echo (int)$v['experience_years']; ?> yrs</div>
            </div>
            <a href="<?php echo BASE; ?>/admin/vets.php" class="btn btn-sm btn-outline-primary">View</a>
          </div>
        <?php endforeach; if(!$featuredVets) echo "<div class='text-muted small'>No vets</div>"; ?>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="list-card">
        <h6>Pending Approvals</h6>
        <ul class="list-group list-group-flush">
          <?php foreach($pendingList as $p): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span><?php echo htmlspecialchars($p['name']); ?> <small class="text-muted">(<?php echo $p['role']; ?>)</small></span>
              <div>
                <a href="<?php echo BASE; ?>/admin/actions/approve_user.php?email=<?php echo urlencode($p['email']); ?>" class="btn btn-sm btn-success"><i class="bi bi-check"></i></a>
                <a href="<?php echo BASE; ?>/admin/actions/reject_user.php?email=<?php echo urlencode($p['email']); ?>" class="btn btn-sm btn-danger"><i class="bi bi-x"></i></a>
              </div>
            </li>
          <?php endforeach; if(!$pendingList) echo "<li class='list-group-item text-muted'>None</li>"; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-4">
      <div class="list-card">
        <h6>Recent Users</h6>
        <ul class="list-group list-group-flush">
          <?php foreach($recentUsers as $u): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?php echo htmlspecialchars($u['name']); ?> <small class="text-muted">(<?php echo $u['role']; ?>)</small></span>
              <span class="small text-muted"><?php echo date('M d', strtotime($u['created_at'])); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="list-card">
        <h6>Latest Pets</h6>
        <ul class="list-group list-group-flush">
          <?php foreach($latestPets as $p): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?php echo htmlspecialchars($p['name']); ?></span>
              <span class="small text-muted"><?php echo htmlspecialchars($p['species']); ?> • <?php echo htmlspecialchars($p['owner_name']); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="list-card">
        <h6>Latest Blogs</h6>
        <ul class="list-group list-group-flush">
          <?php foreach($latestBlogs as $b): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?php echo htmlspecialchars($b['title']); ?></span>
              <span class="small text-muted"><?php echo date('M d', strtotime($b['created_at'])); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <script>
    window.DASHBOARD_DATA = <?php echo json_encode($chartPayload); ?>;
  </script>
</div>
<?php include("includes/footer.php"); ?>
