<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
if (!defined('BASE')) define('BASE', '/furshield');
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return '$'.number_format((float)$n, 2); }

define('FS_SHIP_FLAT', 25.00);

$raw = array_values(array_filter($_SESSION['cart'] ?? [], fn($it)=> isset($it['id']) && (int)($it['qty'] ?? 0) > 0));
$items = [];
$subtotal = 0.0;

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
$total    = $subtotal + $shipping;

if (isset($_POST['update']) && isset($_POST['qty']) && is_array($_POST['qty'])) {
  foreach ($_POST['qty'] as $pid => $q) {
    $pid = (int)$pid; $q = max(0, (int)$q);
    foreach ($_SESSION['cart'] ?? [] as $k => $ci) {
      if ((int)$ci['id'] === $pid) {
        if ($q === 0) unset($_SESSION['cart'][$k]);
        else $_SESSION['cart'][$k]['qty'] = $q;
      }
    }
  }
  header("Location: ".BASE."/cart.php");
  exit;
}

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE; ?>/assets/css/style.css">
<main class="py-4">
  <div class="container">
    <h1 class="h4 mb-3">Your Cart</h1>

    <?php if (!$items): ?>
      <div class="card p-4">
        <p class="mb-3">Your cart is empty.</p>
        <a class="btn btn-primary" href="<?php echo BASE; ?>/catalog.php">Browse Products</a>
      </div>
    <?php else: ?>
      <form method="post">
        <div class="row g-4">
          <div class="col-lg-8">
            <div class="card p-3">
              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th style="width:120px">Price</th>
                      <th style="width:120px">Qty</th>
                      <th style="width:120px" class="text-end">Line</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($items as $it):
                      $img = trim($it['image'] ?? '');
                      if ($img !== '' && !preg_match('#^https?://#i', $img)) {
                        $img = (stripos(ltrim($img,'/'),'uploads/') === 0)
                          ? BASE.'/'.ltrim($img,'/')
                          : BASE.'/uploads/products/'.ltrim($img,'/');
                      }
                      if ($img === '') $img = BASE.'/assets/placeholder/product.jpg';
                    ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <img src="<?php echo h($img); ?>" class="rounded" style="width:56px;height:56px;object-fit:cover" alt="">
                          <div class="fw-semibold"><?php echo h($it['name']); ?></div>
                        </div>
                      </td>
                      <td><?php echo money($it['price']); ?></td>
                      <td>
                        <input type="number" name="qty[<?php echo (int)$it['id']; ?>]" min="0" value="<?php echo (int)$it['qty']; ?>" class="form-control form-control-sm" />
                      </td>
                      <td class="text-end"><?php echo money($it['line']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" name="update" value="1"><i class="bi bi-arrow-repeat me-1"></i>Update Cart</button>
                <a class="btn btn-outline-danger" href="<?php echo BASE; ?>/actions/cart-clear.php">Clear Cart</a>
                <a class="btn btn-outline-primary ms-auto" href="<?php echo BASE; ?>/catalog.php">Continue Shopping</a>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card p-3">
              <h2 class="h6 mb-3">Summary</h2>
              <div class="d-flex justify-content-between mb-1"><span class="text-muted">Subtotal</span><b><?php echo money($subtotal); ?></b></div>
              <div class="d-flex justify-content-between mb-1"><span class="text-muted">Shipping</span><b><?php echo money($shipping); ?></b></div>
              <div class="d-flex justify-content-between fs-5 border-top pt-2 mt-2"><span>Total</span><span class="fw-bold"><?php echo money($total); ?></span></div>
              <a class="btn btn-primary w-100 mt-3" href="<?php echo BASE; ?>/checkout.php"><i class="bi bi-credit-card me-1"></i>Proceed to Checkout</a>
            </div>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
