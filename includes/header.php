<?php
require_once __DIR__."/db.php";
require_once __DIR__."/auth.php";

/* ---- Dynamic BASE Path ---- */
if (!defined('BASE')) {
  $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
  define('BASE', $basePath === '' || $basePath === '/' ? '' : $basePath);
}

$u = user();
$avatar = $u['avatar'] ?? 'avatar.png';
$avatarUrl = BASE.'/assets/img/'.$avatar;

/* cart count (session) */
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
  foreach($_SESSION['cart'] as $it){ $cartCount += (int)($it['qty'] ?? 0); }
}

/* safe helpers */
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

/* wishlist count */
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

  <!-- FurShield Loader styles -->
  <style>
    :root{ --fs-primary:#F59E0B; --fs-accent:#EF4444; --fs-bg:#0b0b0c; --fs-txt:#fff; }
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
      background:conic-gradient(from 0deg, var(--fs-primary), #fdd187, var(--fs-accent), var(--fs-primary)) border-box;
      -webkit-mask:radial-gradient(farthest-side,#0000 calc(100% - 8px),#000 calc(100% - 8px)) content-box,
                   radial-gradient(farthest-side,#000 100%,#0000 0) padding-box;
              mask:radial-gradient(farthest-side,#0000 calc(100% - 8px),#000 calc(100% - 8px)) content-box,
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
  <script>window.__fsLoader = { autoShow:true };</script>
</head>
<body class="bg-app">
