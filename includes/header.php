<?php
require_once __DIR__."/db.php";
require_once __DIR__."/auth.php";

if (!defined('BASE')) define('BASE','/furshield');

$u = user();
$avatar = $u['avatar'] ?? 'avatar.png';
$avatarUrl = BASE.'/assets/img/'.$avatar;

/* cart count (session) */
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
  foreach($_SESSION['cart'] as $it){ $cartCount += (int)($it['qty'] ?? 0); }
}

/* safe helpers (prefixed to avoid re-declare) */
function fs_table_exists($c, $t){
  $t = $c->real_escape_string($t);
  $q = $c->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}'");
  return $q && $q->num_rows>0;
}
function fs_col_exists($c, $t, $col){
  $t = $c->real_escape_string($t); $col = $c->real_escape_string($col);
  $q = $c->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='{$t}' AND column_name='{$col}'");
  return $q && $q->num_rows>0;
}
function fs_pick_col($c, $t, $cands){
  foreach($cands as $x){ if (fs_col_exists($c,$t,$x)) return $x; } return null;
}

/* wishlist count, robust to schema differences */
function fs_wishlist_count($conn, $user){
  if (!$user) return 0;
  $uid = (int)$user['id'];

  foreach (['wishlist','wishlists','favorites','favourites'] as $tbl) {
    if (!fs_table_exists($conn,$tbl)) continue;
    $userCol = fs_pick_col($conn,$tbl,['user_id','owner_id','created_by','account_id']);
    if (!$userCol) continue;
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM `$tbl` WHERE `$userCol`=?");
    $stmt->bind_param('i',$uid);
    $stmt->execute();
    $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $c;
  }

  if (fs_table_exists($conn,'wishlist_items') && fs_table_exists($conn,'wishlists')) {
    $wi_fk   = fs_pick_col($conn,'wishlist_items',['wishlist_id','list_id','wl_id']);
    $wl_id   = fs_pick_col($conn,'wishlists',['id','wishlist_id']);
    $wl_user = fs_pick_col($conn,'wishlists',['user_id','owner_id','created_by','account_id']);
    if ($wi_fk && $wl_id && $wl_user) {
      $sql = "SELECT COUNT(*) c
              FROM `wishlist_items` wi
              JOIN `wishlists` wl ON wi.`$wi_fk` = wl.`$wl_id`
              WHERE wl.`$wl_user`=?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('i',$uid);
      $stmt->execute();
      $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
      $stmt->close();
      return $c;
    }
  }

  return 0;
}

$wishCount = fs_wishlist_count($conn, $u);

