<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('vet');

if (!defined('BASE')) define('BASE', '/furshield');
$uid = (int)($_SESSION['user']['id'] ?? 0);

/* ---------- helpers ---------- */
function table_exists(mysqli $c, string $t): bool
{
  $t = $c->real_escape_string($t);
  $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'");
  return $r && $r->num_rows > 0;
}
function col_exists(mysqli $c, string $t, string $col): bool
{
  $t = $c->real_escape_string($t);
  $col = $c->real_escape_string($col);
  $r = $c->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
  return $r && $r->num_rows > 0;
}
function col_type(mysqli $c, string $t, string $col): ?string
{
  $t = $c->real_escape_string($t);
  $col = $c->real_escape_string($col);
  $r = $c->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$col}'");
  if ($r && ($x = $r->fetch_assoc())) return strtolower($x['DATA_TYPE'] ?? '');
  return null;
}
function pick_col(mysqli $c, string $t, array $cands): ?string
{
  foreach ($cands as $x) {
    if (col_exists($c, $t, $x)) return $x;
  }
  return null;
}
function valid_time($s)
{
  return (bool)preg_match('/^(2[0-3]|[01]\d):[0-5]\d$/', $s);
}          // HH:MM
function valid_date($s)
{
  return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}                 // YYYY-MM-DD

/* ---------- map vet profile id if exists ---------- */
$vetProfileId = null;
if (table_exists($conn, 'vets') && col_exists($conn, 'vets', 'user_id') && col_exists($conn, 'vets', 'id')) {
  $q = $conn->prepare("SELECT id FROM vets WHERE user_id=? LIMIT 1");
  $q->bind_param("i", $uid);
  $q->execute();
  $q->bind_result($vetProfileId);
  $q->fetch();
  $q->close();
}
$whoA = $uid;
$whoB = $vetProfileId ?? -1;

