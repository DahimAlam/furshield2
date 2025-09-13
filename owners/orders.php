<?php
// ===== NO BOM or spaces before this line =====
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

// ---------- AUTH (owner only) ----------
$isLogged = !empty($_SESSION['user']['id']);
$ownerId  = (int)($_SESSION['user']['id'] ?? 0);

// ---------- Helpers ----------
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect_self(array $patch=[]){
  $q = array_merge($_GET, $patch);
  foreach($q as $k=>$v) if ($v===null) unset($q[$k]);
  $uri = strtok($_SERVER['REQUEST_URI'],'?');
  header('Location: '.$uri.(count($q)?('?'.http_build_query($q)):''));
  exit;
}
function productNameExpr(mysqli $conn): string {
  // if products table is present, detect best name column
  $has = false;
  if ($res = $conn->query("SHOW TABLES LIKE 'products'")) { $has = (bool)$res->num_rows; $res->free(); }
  if (!$has) return "CONCAT('Product ', oi.product_id)";
  $cols = [];
  if ($res = $conn->query("SHOW COLUMNS FROM products")) {
    while ($r = $res->fetch_assoc()) $cols[strtolower($r['Field'])] = 1;
    $res->free();
  }
  foreach (['name','title','product_name'] as $c) if (!empty($cols[$c])) return "COALESCE(p.`$c`, CONCAT('Product ', oi.product_id))";
  return "CONCAT('Product ', oi.product_id)";
}
function mapStatusUiToDb(string $ui): array {
  // returns ['mode'=>'all'|'one'|'in', 'list'=>[]]
  switch ($ui) {
    case 'Delivered':  return ['mode'=>'one','list'=>['delivered']];
    case 'Processing': return ['mode'=>'in','list'=>['placed','packed','shipped']];
    case 'Cancelled':  return ['mode'=>'one','list'=>['cancelled']];
    case 'Returned':   return ['mode'=>'one','list'=>['refunded']];
    default:           return ['mode'=>'all','list'=>[]];
  }
}
function chipClassLabel($dbStatus){
  $dbStatus = strtolower((string)$dbStatus);
  if ($dbStatus==='delivered') return ['ok','Delivered'];
  if (in_array($dbStatus,['placed','packed','shipped'],true)) return ['warn','Processing'];
  if ($dbStatus==='cancelled') return ['danger','Cancelled'];
  if ($dbStatus==='refunded')  return ['info','Returned'];
  return ['warn', ucfirst($dbStatus)];
}

// ---------- POST actions (delete) ----------
if ($_SERVER['REQUEST_METHOD']==='POST' && $isLogged) {
  $action = $_POST['action'] ?? '';
  if ($action === 'delete_one') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM orders WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $id, $ownerId);
    $stmt->execute(); $stmt->close();
    redirect_self(['msg'=>'Deleted']);
  }
  if ($action === 'bulk_delete') {
    $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
    if ($ids) {
      $in = implode(',', array_fill(0, count($ids), '?'));
      $types = str_repeat('i', count($ids)) . 'i';
      $sql = "DELETE FROM orders WHERE id IN ($in) AND user_id=?";
      $stmt = $conn->prepare($sql);
      $params = array_merge($ids, [$ownerId]);
      $stmt->bind_param($types, ...$params);
      $stmt->execute(); $stmt->close();
    }
    redirect_self(['msg'=>'Deleted']);
  }
}

// ---------- Filters (GET) ----------
$statusUi = $_GET['status'] ?? 'All'; // All | Delivered | Processing | Cancelled | Returned
$from     = trim($_GET['from'] ?? '');
$to       = trim($_GET['to'] ?? '');
$q        = trim($_GET['q'] ?? '');

