<?php


session_start();
require_once __DIR__."/includes/db.php";
require_once __DIR__."/includes/auth.php";
if (!defined('BASE')) define('BASE', '/furshield');

/* Redirect if already logged in */
if (logged_in()) {
  $role = $_SESSION['user']['role'] ?? '';
  $map = [
    'owner'   => BASE.'/owners/dashboard.php',
    'vet'     => BASE.'/vets/dashboard.php',
    'shelter' => BASE.'/shelters/dashboard.php',
    'admin'   => BASE.'/admin/dashboard.php',
  ];
  header("Location: ".($map[$role] ?? BASE.'/')); exit;
}

$err = '';
$pendingInfo = null; // if set, show waiting UI

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') {
    $err = 'Please enter a valid email and password.';
  } else {
    // NOTE: created_at included because it's used below for pending timers
    $stmt = $conn->prepare("SELECT id, role, name, email, pass_hash, status, created_at FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($u && password_verify($pass, $u['pass_hash'])) {
      $status = $u['status'] ?? 'active';

      /* --- Explicit fast-path: owners go straight to their dashboard --- */
      if (($u['role'] ?? '') === 'owner') {
        unset($u['pass_hash']);
        $_SESSION['user'] = $u;
        header('Location: '.BASE.'/owners/dashboard.php'); exit;
      }

      // For vets/shelters, block if not active; otherwise proceed
      if (($u['role']==='vet' || $u['role']==='shelter') && $status !== 'active') {
        if ($status === 'pending') {
          // compute remaining time within 48h window
          $created = strtotime($u['created_at'] ?? 'now');
          $elapsed = max(0, time() - $created);
          $totalWindow = 48 * 3600;
          $remaining = max(0, $totalWindow - $elapsed);
          $progressPct = min(100, (int)floor(($elapsed / $totalWindow) * 100));

          $h = floor($remaining / 3600);
          $m = floor(($remaining % 3600) / 60);
          $pendingInfo = [
            'name'      => $u['name'],
            'email'     => $u['email'],
            'role'      => $u['role'],
            'createdAt' => date('M d, Y H:i', $created),
            'remaining' => sprintf('%02dh %02dm', $h, $m),
            'progress'  => $progressPct,
            'note'      => 'Once admin approves, you can log in immediately (no need to wait full 48h).'
          ];
        } elseif ($status === 'rejected') {
          $err = 'Your registration was rejected. Please contact support.';
        }
      } else {
        // All other roles (including admin, active vet/shelter)
        unset($u['pass_hash']);
        $_SESSION['user'] = $u;
        $map = [
          'owner'   => BASE.'/owners/dashboard.php',
          'vet'     => BASE.'/vets/dashboard.php',
          'shelter' => BASE.'/shelters/dashboard.php',
          'admin'   => BASE.'/admin/dashboard.php',
        ];
        header("Location: ".($map[$u['role']] ?? BASE.'/')); exit;
      }
    } else {
      $err = 'Invalid email or password.';
    }
  }
}

$justRegistered = isset($_GET['registered']) ? $_GET['registered'] : '';
include __DIR__."/includes/header.php";
?>
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card card-soft p-4 p-md-5">
      <div class="d-flex align-items-center mb-3">
        <span class="logo-badge me-2"><i class="bi bi-shield-heart text-white"></i></span>
        <h1 class="h4 mb-0 brand-text">Welcome back</h1>
      </div>

      <?php if ($justRegistered==='pending'): ?>
        <div class="alert alert-success py-2">
          Registration received. Admin approval required (up to 48 hours). We’ll email you once approved.
        </div>
      <?php elseif ($justRegistered): ?>
        <div class="alert alert-success py-2">Account created successfully. Please log in.</div>
      <?php endif; ?>

      <?php if ($pendingInfo): ?>
        <div class="card card-soft p-3 mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <div><strong>Approval in progress</strong></div>
            <div class="small text-soft"><?php echo htmlspecialchars($pendingInfo['email']); ?></div>
          </div>
          <div class="small text-soft">Submitted: <?php echo htmlspecialchars($pendingInfo['createdAt']); ?></div>

          <div class="progress my-3" style="height:10px;">
            <div class="progress-bar" role="progressbar"
                 style="width: <?php echo (int)$pendingInfo['progress']; ?>%;"
                 aria-valuenow="<?php echo (int)$pendingInfo['progress']; ?>" aria-valuemin="0" aria-valuemax="100">
            </div>
          </div>
          <div class="small">Estimated remaining (within 48h window): <b><?php echo htmlspecialchars($pendingInfo['remaining']); ?></b></div>
          <div class="small text-muted mt-1"><?php echo htmlspecialchars($pendingInfo['note']); ?></div>
        </div>

        <!-- Vertical timeline -->
        <ul class="timeline-vertical mb-3">
          <li class="timeline-item success">
            <span class="dot"></span>
            <div class="timeline-card">
              <div class="d-flex align-items-center gap-2">
                <span class="icon-circle success"><i class="bi bi-person-check"></i></span>
                <div>
                  <div class="fw-semibold">Registration submitted</div>
                  <div class="timeline-meta"><?php echo htmlspecialchars($pendingInfo['createdAt']); ?></div>
                </div>
              </div>
            </div>
          </li>

          <li class="timeline-item active">
            <span class="dot"></span>
            <div class="timeline-card">
              <div class="d-flex align-items-center gap-2">
                <span class="icon-circle"><i class="bi bi-shield-check"></i></span>
                <div>
                  <div class="fw-semibold">Admin reviewing</div>
                  <div class="timeline-meta">You’ll be notified by email once approved.</div>
                </div>
              </div>
            </div>
          </li>

          <li class="timeline-item">
            <span class="dot"></span>
            <div class="timeline-card">
              <div class="d-flex align-items-center gap-2">
                <span class="icon-circle accent"><i class="bi bi-envelope"></i></span>
                <div>
                  <div class="fw-semibold">Approval email</div>
                  <div class="timeline-meta">Log in immediately after approval.</div>
                </div>
              </div>
            </div>
          </li>
        </ul>

        <div class="text-center">
          <a href="<?php echo BASE; ?>/login.php" class="btn btn-outline-primary">Back to Login</a>
        </div>

      <?php else: ?>
        <?php if ($err): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
        <form method="post" novalidate>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required placeholder="you@example.com">
          </div>
          <div class="mb-2">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required placeholder="••••••••">
          </div>
          <button class="btn btn-primary w-100 mt-2">Log In</button>
        </form>

        <div class="mt-3 d-flex justify-content-between flex-wrap gap-2">
          <a class="small link-muted" href="<?php echo BASE; ?>/register.php">Create Owner account</a>
          <a class="small link-muted" href="<?php echo BASE; ?>/register-vet.php">Register as Vet</a>
          <a class="small link-muted" href="<?php echo BASE; ?>/register-shelter.php">Register Shelter</a>
        </div>
        <div class="mt-4 small text-muted">
          We never store plain-text passwords. Your password is secured with bcrypt.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__."/includes/footer.php"; ?>
