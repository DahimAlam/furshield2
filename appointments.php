<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
if (!defined('BASE')) define('BASE','/furshield');

/* ---- owner only ---- */
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'owner') {
  header('Location: '.BASE.'/login.php?next='.urlencode($_SERVER['REQUEST_URI'] ?? BASE.'/'));
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$ownerId   = (int)($_SESSION['user']['id'] ?? 0);
$vetUserId = (int)($_GET['vet_id'] ?? 0);                 // vets.user_id in your schema
if ($vetUserId <= 0) { http_response_code(404); exit('Vet not found.'); }

/* ---------- helpers ---------- */
function rows(mysqli $c, string $sql, array $p = [], string $types = ''): array {
  if (!$p) { $r = $c->query($sql); return $r? $r->fetch_all(MYSQLI_ASSOC) : []; }
  if ($types==='') $types = str_repeat('s', count($p));
  $st = $c->prepare($sql); $st->bind_param($types, ...$p); $st->execute();
  $res = $st->get_result(); return $res? $res->fetch_all(MYSQLI_ASSOC) : [];
}
function col_exists(mysqli $c, string $t, string $col): bool {
  $t=$c->real_escape_string($t); $col=$c->real_escape_string($col);
  $r=$c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
  return $r && $r->num_rows>0;
}
function pickcol(mysqli $c, string $t, array $opts): ?string {
  foreach ($opts as $x) if (col_exists($c,$t,$x)) return $x;
  return null;
}

/* ---------- 1) Vet (by vets.user_id) ---------- */
$vet = rows(
  $conn,
  "SELECT user_id AS vet_id, name, specialization, profile_image
   FROM vets WHERE user_id=? LIMIT 1",
  [$vetUserId], 'i'
);
if (!$vet) { http_response_code(404); exit('Vet not found.'); }
$vet = $vet[0];

/* ---------- 2) Owner pets for dropdown (FK requires a real pet) ---------- */
$PET_OWNER_COL = pickcol($conn, 'pets', ['owner_id','user_id']);  // support either
$ownerPets = [];
if ($PET_OWNER_COL) {
  $ownerPets = rows($conn, "SELECT id, COALESCE(name, CONCAT('Pet #',id)) AS name FROM pets WHERE `$PET_OWNER_COL`=? ORDER BY name", [$ownerId], 'i');
}

/* ---------- 3) Availability ---------- */
$availability = rows(
  $conn,
  "SELECT id, vet_id, dow, specific_date, start_time, end_time, is_active
   FROM vet_availability
   WHERE vet_id=? AND is_active=1
   ORDER BY specific_date IS NULL, specific_date, dow, start_time",
  [$vetUserId], 'i'
);

/* ---------- 4) Hide already booked ---------- */
$todayStart = date('Y-m-d 00:00:00');
$booked = rows(
  $conn,
  "SELECT DATE_FORMAT(scheduled_at,'%Y-%m-%d %H:%i') AS dt
     FROM appointments
    WHERE vet_id=? AND scheduled_at >= ?
      AND status IN ('pending','approved')",
  [$vetUserId, $todayStart], 'is'
);
$bookedSet = [];
foreach ($booked as $b) if (!empty($b['dt'])) $bookedSet[$b['dt']] = true;

/* ---------- 5) Build 14-day slots (30m) ---------- */
$slotLenMin = 30;
$daysAhead  = 14;
$nowTs = time();
$todayTs = strtotime('today');
$perDay = [];

if ($availability) {
  for ($i=0; $i<$daysAhead; $i++) {
    $dTs  = strtotime("+$i day", $todayTs);
    $date = date('Y-m-d', $dTs);
    $phpN = (int)date('N', $dTs);               // 1..7 (Mon..Sun)

    foreach ($availability as $a) {
      $isSpecific = !empty($a['specific_date']);
      $match = false;

      if ($isSpecific) {
        $match = ($a['specific_date'] === $date);
      } else {
        $dbDow = is_null($a['dow']) ? null : (int)$a['dow'];  // supports 0..6 OR 1..7
        $match = ($dbDow === $phpN) || ($phpN===7 && $dbDow===0);
      }
      if (!$match) continue;

      $start = strtotime("$date ".$a['start_time']);
      $end   = strtotime("$date ".$a['end_time']);
      if ($i===0) $start = max($start, $nowTs + 60);         // skip past times
      if ($end <= $start) continue;

      for ($t=$start; $t + ($slotLenMin*60) <= $end; $t += $slotLenMin*60) {
        $dt = date('Y-m-d H:i', $t);
        if (isset($bookedSet[$dt])) continue;
        $perDay[$date][] = date('H:i', $t);
      }
    }
  }
  foreach ($perDay as $d=>$times) {
    $times = array_values(array_unique($times));
    sort($times);
    if (!$times) unset($perDay[$d]); else $perDay[$d]=$times;
  }
}

/* ---------- 6) Book ---------- */
$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['slot'])) {
  $petId = (int)($_POST['pet_id'] ?? 0);
  [$slotDate, $slotTime] = explode('|', $_POST['slot']) + [null,null];

  // require pet (FK)
  if ($petId <= 0) {
    $err = 'Please select your pet.';
  } else {
    $petCheck = $PET_OWNER_COL
      ? rows($conn, "SELECT id FROM pets WHERE id=? AND `$PET_OWNER_COL`=? LIMIT 1", [$petId, $ownerId], 'ii')
      : rows($conn, "SELECT id FROM pets WHERE id=? LIMIT 1", [$petId], 'i');

    if (!$petCheck) $err = 'Invalid pet selected.';
  }

  // slot validation
  if (!$err) {
    $valid = $slotDate && $slotTime && preg_match('~^\d{4}-\d{2}-\d{2}$~', $slotDate) && preg_match('~^\d{2}:\d{2}$~', $slotTime);
    if (!$valid) $err = 'Invalid slot.';
  }

  if (!$err) {
    $dt = "$slotDate $slotTime";

    // double-book guard
    $dup = rows(
      $conn,
      "SELECT id FROM appointments
        WHERE vet_id=? AND DATE_FORMAT(scheduled_at,'%Y-%m-%d %H:%i')=? 
          AND status IN ('pending','approved') LIMIT 1",
      [$vetUserId, $dt], 'is'
    );
    if ($dup) {
      $err = 'Sorry, this slot was just taken. Please pick another.';
    } else {
      // INSERT with a real pet_id (FK will pass)
      $ins = $conn->prepare(
        "INSERT INTO appointments (pet_id, owner_id, vet_id, scheduled_at, appointment_time, status)
         VALUES (?, ?, ?, ?, ?, 'pending')"
      );
      $ins->bind_param('iiiss', $petId, $ownerId, $vetUserId, $dt, $dt);
      $ins->execute();
      $msg = 'Your appointment request has been submitted. Please wait for vet confirmation.';

      // remove just-booked slot from UI
      $bookedSet[$dt] = true;
      if (isset($perDay[$slotDate])) {
        $perDay[$slotDate] = array_values(array_filter($perDay[$slotDate], fn($t) => $t !== $slotTime));
        if (!$perDay[$slotDate]) unset($perDay[$slotDate]);
      }
    }
  }
}

