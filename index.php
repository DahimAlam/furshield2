<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
if (!defined('BASE')) define('BASE', '/furshield');
$conn->set_charset('utf8mb4');
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ur'], true)) $_SESSION['lang'] = $_GET['lang'];
$lang = $_SESSION['lang'] ?? 'en';

$t = [
  'en' => [
    'find_vet' => 'Find a Vet',
    'adopt_pet' => 'Adopt a Pet',
    'browse_products' => 'Browse',
    'view_all' => 'View All',
    'built_for' => 'Built for every role',
    'owners' => 'Owners',
    'vets' => 'Vets',
    'shelters' => 'Shelters',
    'adoption_h' => 'Adoption Highlights',
    'products_h' => 'Top Products',
    'blogs_h' => 'Care Guides & Blogs',
    'read_more' => 'Read more',
    'vet_cta_h' => 'Join verified vets & serve the community',
    'vet_cta_p' => 'Manage availability, appointments & treatment notes.',
    'register_vet' => 'Register as Vet',
    'see_vets' => 'See Vets',
    'testimonials_h' => 'What our community says',
    'faq_h' => 'Frequently Asked Questions',
    'stats_h' => 'Our impact',
    'pets' => 'Pets',
    'verified_vets' => 'Verified Vets',
    'active_shelters' => 'Active Shelters',
    'gallery_h' => 'Community Gallery',
    'events_h' => 'Upcoming Events',
    'newsletter_h' => 'Stay updated with FurShield',
    'newsletter_p' => 'Get tips, drives & product news.',
    'subscribe' => 'Subscribe',
    'how_h' => 'How it works',
    'city_h' => 'Browse by City',
    'success_h' => 'Success Stories',
    'brands_h' => 'Featured Brands',
    'search_ph' => 'Search pets, vets, products…',
    'open_dash' => 'Open Dashboard',
    'adopt_now' => 'Adopt Now'
  ]
];

function rows($c, $sql){ $r=@$c->query($sql); return $r? $r->fetch_all(MYSQLI_ASSOC):[]; }
function rowx($c, $sql){ $r=@$c->query($sql); return $r? $r->fetch_assoc():null; }
function hascol($c,$t,$col){
  $t=$c->real_escape_string($t); $col=$c->real_escape_string($col);
  $q=$c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
  return $q && $q->num_rows>0;
}
function media($rel,$folder){
  $rel=trim((string)$rel);
  if($rel==='') return BASE.'/assets/placeholder/blank.jpg';
  if (strpos($rel,'http://')===0 || strpos($rel,'https://')===0) return $rel;
  if ($rel[0]==='/') return $rel;
  if (strpos($rel,'uploads/')===0) return BASE.'/'.ltrim($rel,'/');
  return BASE.'/uploads/'.$folder.'/'.$rel;
}
function table_exists($c,$t){
  $t=$c->real_escape_string($t);
  $r=$c->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}'");
  return $r && $r->num_rows>0;
}

$settings = table_exists($conn,'homepage_settings') ? rowx($conn,"SELECT * FROM homepage_settings WHERE id=1") : [];
$hero_title = $settings['hero_title'] ?? "Your pet’s health, organized & care made simple.";
$hero_sub   = $settings['hero_subtitle'] ?? "Owners, Vets & Shelters: appointments, health records, adoption, and curated products.";
$hero_img   = $settings['hero_image'] ?? BASE."/assets/img/hero-sand.jpg";
$c1t        = $settings['cta_primary_text'] ?? $t[$lang]['find_vet'];
$c1u        = $settings['cta_primary_url'] ?? BASE."/vets.php";
$c2t        = $settings['cta_secondary_text'] ?? $t[$lang]['adopt_pet'];
$c2u        = $settings['cta_secondary_url'] ?? BASE."/adopt.php";
$prod_mode  = $settings['products_mode'] ?? 'latest';

