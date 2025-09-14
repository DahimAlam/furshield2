<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
$conn->set_charset('utf8mb4');

/* ---------- helpers ---------- */
if (!function_exists('rows')) {
  function rows(mysqli $conn, string $sql, array $params = [], string $types = ''): array {
    if (!$params) {
      $res = $conn->query($sql);
      return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    $stmt = $conn->prepare($sql);
    if ($types === '') $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  }
}

if (!function_exists('hascol')) {
  function hascol(mysqli $conn, string $table, string $col): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($col);
    $q = $conn->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='{$t}' AND column_name='{$c}'");
    return $q && $q->num_rows > 0;
  }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pet_img(?string $file): string {
  if (!$file) return '/assets/placeholder/pet.jpg'; // No BASE needed
  if (str_starts_with($file, 'http')) return $file;
  return '/uploads/pets/' . rawurlencode($file); // No BASE needed
}

/* ---------- i18n labels ---------- */
$lang = $_SESSION['lang'] ?? 'en';
$t = [
  'en' => [
    'pets' => 'Pets',
    'search' => 'Search pets…',
    'adopt_now' => 'Adopt Now',
    'filter' => 'Filter',
    'how_h' => 'How to Adopt',
    'browse' => 'Browse',
    'apply' => 'Apply',
    'contact' => 'Contact',
    'welcome' => 'Welcome',
    'spotlight' => 'Urgent Adoptions',
    'city' => 'City',
    'species' => 'Species',
    'breed' => 'Breed',
    'view_all' => 'View All',
    'testimonials' => 'What Pet Parents Say',
    'adoption_stats' => 'Adoption Impact',
    'newsletter' => 'Stay Updated',
    'sub_btn' => 'Subscribe',
    'shelter_cta_h' => 'Support a Shelter',
    'shelter_cta_p' => 'Donate or help a local shelter.',
  ]
];

$hasSpotlight   = hascol($conn,'addoption','spotlight');
$hasUrgentUntil = hascol($conn,'addoption','urgent_until');

$cities  = rows($conn, "SELECT DISTINCT city FROM addoption WHERE city IS NOT NULL AND city<>'' ORDER BY city");
$species = rows($conn, "SELECT DISTINCT species FROM addoption WHERE species IS NOT NULL AND species<>'' ORDER BY species");
$breeds  = rows($conn, "SELECT DISTINCT breed FROM addoption WHERE breed IS NOT NULL AND breed<>'' ORDER BY breed");

$q    = trim($_GET['q'] ?? '');
$city = trim($_GET['city'] ?? '');
$sp   = trim($_GET['species'] ?? '');
$br   = trim($_GET['breed'] ?? '');

$where  = "WHERE status='available'";
$params = [];
if ($q !== '') {
  $where .= " AND (name LIKE CONCAT('%',?,'%') OR species LIKE CONCAT('%',?,'%') OR breed LIKE CONCAT('%',?,'%'))";
  array_push($params, $q, $q, $q);
}
if ($city !== '') { $where .= " AND city=?";    $params[] = $city; }
if ($sp   !== '') { $where .= " AND species=?"; $params[] = $sp; }
if ($br   !== '') { $where .= " AND breed=?";   $params[] = $br; }

$order      = $hasSpotlight ? "spotlight DESC, id DESC" : "id DESC";
$spotSelect = $hasSpotlight ? "spotlight" : "0 AS spotlight";

/* ---------- pets grid (from addoption) ---------- */
$pets = rows(
  $conn,
  "SELECT id,name,species,COALESCE(breed,'') AS breed,COALESCE(city,'') AS city, avatar, {$spotSelect}
   FROM addoption
   {$where}
   ORDER BY {$order}
   LIMIT 12",
  $params
);

/* ---------- urgent/spotlight strip ---------- */
$urgent = [];
if ($hasSpotlight || $hasUrgentUntil) {
  $conds = [];
  if ($hasSpotlight)   $conds[] = "spotlight=1";
  if ($hasUrgentUntil) $conds[] = "(urgent_until IS NOT NULL AND urgent_until >= CURDATE())";
  if ($conds) {
    $urgent = rows(
      $conn,
      "SELECT id,name FROM addoption
       WHERE status='available' AND (" . implode(' OR ', $conds) . ")
       ORDER BY id DESC
       LIMIT 6"
    );
  }
}

/* ---------- stats ---------- */
$totalAdoptions = (int)($conn->query("SELECT COUNT(*) c FROM addoption WHERE status='adopted'")->fetch_assoc()['c'] ?? 0);
$totalUsers     = (int)($conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0);
$totalPets      = (int)($conn->query("SELECT COUNT(*) c FROM addoption")->fetch_assoc()['c'] ?? 0);

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/style.css"> <!-- No BASE needed -->
<div id="fsLoader" class="fs-loader is-hidden" aria-live="polite" aria-busy="true">
  <div class="fs-bar" id="fsLoaderBar"></div>
  <div class="fs-card">
    <div class="fs-logo"><i class="bi bi-shield-heart"></i></div>
    <div class="fs-brand">FurShield</div>
    <div class="fs-spin"><div class="fs-ring"></div></div>
    <div class="fs-sub">loading…</div>
  </div>
</div>
<main class="bg-app">

  <!-- HERO + SEARCH -->
  <section class="py-5 hero-search text-center">
    <div class="container">
      <h1 class="display-5 fw-bold">Find Your Perfect Companion</h1>
      <p class="lead text-muted">Explore lovable pets and bring joy into your home.</p>

      <form action="" method="get" class="adopt-search mx-auto">
        <input type="text" id="searchInput" name="q" value="<?php echo h($q); ?>" class="form-control form-control-lg search-input" placeholder="<?php echo $t[$lang]['search']; ?>" autocomplete="off" aria-label="Search pets">
        <button class="btn btn-lg btn-primary search-btn" aria-label="Search"><?php echo $t[$lang]['pets']; ?></button>

        <div id="suggestionBox" class="suggestion-box" role="listbox" aria-label="Search suggestions"></div>
      </form>

      <div class="quick-tags">
        <a href="?species=Dog" class="tag">Dog</a>
        <a href="?species=Cat" class="tag">Cat</a>
        <a href="?species=Bird" class="tag">Bird</a>
        <a href="?species=Rabbit" class="tag">Rabbit</a>
      </div>
    </div>
  </section>

  <!-- FILTERS -->
  <section class="py-3">
    <div class="container">
      <form method="get" class="row g-2 align-items-end adopt-filters">
        <div class="col-md-4">
          <label class="form-label small"><?php echo $t[$lang]['city']; ?></label>
          <select name="city" class="form-select">
            <option value=""><?php echo $t[$lang]['view_all']; ?></option>
            <?php foreach ($cities as $c): $val = $c['city']; ?>
              <option value="<?php echo h($val); ?>" <?php echo ($city===$val?'selected':''); ?>>
                <?php echo h($val); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small"><?php echo $t[$lang]['species']; ?></label>
          <select name="species" class="form-select">
            <option value=""><?php echo $t[$lang]['view_all']; ?></option>
            <?php foreach ($species as $s): $val = $s['species']; ?>
              <option value="<?php echo h($val); ?>" <?php echo ($sp===$val?'selected':''); ?>>
                <?php echo h($val); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small"><?php echo $t[$lang]['breed']; ?></label>
          <select name="breed" class="form-select">
            <option value=""><?php echo $t[$lang]['view_all']; ?></option>
            <?php foreach ($breeds as $b): $val = $b['breed']; ?>
              <option value="<?php echo h($val); ?>" <?php echo ($br===$val?'selected':''); ?>>
                <?php echo h($val); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <button class="btn btn-primary w-100"><?php echo $t[$lang]['filter']; ?></button>
        </div>
      </form>
    </div>
  </section>

  <!-- URGENT -->
  <?php if ($urgent) { ?>
  <section class="py-4">
    <div class="container">
      <div class="urgent-banner">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div class="text">
          <strong><?php echo $t[$lang]['spotlight']; ?>:</strong>
          <?php foreach ($urgent as $u): ?>
            <a class="link-dark fw-semibold me-3 badge rounded-pill bg-warning-subtle text-dark" href="/pet.php?id=<?php echo (int)$u['id']; ?>">
              <?php echo h($u['name']); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
  <?php } ?>

  <!-- PETS GRID -->
  <section class="py-5">
    <div class="container">
      <h2 class="h3 mb-4"><?php echo $t[$lang]['pets']; ?></h2>
      <div class="row g-4">
        <?php foreach ($pets as $p): ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="pet-card h-100">
              <div class="pet-thumb">
                <img loading="lazy"
                     src="<?php echo h(pet_img($p['avatar'])); ?>"
                     alt="<?php echo h($p['name'].' - '.$p['species'].' '.$p['breed']); ?>">
                <?php if ($hasSpotlight && (int)$p['spotlight'] === 1): ?>
                  <span class="ribbon">Spotlight</span>
                <?php endif; ?>
              </div>
              <div class="pet-body">
                <h5 class="name"><?php echo h($p['name']); ?></h5>
                <div class="meta"><?php echo h($p['species'].' • '.$p['breed']); ?></div>
                <div class="meta muted"><i class="bi bi-geo-alt me-1"></i><?php echo h($p['city']); ?></div>
                <a href="/pet.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary w-100 mt-2">
                  <?php echo $t[$lang]['adopt_now']; ?>
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$pets): ?>
          <div class="col-12"><p class="text-muted">No pets found.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- HOW TO ADOPT -->
  <section class="py-5 bg-white">
    <div class="container text-center">
      <h3 class="fw-bold mb-4"><?php echo $t[$lang]['how_h']; ?></h3>
      <div class="row g-4 justify-content-center">
        <div class="col-md-3"><div class="step">🐾 <?php echo $t[$lang]['browse']; ?></div></div>
        <div class="col-md-3"><div class="step">📝 <?php echo $t[$lang]['apply']; ?></div></div>
        <div class="col-md-3"><div class="step">📞 <?php echo $t[$lang]['contact']; ?></div></div>
      </div>
    </div>
  </section>

  <!-- SHELTER CTA -->
  <section class="py-5 text-white text-center cta-shelter">
    <div class="container">
      <h3 class="fw-bold mb-3"><?php echo $t[$lang]['shelter_cta_h']; ?></h3>
      <p class="lead"><?php echo $t[$lang]['shelter_cta_p']; ?></p>
      <a href="/shelters" class="btn btn-light btn-lg rounded-pill mt-3">See Shelters</a>
    </div>
  </section>

  <!-- TESTIMONIALS -->
  <section class="py-5">
    <div class="container text-center">
      <h3 class="fw-bold mb-4"><?php echo $t[$lang]['testimonials']; ?></h3>
      <div class="row g-4 justify-content-center">
        <div class="col-md-4">
          <div class="card p-3 shadow-sm t-card">
            <div class="t-stars">★★★★★</div>
            <div>“I found my best friend Milo through FurShield. So happy!”</div>
            <div class="t-user">— Sara & Milo</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card p-3 shadow-sm t-card">
            <div class="t-stars">★★★★★</div>
            <div>“Professional, quick, and trustworthy — highly recommend!”</div>
            <div class="t-user">— Danial</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ADOPTION STATS -->
  <section class="py-5 bg-light">
    <div class="container text-center">
      <h3 class="fw-bold mb-4"><?php echo $t[$lang]['adoption_stats']; ?></h3>
      <div class="row g-4 justify-content-center counters">
        <div class="col-6 col-md-3">
          <div class="counter" data-target="<?php echo $totalPets; ?>">0</div>
          <p class="text-muted">Total Pets</p>
        </div>
        <div class="col-6 col-md-3">
          <div class="counter" data-target="<?php echo $totalAdoptions; ?>">0</div>
          <p class="text-muted">Adopted</p>
        </div>
        <div class="col-6 col-md-3">
          <div class="counter" data-target="<?php echo $totalUsers; ?>">0</div>
          <p class="text-muted">Users</p>
        </div>
      </div>
    </div>
  </section>

  <!-- NEWSLETTER -->
  <section class="py-5">
    <div class="container text-center">
      <h3 class="fw-bold mb-4"><?php echo $t[$lang]['newsletter']; ?></h3>
      <form class="d-flex justify-content-center gap-2 flex-wrap newsletter-form" method="post" action="#">
        <input type="email" class="form-control w-auto" placeholder="Your email" required>
        <button class="btn btn-primary rounded-pill"><?php echo $t[$lang]['sub_btn']; ?></button>
      </form>
      <div class="nl-msg" id="nlMsg" aria-live="polite"></div>
    </div>
  </section>

</main>

<script>
// Same JS functionality
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
