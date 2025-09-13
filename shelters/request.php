<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/db.php";

// ---- Delete Request ----
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $stmt = $conn->prepare("DELETE FROM request WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  header("Location: request.php");
  exit;
}

// ---- Fetch All Requests ----
$requests = [];
$res = $conn->query("SELECT * FROM request ORDER BY request_date DESC");
if ($res) while ($row = $res->fetch_assoc()) $requests[] = $row;

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>FurShield • Adoption Requests</title>

  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>

  <style>
    :root{
      --primary:#F59E0B; --accent:#EF4444; --bg:#FFF7ED; --text:#1F2937;
      --muted:#6B7280; --card:#FFFFFF; --ring:#f0e7da; --radius:18px; --shadow:0 10px 30px rgba(0,0,0,.08);
      --ok:#065f46; --warn:#9a3412; --info:#3730a3; --bad:#991b1b;
    }
    body{margin:0;background:var(--bg);color:var(--text);font-family:Poppins,sans-serif}
    .wrap{width:100%;margin:22px auto;padding:0 16px}
    .card{background:var(--card);border:1px solid var(--ring);border-radius:var(--radius);
          box-shadow:var(--shadow);padding:22px}

    table{width:100%;border-collapse:separate;border-spacing:0 8px;margin-top:15px}
    th{font-size:.85rem;text-align:left;color:var(--muted);font-weight:600;padding:10px 12px}
    td{background:#fff;border:1px solid var(--ring);padding:14px;vertical-align:middle;
       box-shadow:0 2px 6px rgba(0,0,0,.05)}
    tr td:first-child{border-radius:12px 0 0 12px}
    tr td:last-child{border-radius:0 12px 12px 0}
    tr:hover td{background:#fffdf5}

    .thumb{width:46px;height:46px;border-radius:12px;background:#fff4e2;display:grid;place-items:center;color:#b45309}
    .status{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:.8rem;font-weight:600}
    .st-pending{background:#fff7ed;color:var(--warn)}
    .st-pre-approved{background:#eef2ff;color:var(--info)}
    .st-approved{background:#ecfdf5;color:var(--ok)}
    .st-rejected{background:#fee2e2;color:var(--bad)}

    .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:8px;font-size:.85rem;
         border:1px solid var(--ring);text-decoration:none;transition:.2s}
    .btn:hover{transform:translateY(-2px);box-shadow:0 3px 8px rgba(0,0,0,.1)}
    .btn-view{background:#eef2ff;color:#3730a3}
    .btn-danger{background:#fee2e2;color:#991b1b}
    .btn-approve{background:#ecfdf5;color:#065f46}
  </style>
</head>
<body>
  <div class="app">
    <?php include("head.php") ?>
    <?php include("sidebar.php") ?>

    <main class="wrap">
      <section class="card">
        <h2 style="font-family:Montserrat;color:var(--primary);margin-bottom:20px">
          <i class="bi bi-clipboard-check"></i> Adoption Requests
        </h2>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Applicant</th>
              <th>Pet</th>
              <th>Date</th>
              <th>Score</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!$requests): ?>
              <tr><td colspan="7" style="text-align:center;color:var(--muted)">No requests found</td></tr>
            <?php else: foreach($requests as $r): ?>
              <tr>
                <td><?=h($r['id'])?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:10px">
                    <div class="thumb"><i class="bi bi-person"></i></div>
                    <div>
                      <strong><?=h($r['applicant_name'])?></strong>
                      <div class="muted"><?=h($r['email'])?> • <?=h($r['phone'])?></div>
                    </div>
                  </div>
                </td>
                <td><strong><?=h($r['pet_name'])?></strong> • <?=h($r['breed'])?></td>
                <td><?=date("d M Y", strtotime($r['request_date']))?></td>
                <td><span class="badge"><i class="bi bi-star"></i> <?=h($r['score'])?></span></td>
                <td><span class="status st-<?=strtolower($r['status'])?>"><?=ucfirst($r['status'])?></span></td>
                <td>
                  <a class="btn btn-view" href="view-request.php?id=<?=$r['id']?>"><i class="bi bi-eye"></i> View</a>
                  <a class="btn btn-approve" href="update-request.php?id=<?=$r['id']?>&status=approved"><i class="bi bi-check2"></i> Approve</a>
                  <a class="btn btn-danger" href="?delete=<?=$r['id']?>" onclick="return confirm('Delete this request?')"><i class="bi bi-trash"></i> Delete</a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>