// ---------- Query orders ----------
$orders = [];
$totalSum = 0;
if ($isLogged) {
  $where = ["o.user_id=?"];
  $p = [$ownerId];
  $t = "i";

  $map = mapStatusUiToDb($statusUi);
  if ($map['mode']==='one'){ $where[]="o.status=?"; $p[]=$map['list'][0]; $t.="s"; }
  if ($map['mode']==='in'){ $where[]="o.status IN (".implode(',', array_fill(0,count($map['list']),'?')).")"; $p=array_merge($p,$map['list']); $t.=str_repeat('s', count($map['list'])); }
  if ($from!==''){ $where[]="DATE(o.created_at) >= ?"; $p[]=$from; $t.="s"; }
  if ($to  !==''){ $where[]="DATE(o.created_at) <= ?"; $p[]=$to;   $t.="s"; }

  $sqlO = "SELECT o.id, o.status, o.subtotal, o.shipping, o.discount, o.total, o.created_at, o.notes
           FROM orders o
           WHERE ".implode(' AND ',$where)."
           ORDER BY o.created_at DESC";
  $stmt = $conn->prepare($sqlO);
  $stmt->bind_param($t, ...$p);
  $stmt->execute();
  $res = $stmt->get_result();
  $ids = [];
  while ($r = $res->fetch_assoc()){
    $oid = (int)$r['id'];
    $orders[$oid] = [
      'id'         => $oid,
      'status'     => $r['status'],
      'created_at' => $r['created_at'],
      'total'      => (float)($r['total'] ?? 0),
      'notes'      => (string)($r['notes'] ?? ''),
      'items'      => [],
      // payment/city columns tumhare dump me nahi — UI me '—'
      'payment'    => '',
      'city'       => ''
    ];
    $ids[] = $oid;
  }
  $stmt->close();

  if ($ids) {
    // Items bulk fetch (+ optional product name)
    $in = implode(',', array_fill(0,count($ids),'?'));
    $types = str_repeat('i', count($ids));
    $nameExpr = productNameExpr($conn);
    $sqlI = "SELECT oi.order_id, oi.product_id, oi.qty, oi.unit_price, $nameExpr AS product_name
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id IN ($in)";
    $stmt = $conn->prepare($sqlI);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($it = $res->fetch_assoc()){
      $oid = (int)$it['order_id'];
      if (!isset($orders[$oid])) continue;
      $orders[$oid]['items'][] = [
        'product_id' => (int)$it['product_id'],
        'name'       => (string)$it['product_name'],
        'qty'        => (int)$it['qty'],
        'price'      => (float)$it['unit_price'],
      ];
    }
    $stmt->close();

    // If q present, filter by order id or product names
    if ($q!==''){
      $qLow = mb_strtolower($q,'UTF-8');
      $orders = array_filter($orders, function($o) use($qLow){
        if (str_contains((string)$o['id'], $qLow)) return true;
        foreach ($o['items'] as $it){
          if (str_contains(mb_strtolower($it['name'],'UTF-8'), $qLow)) return true;
        }
        return false;
      });
    }

    foreach ($orders as $o) $totalSum += (float)$o['total'];
  }

  // Re-index for rendering
  $orders = array_values($orders);
}