/* ---------- discover/create availability table ---------- */
$tab = null;
foreach (['vet_availability', 'availability', 'availabilities', 'slots', 'vet_slots'] as $cand) {
  if (table_exists($conn, $cand)) {
    $tab = $cand;
    break;
  }
}
if (!$tab) {
  $ddl = "
    CREATE TABLE IF NOT EXISTS vet_availability (
      id INT AUTO_INCREMENT PRIMARY KEY,
      vet_id INT NOT NULL,
      dow TINYINT NULL,                -- 0=Sun..6=Sat (NULL for specific date)
      specific_date DATE NULL,         -- set for one-off exceptions
      start_time TIME NOT NULL,
      end_time TIME NOT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(vet_id), INDEX(dow), INDEX(specific_date), INDEX(is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ";
  $conn->query($ddl);
  $tab = 'vet_availability';
}

/* columns (auto-map) */
$C_ID   = pick_col($conn, $tab, ['id', 'slot_id']);
$C_VET  = pick_col($conn, $tab, ['vet_id', 'vet_user_id', 'doctor_id', 'user_id', 'vet']);
$C_DOW  = pick_col($conn, $tab, ['dow', 'day_of_week', 'weekday', 'day']);
$C_DATE = pick_col($conn, $tab, ['specific_date', 'date', 'for_date', 'slot_date']);
$C_ST   = pick_col($conn, $tab, ['start_time', 'from_time', 'start', 'time_from']);
$C_ET   = pick_col($conn, $tab, ['end_time', 'to_time', 'end', 'time_to']);
$C_ACT  = pick_col($conn, $tab, ['is_active', 'active', 'enabled', 'status']);

if (!$C_ID || !$C_ST || !$C_ET) {
  http_response_code(500);
  exit('availability key/time columns missing');
}

/* bool vs status(active/inactive) */
$act_is_text = ($C_ACT && col_type($conn, $tab, $C_ACT) === 'varchar');

/* ---------- POST actions ---------- */
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add_weekly') {
    $dow = (int)($_POST['dow'] ?? -1);
    $st  = substr(trim($_POST['start'] ?? ''), 0, 5);
    $et  = substr(trim($_POST['end'] ?? ''), 0, 5);
    if ($dow >= 0 && $dow <= 6 && valid_time($st) && valid_time($et) && $st < $et) {
      $cols = ($C_VET ? "`$C_VET`," : "") . ($C_DOW ? "`$C_DOW`," : "") . "`$C_ST`,`$C_ET`" . ($C_ACT ? ",`$C_ACT`" : "");
      $vals = ($C_VET ? "?," : "") . ($C_DOW ? "?," : "") . "?,?" . ($C_ACT ? ",?" : "");
      $sql  = "INSERT INTO `$tab` ($cols) VALUES ($vals)";
      $stt = $conn->prepare($sql);
      $activeVal = $act_is_text ? 'active' : 1;
      if ($C_VET && $C_DOW && $C_ACT) {
        $stt->bind_param("iissi", $whoA, $dow, $st, $et, $activeVal);
      } elseif ($C_VET && $C_DOW && !$C_ACT) {
        $stt->bind_param("iiss", $whoA, $dow, $st, $et);
      } elseif ($C_VET && !$C_DOW && $C_ACT) {
        $stt->bind_param("issi", $whoA, $st, $et, $activeVal);
      } elseif ($C_VET && !$C_DOW && !$C_ACT) {
        $stt->bind_param("iss", $whoA, $st, $et);
      } elseif (!$C_VET && $C_DOW && $C_ACT) {
        $stt->bind_param("issi", $dow, $st, $et, $activeVal);
      } elseif (!$C_VET && $C_DOW && !$C_ACT) {
        $stt->bind_param("iss", $dow, $st, $et);
      } else {
        $stt->bind_param("ss", $st, $et);
      }
      $ok = $stt->execute();
      $stt->close();
      $flash = $ok ? "Weekly slot added" : "Failed to add weekly slot";
    } else {
      $flash = "Invalid weekly slot";
    }
  }

  if ($action === 'add_date') {
    $date = trim($_POST['date'] ?? '');
    $st   = substr(trim($_POST['start'] ?? ''), 0, 5);
    $et   = substr(trim($_POST['end'] ?? ''), 0, 5);
    if (valid_date($date) && valid_time($st) && valid_time($et) && $st < $et) {
      $cols = ($C_VET ? "`$C_VET`," : "") . ($C_DATE ? "`$C_DATE`," : "") . "`$C_ST`,`$C_ET`" . ($C_ACT ? ",`$C_ACT`" : "");
      $vals = ($C_VET ? "?," : "") . ($C_DATE ? "?," : "") . "?,?" . ($C_ACT ? ",?" : "");
      $sql  = "INSERT INTO `$tab` ($cols) VALUES ($vals)";
      $stt = $conn->prepare($sql);
      $activeVal = $act_is_text ? 'active' : 1;
      if ($C_VET && $C_DATE && $C_ACT) {
        $stt->bind_param("isssi", $whoA, $date, $st, $et, $activeVal);
      } elseif ($C_VET && $C_DATE && !$C_ACT) {
        $stt->bind_param("isss", $whoA, $date, $st, $et);
      } elseif ($C_VET && !$C_DATE && $C_ACT) {
        $stt->bind_param("issi", $whoA, $st, $et, $activeVal);
      } elseif ($C_VET && !$C_DATE && !$C_ACT) {
        $stt->bind_param("iss", $whoA, $st, $et);
      } elseif (!$C_VET && $C_DATE && $C_ACT) {
        $stt->bind_param("sssi", $date, $st, $et, $activeVal);
      } elseif (!$C_VET && $C_DATE && !$C_ACT) {
        $stt->bind_param("sss", $date, $st, $et);
      } else {
        $stt->bind_param("ss", $st, $et);
      }
      $ok = $stt->execute();
      $stt->close();
      $flash = $ok ? "Date-specific slot added" : "Failed to add date slot";
    } else {
      $flash = "Invalid date slot";
    }
  }

  if ($action === 'toggle' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($C_ACT) {
      if ($act_is_text) {
        // flip active/inactive
        $conn->query("UPDATE `$tab` SET `$C_ACT`=IF(`$C_ACT`='active','inactive','active') WHERE `$C_ID`={$id} " . ($C_VET ? " AND `$C_VET` IN (" . $whoA . "," . $whoB . ")" : ""));
      } else {
        $conn->query("UPDATE `$tab` SET `$C_ACT`=IF(`$C_ACT`=1,0,1) WHERE `$C_ID`={$id} " . ($C_VET ? " AND `$C_VET` IN (" . $whoA . "," . $whoB . ")" : ""));
      }
      $flash = "Toggled";
    }
  }

  if ($action === 'delete' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $sql = "DELETE FROM `$tab` WHERE `$C_ID`=?" . ($C_VET ? " AND `$C_VET` IN (?,?)" : "");
    $st = $conn->prepare($sql);
    if ($C_VET) {
      $st->bind_param("iii", $id, $whoA, $whoB);
    } else {
      $st->bind_param("i", $id);
    }
    $ok = $st->execute();
    $st->close();
    $flash = $ok ? "Deleted" : "Delete failed";
  }
}

/* ---------- fetch data ---------- */
$bind = [];
$bt = '';
$w = [];
if ($C_VET) {
  $w[] = "`$C_VET` IN (?,?)";
  $bind[] = $whoA;
  $bind[] = $whoB;
  $bt = 'ii';
}
$where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

$selDOW  = $C_DOW  ? "`$C_DOW` AS dow" : "NULL AS dow";
$selDATE = $C_DATE ? "`$C_DATE` AS sdate" : "NULL AS sdate";
$selACT  = $C_ACT  ? "`$C_ACT` AS active" : "1 AS active";

$sql = "SELECT `$C_ID` AS id, $selDOW, $selDATE, `$C_ST` AS st, `$C_ET` AS et, $selACT
      FROM `$tab` $where ORDER BY (sdate IS NULL) DESC, sdate ASC, dow ASC, st ASC";
