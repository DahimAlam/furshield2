<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

$pendingVets = (int)($conn->query("SELECT COUNT(*) c FROM users u JOIN vets v ON v.user_id=u.id WHERE u.role='vet' AND u.status='pending'")->fetch_assoc()['c'] ?? 0);
$pendingShelters = (int)($conn->query("SELECT COUNT(*) c FROM users u JOIN shelters s ON s.user_id=u.id WHERE u.role='shelter' AND u.status='pending'")->fetch_assoc()['c'] ?? 0);
$lowStock = (int)($conn->query("SELECT COUNT(*) c FROM products WHERE stock_qty < 5")->fetch_assoc()['c'] ?? 0);
$draftBlogs = (int)($conn->query("SELECT COUNT(*) c FROM blogs WHERE COALESCE(is_active,0)=0")->fetch_assoc()['c'] ?? 0);
$newsletterCount = (int)($conn->query("SELECT COUNT(*) c FROM newsletter")->fetch_assoc()['c'] ?? 0);

$path = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$active = fn(array $files) => in_array($path, $files, true) ? 'active' : '';
?>

<aside class="admin-sidebar">
  <div class="brand">
    <i class="bi bi-shield-shaded me-2"></i> FurShield Admin
  </div>

  <nav class="nav flex-column">
    <div class="nav-section">Overview</div>
    <a class="nav-link <?php echo $active(['dashboard.php']); ?>" href="<?php echo BASE; ?>/admin/dashboard.php">
      <i class="bi bi-speedometer2"></i><span>Dashboard</span>
    </a>

    <div class="nav-section">Approvals</div>
    <a class="nav-link <?php echo $active(['vets-approvals.php']); ?>" href="<?php echo BASE; ?>/admin/pages/approvals/vets-approvals.php">
      <i class="bi bi-stethoscope"></i><span>Vet Approvals</span>
      <?php if($pendingVets): ?><span class="badge-soft"><?php echo $pendingVets; ?></span><?php endif; ?>
    </a>
    <a class="nav-link <?php echo $active(['shelters-approvals.php']); ?>" href="<?php echo BASE; ?>/admin/pages/approvals/shelters-approvals.php">
      <i class="bi bi-house-heart"></i><span>Shelter Approvals</span>
      <?php if($pendingShelters): ?><span class="badge-soft"><?php echo $pendingShelters; ?></span><?php endif; ?>
        </a>
  

    <div class="nav-section">Users & Roles</div>
    <a class="nav-link <?php echo $active(['vets.php']); ?>" href="<?php echo BASE; ?>/admin/pages/users-roles/vets.php"><i class="bi bi-heart-pulse"></i><span>Vets</span></a>
    <a class="nav-link <?php echo $active(['shelters.php']); ?>" href="<?php echo BASE; ?>/admin/pages/users-roles/shelters.php"><i class="bi bi-buildings"></i><span>Shelters</span></a>
    <a class="nav-link <?php echo $active(['owners.php']); ?>" href="<?php echo BASE; ?>/admin/pages/users-roles/owners.php"><i class="bi bi-person-badge"></i><span>Owners</span></a>

    <div class="nav-section">Content</div>
    <a class="nav-link <?php echo $active(['appointments.php']); ?>" href="<?php echo BASE; ?>/admin/pages/content/appointments.php"><i class="bi bi-paw"></i><span>Appointments</span></a>
    <a class="nav-link <?php echo $active(['pets.php']); ?>" href="<?php echo BASE; ?>/admin/pages/content/pets.php"><i class="bi bi-paw"></i><span>Pets</span></a>
    <a class="nav-link <?php echo $active(['products.php']); ?>" href="<?php echo BASE; ?>/admin/pages/content/products.php"><i class="bi bi-bag-check"></i><span>Products</span>
      <?php if($lowStock): ?><span class="badge-soft"><?php echo $lowStock; ?></span><?php endif; ?>
    </a>
    <a class="nav-link <?php echo $active(['blogs.php']); ?>" href="<?php echo BASE; ?>/admin/pages/content/blogs.php"><i class="bi bi-journal-text"></i><span>Blogs</span>
      <?php if($draftBlogs): ?><span class="badge-soft-amber"><?php echo $draftBlogs; ?></span><?php endif; ?>
    </a>
    <a class="nav-link <?php echo $active(['faqs.php']); ?>" href="<?php echo BASE; ?>/admin/pages/content/faqs.php"><i class="bi bi-question-circle"></i><span>FAQs</span></a>
    <a class="nav-link <?php echo $active(['testimonials.php']); ?>" href="<?php echo BASE; ?>/admin/pages/content/testimonials.php"><i class="bi bi-chat-heart"></i><span>Testimonials</span></a>
    <a class="nav-link <?php echo $active(['events.php']); ?>" href="<?php echo BASE; ?>/admin/pages/content/events.php"><i class="bi bi-calendar-event"></i><span>Events</span></a>

 

    <div class="nav-section">System</div>
    <a class="nav-link <?php echo $active(['settings.php']); ?>" href="<?php echo BASE; ?>/admin/pages/system/settings.php"><i class="bi bi-sliders2"></i><span>Settings</span></a>
    <a class="nav-link logout" href="<?php echo BASE; ?>/logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
  </nav>

  <div class="sidebar-footer">
    <i class="bi bi-stars me-2"></i> Sand Sunset theme active
  </div>
</aside>
