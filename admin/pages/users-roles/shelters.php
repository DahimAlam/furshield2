<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_role('admin');

if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

/* --- Fetch Active Shelters --- */
$shelters = [];
$res = $conn->query("
    SELECT u.id, u.name, u.email, u.created_at, u.status,
           s.shelter_name, s.reg_no, s.capacity, s.address, s.city, s.country, s.logo_image, s.reg_doc
    FROM users u
    JOIN shelters s ON s.user_id = u.id
    WHERE u.role='shelter' AND u.status='active'
    ORDER BY u.created_at DESC
");
if ($res) while ($r = $res->fetch_assoc()) $shelters[] = $r;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="admin-main p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold text-dark">Shelters</h1>
  </div>

  <div class="card shadow-sm rounded-4 border-0">
    <div class="card-body p-4">
      <?php if(!$shelters): ?>
        <div class="alert alert-info">No shelters found.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle table-hover">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Logo</th>
                <th>Shelter Name</th>
                <th>Email</th>
                <th>Reg. No</th>
                <th>Capacity</th>
                <th>Address</th>
                <th>City</th>
                <th>Country</th>
                <th>Reg. Document</th>
                <th>Status</th>
                <th>Created At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($shelters as $i=>$s): ?>
              <tr>
                <td><?php echo $i+1; ?></td>
                <td>
                  <?php if($s['logo_image'] && file_exists(__DIR__."/../../../".$s['logo_image'])): ?>
                    <img src="<?php echo BASE.'/'.$s['logo_image']; ?>" alt="Logo" class="rounded-circle" width="45" height="45">
                  <?php else: ?>
                    <span class="badge bg-secondary">No Logo</span>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($s['shelter_name']); ?></td>
                <td><?php echo htmlspecialchars($s['email']); ?></td>
                <td><?php echo htmlspecialchars($s['reg_no']); ?></td>
                <td><?php echo (int)($s['capacity']); ?></td>
                <td><?php echo htmlspecialchars($s['address']); ?></td>
                <td><?php echo htmlspecialchars($s['city']); ?></td>
                <td><?php echo htmlspecialchars($s['country']); ?></td>
                <td>
                  <?php if($s['reg_doc'] && file_exists(__DIR__."/../../../".$s['reg_doc'])): ?>
                    <a href="<?php echo BASE.'/'.$s['reg_doc']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                  <?php else: ?>
                    <span class="badge bg-secondary">Not Uploaded</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if($s['status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge bg-warning"><?php echo htmlspecialchars($s['status']); ?></span>
                  <?php endif; ?>
                </td>
                <td><?php echo date("d M Y", strtotime($s['created_at'])); ?></td>
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