$st = $conn->prepare($sql);
if ($bt) $st->bind_param($bt, ...$bind);
$st->execute();
$res = $st->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$st->close();

/* split: weekly vs date-specific */
$weekly = array_values(array_filter($rows, fn($r) => is_null($r['sdate'])));
$dates  = array_values(array_filter($rows, fn($r) => !is_null($r['sdate'])));

$days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="main">
  <div class="container-fluid py-4">
    <?php if ($flash) { ?><div class="alert alert-warning border-0 shadow-sm mb-3"><?php echo htmlspecialchars($flash); ?></div><?php } ?>

    <div class="row g-3">
      <div class="col-12 col-xl-7">
        <div class="cardx p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="m-0">Weekly Availability</h5>
            <div class="text-muted small">Repeats every week</div>
          </div>

          <div class="week-grid">
            <?php for ($d = 0; $d < 7; $d++): ?>
              <div class="day-col">
                <div class="day-head"><?php echo $days[$d]; ?></div>
                <div class="slot-list">
                  <?php
                  $has = false;
                  foreach ($weekly as $s) {
                    if ((int)$s['dow'] === $d) {
                      $has = true;
                      $badge = ($act_is_text ? ($s['active'] === 'active') : ((int)$s['active'] === 1)) ? 'on' : 'off';
                      echo '<div class="slot-pill slot-' . $badge . '">' .
                        htmlspecialchars(substr($s['st'], 0, 5)) . '–' . htmlspecialchars(substr($s['et'], 0, 5)) .
                        '<form method="post" class="slot-actions">' .
                        '<input type="hidden" name="id" value="' . (int)$s['id'] . '"/>' .
                        '<button name="action" value="toggle" class="btn btn-xs btn-light">Toggle</button>' .
                        '<button name="action" value="delete" class="btn btn-xs btn-danger" onclick="return confirm(\'Delete slot?\')">Del</button>' .
                        '</form>' .
                        '</div>';
                    }
                  }
                  if (!$has) echo '<div class="text-muted small">Closed</div>';
                  ?>
                </div>
                <form method="post" class="add-slot mt-2">
                  <input type="hidden" name="action" value="add_weekly" />
                  <input type="hidden" name="dow" value="<?php echo $d; ?>" />
                  <div class="input-group">
                    <input type="time" name="start" required class="form-control" placeholder="Start" />
                    <span class="input-group-text">to</span>
                    <input type="time" name="end" required class="form-control" placeholder="End" />
                    <button class="btn btn-primary" type="submit">Add</button>
                  </div>
                </form>

              </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-5">
        <div class="cardx p-3 h-100">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="m-0">Date Overrides</h5>
            <div class="text-muted small">Specific dates (holidays, extra hours)</div>
          </div>

          <form method="post" class="mb-3">
            <input type="hidden" name="action" value="add_date" />
            <div class="row g-2">
              <div class="col-5"><input type="date" name="date" required class="form-control" value="<?php echo date('Y-m-d'); ?>" /></div>
              <div class="col-3"><input type="time" name="start" required class="form-control" placeholder="Start" /></div>
              <div class="col-3"><input type="time" name="end" required class="form-control" placeholder="End" /></div>
              <div class="col-1 d-grid"><button class="btn btn-primary" type="submit">Add</button></div>
            </div>


          </form>

          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$dates) { ?>
                  <tr>
                    <td colspan="4" class="text-center text-muted py-3">No overrides</td>
                  </tr>
                  <?php } else {
                  foreach ($dates as $s) {
                    $isOn = $act_is_text ? ($s['active'] === 'active') : ((int)$s['active'] === 1); ?>
                    <tr>
                      <td class="nowrap"><?php echo htmlspecialchars($s['sdate']); ?></td>
                      <td class="fw-semibold"><?php echo htmlspecialchars(substr($s['st'], 0, 5) . '–' . substr($s['et'], 0, 5)); ?></td>
                      <td><span class="badge <?php echo $isOn ? 'bg-soft-approved' : 'bg-soft-cancelled'; ?>"><?php echo $isOn ? 'Active' : 'Off'; ?></span></td>
                      <td class="text-end">
                        <form method="post" class="d-inline">
                          <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>" />
                          <button class="btn btn-sm btn-light" name="action" value="toggle">Toggle</button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this slot?')">
                          <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>" />
                          <button class="btn btn-sm btn-danger" name="action" value="delete">Delete</button>
                        </form>
                      </td>
                    </tr>
                <?php }
                } ?>
              </tbody>
            </table>
          </div>

          <div class="alert alert-light border mt-3">
            <div class="fw-semibold mb-1">How it works</div>
            <ul class="m-0 ps-3">
              <li>Weekly slots repeat every week.</li>
              <li>Date overrides add extra hours or mark special openings.</li>
              <li>Disabled slots are ignored when booking.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>