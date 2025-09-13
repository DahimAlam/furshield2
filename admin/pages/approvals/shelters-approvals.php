<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_role('admin');

if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

/* --- Handle Approve/Reject --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['email'])) {
    $id = (int)$_POST['id'];
    $email = trim($_POST['email']);
    if ($_POST['action'] === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status='active', approved_at=NOW() WHERE id=? AND role='shelter'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "<script>sendEmail('approved', '".htmlspecialchars($email,ENT_QUOTES)."');</script>";
    } elseif ($_POST['action'] === 'reject') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role='shelter'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "<script>sendEmail('rejected', '".htmlspecialchars($email,ENT_QUOTES)."');</script>";
    }
}

/* --- Fetch Pending Shelters --- */
$shelters = [];
$res = $conn->query("
    SELECT u.id, u.name, u.email, u.created_at,
           s.shelter_name, s.reg_no, s.capacity, s.address, s.city, s.country, s.logo_image, s.reg_doc
    FROM users u
    JOIN shelters s ON s.user_id = u.id
    WHERE u.role='shelter' AND u.status='pending'
    ORDER BY u.created_at DESC
");
if ($res) while ($r = $res->fetch_assoc()) $shelters[] = $r;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="admin-main p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold text-dark">Shelter Approvals</h1>
  </div>

  <div class="card shadow-sm rounded-4 border-0">
    <div class="card-body p-4">
      <?php if(!$shelters): ?>
        <div class="alert alert-info">No pending shelters at the moment.</div>
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
                <th>Applied On</th>
                <th>Action</th>
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
                <td><span class="badge bg-secondary"><?php echo date("d M Y", strtotime($s['created_at'])); ?></span></td>
                <td>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($s['email']); ?>">
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm rounded-pill px-3 me-1">
                      <i class="bi bi-check-circle"></i> Approve
                    </button>
                  </form>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($s['email']); ?>">
                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm rounded-pill px-3">
                      <i class="bi bi-x-circle"></i> Reject
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
<script>
(function(){ emailjs.init("RDrS71iB2MkkT_3oY"); })();

function sendEmail(type, email){
  let templateParams = {
    to_email: email,
    subject: type === 'approved' ? "Your Shelter Account Approved – FurShield" : "Your Shelter Account Rejected – FurShield",
    message: type === 'approved'
      ? "Congratulations! Your shelter account has been approved. You can now log in to FurShield."
      : "We are sorry. Your shelter account application was not approved. Please contact support for details."
  };

  emailjs.send("service_7u2waid", "template_4m29zcc", templateParams)
    .then(() => { alert("Email sent to " + email); })
    .catch((err) => { alert("Email failed: " + JSON.stringify(err)); });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
