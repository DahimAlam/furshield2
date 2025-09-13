<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_role('admin');

if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

/* --- Fetch Active Owners --- */
$owners = [];
$res = $conn->query("
    SELECT u.id, u.name, u.email, u.created_at, u.status,
           o.phone, o.address, o.city, o.country, o.adopt_interest
    FROM users u
    LEFT JOIN owners o ON o.user_id = u.id
    WHERE u.role='owner'
    ORDER BY u.created_at DESC
");
if ($res) while ($r = $res->fetch_assoc()) $owners[] = $r;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="admin-main p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold text-dark">Owners</h1>
  </div>

  <div class="card shadow-sm rounded-4 border-0">
    <div class="card-body p-4">
      <?php if(!$owners): ?>
        <div class="alert alert-info">No owners found.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle table-hover">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th>City</th>
                <th>Country</th>
                <th>Interested in Adoption</th>
                <th>Status</th>
                <th>Created At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($owners as $i=>$o): ?>
              <tr>
                <td><?php echo $i+1; ?></td>
                <td><?php echo htmlspecialchars($o['name']); ?></td>
                <td><?php echo htmlspecialchars($o['email']); ?></td>
                <td><?php echo htmlspecialchars($o['phone'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($o['address'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($o['city'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($o['country'] ?? '—'); ?></td>
                <td>
                  <?php if($o['adopt_interest'] == 1): ?>
                    <span class="badge bg-success">Yes</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">No</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if($o['status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge bg-warning"><?php echo htmlspecialchars($o['status']); ?></span>
                  <?php endif; ?>
                </td>
                <td><?php echo date("d M Y", strtotime($o['created_at'])); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
