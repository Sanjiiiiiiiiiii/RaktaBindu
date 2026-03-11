<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");
require_once __DIR__ . "/db.php";

// ✅ must be logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$uid = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? "User";
$avatarLetter = strtoupper(mb_substr($userName, 0, 1));

$success = "";
$error = "";

// ✅ Cancel request (only pending)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["request_id"])) {
  $action = $_POST["action"];
  $rid = (int)$_POST["request_id"];

  if ($action === "cancel") {
    // NOTE: This assumes blood_requests has a 'status' column.
    // If your column name is different, change status='Cancelled'.
    $up = $conn->prepare("
      UPDATE blood_requests
      SET status='Cancelled'
      WHERE id=? AND user_id=? AND (status='Pending' OR status='' OR status IS NULL)
    ");
    if (!$up) {
      $error = "DB Error: " . $conn->error;
    } else {
      $up->bind_param("ii", $rid, $uid);
      if ($up->execute() && $up->affected_rows > 0) {
        $success = "Request cancelled successfully.";
      } else {
        $error = "Unable to cancel (maybe already approved/completed).";
      }
      $up->close();
    }
  }
}

// ✅ Fetch my requests
// Assumes columns exist: id, user_id, blood_group, quantity, hospital_location, urgency, needed_date, needed_time, patient_notes, status, created_at
// If your table doesn’t have status/created_at, see the notes below.
$stmt = $conn->prepare("
  SELECT
    id,
    blood_group,
    quantity,
    hospital_location,
    urgency,
    needed_date,
    needed_time,
    patient_notes,
    status,
    created_at
  FROM blood_requests
  WHERE user_id = ?
  ORDER BY created_at DESC, id DESC
");

$requests = [];
if (!$stmt) {
  // fallback if created_at column doesn't exist
  $stmt2 = $conn->prepare("
    SELECT
      id,
      blood_group,
      quantity,
      hospital_location,
      urgency,
      needed_date,
      needed_time,
      patient_notes,
      status
    FROM blood_requests
    WHERE user_id = ?
    ORDER BY id DESC
  ");

  if (!$stmt2) {
    $error = "DB Error: " . $conn->error;
  } else {
    $stmt2->bind_param("i", $uid);
    $stmt2->execute();
    $res = $stmt2->get_result();
    while ($row = $res->fetch_assoc()) $requests[] = $row;
    $stmt2->close();
  }
} else {
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $requests[] = $row;
  $stmt->close();
}

function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function badgeClass($status) {
  $s = strtolower(trim((string)$status));
  if ($s === "approved") return "badge badge-ok";
  if ($s === "completed") return "badge badge-ok";
  if ($s === "pending" || $s === "") return "badge badge-warn";
  if ($s === "cancelled") return "badge badge-bad";
  return "badge";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Requests | RaktaBindu</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

  <style>
    :root{
      --red:#c62828;
      --red2:#ff3d3d;
      --bg:#f6f7fb;
      --card:#ffffff;
      --text:#1f2430;
      --muted:#667085;
      --line:rgba(0,0,0,.08);
      --shadow2:0 10px 30px rgba(0,0,0,.08);
      --r:18px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Segoe UI",system-ui,Arial;background:var(--bg);color:var(--text);}
    a{text-decoration:none;color:inherit}

    .page-title{width:min(1100px,92%);margin:22px auto 10px;color:#98a2b3;font-weight:900}

    .shell{
      width:min(1100px,92%);
      margin:0 auto 28px;
      background:#fff;
      border:1px solid var(--line);
      border-radius:14px;
      box-shadow:0 12px 40px rgba(0,0,0,.06);
      overflow:hidden;
    }

    .topnav{
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:14px 18px;
      border-bottom:1px solid rgba(0,0,0,.06);
      background:#fff;
      gap:12px;
    }
    .brand{display:flex;align-items:center;gap:10px;font-weight:1000;}
    .brand .logo{
      width:34px;height:34px;border-radius:10px;background:rgba(198,40,40,.10);
      display:grid;place-items:center;color:var(--red);
    }
    .brand small{display:block;color:#98a2b3;font-weight:800;font-size:11px;margin-top:-2px}
    .brand .text b{color:var(--red)}
    .links{display:flex;gap:22px;align-items:center;flex-wrap:wrap}
    .links a{
      color:#667085;font-weight:900;font-size:12px;
      padding:10px 12px;border-radius:12px;display:inline-flex;gap:8px;align-items:center;
    }
    .links a:hover{background:rgba(198,40,40,.08);color:var(--red)}
    .links a.active{color:var(--red); position:relative;}
    .links a.active::after{
      content:""; position:absolute; left:12px; right:12px; bottom:6px;
      height:2px; background:var(--red); border-radius:999px;
    }
    .rightbar{display:flex;align-items:center;gap:12px}
    .bell{
      width:38px;height:38px;border-radius:12px;border:1px solid var(--line);background:#fff;
      display:grid;place-items:center;color:#667085;cursor:pointer;
      position:relative;
    }
    .dot-red{
      position:absolute; top:8px; right:10px;
      width:8px; height:8px; border-radius:50%;
      background:var(--red2);
      border:2px solid #fff;
    }
    .avatar{
      width:38px;height:38px;border-radius:50%;
      background:rgba(198,40,40,.10);
      color:var(--red);
      display:grid;place-items:center;
      font-weight:1000;
      border:1px solid rgba(198,40,40,.18);
    }

    .content{padding:18px;}
    .card{
      background:var(--card);
      border:1px solid rgba(0,0,0,.08);
      border-radius:16px;
      box-shadow:var(--shadow2);
      overflow:hidden;
    }

    .head{
      display:flex;align-items:flex-start;justify-content:space-between;
      padding:14px 16px;border-bottom:1px solid rgba(0,0,0,.06);
      gap:12px; flex-wrap:wrap;
    }
    .head h2{font-size:16px;font-weight:1000}
    .head p{font-size:12px;color:var(--muted);margin-top:4px}

    .msg{
      margin:14px 16px 0;
      padding:12px 14px;border-radius:14px;font-size:13px;font-weight:900;
      border:1px solid;display:flex; gap:10px; align-items:flex-start;
    }
    .msg.ok{background:#e8f5e9;border-color:#c8e6c9;color:#2e7d32}
    .msg.bad{background:#ffebee;border-color:#ffcdd2;color:#b71c1c}

    table{width:100%;border-collapse:collapse}
    th, td{padding:12px 14px;border-bottom:1px solid rgba(0,0,0,.06);font-size:12px;vertical-align:top}
    th{color:#667085;font-weight:1000;background:rgba(0,0,0,.02);text-align:left}

    .badge{
      display:inline-flex;align-items:center;
      padding:6px 10px;border-radius:999px;
      font-weight:1000;font-size:11px;
      border:1px solid rgba(0,0,0,.10);
      background:#fff;
      text-transform:capitalize;
      white-space:nowrap;
    }
    .badge-ok{border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.10);color:#166534}
    .badge-warn{border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.10);color:#92400e}
    .badge-bad{border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.10);color:#991b1b}

    .actions{display:flex;gap:8px;flex-wrap:wrap}
    .btn{
      height:34px; padding:0 10px; border-radius:10px;
      border:1px solid rgba(0,0,0,.10);
      background:#fff; cursor:pointer;
      font-size:11px; font-weight:1000; color:#344054;
      display:inline-flex; align-items:center; gap:7px;
    }
    .btn.red{background:rgba(198,40,40,.10);border-color:rgba(198,40,40,.20);color:var(--red);}
    .btn:hover{transform:translateY(-1px); box-shadow:0 10px 20px rgba(0,0,0,.08)}

    .empty{padding:16px;color:#98a2b3;font-weight:1000}

    @media (max-width: 980px){
      .links{gap:10px}
      table{display:block;overflow:auto}
    }
  </style>
</head>

<body>
  <div class="page-title">My Blood Requests</div>

  <div class="shell">
    <div class="topnav">
      <div class="brand">
        <div class="logo"><i class="fa-solid fa-droplet"></i></div>
        <div class="text">
          Rakta.<b>Bindu</b>
          <small>Save Lives Together</small>
        </div>
      </div>

      <div class="links">
        <a href="index.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <a href="request-blood.php"><i class="fa-solid fa-droplet"></i> Request Blood</a>
        <a class="active" href="my-requests.php"><i class="fa-regular fa-file-lines"></i> My Requests</a>
        <a href="donors.php"><i class="fa-solid fa-users"></i> Donors</a>
      </div>

      <div class="rightbar">
        <a class="bell" href="notifications.php" title="Notifications">
          <i class="fa-regular fa-bell"></i>
          <span class="dot-red"></span>
        </a>
        <a class="avatar" href="profile.php" title="<?php echo esc($userName); ?>">
          <?php echo esc($avatarLetter); ?>
        </a>
      </div>
    </div>

    <div class="content">
      <div class="card">
        <div class="head">
          <div>
            <h2>Your Requests</h2>
            <p>Track request status and manage pending requests.</p>
          </div>
          <a class="btn" href="request-blood.php"><i class="fa-solid fa-plus"></i> New Request</a>
        </div>

        <?php if ($success): ?>
          <div class="msg ok"><i class="fa-solid fa-circle-check"></i> <?php echo esc($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="msg bad"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo esc($error); ?></div>
        <?php endif; ?>

        <?php if (count($requests) === 0): ?>
          <div class="empty">No requests yet. Create your first request from “Request Blood”.</div>
        <?php else: ?>
          <div style="overflow:auto;">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Blood Group</th>
                  <th>Units</th>
                  <th>Hospital / Location</th>
                  <th>Urgency</th>
                  <th>Needed</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($requests as $i => $r): ?>
                  <?php
                    $status = $r["status"] ?? "Pending";
                    $needed = (trim((string)($r["needed_date"] ?? "")) ?: "—") . " " . (trim((string)($r["needed_time"] ?? "")) ?: "");
                    $canCancel = (strtolower(trim((string)$status)) === "pending" || trim((string)$status) === "");
                  ?>
                  <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><b><?php echo esc($r["blood_group"] ?? "—"); ?></b></td>
                    <td><?php echo esc($r["quantity"] ?? "—"); ?></td>
                    <td><?php echo esc($r["hospital_location"] ?? "—"); ?></td>
                    <td><?php echo esc($r["urgency"] ?? "—"); ?></td>
                    <td><?php echo esc(trim($needed)); ?></td>
                    <td><span class="<?php echo badgeClass($status); ?>"><?php echo esc($status ?: "Pending"); ?></span></td>
                    <td>
                      <div class="actions">
                        <button class="btn" type="button"
                          onclick="alert('Notes: <?php echo esc($r['patient_notes'] ?? ''); ?>');">
                          <i class="fa-regular fa-note-sticky"></i> Notes
                        </button>

                        <?php if ($canCancel): ?>
                          <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this request?');">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                            <button class="btn red" type="submit"><i class="fa-solid fa-ban"></i> Cancel</button>
                          </form>
                        <?php else: ?>
                          <span style="color:#98a2b3;font-weight:1000;font-size:11px;">Locked</span>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
