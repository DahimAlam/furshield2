<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
if (!defined('BASE')) define('BASE', '/furshield');
function e($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
  http_response_code(404);
  exit('Vet not found');
}

$stmt = $conn->prepare("SELECT user_id,name,specialization,experience_years,license_no,license_image,profile_image,slots_json,clinic_address,city,country FROM vets WHERE user_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$vet = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$vet) {
  http_response_code(404);
  exit('Vet not found');
}

$slots = [];
if (!empty($vet['slots_json'])) {
  $j = json_decode($vet['slots_json'], true);
  if (is_array($j)) $slots = $j;
}

// appointments count
$consults = 0;
try {
  $st = $conn->prepare("SELECT COUNT(*) c FROM appointments WHERE vet_id=? AND status='done'");
  $st->bind_param("i", $id);
  $st->execute();
  $consults = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
} catch (Throwable $e) {
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
      <img src="<?php echo e(!empty($vet['profile_image']) ? BASE . '/uploads/avatars/' . $vet['profile_image'] : BASE . '/assets/placeholder/vet.jpg'); ?>"
        class="rounded-circle mb-3 shadow" style="width:160px;height:160px;object-fit:cover;border:5px solid #fff">
      <h1 class="fw-bold"><?php echo e($vet['name']); ?></h1>
      <p class="lead mb-1"><?php echo e($vet['specialization']); ?></p>
      <p><i class="bi bi-geo-alt"></i> <?php echo e($vet['city'] . ', ' . $vet['country']); ?></p>
      <a href="appointments.php?vet_id=<?php echo $vet['user_id']; ?>" class="btn btn-lg text-white mt-2" style="background:var(--primary);border-radius:14px">Book Appointment</a>
    </div>
  </section>

  <!-- Details -->
  <section class="py-5">
    <div class="container">
      <div class="row g-4">
        <!-- Stats -->
        <div class="col-lg-4">
          <div class="row g-3">
            <div class="col-6">
              <div class="p-3 bg-white rounded text-center shadow" style="border-radius:var(--radius)">
                <div class="text-muted small">Experience</div>
                <h3 class="fw-bold text-primary"><?php echo (int)$vet['experience_years']; ?> yrs</h3>
              </div>
            </div>
            <div class="col-6">
              <div class="p-3 bg-white rounded text-center shadow" style="border-radius:var(--radius)">
                <div class="text-muted small">Consults</div>
                <h3 class="fw-bold text-primary"><?php echo $consults; ?></h3>
              </div>
            </div>
          </div>
        </div>

        <!-- About + Availability -->
        <div class="col-lg-8">
          <div class="card border-0 p-4 shadow" style="border-radius:var(--radius)">
            <h4 class="fw-bold mb-3">About</h4>
            <p><strong>Clinic Address:</strong> <?php echo e($vet['clinic_address']); ?></p>
            <p><strong>License No:</strong> <?php echo e($vet['license_no']); ?></p>

            <h5 class="fw-bold mt-4 mb-2">Availability</h5>
            <?php if ($slots): ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($slots as $day => $times): ?>
                  <li class="list-group-item d-flex justify-content-between">
                    <span class="fw-semibold"><?php echo e(ucfirst($day)); ?></span>
                    <span><?php echo e(implode(', ', $times)); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="text-muted">No availability schedule set.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- License -->
      <?php if (!empty($vet['license_image'])): ?>
        <div class="row g-4 mt-4">
          <div class="col-lg-6">
            <div class="card border-0 shadow" style="border-radius:var(--radius)">
              <div class="card-header fw-bold">License Image</div>
              <img src="<?php echo e(BASE . '/uploads/vets/' . $vet['license_image']); ?>" class="card-img-bottom" style="max-height:350px;object-fit:cover;border-bottom-left-radius:var(--radius);border-bottom-right-radius:var(--radius)">
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Reviews -->
      <?php
      $reviews = null;
      try {
        $s = $conn->prepare("SELECT r.comment,r.rating,r.created_at,o.full_name 
                           FROM reviews r JOIN owners o ON o.user_id=r.owner_id 
                           WHERE r.vet_id=? ORDER BY r.id DESC");
        $s->bind_param("i", $id);
        $s->execute();
        $reviews = $s->get_result();
      } catch (Throwable $e) {
      }
      ?>
      <div class="mt-5">
        <h4 class="fw-bold mb-4">Client Reviews</h4>
        <?php if ($reviews && $reviews->num_rows): ?>
          <div class="row g-3">
            <?php while ($rev = $reviews->fetch_assoc()): ?>
              <div class="col-md-6">
                <div class="p-3 bg-white rounded shadow-sm h-100" style="border-radius:var(--radius)">
                  <div class="mb-1">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="bi <?php echo $i <= $rev['rating'] ? 'bi-star-fill text-warning' : 'bi-star text-muted'; ?>"></i>
                    <?php endfor; ?>
                  </div>
                  <div class="mb-2"><?php echo nl2br(e($rev['comment'])); ?></div>
                  <div class="small text-muted">— <?php echo e($rev['full_name']); ?> • <?php echo date('d M Y', strtotime($rev['created_at'])); ?></div>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <p class="text-muted">No reviews yet.</p>
        <?php endif; ?>
      </div>

      <a href="vets.php" class="btn btn-outline-dark mt-5" style="border-radius:12px"><i class="bi bi-arrow-left"></i> Back to Vets</a>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>