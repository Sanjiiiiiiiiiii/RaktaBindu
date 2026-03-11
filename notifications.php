<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}
$uid = (int)$_SESSION['user_id'];

$success = "";
$error = "";

// mark all read
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "mark_all") {
  $u = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0");
  if ($u) {
    $u->bind_param("i", $uid);
    $u->execute();
    $u->close();
    $success = "All notifications marked as read.";
  } else {
    $error = "DB Error: " . $conn->error;
  }
}

// fetch notifications
$items = [];
$q = $conn->prepare("
  SELECT id, type, title, message, link, is_read, created_at
  FROM notifications
  WHERE user_id=?
  ORDER BY created_at DESC, id DESC
  LIMIT 200
");
$q->bind_param("i", $uid);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) $items[] = $row;
$q->close();

function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications | RaktaBindu</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <style>
    :root{--red:#c62828;--bg:#f6f7fb;--card:#fff;--text:#1f2430;--muted:#667085;--line:rgba(0,0,0,.08)}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Segoe UI",system-ui,Arial;background:var(--bg);color:var(--text)}
    .wrap{width:min(900px,92%);margin:24px auto}
    .top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .top h1{font-size:20px;font-weight:1000}
    .top p{margin-top:6px;color:var(--muted);font-size:12px}
    .btn{height:38px;padding:0 12px;border-radius:12px;border:1px solid rgba(0,0,0,.12);background:#fff;cursor:pointer;font-weight:900;font-size:12px;display:inline-flex;gap:8px;align-items:center}
    .btn.red{background:rgba(198,40,40,.10);border-color:rgba(198,40,40,.2);color:var(--red)}
    .card{margin-top:16px;background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden}
    .msg{margin-top:12px;padding:12px 14px;border-radius:14px;border:1px solid;font-weight:900;font-size:13px;display:flex;gap:10px}
    .ok{background:#e8f5e9;border-color:#c8e6c9;color:#2e7d32}
    .bad{background:#ffebee;border-color:#ffcdd2;color:#b71c1c}
    .item{padding:14px 16px;border-bottom:1px solid rgba(0,0,0,.06);display:flex;gap:12px;align-items:flex-start}
    .item:last-child{border-bottom:none}
    .dot{width:10px;height:10px;border-radius:50%;background:rgba(198,40,40,.25);margin-top:6px}
    .unread .dot{background:var(--red)}
    .title{font-weight:1000}
    .meta{font-size:11px;color:var(--muted);margin-top:4px}
    .text{font-size:12px;color:#344054;line-height:1.6;margin-top:6px}
    .link{display:inline-flex;gap:8px;align-items:center;color:var(--red);font-weight:900;font-size:12px;margin-top:8px;text-decoration:none}
    .empty{padding:16px;color:var(--muted);font-weight:900}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div>
        <h1><i class="fa-regular fa-bell"></i> Notifications</h1>
        <p>Your latest updates (donor accepts, request updates, etc.)</p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn" href="index.php"><i class="fa-solid fa-house"></i> Home</a>
        <form method="POST">
          <input type="hidden" name="action" value="mark_all">
          <button class="btn red" type="submit"><i class="fa-solid fa-check-double"></i> Mark all read</button>
        </form>
      </div>
    </div>

    <?php if ($success): ?><div class="msg ok"><i class="fa-solid fa-circle-check"></i> <?php echo esc($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg bad"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo esc($error); ?></div><?php endif; ?>

    <div class="card">
      <?php if (!$items): ?>
        <div class="empty">No notifications yet.</div>
      <?php else: ?>
        <?php foreach($items as $n): ?>
          <div class="item <?php echo ((int)$n['is_read']===0) ? 'unread' : ''; ?>">
            <div class="dot"></div>
            <div style="flex:1">
              <div class="title"><?php echo esc($n['title']); ?></div>
              <div class="meta"><?php echo esc($n['created_at']); ?> • <?php echo esc($n['type']); ?></div>
              <div class="text"><?php echo esc($n['message']); ?></div>
              <?php if (!empty($n['link'])): ?>
                <a class="link" href="<?php echo esc($n['link']); ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>