/* role → dashboard */
$dashUrl = BASE."/";
if ($u) {
  $map = [
    'owner'   => BASE.'/owners/dashboard.php',
    'vet'     => BASE.'/vets/dashboard.php',
    'shelter' => BASE.'/shelters/dashboard.php',
    'admin'   => BASE.'/admin/dashboard.php',
  ];
  $dashUrl = $map[$u['role']] ?? BASE.'/';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>FurShield</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?php echo BASE; ?>/assets/css/theme-sand-sunset.css">
  <link rel="stylesheet" href="<?php echo BASE; ?>/assets/css/style.css">
  <!-- FurShield Loader styles + early show -->
<style>
  :root{
    --fs-primary:#F59E0B; --fs-accent:#EF4444; --fs-bg:#0b0b0c; --fs-txt:#fff;
  }
  .fs-loader{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;
    background:radial-gradient(900px 400px at 20% -10%, rgba(245,158,11,.15), transparent 40%),
               radial-gradient(900px 400px at 90% 0%, rgba(239,68,68,.12), transparent 45%),
               linear-gradient(180deg,#0b0b0c,#111214);}
  .fs-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none;transition:opacity .35s ease, visibility .35s ease}
  .fs-card{display:grid;gap:12px;justify-items:center;padding:28px 32px;border-radius:18px;
    background:rgba(255,255,255,.04);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.08)}
  .fs-logo{width:70px;height:70px;border-radius:20px;display:grid;place-items:center;
    background:linear-gradient(135deg,var(--fs-primary),#ffb444);color:#111;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  .fs-logo i{font-size:32px}
  .fs-brand{font:800 20px/1.1 Montserrat,system-ui,sans-serif;letter-spacing:.3px;color:#fff}
  .fs-sub{font:600 12px/1 Poppins,system-ui,sans-serif;color:#c9cbd1;opacity:.9}
  .fs-spin{width:72px;height:72px;position:relative}
  .fs-ring{position:absolute;inset:0;border-radius:999px;
    background:
      conic-gradient(from 0deg, var(--fs-primary), #fdd187, var(--fs-accent), var(--fs-primary)) border-box;
    -webkit-mask:
      radial-gradient(farthest-side,#0000 calc(100% - 8px),#000 calc(100% - 8px)) content-box,
      radial-gradient(farthest-side,#000 100%,#0000 0) padding-box;
            mask:
      radial-gradient(farthest-side,#0000 calc(100% - 8px),#000 calc(100% - 8px)) content-box,
      radial-gradient(farthest-side,#000 100%,#0000 0) padding-box;
    animation:fs-rot 1.1s linear infinite}
  @keyframes fs-rot{to{transform:rotate(1turn)}}
  .fs-bar{position:fixed;left:0;top:0;height:3px;width:0;background:linear-gradient(90deg,var(--fs-primary),#ffb444);
    box-shadow:0 0 12px rgba(245,158,11,.65); transition:width .2s ease}
  @media (prefers-reduced-motion:reduce){
    .fs-ring{animation:none}
    .fs-loader{transition:none}
  }
</style>
<script>
  window.__fsLoader = { autoShow:true }; 
</script>

</head>
<body class="bg-app">
  <div class="topbar text-white small">
    <div class="container d-flex justify-content-between align-items-center py-1">
      <span class="opacity-75">🌍 International Competition Build</span>
      <span class="opacity-75">Owners • Vets • Shelters • Catalog</span>
    </div>
  </div>

  <nav class="navbar navbar-expand-lg sticky-top bg-glass border-bottom">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE; ?>/">
        <span class="logo-badge me-2"><i class="bi bi-shield-heart text-white"></i></span>
        <span class="fw-bold brand-text">FurShield</span>
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <form action="<?php echo BASE; ?>/search.php" method="get" class="d-none d-lg-flex ms-3 flex-grow-1" role="search">
        <div class="search-wrap w-100">
          <i class="bi bi-search"></i>
          <input name="q" class="form-control" type="search" placeholder="Search pets, vets, products…" autocomplete="off">
          <button class="btn btn-primary btn-sm" type="submit">Search</button>
        </div>
      </form>

      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-lg-3 me-auto my-2 my-lg-0">
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/adoption.php">Adopt</a></li>
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/vets.php">Vets</a></li>
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/catalog.php">Products</a></li>
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/about.php">About</a></li>
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE; ?>/contact.php">Contact</a></li>
        </ul>

        <form action="<?php echo BASE; ?>/search.php" method="get" class="d-lg-none mb-2" role="search">
          <div class="search-wrap w-100">
            <i class="bi bi-search"></i>
            <input name="q" class="form-control" type="search" placeholder="Search…" autocomplete="off">
            <button class="btn btn-primary btn-sm" type="submit">Go</button>
          </div>
        </form>

        <?php if (!logged_in()) { ?>
          <div class="d-flex align-items-center gap-2">
            <a href="<?php echo BASE; ?>/wishlist.php" class="btn btn-outline-primary position-relative" title="Wishlist">
              <i class="bi bi-heart"></i>
              <?php if ($wishCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo (int)$wishCount; ?></span>
              <?php endif; ?>
            </a>
            <a href="<?php echo BASE; ?>/cart.php" class="btn btn-outline-primary position-relative" title="Cart">
              <i class="bi bi-cart"></i>
              <?php if ($cartCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo (int)$cartCount; ?></span>
              <?php endif; ?>
            </a>
            <a href="<?php echo BASE; ?>/login.php" class="btn btn-outline-primary">Login</a>
            <a href="<?php echo BASE; ?>/register.php" class="btn btn-accent">Register</a>
            <a href="<?php echo BASE; ?>/register-vet.php" class="btn btn-primary">Register as Vet</a>
            <a href="<?php echo BASE; ?>/register-shelter.php" class="btn btn-outline-primary">Register Shelter</a>
          </div>
        <?php } else { ?>
          <div class="d-flex align-items-center gap-3">
            <a href="<?php echo BASE; ?>/wishlist.php" class="btn btn-outline-primary position-relative" title="Wishlist">
              <i class="bi bi-heart"></i>
              <?php if ($wishCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo (int)$wishCount; ?></span>
              <?php endif; ?>
            </a>

            <a href="<?php echo BASE; ?>/cart.php" class="btn btn-outline-primary position-relative" title="Cart">
              <i class="bi bi-cart"></i>
              <?php if ($cartCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cartCount"><?php echo (int)$cartCount; ?></span>
              <?php endif; ?>
            </a>

            <div class="dropdown">
              <a class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown" href="#">
                <img src="<?php echo $avatarUrl; ?>" onerror="this.src='<?php echo BASE; ?>/assets/img/avatar.png'" class="rounded-circle me-2" width="34" height="34" alt="">
                <div class="lh-1 me-2">
                  <div class="fw-semibold small"><?php echo htmlspecialchars($u['name']); ?></div>
                  <div class="badge bg-light text-dark text-capitalize"><?php echo htmlspecialchars($u['role']); ?></div>
                </div>
                <i class="bi bi-caret-down-fill small ms-1"></i>
              </a>
              <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><a class="dropdown-item" href="<?php echo $dashUrl; ?>">Dashboard</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?php echo BASE; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
              </ul>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>
  </nav>

  <main class="py-4">
    <div class="container">