// ---------- Small helpers for view ----------
function itemsLabelShort(array $items): string {
  if (!$items) return '0 • —';
  $count = 0; foreach($items as $it) $count += (int)$it['qty'];
  $first = $items[0]['name'] ?? '';
  if (mb_strlen($first,'UTF-8')>24) $first = mb_substr($first,0,24,'UTF-8').'…';
  return $count.' • '.$first.((count($items)>1)?(' +'.(count($items)-1)):'');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • Orders</title>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>

  <style>
    :root{
      --primary:#F59E0B; --accent:#EF4444; --bg:#FFF7ED; --text:#1F2937; --card:#FFFFFF; --muted:#6B7280;
      --border:#f1e6d7; --radius:18px; --shadow:0 10px 30px rgba(0,0,0,.08); --shadow-sm:0 6px 16px rgba(0,0,0,.06);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0}
    body.bg-app{background:var(--bg);color:var(--text);font-family:Poppins,system-ui,sans-serif;line-height:1.5}
    .page{margin-left:280px;padding:28px 24px 60px}
    .page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .page-title h1{margin:0;font-family:Montserrat,sans-serif;font-size:28px}
    .breadcrumbs{font-size:13px;color:var(--muted)}
    .tag{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid var(--border);font-size:12px}
    .card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .muted{color:var(--muted)}
    .toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px}
    .stat{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid var(--border);font-weight:600}
    .input,.select{border:1px solid var(--border);background:#fff;border-radius:12px;padding:10px 12px;font-size:14px;outline:0}
    .input:focus,.select:focus{box-shadow:0 0 0 4px #ffe7c6;border-color:#f2cf97}
    .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:10px 14px;cursor:pointer;font-weight:600}
    .btn-primary{background:linear-gradient(135deg,var(--primary),#ffb444);color:#fff}
    .btn-ghost{background:#fff;border:1px solid var(--border);color:#92400e}
    .btn-danger{background:linear-gradient(135deg,#f87171,#ef4444);color:#fff}
    .table-wrap{overflow:auto;border:1px solid var(--border);border-radius:14px}
    table{width:100%;border-collapse:separate;border-spacing:0}
    thead th{position:sticky;top:0;background:#fff7ef;border-bottom:1px solid var(--border);text-align:left;font-size:13px;padding:12px;color:#92400e}
    tbody td{padding:12px;border-bottom:1px solid #f6efe4;font-size:14px;vertical-align:middle}
    tbody tr:hover{background:#fffdfa}
    .chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#fff;font-size:12px}
    .chip.ok{background:#ecfdf5;border-color:#bbf7d0;color:#047857}
    .chip.warn{background:#fff7ed;border-color:#fde68a;color:#b45309}
    .chip.danger{background:#fee2e2;border-color:#fecaca;color:#b91c1c}
    .chip.info{background:#eef2ff;border-color:#e0e7ff;color:#3730a3}
    .icon-btn{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:#fff;border:1px solid var(--border);cursor:pointer}
    .icon-btn:hover{box-shadow:var(--shadow-sm);transform:translateY(-1px)}
    .empty{padding:24px;text-align:center;color:var(--muted)}
    .checkbox{width:18px;height:18px}
    @media (max-width: 640px){ .page{margin-left:0} }

    /* Modal (simple) */
    .modal{position:fixed;inset:0;display:none;place-items:center;background:rgba(0,0,0,.25);z-index:100}
    .modal.open{display:grid}
    .modal .sheet{width:min(720px,92vw);background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);overflow:hidden}
    .sheet-head{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid #f3e7d9}
    .sheet-head h3{margin:0;font-family:Montserrat,sans-serif}
    .sheet-body{padding:16px}
    .close-x{background:#fff;border:1px solid var(--border);width:36px;height:36px;border-radius:10px;display:grid;place-items:center;cursor:pointer}
  </style>
</head>
<body class="bg-app">

<?php if (file_exists(__DIR__.'/sidebar.php')) include __DIR__.'/sidebar.php'; ?>

<main class="page">
  <div class="page-head">
    <div class="page-title">
      <div class="breadcrumbs">Owner • Orders</div>
      <h1>Orders</h1>
    </div>
    <span class="tag"><i class="bi bi-bag"></i> History</span>
  </div>

  <section class="card">
    <!-- Toolbar -->
    <form class="toolbar" method="get" id="filters">
      <div class="stat"><i class="bi bi-receipt"></i> Total: <span id="count"><?= $isLogged ? count($orders) : 0 ?></span></div>
      <div class="stat"><i class="bi bi-cash-coin"></i> Sum: <span id="sum">PKR <?= number_format($totalSum) ?></span></div>

      <select class="select" name="status">
        <?php foreach (['All','Delivered','Processing','Cancelled','Returned'] as $opt): ?>
          <option value="<?= $opt ?>" <?= $statusUi===$opt?'selected':''; ?>><?= $opt ?> Status</option>
        <?php endforeach; ?>
      </select>

      <input class="input" type="date" name="from" value="<?= e($from) ?>">
      <input class="input" type="date" name="to" value="<?= e($to) ?>">
      <input class="input" name="q" value="<?= e($q) ?>" placeholder="Search order # or product…">

      <button class="btn btn-ghost" type="button" id="clearFilters"><i class="bi bi-eraser"></i> Clear</button>
      <button class="btn btn-primary"><i class="bi bi-search"></i> Apply</button>
    </form>

    <!-- Table (bulk form) -->
    <form method="post" id="bulkForm">
      <input type="hidden" name="action" value="bulk_delete">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:44px"><input type="checkbox" id="selectAll" class="checkbox"></th>
              <th>Order #</th>
              <th>Date</th>
              <th>Items</th>
              <th>Amount</th>
              <th>Payment</th>
              <th>City</th>
              <th>Status</th>
              <th style="width:140px">Actions</th>
            </tr>
          </thead>
          <tbody id="ordersTbody">
            <?php if (!$isLogged): ?>
              <tr><td colspan="9" class="empty">Please login to see your orders.</td></tr>
            <?php elseif (!$orders): ?>
              <tr><td colspan="9" class="empty">No orders found.</td></tr>
            <?php else: foreach ($orders as $o):
              [$cls,$lbl] = chipClassLabel($o['status']);
              $itemsJson = e(json_encode($o['items'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            ?>
              <tr data-id="<?= (int)$o['id'] ?>" data-items='<?= $itemsJson ?>' data-total="<?= e($o['total']) ?>" data-date="<?= e(substr((string)$o['created_at'],0,10)) ?>" data-status="<?= e($lbl) ?>">
                <td><input type="checkbox" class="checkbox rowCheck" name="ids[]" value="<?= (int)$o['id'] ?>"></td>
                <td><b>FS-<?= str_pad((string)$o['id'],5,'0',STR_PAD_LEFT) ?></b></td>
                <td><?= e(substr((string)$o['created_at'],0,10)) ?></td>
                <td><?= e(itemsLabelShort($o['items'])) ?></td>
                <td>PKR <?= number_format($o['total']) ?></td>
                <td>—</td>
                <td>—</td>
                <td><span class="chip <?= $cls ?>"><i class="bi bi-circle-fill" style="font-size:8px"></i> <?= e($lbl) ?></span></td>
                <td>
                  <button class="icon-btn" type="button" data-action="view" title="View"><i class="bi bi-eye"></i></button>
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete this order?');">
                    <input type="hidden" name="action" value="delete_one">
                    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                    <button class="icon-btn" title="Delete"><i class="bi bi-trash3"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:10px;display:flex;justify-content:flex-end;gap:10px">
        <button type="submit" class="btn btn-danger" id="deleteSelected" <?= ($isLogged && $orders)? '' : 'disabled' ?>><i class="bi bi-trash3"></i> Delete Selected</button>
      </div>
    </form>
  </section>
</main>

<!-- Details Modal -->
<div class="modal" id="detailsModal" aria-hidden="true">
  <div class="sheet">
    <div class="sheet-head">
      <h3 id="modalTitle">Order Details</h3>
      <button class="close-x" id="modalClose"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sheet-body" id="modalBody"></div>
  </div>
</div>

<script>
(function(){
  const $ = id => document.getElementById(id);
  const tbody = $('ordersTbody');
  const selectAll = $('selectAll');
  const bulkBtn = $('deleteSelected');
  const clearBtn = $('clearFilters');
  const filters = $('filters');
  const modal = $('detailsModal');
  const modalTitle = $('modalTitle');
  const modalBody = $('modalBody');
  const modalClose = $('modalClose');

  function updateBulk(){
    const checks = tbody.querySelectorAll('.rowCheck');
    const any = Array.from(checks).some(c => c.checked);
    if (bulkBtn) bulkBtn.disabled = !any;
    if (selectAll) {
      const rows = Array.from(checks);
      selectAll.checked = rows.length>0 && rows.every(c=>c.checked);
    }
  }

  // Select all
  selectAll?.addEventListener('change', ()=>{
    tbody.querySelectorAll('.rowCheck').forEach(c => c.checked = selectAll.checked);
    updateBulk();
  });
  // Row check
  tbody.addEventListener('change', (e)=>{
    if (!e.target.classList.contains('rowCheck')) return;
    updateBulk();
  });

  // Clear filters
  clearBtn?.addEventListener('click', ()=>{
    filters.querySelector('[name="status"]').value = 'All';
    filters.querySelector('[name="from"]').value = '';
    filters.querySelector('[name="to"]').value = '';
    filters.querySelector('[name="q"]').value = '';
    filters.submit();
  });

  // View modal (uses data-* from row)
  function money(n){ return 'PKR ' + Number(n||0).toLocaleString(); }
  tbody.addEventListener('click', (e)=>{
    const btn = e.target.closest('button[data-action="view"]');
    if (!btn) return;
    const tr = btn.closest('tr');
    const items = JSON.parse(tr.dataset.items || '[]');
    const date  = tr.dataset.date || '';
    const status = tr.dataset.status || '';
    const total = tr.dataset.total || 0;

    modalTitle.textContent = `Order ${tr.querySelector('td:nth-child(2) b').textContent}`;
    modalBody.innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:6px">
        <div><b>Date:</b> ${date}</div>
        <div><b>Status:</b> ${status}</div>
        <div><b>Total:</b> ${money(total)}</div>
        <div><b>Notes:</b> —</div>
      </div>
      <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden">
        <table style="width:100%;border-collapse:separate;border-spacing:0">
          <thead>
            <tr>
              <th style="background:#fff7ef;border-bottom:1px solid var(--border);padding:10px;text-align:left">Item</th>
              <th style="background:#fff7ef;border-bottom:1px solid var(--border);padding:10px;text-align:center;width:100px">Qty</th>
              <th style="background:#fff7ef;border-bottom:1px solid var(--border);padding:10px;text-align:right;width:140px">Price</th>
            </tr>
          </thead>
          <tbody>
            ${items.map(it=>`
              <tr>
                <td style="padding:10px;border-bottom:1px solid #f6efe4">${it.name}</td>
                <td style="padding:10px;border-bottom:1px solid #f6efe4;text-align:center">${it.qty}</td>
                <td style="padding:10px;border-bottom:1px solid #f6efe4;text-align:right">${money(it.price)}</td>
              </tr>
            `).join('')}
            <tr>
              <td colspan="2" style="padding:12px;text-align:right"><b>Total</b></td>
              <td style="padding:12px;text-align:right"><b>${money(total)}</b></td>
            </tr>
          </tbody>
        </table>
      </div>
    `;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
  });
  modalClose?.addEventListener('click', ()=>{
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden','true');
  });
  modal.addEventListener('click', (e)=>{ if (e.target===modal){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); }});

  updateBulk();
})();
</script>
</body>
</html>
