<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
if (!defined('BASE')) define('BASE', '/furshield');
$conn->set_charset('utf8mb4');

/* ---------- helpers (guard against re-declare) ---------- */
if (!function_exists('col_exists')) {
  function col_exists(mysqli $c, string $t, string $col): bool
  {
    $t = $c->real_escape_string($t);
    $col = $c->real_escape_string($col);
    $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
    return $r && $r->num_rows > 0;
  }
}
if (!function_exists('table_exists')) {
  function table_exists(mysqli $c, string $t): bool
  {
    $t = $c->real_escape_string($t);
    $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'");
    return $r && $r->num_rows > 0;
  }
}
if (!function_exists('pickcol')) {
  function pickcol(mysqli $c, string $t, array $cands): ?string
  {
    foreach ($cands as $x) {
      if (col_exists($c, $t, $x)) return $x;
    }
    return null;
  }
}
if (!function_exists('media')) {
  function media($rel, $folder)
  {
    $rel = trim((string)$rel);
    if ($rel === '') return BASE . '/assets/placeholder/blank.jpg';
    if (str_starts_with($rel, 'http://') || str_starts_with($rel, 'https://')) return $rel;
    if ($rel[0] === '/') return $rel;
    if (str_starts_with($rel, 'uploads/')) return BASE . '/' . ltrim($rel, '/');
    return BASE . '/uploads/' . $folder . '/' . $rel;
  }
}
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ---------- site counters ---------- */
$counts = [
  'pets' => (int)($conn->query("SELECT COUNT(*) c FROM pets")->fetch_assoc()['c'] ?? 0),
  'vets' => (int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='vet' AND status='active'")->fetch_assoc()['c'] ?? 0),
  'shel' => (int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='shelter' AND status='active'")->fetch_assoc()['c'] ?? 0),
];

/* ---------- team (optional table) ---------- */
$team = [];
if (table_exists($conn, 'team')) {
  $tn = 'team';
  $C_ID  = pickcol($conn, $tn, ['id', 'team_id']);
  $C_NM  = pickcol($conn, $tn, ['name', 'full_name', 'display_name']);
  $C_RL  = pickcol($conn, $tn, ['role', 'title', 'position']);
  $C_AV  = pickcol($conn, $tn, ['avatar', 'photo', 'image']);
  $C_BIO = pickcol($conn, $tn, ['bio', 'about', 'summary']);
  $C_LI  = pickcol($conn, $tn, ['linkedin', 'li']);
  $C_GH  = pickcol($conn, $tn, ['github', 'gh']);
  $C_X   = pickcol($conn, $tn, ['twitter', 'x', 'handle']);
  $C_ACT = pickcol($conn, $tn, ['is_active', 'active', 'visible']);
  $sel = [];
  $sel[] = $C_ID ? "`$C_ID` AS id" : "id";
  $sel[] = $C_NM ? "`$C_NM` AS name" : "name";
  $sel[] = $C_RL ? "`$C_RL` AS role" : "'' AS role";
  $sel[] = $C_AV ? "`$C_AV` AS avatar" : "'' AS avatar";
  $sel[] = $C_BIO ? "`$C_BIO` AS bio" : "'' AS bio";
  if ($C_LI) $sel[] = "`$C_LI` AS li";
  if ($C_GH) $sel[] = "`$C_GH` AS gh";
  if ($C_X)  $sel[] = "`$C_X` AS tw";
  $where = $C_ACT ? "WHERE `$C_ACT`=1" : "";
  $q = $conn->query("SELECT " . implode(', ', $sel) . " FROM `$tn` $where ORDER BY id LIMIT 12");
  if ($q) {
    while ($r = $q->fetch_assoc()) {
      $team[] = $r;
    }
  }
}

/* ---------- milestones / timeline (optional) ---------- */
$timeline = [];
if (table_exists($conn, 'milestones') || table_exists($conn, 'timeline')) {
  $tb = table_exists($conn, 'milestones') ? 'milestones' : 'timeline';
  $C_T = pickcol($conn, $tb, ['title', 'name', 'heading']);
  $C_D = pickcol($conn, $tb, ['date', 'occurred_at', 'happened_on']);
  $C_P = pickcol($conn, $tb, ['description', 'details', 'content']);
  $C_I = pickcol($conn, $tb, ['image', 'cover', 'thumb']);
  $sel = [];
  $sel[] = $C_T ? "`$C_T` AS title" : "'' AS title";
  $sel[] = $C_D ? "`$C_D` AS date" : "NULL AS date";
  $sel[] = $C_P ? "`$C_P` AS description" : "'' AS description";
  $sel[] = $C_I ? "`$C_I` AS image" : "'' AS image";
  $q = $conn->query("SELECT " . implode(', ', $sel) . " FROM `$tb` ORDER BY " . ($C_D ? "`$C_D`" : "1") . " ASC LIMIT 8");
  if ($q) {
    while ($r = $q->fetch_assoc()) {
      $timeline[] = $r;
    }
  }
} else {
  $timeline = [
    ['title' => 'FurShield begins', 'date' => '2023-04-01', 'description' => 'A small idea to simplify pet care.'],
    ['title' => 'Adoption & Records', 'date' => '2023-10-01', 'description' => 'Unified health records & adoption module.'],
    ['title' => 'Vets & Shelters onboard', 'date' => '2024-04-01', 'description' => 'Verified vets and partner shelters join.'],
    ['title' => 'Community 10k+', 'date' => '2025-03-01', 'description' => 'Growing impact with drives and events.'],
  ];
}

/* ---------- partners / brands (optional) ---------- */
$brands = [];
if (table_exists($conn, 'brands')) {
  $bn = 'brands';
  $C_N = pickcol($conn, $bn, ['name', 'title']);
  $C_L = pickcol($conn, $bn, ['logo', 'image', 'icon']);
  $C_U = pickcol($conn, $bn, ['url', 'link']);
  $C_A = pickcol($conn, $bn, ['is_active', 'active', 'visible']);
  $sel = ["id"];
  if ($C_N) $sel[] = "`$C_N` AS name";
  $sel[] = $C_L ? "`$C_L` AS logo" : "'' AS logo";
  if ($C_U) $sel[] = "`$C_U` AS url";
  $w = $C_A ? "WHERE `$C_A`=1" : "";
  $q = $conn->query("SELECT " . implode(', ', $sel) . " FROM `$bn` $w ORDER BY id LIMIT 10");
  if ($q) {
    while ($r = $q->fetch_assoc()) {
      $brands[] = $r;
    }
  }
}

include __DIR__ . '/includes/header.php';
?>
<style>
  :root {
    --primary: #F59E0B;
    --accent: #EF4444;
    --bg: #FFF7ED;
    --text: #1F2937;
    --card: #FFFFFF;
    --radius: 18px;
    --shadow: 0 10px 30px rgba(0, 0, 0, .08);
  }

  .container,
  .container-fluid {
    max-width: 100%;
    padding-left: clamp(12px, 2.4vw, 32px);
    padding-right: clamp(12px, 2.4vw, 32px)
  }

  .row {
    --bs-gutter-x: 1.2rem
  }

  .hero-about {
    background: radial-gradient(1200px 400px at 20% -10%, rgba(245, 158, 11, .25), transparent 40%),
      radial-gradient(900px 320px at 90% 0%, rgba(239, 68, 68, .18), transparent 45%),
      linear-gradient(180deg, #fff, rgba(255, 247, 237, .75));
    border-bottom: 1px solid #f1e6d7;
  }

  .hero-card {
    border: 1px solid #eee;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
  }

  .hero-card img {
    height: 320px;
    object-fit: cover;
    width: 100%
  }

  .kpi {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 16px;
    box-shadow: var(--shadow);
    text-align: center;
    padding: 18px 10px;
  }

  .kpi .num {
    font-weight: 900;
    font-size: clamp(28px, 4.5vw, 44px);
    letter-spacing: .5px
  }

  .kpi .lbl {
    color: #6b7280;
    font-weight: 600
  }

  .values .card {
    border: 1px solid #eee;
    border-radius: 18px;
    box-shadow: var(--shadow);
    height: 100%;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  }

  .values .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 18px 50px rgba(0, 0, 0, .12);
    border-color: #e9e3d6
  }

  .timeline {
    position: relative;
    padding-left: 28px;
    list-style: none;
    margin: 0;
  }

  .timeline::before {
    content: "";
    position: absolute;
    left: 12px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, var(--primary), var(--accent));
    opacity: .4;
  }

  .ti {
    position: relative;
    margin-bottom: 16px
  }

  .ti:last-child {
    margin-bottom: 0
  }

  .ti .dot {
    position: absolute;
    left: -2px;
    top: .45rem;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--primary);
    box-shadow: 0 0 0 4px rgba(245, 158, 11, .18)
  }

  .ti .card {
    border: 1px solid #eee;
    border-radius: 16px;
    box-shadow: var(--shadow);
  }

  .t-meta {
    font-size: .85rem;
    color: #6b7280
  }

  .team-card {
    border: 1px solid #eee;
    border-radius: 22px;
    box-shadow: var(--shadow);
    height: 100%;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  }

  .team-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 22px 60px rgba(0, 0, 0, .14);
    border-color: #e9e3d6
  }

  .team-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    background: #f3f4f6
  }

  .brand-strip img {
    max-height: 42px;
    opacity: .9;
    filter: grayscale(10%)
  }

  .cta {
    background: linear-gradient(135deg, var(--accent), #ff8b73);
    border-radius: 22px;
    color: #fff;
    box-shadow: 0 30px 70px rgba(239, 68, 68, .25);
  }

  .btn-pill {
    border-radius: 999px
  }

  .reveal {
    opacity: 0;
    transform: translateY(12px) scale(.98)
  }

  .reveal.show {
    opacity: 1;
    transform: none;
    transition: 420ms cubic-bezier(.2, .7, .2, 1)
  }
</style>
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

<main style="background:var(--bg); color:var(--text)">

  <section class="hero-about py-5">
    <div class="container-fluid">
      <div class="row align-items-center g-4">
        <div class="col-lg-7">
          <span class="badge rounded-pill bg-white text-dark border me-2">About</span>
          <h1 class="display-6 fw-bold mt-2">We’re building the easiest way to care for pets—together.</h1>
          <p class="lead text-secondary">FurShield connects owners, vets and shelters with health records, appointments, adoption and trusted products in one place.</p>
          <div class="d-flex gap-2 flex-wrap mt-2">
            <a href="<?php echo BASE . '/adopt.php'; ?>" class="btn btn-primary btn-pill" style="background:var(--primary);border:none">Adopt a Pet</a>
            <a href="<?php echo BASE . '/vets.php'; ?>" class="btn btn-outline-dark btn-pill">Find a Vet</a>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="hero-card">
            <img src="<?php echo BASE . '/assets/placeholder/hero-about.jpg'; ?>" alt="FurShield" loading="lazy">
            <div class="p-3 small text-muted d-flex justify-content-between">
              <div><?php echo number_format($counts['vets']); ?> Verified Vets</div>
              <div><?php echo number_format($counts['shel']); ?> Active Shelters</div>
              <div><?php echo number_format($counts['pets']); ?> Pets</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="py-4">
    <div class="container-fluid">
      <div class="row g-3 text-center">
        <div class="col-6 col-md-4">
          <div class="kpi reveal">
            <div class="num counter" data-to="<?php echo (int)$counts['pets']; ?>">0</div>
            <div class="lbl">Pets</div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="kpi reveal">
            <div class="num counter" data-to="<?php echo (int)$counts['vets']; ?>">0</div>
            <div class="lbl">Verified Vets</div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="kpi reveal">
            <div class="num counter" data-to="<?php echo (int)$counts['shel']; ?>">0</div>
            <div class="lbl">Active Shelters</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="py-5 values">
    <div class="container-fluid">
      <h2 class="h4 mb-3">Our Values</h2>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="card p-3 reveal">
            <div class="d-flex align-items-center mb-2">
              <span class="icon-circle me-2" style="background:color-mix(in srgb, var(--primary) 12%, #fff)"><i class="bi bi-shield-heart"></i></span>
              <h6 class="m-0 fw-bold">Trust & Safety</h6>
            </div>
            <p class="mb-0 text-muted">Verified profiles, transparent actions and privacy-first design—so you can focus on care.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card p-3 reveal">
            <div class="d-flex align-items-center mb-2">
              <span class="icon-circle me-2" style="background:rgba(16,185,129,.12)"><i class="bi bi-activity"></i></span>
              <h6 class="m-0 fw-bold">Quality Care</h6>
            </div>
            <p class="mb-0 text-muted">Structured records and guided flows to help vets, shelters and owners make better decisions.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card p-3 reveal">
            <div class="d-flex align-items-center mb-2">
              <span class="icon-circle me-2" style="background:color-mix(in srgb, var(--accent) 12%, #fff)"><i class="bi bi-heart-pulse"></i></span>
              <h6 class="m-0 fw-bold">Community Impact</h6>
            </div>
            <p class="mb-0 text-muted">Events, drives and adoption programs that create real outcomes for animals in need.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="py-5">
    <div class="container-fluid">
      <div class="row g-4">
        <div class="col-lg-6">
          <h2 class="h4 mb-3">Our Story</h2>
          <ul class="timeline">
            <?php foreach ($timeline as $i => $m): ?>
              <li class="ti reveal">
                <span class="dot"></span>
                <div class="card p-3">
                  <div class="d-flex justify-content-between align-items-center">
                    <strong><?php echo h($m['title'] ?? 'Milestone'); ?></strong>
                    <span class="t-meta"><?php echo !empty($m['date']) ? date('M Y', strtotime($m['date'])) : ''; ?></span>
                  </div>
                  <?php if (!empty($m['description'])) { ?><div class="text-muted mt-1"><?php echo h($m['description']); ?></div><?php } ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="col-lg-6">
          <h2 class="h4 mb-3">What we do</h2>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="card p-3 reveal">
                <h6 class="fw-bold"><i class="bi bi-calendar2-check me-1"></i> Appointments</h6>
                <p class="text-muted mb-0">Real-time vet availability, booking and reminders.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card p-3 reveal">
                <h6 class="fw-bold"><i class="bi bi-file-medical me-1"></i> Health Records</h6>
                <p class="text-muted mb-0">Vaccinations, prescriptions and surgeries in one timeline.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card p-3 reveal">
                <h6 class="fw-bold"><i class="bi bi-house-heart me-1"></i> Adoption</h6>
                <p class="text-muted mb-0">Modern discovery with urgent/spotlight programs.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card p-3 reveal">
                <h6 class="fw-bold"><i class="bi bi-bag-heart me-1"></i> Products</h6>
                <p class="text-muted mb-0">Curated essentials with clear info and reviews.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="py-5">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 m-0">Meet the Team</h2>
        <span class="text-muted small">Humans who love animals</span>
      </div>
      <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4">
        <?php if ($team): foreach ($team as $t):
            $img = $t['avatar'] ? media($t['avatar'], 'avatars') : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($t['name'] ?? 'F');
        ?>
            <div class="col">
              <div class="team-card p-3 d-flex flex-column reveal">
                <div class="d-flex align-items-center gap-3">
                  <img src="<?php echo h($img); ?>" class="team-avatar" alt="<?php echo h($t['name']); ?>" loading="lazy">
                  <div>
                    <div class="fw-bold"><?php echo h($t['name'] ?? 'Teammate'); ?></div>
                    <div class="text-muted small"><?php echo h($t['role'] ?? ''); ?></div>
                  </div>
                </div>
                <?php if (!empty($t['bio'])) { ?><p class="text-muted mt-2 mb-0"><?php echo h($t['bio']); ?></p><?php } ?>
                <div class="mt-2 d-flex gap-2">
                  <?php if (!empty($t['li'])) { ?><a href="<?php echo h($t['li']); ?>" class="btn btn-sm btn-outline-secondary btn-pill" target="_blank"><i class="bi bi-linkedin"></i></a><?php } ?>
                  <?php if (!empty($t['gh'])) { ?><a href="<?php echo h($t['gh']); ?>" class="btn btn-sm btn-outline-secondary btn-pill" target="_blank"><i class="bi bi-github"></i></a><?php } ?>
                  <?php if (!empty($t['tw'])) { ?><a href="<?php echo h($t['tw']); ?>" class="btn btn-sm btn-outline-secondary btn-pill" target="_blank"><i class="bi bi-twitter-x"></i></a><?php } ?>
                </div>
              </div>
            </div>
          <?php endforeach;
        else: ?>

          <div class="col">
            <div class="team-card p-3 d-flex flex-column reveal">
              <div class="d-flex align-items-center gap-3">
                <img src="team/dahim.jpeg" class="team-avatar" alt="Team">
                <div>
                  <div class="fw-bold">Dahim Alam </div>
                  <div class="text-muted small">Backend-Developer</div>
                </div>
              </div>
              <p class="text-muted mt-2 mb-0">We’re expanding our team—reach out to collaborate and build!</p>
            </div>
          </div>

          <div class="col">
            <div class="team-card p-3 d-flex flex-column reveal">
              <div class="d-flex align-items-center gap-3">
                <img src="" class="team-avatar" alt="Team">
                <div>
                  <div class="fw-bold">Talha Rabbani </div>
                  <div class="text-muted small">Frontend-Developer</div>
                </div>
              </div>
              <p class="text-muted mt-2 mb-0">We’re expanding our team—reach out to collaborate and build!</p>
            </div>
          </div>

          <div class="col">
            <div class="team-card p-3 d-flex flex-column reveal">
              <div class="d-flex align-items-center gap-3">
                <img src="" class="team-avatar" alt="Team">
                <div>
                  <div class="fw-bold">Athisham</div>
                  <div class="text-muted small">Backend-Developer</div>
                </div>
              </div>
              <p class="text-muted mt-2 mb-0">We’re expanding our team—reach out to collaborate and build!</p>
            </div>
          </div>

          <div class="col">
            <div class="team-card p-3 d-flex flex-column reveal">
              <div class="d-flex align-items-center gap-3">
                <img src="" class="team-avatar" alt="Team">
                <div>
                  <div class="fw-bold">Hunnain Sheikh</div>
                  <div class="text-muted small">Frontend-Developer</div>
                </div>
              </div>
              <p class="text-muted mt-2 mb-0">We’re expanding our team—reach out to collaborate and build!</p>
            </div>
          </div>

        <?php endif; ?>
      </div>
    </div>
  </section>



  <section class="py-5">
    <div class="container-fluid">
      <div class="cta p-4 p-lg-5 d-flex flex-column flex-lg-row align-items-center justify-content-between gap-3">
        <div>
          <h3 class="mb-1 fw-bold">Join the mission</h3>
          <p class="mb-0">Are you a vet or shelter? Create your profile and start helping pets today.</p>
        </div>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE . '/register-vet.php'; ?>" class="btn btn-light btn-pill">Register as Vet</a>
          <a href="<?php echo BASE . '/register-shelter.php'; ?>" class="btn btn-outline-light btn-pill">Register as Shelter</a>
        </div>
      </div>
    </div>
  </section>

</main>

<script>
  (function() {
    const els = document.querySelectorAll('.reveal');
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('show');
          io.unobserve(e.target);
        }
      });
    }, {
      threshold: .12
    });
    els.forEach(el => io.observe(el));

    const counters = document.querySelectorAll('.counter');
    const animate = (el) => {
      const to = parseInt(el.dataset.to || '0', 10);
      let cur = 0,
        step = Math.max(1, Math.ceil(to / 60));
      const tick = () => {
        cur += step;
        if (cur >= to) {
          el.textContent = to.toLocaleString();
          return;
        }
        el.textContent = cur.toLocaleString();
        requestAnimationFrame(tick);
      };
      tick();
    };
    counters.forEach(c => animate(c));
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>