include __DIR__.'/includes/header.php';
?>
<main class="bg-app">

  <!-- Hero -->
  <section class="py-5 text-center" style="background:linear-gradient(120deg,var(--primary),var(--accent));color:#fff">
    <div class="container">
      <img
        src="<?php echo !empty($vet['profile_image']) ? BASE.'/uploads/avatars/'.$vet['profile_image'] : BASE.'/assets/placeholder/vet.jpg'; ?>"
        class="rounded-circle mb-3 shadow" style="width:140px;height:140px;object-fit:cover;border:5px solid #fff" alt="Vet">
      <h1 class="fw-bold mb-1"><?php echo htmlspecialchars($vet['name'] ?: 'Vet'); ?></h1>
      <?php if (!empty($vet['specialization'])) { ?>
        <p class="lead mb-0"><?php echo htmlspecialchars($vet['specialization']); ?></p>
      <?php } ?>
    </div>
  </section>

  <!-- Booking -->
  <section class="py-5">
    <div class="container" style="max-width:1024px">
      <?php if ($msg) { ?><div class="alert alert-success fw-semibold text-center shadow-sm mb-4"><?php echo htmlspecialchars($msg); ?></div><?php } ?>
      <?php if ($err) { ?><div class="alert alert-danger  fw-semibold text-center shadow-sm mb-4"><?php echo htmlspecialchars($err); ?></div><?php } ?>

      <form method="post" class="mt-2">
        <!-- Pet picker (required because of FK) -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:var(--radius)">
          <div class="card-body">
            <label class="form-label fw-semibold">Select Your Pet</label>
            <?php if ($ownerPets) { ?>
              <select name="pet_id" class="form-select" required>
                <option value="">Choose a pet...</option>
                <?php foreach ($ownerPets as $p) { ?>
                  <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                <?php } ?>
              </select>
              <div class="form-text">We’ll attach this visit to the selected pet’s record.</div>
            <?php } else { ?>
              <div class="alert alert-warning mb-0">
                You don’t have any pet profiles yet. Please add a pet profile first to book an appointment.
              </div>
            <?php } ?>
          </div>
        </div>

        <?php if (!empty($perDay) && $ownerPets) { ?>
          <div class="row g-3">
            <?php foreach ($perDay as $date => $times) { ?>
              <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow" style="border-radius:var(--radius);transition:.25s">
                  <div class="card-header text-center fw-bold text-white"
                       style="background:var(--primary);border-top-left-radius:var(--radius);border-top-right-radius:var(--radius)">
                    <?php echo date('D, M d', strtotime($date)); ?>
                  </div>
                  <div class="card-body">
                    <?php foreach ($times as $time) {
                      $id = 's_'.md5($date.'|'.$time); ?>
                      <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="slot" id="<?php echo $id; ?>" value="<?php echo $date.'|'.$time; ?>" required>
                        <label class="form-check-label fw-semibold" for="<?php echo $id; ?>">
                          <?php echo date('h:i A', strtotime($time)); ?>
                        </label>
                      </div>
                    <?php } ?>
                  </div>
                </div>
              </div>
            <?php } ?>
          </div>

          <div class="text-center mt-4">
            <button class="btn btn-lg px-5 text-white" style="background:linear-gradient(45deg,var(--primary),var(--accent));border-radius:12px">
              <i class="bi bi-calendar-check me-2"></i> Book Appointment
            </button>
          </div>
        <?php } elseif ($ownerPets) { ?>
          <div class="alert alert-warning text-center shadow-sm">
            This vet has not set any availability slots yet.
          </div>
        <?php } ?>
      </form>
    </div>
  </section>
</main>

<style>
.card:hover{transform:translateY(-4px);box-shadow:0 12px 30px rgba(0,0,0,.12)!important}
</style>

<?php include __DIR__.'/includes/footer.php'; ?>
