<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
if (!defined('BASE')) define('BASE', '/furshield');
$conn->set_charset('utf8mb4');

/* ---------- helpers ---------- */
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function table_exists(mysqli $c, string $t): bool
{
  $t = $c->real_escape_string($t);
  $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'");
  return $r && $r->num_rows > 0;
}
function col_exists(mysqli $c, string $t, string $col): bool
{
  $t = $c->real_escape_string($t);
  $col = $c->real_escape_string($col);
  $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
  return $r && $r->num_rows > 0;
}
function pickcol(mysqli $c, string $t, array $cands): ?string
{
  foreach ($cands as $x) {
    if (col_exists($c, $t, $x)) return $x;
  }
  return null;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

$settings = [];
if (table_exists($conn, 'site_settings') || table_exists($conn, 'homepage_settings')) {
  $st = table_exists($conn, 'site_settings') ? 'site_settings' : 'homepage_settings';
  $q = $conn->query("SELECT * FROM `$st` WHERE id=1");
  $settings = $q ? ($q->fetch_assoc() ?: []) : [];
}
$support_email = '';
foreach (['support_email', 'email', 'contact_email'] as $k) {
  if (!empty($settings[$k])) {
    $support_email = (string)$settings[$k];
    break;
  }
}
$support_phone = '';
foreach (['support_phone', 'phone', 'contact_phone'] as $k) {
  if (!empty($settings[$k])) {
    $support_phone = (string)$settings[$k];
    break;
  }
}
$address = '';
foreach (['address', 'office_address', 'contact_address'] as $k) {
  if (!empty($settings[$k])) {
    $address = (string)$settings[$k];
    break;
  }
}
$whatsapp = '';
foreach (['whatsapp', 'wa', 'whatsapp_number'] as $k) {
  if (!empty($settings[$k])) {
    $whatsapp = preg_replace('/\D+/', '', $settings[$k]);
    break;
  }
}
$map_embed = '';
foreach (['map_embed', 'map_iframe', 'map'] as $k) {
  if (!empty($settings[$k])) {
    $map_embed = (string)$settings[$k];
    break;
  }
}

$tbl = table_exists($conn, 'contact_messages') ? 'contact_messages' : (table_exists($conn, 'contacts') ? 'contacts' : null);
if (!$tbl) {
  $conn->query("CREATE TABLE IF NOT EXISTS contact_messages(
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL,
    phone VARCHAR(40) NULL,
    subject VARCHAR(200) NULL,
    message TEXT NOT NULL,
    user_id INT NULL,
    ip VARCHAR(64) NULL,
    ua VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(email), INDEX(created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  $tbl = 'contact_messages';
}

$C_ID   = pickcol($conn, $tbl, ['id', 'msg_id']);
$C_NAME = pickcol($conn, $tbl, ['name', 'full_name']);
$C_MAIL = pickcol($conn, $tbl, ['email', 'mail']);
$C_PH   = pickcol($conn, $tbl, ['phone', 'mobile', 'contact']);
$C_SUB  = pickcol($conn, $tbl, ['subject', 'title']);
$C_MSG  = pickcol($conn, $tbl, ['message', 'content', 'body']);
$C_UID  = pickcol($conn, $tbl, ['user_id', 'uid']);
$C_IP   = pickcol($conn, $tbl, ['ip', 'ip_address']);
$C_UA   = pickcol($conn, $tbl, ['ua', 'user_agent']);
$C_AT   = pickcol($conn, $tbl, ['created_at', 'created', 'added_at']);

$errors = [];
$flash = null;
$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Honeypot & CSRF
  $hp = trim($_POST['website'] ?? '');
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '')) $errors[] = 'Invalid session, please refresh and try again.';
  if ($hp !== '') $errors[] = 'Spam detected.';

  // Basic throttle: 10s between posts
  $last = $_SESSION['last_contact_submit'] ?? 0;
  if (time() - (int)$last < 10) $errors[] = 'Please wait a few seconds before submitting again.';

  // Inputs
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $subj = trim($_POST['subject'] ?? '');
  $msg  = trim($_POST['message'] ?? '');
  if ($name === '')  $errors[] = 'Name is required.';
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
  if ($msg === '')   $errors[] = 'Message is required.';
  if (strlen($msg) < 10) $errors[] = 'Message is too short.';

  if (!$errors) {
    // Prepare insert
    $cols = [];
    $phs = [];
    $types = '';
    $vals = [];
    if ($C_NAME) {
      $cols[] = "`$C_NAME`";
      $phs[] = '?';
      $types .= 's';
      $vals[] = $name;
    }
    if ($C_MAIL) {
      $cols[] = "`$C_MAIL`";
      $phs[] = '?';
      $types .= 's';
      $vals[] = $email;
    }
    if ($C_PH) {
      $cols[] = "`$C_PH`";
      $phs[] = '?';
      $types .= 's';
      $vals[] = $phone;
    }
    if ($C_SUB) {
      $cols[] = "`$C_SUB`";
      $phs[] = '?';
      $types .= 's';
      $vals[] = $subj;
    }
    if ($C_MSG) {
      $cols[] = "`$C_MSG`";
      $phs[] = '?';
      $types .= 's';
      $vals[] = $msg;
    }
    if ($C_UID) {
      $cols[] = "`$C_UID`";
      $phs[] = '?';
      $types .= 'i';
      $vals[] = (int)($_SESSION['user']['id'] ?? 0);
    }
    if ($C_IP) {
      $cols[] = "`$C_IP`";
      $phs[] = '?';
      $types .= 's';
      $vals[] = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    if ($C_UA) {
      $cols[] = "`$C_UA`";
      $phs[] = '?';
      $types .= 's';
      $vals[] = substr(($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    }

    if (!$cols || !$C_MSG || !$C_MAIL || !$C_NAME) {
      $errors[] = 'Storage not configured properly.';
    } else {
      $sql = "INSERT INTO `$tbl`(" . implode(',', $cols) . ") VALUES (" . implode(',', $phs) . ")";
      $st = $conn->prepare($sql);
      $st->bind_param($types, ...$vals);
      $ok = $st->execute();
      $st->close();

      if ($ok) {
        $_SESSION['last_contact_submit'] = time();
        $flash = 'Thanks! Your message has been sent.';
        // Email notify (optional)
        if ($support_email && function_exists('mail')) {
          $subClean = mb_substr(preg_replace("/[\r\n]+/", " ", $subj ?: 'New contact message'), 0, 180);
          $body = "Name: $name\nEmail: $email\nPhone: $phone\n\nMessage:\n$msg\n\n--\nFurShield Contact";
          @mail($support_email, $subClean, $body, "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\nReply-To: " . $email);
        }
        // Reset CSRF for one-time form
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
      } else {
        $errors[] = 'Could not save your message, please try again.';
      }
    }
  }
}

include __DIR__ . '/includes/header.php';
?>
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

  .hero-contact {
    background: radial-gradient(1200px 400px at 20% -10%, rgba(245, 158, 11, .22), transparent 40%),
      radial-gradient(900px 320px at 90% 0%, rgba(239, 68, 68, .16), transparent 45%),
      linear-gradient(180deg, #fff, rgba(255, 247, 237, .75));
    border-bottom: 1px solid #f1e6d7;
  }

  .hero-card {
    border: 1px solid #eee;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
  }

  .hero-card iframe,
  .hero-card img {
    width: 100%;
    height: 320px;
    border: 0;
    display: block;
  }

  .cardx {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 18px;
    box-shadow: var(--shadow);
  }

  .form-control,
  .form-select {
    border-radius: 12px;
    border: 1px solid #e8e8e8;
  }

  .btn-primary {
    background: var(--primary);
    border: none;
    color: #111;
    font-weight: 700
  }

  .btn-outline-ss {
    border: 1px solid #e6dcc9;
    color: #6b7280;
    background: #fff
  }

  .info-card {
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  }

  .info-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 44px rgba(0, 0, 0, .12);
    border-color: #e9e3d6
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

  /* hide honeypot */
  .hp {
    position: absolute !important;
    left: -9999px !important;
    top: -9999px !important;
    width: 1px;
    height: 1px;
    overflow: hidden;
  }
</style>

<main style="background:var(--bg); color:var(--text)">
  <section class="hero-contact py-5">
    <div class="container-fluid">
      <div class="row align-items-center g-4">
        <div class="col-lg-6">
          <span class="badge rounded-pill bg-white text-dark border me-2">Contact</span>
          <h1 class="display-6 fw-bold mt-2">We’d love to hear from you</h1>
          <p class="lead text-secondary">Questions, feedback, partnerships or press—drop us a line. We usually reply within 24–48 hours.</p>

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <div class="cardx p-3 info-card reveal">
                <div class="fw-semibold"><i class="bi bi-envelope me-1"></i> Email</div>
                <div class="text-muted"><?php echo $support_email ? '<a href="mailto:' . h($support_email) . '">' . h($support_email) . '</a>' : 'support@yourdomain.com'; ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="cardx p-3 info-card reveal">
                <div class="fw-semibold"><i class="bi bi-telephone me-1"></i> Phone</div>
                <div class="text-muted"><?php echo $support_phone ? '<a href="tel:' . h($support_phone) . '">' . h($support_phone) . '</a>' : '+92 300 0000000'; ?></div>
              </div>
            </div>
            <div class="col-md-12">
              <div class="cardx p-3 info-card reveal">
                <div class="fw-semibold"><i class="bi bi-geo-alt me-1"></i> Address</div>
                <div class="text-muted"><?php echo $address ? h($address) : 'Karachi, Pakistan'; ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="hero-card reveal">
            <?php if ($map_embed) {
              echo $map_embed;
            } else { ?>
              <iframe
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps?q=Karachi%20Pakistan&z=12&output=embed">
              </iframe>
            <?php } ?>
            <div class="p-3 small text-muted d-flex justify-content-between">
              <div>Mon–Fri · 10:00–18:00</div>
              <?php if ($whatsapp) { ?>
                <div><a href="https://wa.me/<?php echo h($whatsapp); ?>" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a></div>
              <?php } ?>
            </div>
          </div>
        </div>
      </div>

      <?php if ($flash) { ?>
        <div class="alert alert-success mt-3 border-0 shadow-sm"><?php echo h($flash); ?></div>
      <?php } ?>
      <?php if ($errors) { ?>
        <div class="alert alert-danger mt-3 border-0 shadow-sm">
          <?php foreach ($errors as $e) {
            echo '<div>' . h($e) . '</div>';
          } ?>
        </div>
      <?php } ?>
    </div>
  </section>

  <section class="py-5">
    <div class="container-fluid">
      <div class="row g-4">
        <div class="col-lg-7">
          <div class="cardx p-3 p-md-4 reveal">
            <h2 class="h4 mb-3">Send a Message</h2>
            <form method="post" novalidate>
              <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf_token']); ?>">
              <div class="hp"><input type="text" name="website" autocomplete="off" tabindex="-1" value=""></div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Name <span class="text-danger">*</span></label>
                  <input type="text" name="name" class="form-control" required placeholder="Your full name" value="<?php echo h($_POST['name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email <span class="text-danger">*</span></label>
                  <input type="email" name="email" class="form-control" required placeholder="you@email.com" value="<?php echo h($_POST['email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" name="phone" class="form-control" placeholder="+92 ..." value="<?php echo h($_POST['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Subject</label>
                  <input type="text" name="subject" class="form-control" placeholder="How can we help?" value="<?php echo h($_POST['subject'] ?? ''); ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Message <span class="text-danger">*</span></label>
                  <textarea name="message" class="form-control" rows="6" required placeholder="Write your message..."><?php echo h($_POST['message'] ?? ''); ?></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                  <button class="btn btn-primary">Send Message</button>
                  <a href="<?php echo BASE; ?>/" class="btn btn-outline-ss">Back to Home</a>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="cardx p-3 p-md-4 h-100 reveal">
            <h2 class="h5 mb-3">FAQs</h2>
            <div class="accordion" id="cfaq">
              <?php
              // Try loading FAQs if available
              $faqs = [];
              if (table_exists($conn, 'faqs')) {
                $fa = $conn->query("SELECT question,answer" . (col_exists($conn, 'faqs', 'is_active') ? ",is_active" : "") . " FROM faqs " . (col_exists($conn, 'faqs', 'is_active') ? "WHERE is_active=1" : "") . " ORDER BY id DESC LIMIT 5");
                if ($fa) {
                  while ($r = $fa->fetch_assoc()) {
                    $faqs[] = $r;
                  }
                }
              }
              if (!$faqs) {
                $faqs = [
                  ['question' => 'How soon will you reply?', 'answer' => 'We usually respond within 24–48 hours (Mon–Fri).'],
                  ['question' => 'Do you work with shelters?', 'answer' => 'Yes! Verified shelters can list pets and manage adoption interest.'],
                  ['question' => 'How do I book a vet?', 'answer' => 'Find a vet on the Vets page, view availability and book an appointment.'],
                ];
              }
              $i = 0;
              foreach ($faqs as $f): $i++; ?>
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button <?php echo $i > 1 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#fq<?php echo $i; ?>">
                      <?php echo h($f['question']); ?>
                    </button>
                  </h2>
                  <div id="fq<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 1 ? 'show' : ''; ?>">
                    <div class="accordion-body small text-muted"><?php echo nl2br(h($f['answer'])); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="mt-4">
              <h2 class="h6 mb-2">Other ways to reach us</h2>
              <div class="d-flex flex-wrap gap-2">
                <?php if ($support_email) { ?><a class="btn btn-sm btn-outline-secondary" href="mailto:<?php echo h($support_email); ?>"><i class="bi bi-envelope me-1"></i>Email</a><?php } ?>
                <?php if ($support_phone) { ?><a class="btn btn-sm btn-outline-secondary" href="tel:<?php echo h($support_phone); ?>"><i class="bi bi-telephone me-1"></i>Call</a><?php } ?>
                <?php if ($whatsapp) { ?><a class="btn btn-sm btn-outline-secondary" target="_blank" href="https://wa.me/<?php echo h($whatsapp); ?>"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a><?php } ?>
              </div>
            </div>

          </div>
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
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>