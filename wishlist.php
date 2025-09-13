<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
if (!defined('BASE')) define('BASE', '/furshield');
if (empty($_SESSION['user'])) {
    header("Location: " . BASE . "/login.php?next=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
$conn->set_charset('utf8mb4');
$userId = (int)$_SESSION['user']['id'];
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function table_exists(mysqli $c, string $t): bool
{
    $t = $c->real_escape_string($t);
    $r = $c->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}'");
    return $r && $r->num_rows > 0;
}
function hascol(mysqli $c, string $t, string $col): bool
{
    $t = $c->real_escape_string($t);
    $col = $c->real_escape_string($col);
    $r = $c->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='{$t}' AND column_name='{$col}'");
    return $r && $r->num_rows > 0;
}
function rows(mysqli $c, string $sql, array $params = [], string $types = '')
{
    if (!$params) {
        $r = $c->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }
    $st = $c->prepare($sql);
    if ($types === '') $types = str_repeat('s', count($params));
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
function product_img(mysqli $c, array $p): string
{
    $imgCol = hascol($c, 'products', 'image_path') ? 'image_path' : (hascol($c, 'products', 'image') ? 'image' : (hascol($c, 'products', 'cover') ? 'cover' : ''));
    $rel = $imgCol ? ($p[$imgCol] ?? '') : '';
    if (!$rel) return BASE . '/assets/placeholder/product.jpg';
    if (strpos($rel, 'http://') === 0 || strpos($rel, 'https://') === 0) return $rel;
    if ($rel[0] === '/') return BASE . $rel;
    if (strpos($rel, 'uploads/') === 0) return BASE . '/' . ltrim($rel, '/');
    return BASE . '/uploads/products/' . rawurlencode($rel);
}
if (!table_exists($conn, 'wishlist')) {
    $conn->query("CREATE TABLE IF NOT EXISTS wishlist(
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_prod(user_id,product_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $err = 'Invalid request.';
    } else {
        if (isset($_POST['remove']) && isset($_POST['wid'])) {
            $wid = (int)$_POST['wid'];
            $st = $conn->prepare("DELETE FROM wishlist WHERE id=? AND user_id=?");
            $st->bind_param('ii', $wid, $userId);
            $st->execute();
            $st->close();
            $msg = 'Removed from wishlist.';
        } elseif (isset($_POST['clear'])) {
            $st = $conn->prepare("DELETE FROM wishlist WHERE user_id=?");
            $st->bind_param('i', $userId);
            $st->execute();
            $st->close();
            $msg = 'Wishlist cleared.';
        }
    }
}
$imgCol = hascol($conn, 'products', 'image_path') ? 'image_path' : (hascol($conn, 'products', 'image') ? 'image' : (hascol($conn, 'products', 'cover') ? 'cover' : "''"));
$items = rows(
    $conn,
    "SELECT w.id AS wid, p.id, p.name, p.price, " . (is_string($imgCol) && $imgCol !== "''" ? "p.`$imgCol` AS image" : "'' AS image") . "
   FROM wishlist w
   LEFT JOIN products p ON p.id=w.product_id
   WHERE w.user_id=?
   ORDER BY w.id DESC",
    [$userId],
    'i'
);
$subtotal = 0.0;
foreach ($items as $it) $subtotal += (float)($it['price'] ?? 0);
include __DIR__ . '/includes/header.php';
?>
<style>
    :root {
        --primary: #F59E0B;
        --border: #eee;
        --muted: #6B7280;
        --shadow: 0 10px 30px rgba(0, 0, 0, .08)
    }

    .page {
        max-width: 1100px;
        margin: 24px auto;
        padding: 0 16px
    }

    .h1 {
        font-family: Montserrat, system-ui, sans-serif;
        font-weight: 800;
        font-size: 28px;
        margin: 0
    }

    .sub {
        color: var(--muted);
        font-size: 14px
    }

    .actions {
        display: flex;
        gap: 8px;
        align-items: center
    }

    .card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: var(--shadow);
        padding: 16px
    }

    .grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 16px
    }

    .empty {
        padding: 28px;
        text-align: center;
        color: var(--muted)
    }

    .item {
        display: grid;
        grid-template-columns: 88px 1fr auto;
        gap: 12px;
        align-items: center;
        padding: 12px;
        border-bottom: 1px dashed #f1e6d7
    }

    .item:last-child {
        border-bottom: 0
    }

    .thumb {
        width: 88px;
        height: 88px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid #f3eee4;
        background: #faf8f4
    }

    .name {
        font-weight: 700
    }

    .meta {
        color: var(--muted);
        font-size: 13px
    }

    .price {
        font-weight: 800
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 0;
        border-radius: 12px;
        padding: 10px 14px;
        cursor: pointer;
        font-weight: 700
    }

    .btn-ghost {
        background: #fff;
        border: 1px solid #f0e7da
    }

    .btn-danger {
        background: linear-gradient(135deg, #f87171, #ef4444);
        color: #fff
    }

    .btn-primary {
        background: linear-gradient(135deg, #f59e0b, #ffb13b);
        color: #111
    }

    .btn-outline {
        background: #fff;
        border: 1px solid #f0e7da;
        color: #92400e
    }

    .header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 16px
    }

    .tools {
        display: flex;
        gap: 8px
    }

    .summary {
        position: sticky;
        top: 16px
    }

    .row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 8px 0
    }

    .total {
        font-size: 20px;
        font-weight: 900
    }

    .link {
        color: #b91c1c;
        text-decoration: none
    }

    @media (max-width: 920px) {
        .grid {
            grid-template-columns: 1fr
        }
    }
</style>

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

<main class="page">
    <div class="header">
        <div>
            <div class="h1">Your Wishlist</div>
            <div class="sub"><?= count($items) ?> item<?= count($items) == 1 ? '' : 's' ?></div>
        </div>
        <div class="tools">
            <?php if ($items): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <button class="btn btn-outline" name="clear" value="1"><i class="bi bi-x-circle"></i> Clear All</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="card" style="border-color:#bbf7d0;background:#ecfdf5;color:#065f46;margin-bottom:12px"><?= $msg ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="card" style="border-color:#fecaca;background:#fee2e2;color:#991b1b;margin-bottom:12px"><?= $err ?></div>
    <?php endif; ?>

    <div class="grid">
        <section class="card">
            <?php if (!$items): ?>
                <div class="empty">
                    Nothing here yet. Browse the catalog and tap the heart to save items.
                    <div style="margin-top:12px">
                        <a class="btn btn-primary" href="<?= BASE ?>/catalog.php"><i class="bi bi-bag-heart"></i> Start Shopping</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($items as $it):
                    $img = product_img($conn, $it);
                    $pid = (int)$it['id'];
                    $wid = (int)$it['wid'];
                    $name = $it['name'] ?? 'Product';
                    $price = (float)($it['price'] ?? 0);
                ?>
                    <div class="item">
                        <a href="<?= BASE ?>/product-details.php?id=<?= $pid ?>"><img class="thumb" src="<?= e($img) ?>" alt="<?= e($name) ?>"></a>
                        <div>
                            <a class="name" href="<?= BASE ?>/product-details.php?id=<?= $pid ?>"><?= e($name) ?></a>
                            <div class="meta">ID #<?= $pid ?></div>
                            <div class="actions" style="margin-top:8px">
                                <a class="btn btn-ghost" href="<?= BASE ?>/actions/cart-add.php?id=<?= $pid ?>"><i class="bi bi-cart-plus"></i> Add to Cart</a>
                                <form method="post" onsubmit="return confirm('Remove from wishlist?')">
                                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                    <input type="hidden" name="wid" value="<?= $wid ?>">
                                    <button class="btn btn-danger" name="remove" value="1"><i class="bi bi-trash3"></i> Remove</button>
                                </form>
                            </div>
                        </div>
                        <div class="price">$<?= number_format($price, 2) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <aside class="card summary">
            <div class="row">
                <div>Items</div>
                <div><?= count($items) ?></div>
            </div>
            <div class="row">
                <div>Subtotal</div>
                <div>$<?= number_format($subtotal, 2) ?></div>
            </div>
            <hr style="border:none;border-top:1px dashed #f0e7da;margin:12px 0">
            <div class="row total">
                <div>Total</div>
                <div>$<?= number_format($subtotal, 2) ?></div>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                <a class="btn btn-primary" href="<?= BASE ?>/catalog.php"><i class="bi bi-bag"></i> Continue Shopping</a>
                <?php if ($items): ?>
                    <a class="btn btn-ghost" href="<?= BASE ?>/cart.php"><i class="bi bi-cart"></i> Go to Cart</a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>