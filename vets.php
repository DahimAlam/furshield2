<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
if (!defined('BASE')) define('BASE', '/furshield');

function e($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ---------- AJAX HANDLER ----------
if (isset($_GET['ajax'])) {
  $q = trim($_GET['q'] ?? '');
  $city = trim($_GET['city'] ?? '');
  $spec = trim($_GET['spec'] ?? '');
  $rating = (int)($_GET['rating'] ?? 0);

  // live suggestions
  if ($_GET['ajax'] === 'suggest') {
    if (strlen($q) < 2) exit;
    $qf = "%$q%";
    $stmt = $conn->prepare("SELECT DISTINCT name FROM vets WHERE name LIKE ? OR specialization LIKE ? OR city LIKE ? LIMIT 8");
    $stmt->bind_param("sss", $qf, $qf, $qf);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
      echo '<li class="list-group-item">' . e($r['name']) . '</li>';
    }
    exit;
  }

  // vets list
  $sql = "SELECT user_id AS id, name, specialization, city, profile_image, 0 as rating FROM vets WHERE 1";
  $params = [];
  $types = '';

  if ($q !== '') {
    $sql .= " AND (name LIKE ? OR specialization LIKE ? OR city LIKE ?)";
    $qf = "%$q%";
    $params = [$qf, $qf, $qf];
    $types .= 'sss';
  }
  if ($city !== '') {
    $sql .= " AND city=?";
    $params[] = $city;
    $types .= 's';
  }
  if ($spec !== '') {
    $sql .= " AND specialization=?";
    $params[] = $spec;
    $types .= 's';
  }

  $sql .= " ORDER BY name ASC";
  $stmt = $conn->prepare($sql);
  if ($types) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();

  if (!$res->num_rows) {
    echo '<p class="text-muted">No vets found.</p>';
    exit;
  }

  while ($r = $res->fetch_assoc()): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card border-0 h-100" style="border-radius:var(--radius);box-shadow:var(--shadow)">
        <img src="<?php echo e(!empty($r['profile_image']) ? BASE . '/' . $r['profile_image'] : BASE . '/assets/placeholder/vet.jpg'); ?>"
          class="card-img-top" style="height:180px;object-fit:cover;border-top-left-radius:var(--radius);border-top-right-radius:var(--radius)">
        <div class="card-body">
          <h5 class="fw-bold mb-1"><?php echo e($r['name']); ?></h5>
          <p class="text-muted mb-1"><?php echo e($r['specialization']); ?></p>
          <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <?php echo e($r['city']); ?></p>
          <div class="mb-2">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="bi <?php echo $i <= ($r['rating'] ?? 0) ? 'bi-star-fill text-warning' : 'bi-star'; ?>"></i>
            <?php endfor; ?>
          </div>
          <a href="<?php echo BASE . '/vet-profile.php?id=' . $r['id']; ?>" class="btn btn-sm text-white" style="background:var(--primary);border-radius:12px">View Profile</a>
        </div>
      </div>
    </div>
<?php endwhile;
  exit;
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>
<div id="fsLoader" class="fs-loader is-hidden" aria-live="polite" aria-busy="true">
  <div class="fs-bar" id="fsLoaderBar"></div>
  <div class="fs-card">
    <div class="fs-logo"><i class="bi bi-shield-heart"></i></div>
    <div class="fs-brand">FurShield</div>
    <div class="fs-spin">
      <div class="fs-ring"></div>
    </div>
    <div class="fs-sub">loading…</div>
  </div>
</div>
<main class="bg-app">
  <!-- Hero -->
  <section class="py-5 text-center" style="background:linear-gradient(120deg,var(--primary),var(--accent));color:#fff">
    <div class="container">
      <h1 class="fw-bold mb-3">Find a Vet</h1>
      <p class="mb-4">Search & connect with trusted vets near you</p>
      <div class="mx-auto position-relative" style="max-width:600px">
        <input type="text" id="vetSearch" class="form-control form-control-lg rounded-pill shadow" placeholder="Search by name, city, or specialization...">
        <ul id="searchSuggest" class="list-group position-absolute w-100 mt-1 shadow d-none" style="z-index:1000;border-radius:var(--radius)"></ul>
      </div>
    </div>
  </section>

  <!-- Filters + Grid -->
  <section class="py-5">
    <div class="container">
      <div class="row g-4">
        <!-- Filters -->
        <aside class="col-lg-3">
          <div class="card border-0 p-3" style="border-radius:var(--radius);box-shadow:var(--shadow)">
            <h5 class="fw-bold mb-3">Filters</h5>
            <div class="mb-3">
              <label class="form-label">City</label>
              <select id="filterCity" class="form-select">
                <option value="">All</option>
                <option>Karachi</option>
                <option>Lahore</option>
                <option>Islamabad</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Specialization</label>
              <select id="filterSpec" class="form-select">
                <option value="">All</option>
                <option>Dermatology</option>
                <option>Dentist</option>
                <option>Surgeon</option>
                <option>General</option>
              </select>
            </div>
          </div>
        </aside>

        <!-- Vets Grid -->
        <div class="col-lg-9">
          <div id="vetsGrid" class="row g-4"></div>
        </div>
      </div>
    </div>
  </section>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  function loadVets() {
    $.get('vets.php', {
      ajax: 'list',
      q: $('#vetSearch').val(),
      city: $('#filterCity').val(),
      spec: $('#filterSpec').val()
    }, function(data) {
      $('#vetsGrid').html(data);
    });
  }

  $('#vetSearch').on('input', function() {
    let q = $(this).val();
    if (q.length < 2) {
      $('#searchSuggest').addClass('d-none');
      return;
    }
    $.get('vets.php', {
      ajax: 'suggest',
      q: q
    }, function(res) {
      if (res.trim() !== '') {
        $('#searchSuggest').removeClass('d-none').html(res);
      } else {
        $('#searchSuggest').addClass('d-none');
      }
    });
  });

  $(document).on('click', '#searchSuggest li', function() {
    $('#vetSearch').val($(this).text());
    $('#searchSuggest').addClass('d-none');
    loadVets();
  });

  $('#filterCity,#filterSpec').on('change', function() {
    loadVets();
  });

  $(document).ready(function() {
    loadVets();
  });
</script>

<style>
  #searchSuggest li {
    cursor: pointer
  }

  #searchSuggest li:hover {
    background: var(--bg)
  }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>