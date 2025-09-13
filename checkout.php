<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
if (!defined('BASE')) define('BASE', '/furshield');
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return '$'.number_format((float)$n, 2); }

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];
$user = $_SESSION['user'] ?? null;

define('FS_SHIP_FLAT', 25.00);

$raw = array_values(array_filter($_SESSION['cart'] ?? [], fn($it)=> isset($it['id']) && (int)($it['qty'] ?? 0) > 0));
$items = [];
$subtotal = 0.0;
$shipping = 0.0;
$discount = 0.0;
$total    = 0.0;

if ($raw) {
  $ids = array_map(fn($it)=> (int)$it['id'], $raw);
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $stmt = $conn->prepare("SELECT id,name,price,IFNULL(image,'') AS image FROM products WHERE id IN ($in)");
  $stmt->bind_param($types, ...$ids);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $map = [];
  foreach ($rows as $r) $map[(int)$r['id']] = $r;

  foreach ($raw as $r) {
    $pid = (int)$r['id'];
    $qty = max(1, (int)$r['qty']);
    if (!isset($map[$pid])) continue;
    $p = $map[$pid];
    $line = (float)$p['price'] * $qty;
    $items[] = [
      'id'    => $pid,
      'name'  => $p['name'],
      'image' => $p['image'],
      'price' => (float)$p['price'],
      'qty'   => $qty,
      'line'  => $line,
    ];
    $subtotal += $line;
  }
}

$shipping = $items ? FS_SHIP_FLAT : 0.0;
$total    = $subtotal + $shipping - $discount;

$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
    $err = 'Invalid request. Please refresh and try again.';
  } elseif (!$items) {
    $err = 'Your cart is empty.';
  } else {
    $name   = trim($_POST['name']   ?? ($user['name']  ?? ''));
    $email  = trim($_POST['email']  ?? ($user['email'] ?? ''));
    $phone  = trim($_POST['phone']  ?? '');
    $addr   = trim($_POST['address']?? '');
    $city   = trim($_POST['city']   ?? '');
    $zip    = trim($_POST['postal'] ?? '');
    $pay    = ($_POST['pay'] ?? 'COD') === 'CARD' ? 'CARD' : 'COD';

    if ($name==='' || $email==='' || $phone==='' || $addr==='' || $city==='') {
      $err = 'Please complete all required fields.';
    } else {
      $conn->query("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(160) NOT NULL,
        phone VARCHAR(40) NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(80) NOT NULL,
        postal VARCHAR(20) NULL,
        payment_method ENUM('COD','CARD') NOT NULL DEFAULT 'COD',
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        shipping DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status ENUM('pending','paid','shipped','cancelled') NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $conn->query("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        name VARCHAR(200) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        qty INT NOT NULL,
        image VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("INSERT INTO orders
          (user_id,name,email,phone,address,city,postal,payment_method,subtotal,shipping,discount,total,status)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'pending')");
        $uid = isset($user['id']) ? (int)$user['id'] : null;
        // For NULL binding use 'i' and pass null safely via bind_param by using variable that is null
        $stmt->bind_param(
          'isssssssddds',
          $uid, $name, $email, $phone, $addr, $city, $zip, $pay,
          $subtotal, $shipping, $discount, $total
        );
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO order_items (order_id,product_id,name,price,qty,image) VALUES (?,?,?,?,?,?)");
        foreach ($items as $it) {
          $stmt->bind_param('iisdis', $orderId, $it['id'], $it['name'], $it['price'], $it['qty'], $it['image']);
          $stmt->execute();
        }
        $stmt->close();

        $conn->commit();
        $_SESSION['cart'] = [];
        $ok = "Order #$orderId placed successfully.";
      } catch (Throwable $e) {
        $conn->rollback();
        $err = 'Database error: '.$e->getMessage();
      }
    }
  }
}

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE; ?>/assets/css/style.css">
<main class="py-4">
  <div class="container">
    <h1 class="h3 mb-3">Checkout</h1>

    <?php if ($err): ?>
      <div class="alert alert-danger"><?php echo h($err); ?></div>
    <?php elseif ($ok): ?>
      <div class="alert alert-success d-flex justify-content-between align-items-center">
        <span><?php echo h($ok); ?></span>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo BASE; ?>/orders.php">View Orders</a>
      </div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="card p-4">
        <p class="mb-3">Your cart is empty.</p>
        <a class="btn btn-primary" href="<?php echo BASE; ?>/catalog.php">Continue Shopping</a>
      </div>
    <?php else: ?>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card p-3">
          <h2 class="h5 mb-3">Shipping Details</h2>
          <form method="post">
            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input class="form-control" name="name" required value="<?php echo h($_POST['name'] ?? ($user['name'] ?? '')); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" required value="<?php echo h($_POST['email'] ?? ($user['email'] ?? '')); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" required value="<?php echo h($_POST['phone'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">City</label>
                <input class="form-control" name="city" required value="<?php echo h($_POST['city'] ?? ''); ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <input class="form-control" name="address" required value="<?php echo h($_POST['address'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Postal Code</label>
                <input class="form-control" name="postal" value="<?php echo h($_POST['postal'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Payment Method</label>
                <select class="form-select" name="pay">
                  <option value="COD" <?php echo (($_POST['pay'] ?? '')!=='CARD'?'selected':''); ?>>Cash on Delivery</option>
                  <option value="CARD" <?php echo (($_POST['pay'] ?? '')==='CARD'?'selected':''); ?>>Card (manual confirm)</option>
                </select>
              </div>
              <div class="col-12">
                <button name="place_order" class="btn btn-primary w-100 py-2">
                  <i class="bi bi-check2-circle me-1"></i> Place Order
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card p-3">
          <h2 class="h5 mb-3">Order Summary</h2>
          <div class="vstack gap-2">
            <?php foreach ($items as $it):
              $img = trim($it['image'] ?? '');
              if ($img !== '' && !preg_match('#^https?://#i', $img)) {
                $img = (stripos(ltrim($img,'/'),'uploads/') === 0)
                  ? BASE.'/'.ltrim($img,'/')
                  : BASE.'/uploads/products/'.ltrim($img,'/');
              }
              if ($img === '') $img = BASE.'/assets/placeholder/product.jpg';
            ?>
            <div class="d-flex align-items-center gap-2 py-1 border-bottom">
              <img src="<?php echo h($img); ?>" alt="" class="rounded" style="width:56px;height:56px;object-fit:cover">
              <div class="flex-grow-1">
                <div class="fw-semibold small text-truncate" title="<?php echo h($it['name']); ?>"><?php echo h($it['name']); ?></div>
                <div class="text-muted small">Qty <?php echo (int)$it['qty']; ?> • <?php echo money($it['price']); ?></div>
              </div>
              <div class="fw-semibold"><?php echo money($it['line']); ?></div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-3 pt-2 border-top">
            <div class="d-flex justify-content-between"><span class="text-muted">Subtotal</span><b><?php echo money($subtotal); ?></b></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Shipping</span><b><?php echo money($shipping); ?></b></div>
            <div class="d-flex justify-content-between fs-5 mt-2"><span>Total</span><span class="fw-bold"><?php echo money($total); ?></span></div>
          </div>
          <p class="small text-muted mt-2 mb-0">Flat shipping: <?php echo money(FS_SHIP_FLAT); ?> on all orders.</p>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
