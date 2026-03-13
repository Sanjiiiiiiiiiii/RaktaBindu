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

$uid = (int)($_SESSION['user_id'] ?? 0);
$request_id = (int)($_GET['request_id'] ?? 0);
$other_id = (int)($_GET['user_id'] ?? 0);

if ($request_id < 1 || $other_id < 1) {
  die("Invalid chat.");
}

$verify = $conn->prepare("
  SELECT rm.id
  FROM request_matches rm
  INNER JOIN blood_requests br ON br.id = rm.request_id
  WHERE rm.request_id = ?
    AND rm.status = 'Accepted'
    AND (rm.donor_id = ? OR br.user_id = ?)
  LIMIT 1
");
$verify->bind_param("iii", $request_id, $uid, $uid);
$verify->execute();
$allowed = $verify->get_result()->fetch_assoc();
$verify->close();

if (!$allowed) {
  die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $message = trim($_POST['message'] ?? '');

  if ($message !== "") {
    $ins = $conn->prepare("
      INSERT INTO chat_messages (request_id, sender_id, receiver_id, message)
      VALUES (?, ?, ?, ?)
    ");
    $ins->bind_param("iiis", $request_id, $uid, $other_id, $message);
    $ins->execute();
    $ins->close();

    header("Location: chat.php?request_id={$request_id}&user_id={$other_id}");
    exit();
  }
}

$otherName = "User";
$u = $conn->prepare("
  SELECT CONCAT(TRIM(COALESCE(firstName,'')), ' ', TRIM(COALESCE(lastName,''))) AS full_name
  FROM users
  WHERE id = ?
  LIMIT 1
");
$u->bind_param("i", $other_id);
$u->execute();
$row = $u->get_result()->fetch_assoc();
if ($row && trim($row['full_name']) !== '') {
  $otherName = trim($row['full_name']);
}
$u->close();

$messages = [];
$msg = $conn->prepare("
  SELECT sender_id, receiver_id, message, created_at
  FROM chat_messages
  WHERE request_id = ?
    AND (
      (sender_id = ? AND receiver_id = ?)
      OR
      (sender_id = ? AND receiver_id = ?)
    )
  ORDER BY id ASC
");
$msg->bind_param("iiiii", $request_id, $uid, $other_id, $other_id, $uid);
$msg->execute();
$res = $msg->get_result();
while ($m = $res->fetch_assoc()) {
  $messages[] = $m;
}
$msg->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat | RaktaBindu</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <style>
    body{font-family:"Segoe UI",Arial,sans-serif;background:#f8fafc;margin:0;color:#101828}
    .wrap{width:min(900px,92%);margin:24px auto}
    .back{display:inline-flex;align-items:center;gap:8px;margin-bottom:12px;color:#c62828;text-decoration:none;font-weight:800}
    .card{background:#fff;border:1px solid #eaecf0;border-radius:18px;box-shadow:0 10px 25px rgba(16,24,40,.06);overflow:hidden}
    .head{padding:18px 20px;border-bottom:1px solid #eaecf0;font-weight:900;font-size:18px}
    .msgs{min-height:350px;max-height:500px;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;background:#f9fafb}
    .bubble{max-width:72%;padding:12px 14px;border-radius:16px;line-height:1.6;font-size:14px}
    .mine{align-self:flex-end;background:#c62828;color:#fff}
    .theirs{align-self:flex-start;background:#fff;color:#101828;border:1px solid #eaecf0}
    .time{font-size:11px;opacity:.75;margin-top:4px}
    .empty{color:#667085;font-size:14px}
    .form{display:flex;gap:10px;padding:16px;border-top:1px solid #eaecf0;background:#fff}
    .form input{flex:1;height:46px;border:1px solid #d0d5dd;border-radius:12px;padding:0 14px;font-size:14px;outline:none}
    .form button{height:46px;border:none;border-radius:12px;background:#c62828;color:#fff;font-weight:800;padding:0 18px;cursor:pointer}
  </style>
</head>
<body>
  <div class="wrap">
    <a class="back" href="javascript:history.back()"><i class="fa-solid fa-arrow-left"></i> Back</a>

    <div class="card">
      <div class="head">
        <i class="fa-regular fa-comment-dots"></i> Chat with <?php echo htmlspecialchars($otherName); ?>
      </div>

      <div class="msgs">
        <?php if (!$messages): ?>
          <div class="empty">No messages yet. Start the conversation.</div>
        <?php else: ?>
          <?php foreach ($messages as $m): ?>
            <div class="bubble <?php echo ((int)$m['sender_id'] === $uid) ? 'mine' : 'theirs'; ?>">
              <div><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
              <div class="time"><?php echo htmlspecialchars($m['created_at']); ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <form method="POST" class="form">
        <input type="text" name="message" placeholder="Type your message..." required>
        <button type="submit"><i class="fa-solid fa-paper-plane"></i> Send</button>
      </form>
    </div>
  </div>
</body>
</html>