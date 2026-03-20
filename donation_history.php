<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");


if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$userId   = (int)$_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'User');


require_once "db.php"; 


$sql = "
  SELECT 
    id,
    blood_group,
    hospital_id,
    preferred_date,
    preferred_time,
    availability,
    health_confirmations,
    status,
    created_at
  FROM donations
  WHERE user_id = ?
  ORDER BY created_at DESC
";


$stmt = $conn->prepare($sql);
if (!$stmt) {
  die("SQL Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$total = 0;
$completed = 0;
$pending = 0;

while ($r = $result->fetch_assoc()) {
  $rows[] = $r;
  $total++;

  $st = strtolower(trim($r['status'] ?? ''));
  if ($st === 'completed') $completed++;
  else if ($st === 'pending') $pending++;
}

$stmt->close();

function esc($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function badgeClass($status) {
  $s = strtolower(trim((string)$status));
  if ($s === 'completed') return "badge badge-ok";
  if ($s === 'pending')   return "badge badge-warn";
  if ($s === 'cancelled') return "badge badge-bad";
  return "badge";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Donation History | Rakta.Bindu</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root{
      --red:#c4161c;
      --dark:#111827;
      --soft:#f5f6f8;
      --card:#ffffff;
      --muted:#6b7280;
      --border:rgba(0,0,0,.08);
      --shadow:0 10px 30px rgba(0,0,0,.06);
      --radius:18px;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background:var(--soft);
      color:var(--dark);
    }
    a{text-decoration:none;color:inherit}
    .container{max-width:1100px;margin:0 auto;padding:0 16px}

    /* Topbar */
    header{
      background:#fff;
      border-bottom:1px solid var(--border);
      position:sticky; top:0; z-index:1000;
    }
    .topbar{
      height:64px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
    }
    .brand{
      display:flex;align-items:center;gap:10px;font-weight:800;
      letter-spacing:.2px;
    }
    .drop{
      width:12px;height:18px;background:var(--red);
      border-radius:10px 10px 14px 14px;
      position:relative;
    }
    .brand span{color:var(--red)}
    nav ul{list-style:none;display:flex;gap:18px;align-items:center;margin:0;padding:0}
    nav a{
      font-size:13px;
      color:#111827;
      font-weight:600;
      opacity:.85;
      padding:10px 10px;
      border-radius:10px;
    }
    nav a:hover{opacity:1;color:var(--red);background:rgba(196,22,28,.06)}
    .right-actions{display:flex;align-items:center;gap:10px}
    .pill{
      display:inline-flex;align-items:center;gap:8px;
      border:1px solid var(--border);
      background:#fff;
      padding:10px 12px;border-radius:999px;
      font-weight:700;font-size:13px;
    }
    .btn{
      display:inline-flex;align-items:center;justify-content:center;
      padding:10px 14px;border-radius:12px;
      font-weight:800;font-size:13px;border:1px solid var(--border);
      background:#fff; cursor:pointer;
    }
    .btn-red{background:var(--red);border-color:var(--red);color:#fff}
    .btn:hover{filter:brightness(.98)}

    /* Page */
    .page{padding:22px 0 40px}
    .hero{
      display:flex;align-items:flex-start;justify-content:space-between;gap:14px;
      margin-bottom:14px;
    }
    .title{
      font-size:22px;font-weight:900;margin:0;
    }
    .subtitle{
      margin:6px 0 0;
      color:var(--muted);
      font-size:13px;
      line-height:1.4;
    }

    /* Summary cards */
    .grid{
      display:grid;
      grid-template-columns:repeat(3, minmax(0,1fr));
      gap:12px;
      margin:16px 0 16px;
    }
    .card{
      background:var(--card);
      border:1px solid var(--border);
      box-shadow:var(--shadow);
      border-radius:var(--radius);
      padding:14px;
    }
    .metric{
      display:flex;align-items:center;justify-content:space-between;gap:10px;
    }
    .metric h3{margin:0;font-size:13px;color:var(--muted);font-weight:800}
    .metric .val{font-size:22px;font-weight:900}
    .dot{
      width:10px;height:10px;border-radius:50%;
      background:rgba(0,0,0,.12);
    }
    .dot.red{background:rgba(196,22,28,.9)}
    .dot.green{background:rgba(16,185,129,.9)}
    .dot.yellow{background:rgba(245,158,11,.9)}

    /* Table */
    .table-card{padding:0; overflow:hidden}
    .table-head{
      display:flex;align-items:center;justify-content:space-between;
      padding:14px 14px;
      border-bottom:1px solid var(--border);
    }
    .table-head h2{
      margin:0;font-size:14px;font-weight:900;
    }
    .search{
      display:flex;align-items:center;gap:8px;
      background:#fff;border:1px solid var(--border);
      border-radius:12px;padding:10px 12px;
      min-width:260px;
    }
    .search input{
      border:none;outline:none;width:100%;
      font-size:13px;
    }
    table{width:100%;border-collapse:collapse}
    th, td{
      padding:12px 14px;
      border-bottom:1px solid var(--border);
      font-size:13px;
      vertical-align:top;
    }
    th{
      text-align:left;
      color:var(--muted);
      font-weight:900;
      font-size:12px;
      letter-spacing:.2px;
      background:rgba(0,0,0,.02);
    }
    tr:hover td{background:rgba(196,22,28,.03)}
    .badge{
      display:inline-flex;align-items:center;gap:8px;
      padding:6px 10px;border-radius:999px;
      font-weight:900;font-size:12px;
      border:1px solid var(--border);
      background:#fff;
      text-transform:capitalize;
      white-space:nowrap;
    }
    .badge-ok{border-color:rgba(16,185,129,.35);background:rgba(16,185,129,.08)}
    .badge-warn{border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.10)}
    .badge-bad{border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.08)}
    .muted{color:var(--muted)}
    .empty{
      padding:18px 14px;
      color:var(--muted);
      font-weight:700;
    }

    @media (max-width: 900px){
      nav{display:none}
      .grid{grid-template-columns:1fr}
      .search{min-width:0;width:100%}
      .hero{flex-direction:column}
    }
  </style>
</head>

<body>
<?php include "navbar.php"; ?>

<main class="page">
  <div class="container">

    <div class="hero">
      <div>
        <h1 class="title">Donation History</h1>
        <p class="subtitle">
          View your past donations, status updates, and donation details in one place.
        </p>
      </div>
      <a class="btn" href="index.php">← Back to Dashboard</a>
    </div>

    <div class="grid">
      <div class="card">
        <div class="metric">
          <div>
            <h3>Total Donations</h3>
            <div class="val"><?= (int)$total ?></div>
          </div>
          <div class="dot red"></div>
        </div>
      </div>

      <div class="card">
        <div class="metric">
          <div>
            <h3>Completed</h3>
            <div class="val"><?= (int)$completed ?></div>
          </div>
          <div class="dot green"></div>
        </div>
      </div>

      <div class="card">
        <div class="metric">
          <div>
            <h3>Pending</h3>
            <div class="val"><?= (int)$pending ?></div>
          </div>
          <div class="dot yellow"></div>
        </div>
      </div>
    </div>

    <div class="card table-card">
      <div class="table-head">
        <h2>Your Records</h2>
        <div class="search">
          <span class="muted">🔎</span>
          <input id="searchBox" type="text" placeholder="Search hospital / blood group / status..." />
        </div>
      </div>

      <?php if (count($rows) === 0): ?>
        <div class="empty">No donation records found yet.</div>
      <?php else: ?>
        <div style="overflow:auto;">
          <table id="historyTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Blood Group</th>
                <th>Units</th>
                <th>Hospital / Center</th>
                <th>Date</th>
                <th>Status</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $i => $r): ?>
                <?php
    $date = !empty($r['created_at']) ? date("Y-m-d H:i", strtotime($r['created_at'])) : "—";

                  $status = $r['status'] ?? "unknown";
                ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><strong><?= esc($r['blood_group'] ?? "—") ?></strong></td>
                  <td><?= esc($r['units'] ?? "—") ?></td>
                  <td><?= esc($r['hospital_name'] ?? "—") ?></td>
                  <td><?= esc($date) ?></td>
                  <td><span class="<?= badgeClass($status) ?>"><?= esc($status) ?></span></td>
                  <td class="muted"><?= esc($r['notes'] ?? "") ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<script>
  // Simple client-side search filter
  const searchBox = document.getElementById('searchBox');
  const table = document.getElementById('historyTable');

  if (searchBox && table) {
    searchBox.addEventListener('input', () => {
      const q = searchBox.value.toLowerCase().trim();
      const rows = table.querySelectorAll('tbody tr');
      rows.forEach(tr => {
        const text = tr.innerText.toLowerCase();
        tr.style.display = text.includes(q) ? '' : 'none';
      });
    });
  }
</script>

</body>
</html>
