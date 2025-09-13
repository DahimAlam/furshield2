<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_role('admin');

if (!defined('BASE')) define('BASE','/furshield');
$conn->set_charset('utf8mb4');

/* --- Handle Upload --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = uniqid('gallery_', true) . '.' . $ext;
        $dest = __DIR__ . '/../../../uploads/gallery/' . $newName;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $stmt = $conn->prepare("INSERT INTO gallery (image_path) VALUES (?)");
            $stmt->bind_param("s", $newName);
            $stmt->execute();
            header("Location: gallery.php?success=1");
            exit;
        }
    }
}

/* --- Handle Delete --- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $res = $conn->query("SELECT image_path FROM gallery WHERE id=$id");
    if ($res && $row = $res->fetch_assoc()) {
        $filePath = __DIR__ . '/../../../uploads/gallery/' . $row['image_path'];
        if (file_exists($filePath)) unlink($filePath);
        $conn->query("DELETE FROM gallery WHERE id=$id");
    }
    header("Location: gallery.php?deleted=1");
    exit;
}

/* --- Fetch All Images --- */
$gallery = [];
$res = $conn->query("SELECT id, image_path, created_at FROM gallery ORDER BY created_at DESC");
if ($res) while ($r = $res->fetch_assoc()) $gallery[] = $r;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="admin-main p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold text-dark">Gallery Manager</h1>
  </div>

  <!-- Upload Form -->
  <div class="card mb-4 shadow-sm border-0 rounded-4">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" class="d-flex align-items-center gap-3">
        <input type="file" name="image" class="form-control" accept="image/*" required>
        <button type="submit" class="btn btn-primary rounded-pill px-4">
          <i class="bi bi-upload"></i> Upload
        </button>
      </form>
    </div>
  </div>

  <!-- Gallery Grid -->
  <?php if(!$gallery): ?>
    <div class="alert alert-info">No images uploaded yet.</div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach($gallery as $g): ?>
      <div class="col-md-4 col-lg-3 gallery-card">
        <div class="card shadow-sm border-0 rounded-4 h-100">
          <div class="ratio ratio-1x1">
            <img src="<?php echo BASE.'/uploads/gallery/'.htmlspecialchars($g['image_path']); ?>" 
                 class="card-img-top rounded-top-4 gallery-img" alt="Gallery Image">
          </div>
          <div class="card-body text-center">
            <span class="badge bg-secondary mb-2">
              <?php echo date("d M Y", strtotime($g['created_at'])); ?>
            </span>
            <div>
              <a href="gallery.php?delete=<?php echo $g['id']; ?>" 
                 class="btn btn-danger btn-sm rounded-pill px-3"
                 onclick="return confirm('Delete this image?')">
                <i class="bi bi-trash"></i> Delete
              </a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