$counts = [
  'pets' => (int)($conn->query("SELECT COUNT(*) c FROM addoption")->fetch_assoc()['c'] ?? 0),
  'vets' => (int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='vet' AND status='active'")->fetch_assoc()['c'] ?? 0),
  'shel' => (int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='shelter' AND status='active'")->fetch_assoc()['c'] ?? 0),
];

$pets = rows(
  $conn,
  "SELECT a.id,a.name,a.species,COALESCE(a.breed,'') AS breed,COALESCE(a.city,'') AS city,
          a.avatar AS image
   FROM addoption a
   JOIN users u ON u.id=a.shelter_id AND u.role='shelter' AND u.status='active'
   WHERE a.status='available'
   ORDER BY a.spotlight DESC, a.id DESC
   LIMIT 8"
);

$urgent = [];
if (table_exists($conn,'addoption') && (hascol($conn,'addoption','urgent_until') || hascol($conn,'addoption','spotlight'))) {
  $urgent = rows(
    $conn,
    "SELECT a.id,a.name,a.avatar AS image
     FROM addoption a
     JOIN users u ON u.id=a.shelter_id AND u.role='shelter' AND u.status='active'
     WHERE a.status='available'
       AND (
         a.spotlight=1
         OR (a.urgent_until IS NOT NULL AND a.urgent_until >= CURDATE())
       )
     ORDER BY COALESCE(a.urgent_until,NOW()) ASC, a.id DESC
     LIMIT 3"
  );
}

$prodImg = null;
if (hascol($conn,'products','image_path')) $prodImg='image_path';
elseif (hascol($conn,'products','image')) $prodImg='image';
elseif (hascol($conn,'products','cover')) $prodImg='cover';
$prodFeat= hascol($conn,'products','featured') ? 'featured' : null;
$prodActive= hascol($conn,'products','is_active') ? 'is_active' : null;
$prodWhere = $prodActive ? "WHERE `$prodActive`=1" : '';
$products = rows(
  $conn,
  ($prod_mode==='featured' && $prodFeat)
    ? "SELECT id,name,price,".($prodImg?("`$prodImg`"):"''")." AS image FROM products $prodWhere ORDER BY `$prodFeat` DESC, id DESC LIMIT 8"
    : "SELECT id,name,price,".($prodImg?("`$prodImg`"):"''")." AS image FROM products $prodWhere ORDER BY id DESC LIMIT 8"
);

$blogImg = null;
if (hascol($conn,'blogs','cover_image')) $blogImg='cover_image';
elseif (hascol($conn,'blogs','image')) $blogImg='image';
$blogs=rows($conn,"SELECT id,title,".($blogImg?"`$blogImg`":"''")." AS image,created_at FROM blogs ".(hascol($conn,'blogs','is_active')?'WHERE is_active=1 ':'')."ORDER BY id DESC LIMIT 4");

$faqs=rows($conn,"SELECT id,question,answer FROM faqs ".(hascol($conn,'faqs','is_active')?'WHERE is_active=1 ':'')."ORDER BY id DESC LIMIT 6");

$testimonials = rows($conn,"SELECT name,".(hascol($conn,'testimonials','role')?'role':'NULL AS role').",".(hascol($conn,'testimonials','rating')?'rating':'NULL AS rating').",message,".(hascol($conn,'testimonials','avatar')?'avatar':'NULL AS avatar')." FROM testimonials ".(hascol($conn,'testimonials','is_active')?'WHERE is_active=1 ':'')."ORDER BY id DESC LIMIT 9");

$galImg = null;
if (hascol($conn,'gallery','image_path')) $galImg='image_path';
elseif (hascol($conn,'gallery','image')) $galImg='image';
$gallery = $galImg? rows($conn,"SELECT `$galImg` AS image FROM gallery ORDER BY id DESC LIMIT 8"): [];

$events=rows($conn,"SELECT id,".(hascol($conn,'events','title')?'title':'name AS title').",".(hascol($conn,'events','date')?'date':'event_date AS date').",".(hascol($conn,'events','city')?'city':'NULL AS city').",".(hascol($conn,'events','image')?'image':'NULL AS image').",".(hascol($conn,'events','link')?'link':'NULL AS link')." FROM events ".(hascol($conn,'events','is_active')?'WHERE is_active=1 AND ':'WHERE ')."date>=CURDATE() ORDER BY date ASC LIMIT 4");

$cities=rows($conn,"SELECT DISTINCT city FROM shelters WHERE city IS NOT NULL AND city<>'' ORDER BY city LIMIT 8");
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<style>
:root{--primary:#F59E0B;--accent:#EF4444;--bg:#FFF7ED;--text:#1F2937;--card:#FFFFFF;--radius:20px;--shadow:0 12px 34px rgba(0,0,0,.08);--page-pad:clamp(12px,2.4vw,32px)}
.container,.container-sm,.container-md,.container-lg,.container-xl,.container-xxl,.container-fluid{max-width:100%!important;padding-left:var(--page-pad);padding-right:var(--page-pad)}
.row{--bs-gutter-x:1.2rem}
body{background:var(--bg);color:var(--text)}
h1,h2,h3,h4{font-family:Montserrat,Poppins,sans-serif}
.btn{border-radius:12px;font-weight:700}
.btn-primary{background:var(--primary);border:none;color:#111}
.btn-outline-primary{border-color:var(--primary);color:var(--primary);background:#fff}
.card{border:1px solid #eee;border-radius:var(--radius);background:#fff;box-shadow:var(--shadow)}
.hero-section{background:radial-gradient(1200px 400px at 15% -10%,rgba(245,158,11,.25),transparent 40%),radial-gradient(900px 320px at 95% 0%,rgba(239,68,68,.16),transparent 45%),linear-gradient(180deg,#fff,rgba(255,247,237,.7));border-bottom:1px solid #f1e6d7}
.hero-title{letter-spacing:.2px}
.search-xl{display:flex;gap:.5rem}
.search-xl .form-control{border-radius:999px;padding:.9rem 1.1rem;border:1px solid #e9e9e9;background:#fff}
.search-xl .btn{border-radius:999px}
.chip{background:#fff;border:1px solid #efe6d6;border-radius:999px;padding:.35rem .7rem;font-weight:700}
.section-title{font-weight:800}
.feature-card{transition:.22s ease}
.feature-card:hover{transform:translateY(-5px);box-shadow:0 18px 55px rgba(0,0,0,.12);border-color:#e9e3d6}
.pet-card{border-radius:22px;overflow:hidden;border:1px solid #eee;transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}
.pet-card:hover{transform:translateY(-6px);box-shadow:0 20px 60px rgba(0,0,0,.14);border-color:#e9e3d6}
.pet-thumb{position:relative;aspect-ratio:4/3;overflow:hidden;background:#f6f6f6}
.pet-thumb img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease}
.pet-card:hover .pet-thumb img{transform:scale(1.06)}
.ribbon{position:absolute;top:12px;left:12px;background:#fff;border:1px solid #eee;border-radius:999px;padding:.25rem .55rem;font-size:.75rem;font-weight:800}
.stat-card{background:#fff;border:1px solid #eee;border-radius:18px;box-shadow:var(--shadow);padding:18px}
.stat-num{font-size:32px;font-weight:900;letter-spacing:.4px}
.product-card{border-radius:22px;border:1px solid #eee;overflow:hidden;transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}
.product-card:hover{transform:translateY(-6px);box-shadow:0 22px 64px rgba(0,0,0,.14);border-color:#e9e3d6}
.product-thumb{position:relative;aspect-ratio:4/3;overflow:hidden;background:#f6f6f6}
.product-thumb img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease,opacity .3s ease}
.product-card:hover .product-thumb img{transform:scale(1.06)}
.blog-card img{height:260px;object-fit:cover}
.blog-card .title{font-weight:700}
.event-card img{height:220px;object-fit:cover}
.testi-card{border-radius:20px}
.star{color:#F59E0B}
.accordion-button{border-radius:12px;font-weight:700}
.accordion-button:not(.collapsed){background:rgba(245,158,11,.08);color:#1f2937}
.gallery img{border-radius:14px;height:160px;object-fit:cover}
.reveal{opacity:0;transform:translateY(12px) scale(.98)}
.reveal.show{opacity:1;transform:none;transition:420ms cubic-bezier(.2,.7,.2,1)}
.urgent{border:1px solid #f0dcdc;background:linear-gradient(90deg,#fff,#fff0);border-radius:18px;box-shadow:var(--shadow)}
</style>
<div id="fsLoader" class="fs-loader is-hidden" aria-live="polite" aria-busy="true">
  <div class="fs-bar" id="fsLoaderBar"></div>
  <div class="fs-card">
    <div class="fs-logo"><i class="bi bi-shield-heart"></i></div>
    <div class="fs-brand">FurShield</div>
    <div class="fs-spin"><div class="fs-ring"></div></div>
    <div class="fs-sub">loading…</div>
  </div>
</div>

<main style="background:var(--bg); color:var(--text)">
  <section class="py-5 py-lg-6 hero-section">
    <div class="container-fluid">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="chip">FurShield</span>
        <span class="text-muted small">One place for care</span>
      </div>
      <div class="row align-items-center g-4 g-xl-5">
        <div class="col-lg-7">
          <h1 class="display-5 fw-bold hero-title"><?php echo htmlspecialchars($hero_title); ?></h1>
          <p class="lead text-secondary mb-4"><?php echo htmlspecialchars($hero_sub); ?></p>
          <form class="search-xl mb-4" role="search" action="<?php echo BASE.'/search.php'; ?>">
            <input name="q" class="form-control form-control-lg" placeholder="<?php echo $t[$lang]['search_ph']; ?>" />
            <button class="btn btn-lg btn-primary"><i class="bi bi-search me-1"></i><?php echo $t[$lang]['browse_products']; ?></button>
          </form>
          <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-lg btn-primary" href="<?php echo htmlspecialchars($c1u); ?>"><i class="bi bi-heart-pulse me-1"></i><?php echo htmlspecialchars($c1t); ?></a>
            <a class="btn btn-lg btn-outline-primary" href="<?php echo htmlspecialchars($c2u); ?>"><i class="bi bi-paw me-1"></i><?php echo htmlspecialchars($c2t); ?></a>
          </div>
        </div>
        <div class="col-lg-5">
          <?php $hero_img = !empty($settings['hero_image']) ? BASE.$settings['hero_image'] : BASE.'/uploads/pets/premium_photo-1668114375111-e90b5e975df6.avif'; ?>
          <div class="card border-0 shadow" style="border-radius:var(--radius); overflow:hidden">
            <img src="<?php echo htmlspecialchars($hero_img); ?>" alt="FurShield Hero" style="object-fit:cover;height:360px;width:100%">
            <div class="card-body">
              <div class="d-flex justify-content-between small text-muted">
                <span><i class="bi bi-patch-check-fill text-warning me-1"></i><?php echo $counts['vets']; ?> <?php echo $t[$lang]['verified_vets']; ?></span>
                <span><i class="bi bi-building text-warning me-1"></i><?php echo $counts['shel']; ?> <?php echo $t[$lang]['active_shelters']; ?></span>
                <span><i class="bi bi-heart-fill text-warning me-1"></i><?php echo $counts['pets']; ?> <?php echo $t[$lang]['pets']; ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php if ($urgent) { ?>
      <div class="urgent d-flex align-items-center gap-3 p-3 mt-4">
        <i class="bi bi-exclamation-octagon text-danger fs-5"></i>
        <div class="d-flex flex-wrap gap-3 small">
          <?php foreach($urgent as $u){ echo '<a class="link-dark fw-semibold" href="'.BASE.'/pet.php?id='.$u['id'].'">'.htmlspecialchars($u['name']).'</a>'; } ?>
        </div>
      </div>
      <?php } ?>
    </div>
  </section>

  <section class="py-5">
    <div class="container-fluid">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h4 section-title m-0"><?php echo $t[$lang]['built_for']; ?></h2>
      </div>
      <div class="row g-3">
        <div class="col-md-4"><div class="card feature-card h-100 p-3"><div class="d-flex align-items-center mb-2"><span class="chip me-2"><i class="bi bi-person-heart"></i></span><h3 class="h5 m-0"><?php echo $t[$lang]['owners']; ?></h3></div><p class="text-muted mb-0">Create pet profiles, track health, set reminders.</p></div></div>
        <div class="col-md-4"><div class="card feature-card h-100 p-3"><div class="d-flex align-items-center mb-2"><span class="chip me-2"><i class="bi bi-heart-pulse"></i></span><h3 class="h5 m-0"><?php echo $t[$lang]['vets']; ?></h3></div><p class="text-muted mb-0">Manage slots, appointments & treatment notes.</p></div></div>
        <div class="col-md-4"><div class="card feature-card h-100 p-3"><div class="d-flex align-items-center mb-2"><span class="chip me-2"><i class="bi bi-house-heart"></i></span><h3 class="h5 m-0"><?php echo $t[$lang]['shelters']; ?></h3></div><p class="text-muted mb-0">List adoptable pets & handle interest.</p></div></div>
      </div>
    </div>
  </section>

  <?php if ($cities) { ?>
  <section class="py-4 bg-white">
    <div class="container-fluid">
      <h2 class="h6 mb-3"><?php echo $t[$lang]['city_h']; ?></h2>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($cities as $c) { ?>
          <a class="btn btn-sm btn-outline-primary" href="<?php echo BASE.'/adopt.php?city='.urlencode($c['city']); ?>"><?php echo htmlspecialchars($c['city']); ?></a>
        <?php } ?>
      </div>
    </div>
  </section>
  <?php } ?>

  <section class="py-5">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 m-0"><?php echo $t[$lang]['adoption_h']; ?></h2>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo BASE.'/adoption.php'; ?>"><?php echo $t[$lang]['view_all']; ?></a>
      </div>
      <div class="row g-4 row-cols-2 row-cols-md-3 row-cols-lg-4">
        <?php foreach ($pets as $p): ?>
          <div class="col">
            <div class="pet-card reveal">
              <div class="pet-thumb">
                <img src="<?php echo media($p['image'],'pets'); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
                <?php if(!empty($p['city'])){ ?><span class="ribbon"><?php echo htmlspecialchars($p['city']); ?></span><?php } ?>
              </div>
              <div class="p-3">
                <div class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($p['name']); ?></div>
                <div class="small text-muted mb-2"><?php echo htmlspecialchars($p['species'] ?? ''); ?><?php if(!empty($p['breed'])) echo ' • '.htmlspecialchars($p['breed']); ?></div>
                <a href="<?php echo BASE.'/pet.php?id='.$p['id']; ?>" class="btn btn-sm btn-outline-primary"><?php echo $t[$lang]['adopt_now']; ?></a>
              </div>
            </div>
          </div>
        <?php endforeach; if(!$pets){ echo '<p class="text-muted">No pets yet.</p>'; } ?>
      </div>
    </div>
  </section>

  <section class="py-5 bg-white">
    <div class="container-fluid">
      <h2 class="h4 mb-4"><?php echo $t[$lang]['stats_h']; ?></h2>
      <div class="row g-3 text-center">
        <div class="col-4"><div class="stat-card reveal"><div class="stat-num counter" data-to="<?php echo (int)$counts['pets']; ?>"><?php echo $counts['pets']; ?></div><div class="small text-muted"><?php echo $t[$lang]['pets']; ?></div></div></div>
        <div class="col-4"><div class="stat-card reveal"><div class="stat-num counter" data-to="<?php echo (int)$counts['vets']; ?>"><?php echo $counts['vets']; ?></div><div class="small text-muted"><?php echo $t[$lang]['verified_vets']; ?></div></div></div>
        <div class="col-4"><div class="stat-card reveal"><div class="stat-num counter" data-to="<?php echo (int)$counts['shel']; ?>"><?php echo $counts['shel']; ?></div><div class="small text-muted"><?php echo $t[$lang]['active_shelters']; ?></div></div></div>
      </div>
    </div>
  </section>

  <section class="py-5">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 m-0"><?php echo $t[$lang]['products_h']; ?></h2>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo BASE.'/catalog.php'; ?>"><?php echo $t[$lang]['view_all']; ?></a>
      </div>
      <div class="row g-4 row-cols-2 row-cols-md-3 row-cols-lg-4">
        <?php foreach ($products as $pr): ?>
          <div class="col">
            <div class="product-card reveal">
              <div class="product-thumb">
                <img src="<?php echo media($pr['image'],'products'); ?>" alt="<?php echo htmlspecialchars($pr['name']); ?>">
              </div>
              <div class="p-3 d-flex flex-column">
                <div class="fw-semibold text-truncate" title="<?php echo htmlspecialchars($pr['name']); ?>"><?php echo htmlspecialchars($pr['name']); ?></div>
                <div class="text-muted mb-3">$<?php echo number_format((float)$pr['price'],2); ?></div>
                <div class="mt-auto d-flex gap-2">
                  <a href="<?php echo BASE.'/product-details.php?id='.(int)$pr['id']; ?>" class="btn btn-sm btn-outline-secondary flex-grow-1">View</a>
                  <a href="<?php echo BASE.'/actions/cart-add.php?id='.(int)$pr['id']; ?>" class="btn btn-sm btn-primary flex-grow-1">Add to Cart</a>
                  <a href="<?php echo BASE.'/actions/wishlist-add.php?id='.(int)$pr['id']; ?>" class="btn btn-sm btn-outline-danger" title="Add to Wishlist"><i class="bi bi-heart"></i></a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; if(!$products){ echo '<p class="text-muted">No products yet.</p>'; } ?>
      </div>
    </div>
  </section>

  <section class="py-5 bg-white">
    <div class="container-fluid">
      <h2 class="h4 mb-3"><?php echo $t[$lang]['blogs_h']; ?></h2>
      <div class="row g-3 row-cols-2 row-cols-md-4">
        <?php foreach ($blogs as $b): ?>
          <div class="col">
            <div class="card blog-card h-100 reveal">
              <img src="<?php echo htmlspecialchars(!empty($b['image']) ? BASE.$b['image'] : BASE.'/assets/placeholder/blog.jpg'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($b['title']); ?>">
              <div class="card-body">
                <div class="title mb-1 text-truncate"><?php echo htmlspecialchars($b['title']); ?></div>
                <div class="small text-muted"><?php echo date('M d, Y', strtotime($b['created_at'])); ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; if(!$blogs){ echo '<p class="text-muted">No articles yet.</p>'; } ?>
      </div>
    </div>
  </section>

  <?php if ($gallery) { ?>
  <section class="py-5 bg-white">
    <div class="container-fluid">
      <h2 class="h5 mb-3"><?php echo $t[$lang]['gallery_h']; ?></h2>
      <div class="row g-3 gallery row-cols-2 row-cols-md-4">
        <?php foreach ($gallery as $g) { ?>
          <div class="col"><a href="<?php echo media($g['image'],'gallery'); ?>"><img src="<?php echo media($g['image'],'gallery'); ?>" class="img-fluid" alt=""></a></div>
        <?php } ?>
      </div>
    </div>
  </section>
  <?php } ?>

  <?php if ($events) { ?>
  <section class="py-5">
    <div class="container-fluid">
      <h2 class="h5 mb-3"><?php echo $t[$lang]['events_h']; ?></h2>
      <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-lg-4">
        <?php foreach ($events as $e) { ?>
          <div class="col">
            <div class="card event-card h-100 reveal">
              <img src="<?php echo htmlspecialchars(!empty($e['image']) ? BASE.$e['image'] : BASE.'/assets/placeholder/event.jpg'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($e['title']); ?>">
              <div class="card-body">
                <h6 class="fw-semibold mb-1 text-truncate"><?php echo htmlspecialchars($e['title']); ?></h6>
                <small class="text-muted nowrap"><?php echo date('M d, Y', strtotime($e['date'])); ?><?php if ($e['city']) echo ' • '.htmlspecialchars($e['city']); ?></small>
              </div>
              <?php if ($e['link']) { ?><div class="card-footer bg-white"><a href="<?php echo htmlspecialchars($e['link']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">Details</a></div><?php } ?>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>
  </section>
  <?php } ?>

  <?php if ($testimonials) { ?>
  <section class="py-5 bg-white">
    <div class="container-fluid">
      <h2 class="h5 mb-3"><?php echo $t[$lang]['testimonials_h']; ?></h2>
      <div class="row g-3 row-cols-1 row-cols-md-3">
        <?php foreach ($testimonials as $ts) { ?>
          <div class="col">
            <div class="card testi-card h-100 reveal p-3">
              <div class="d-flex align-items-center mb-2">
                <?php $avatar = !empty($ts['avatar']) ? BASE.$ts['avatar'] : BASE.'/assets/placeholder/avatar.png'; ?>
                <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle me-2" width="40" height="40" alt="">
                <div>
                  <strong class="d-block text-truncate" style="max-width:180px"><?php echo htmlspecialchars($ts['name']); ?></strong>
                  <?php if (!empty($ts['role'])) echo '<div class="small text-muted">'.htmlspecialchars($ts['role']).'</div>'; ?>
                </div>
              </div>
              <?php if(!empty($ts['rating'])){ ?><div class="mb-2"><?php for($i=0;$i<(int)$ts['rating'];$i++) echo '<i class="bi bi-star-fill star"></i>'; ?></div><?php } ?>
              <p class="mb-0 text-muted"><?php echo htmlspecialchars($ts['message']); ?></p>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>
  </section>
  <?php } ?>

  <section class="py-5">
    <div class="container-fluid">
      <h2 class="h5 mb-3"><?php echo $t[$lang]['faq_h']; ?></h2>
      <div class="accordion" id="faqAcc">
        <?php $i=0; foreach ($faqs as $f): $i++; ?>
          <div class="accordion-item reveal">
            <h2 class="accordion-header">
              <button class="accordion-button <?php echo $i>1?'collapsed':''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#f<?php echo $i; ?>">
                <?php echo htmlspecialchars($f['question']); ?>
              </button>
            </h2>
            <div id="f<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i===1?'show':''; ?>">
              <div class="accordion-body"><?php echo nl2br(htmlspecialchars($f['answer'])); ?></div>
            </div>
          </div>
        <?php endforeach; if(!$faqs){ echo '<p class="text-muted">No FAQs yet.</p>'; } ?>
      </div>
    </div>
  </section>

  <section class="py-5 bg-white">
    <div class="container-fluid">
      <div class="row g-3 align-items-center">
        <div class="col-md-6">
          <h3 class="mb-1"><?php echo $t[$lang]['newsletter_h']; ?></h3>
          <p class="mb-0 text-muted"><?php echo $t[$lang]['newsletter_p']; ?></p>
        </div>
        <div class="col-md-6">
          <form method="post" action="<?php echo BASE.'/newsletter-subscribe.php'; ?>" class="d-flex">
            <input type="hidden" name="csrf" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="email" required name="email" class="form-control me-2" placeholder="you@email.com">
            <button class="btn btn-primary"><?php echo $t[$lang]['subscribe']; ?></button>
          </form>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
(function(){
  const els = document.querySelectorAll('.reveal, .pet-card, .product-card, .card.h-100');
  const io = new IntersectionObserver((entries)=>{entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('show');io.unobserve(e.target);}})},{threshold:.12});
  els.forEach(el=> io.observe(el));
  const tilt=(card,e)=>{const r=card.getBoundingClientRect();const x=((e.clientX-r.left)/r.width-.5)*4;const y=((e.clientY-r.top)/r.height-.5)*-4;card.style.transform=`translateY(-6px) rotateX(${y}deg) rotateY(${x}deg)`}; const reset=(card)=> card.style.transform='';
  document.querySelectorAll('.product-card, .pet-card').forEach(card=>{card.addEventListener('mousemove', e=> tilt(card,e));card.addEventListener('mouseleave', ()=> reset(card));});
  document.querySelectorAll('.counter').forEach(el=>{const to=parseInt(el.dataset.to||el.textContent||'0',10);let cur=0,step=Math.max(1,Math.ceil(to/60));const tick=()=>{cur+=step;if(cur>=to){el.textContent=to.toLocaleString();return}el.textContent=cur.toLocaleString();requestAnimationFrame(tick)};tick();});
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
