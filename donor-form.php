<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");
require_once __DIR__ . "/db.php"; // expects $conn (mysqli)

// ==============================
// ✅ AUTH GUARD (donor page)
// ==============================
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}
$uid = (int)$_SESSION['user_id'];

$success = "";
$error   = "";

// ==============================
// ✅ CSRF (simple + solid)
// ==============================
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];
function csrf_ok(): bool {
  return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Helpers
function clean($v): string { return trim((string)$v); }

// ==============================
// ✅ Prefill user info (if you store these in session)
// ==============================
$prefillName  = $_SESSION['user_name']  ?? "";
$prefillEmail = $_SESSION['user_email'] ?? "";

// ==============================
// ✅ Fetch donor blood group from users table
// ==============================
$donorBloodGroup = "";
$u = $conn->prepare("SELECT bloodType FROM users WHERE id=? LIMIT 1");
if ($u) {
  $u->bind_param("i", $uid);
  $u->execute();
  $ur = $u->get_result()->fetch_assoc();
  $donorBloodGroup = clean($ur['bloodType'] ?? "");
  $u->close();
}

// ==============================
// ✅ Fetch hospitals for dropdown
// ==============================
$hospitals = [];
$h = $conn->prepare("SELECT id, name, city FROM hospitals WHERE is_active = 1 ORDER BY city, name");
if ($h && $h->execute()) {
  $hrs = $h->get_result();
  while ($row = $hrs->fetch_assoc()) $hospitals[] = $row;
  $h->close();
}

// ==============================
// ✅ Handle POST actions
// - accept_request (donor accepts an Open request)
// - schedule_donation (your existing donations insert)
// ==============================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if (!csrf_ok()) {
    $error = "Security check failed. Please refresh and try again.";
  } else {

    $action = clean($_POST['action'] ?? '');
// --------------------------------
// ✅ 1) Accept Request (PRO VERSION WITH NOTIFICATIONS)
// --------------------------------
if ($action === 'accept_request') {
  $request_id = (int)($_POST['request_id'] ?? 0);

  if ($request_id < 1) {
    $error = "Invalid request.";
  } elseif ($donorBloodGroup === "") {
    $error = "Your blood group is missing. Please update your profile.";
  } else {

    $conn->begin_transaction();

    try {

      // 1️⃣ Get request details first (for notification)
      $getReq = $conn->prepare("
        SELECT id, user_id, blood_group, hospital_location
        FROM blood_requests
        WHERE id=? AND status='Open' AND blood_group=?
        LIMIT 1
      ");
      if (!$getReq) throw new Exception($conn->error);

      $getReq->bind_param("is", $request_id, $donorBloodGroup);
      $getReq->execute();
      $reqData = $getReq->get_result()->fetch_assoc();
      $getReq->close();

      if (!$reqData) {
        throw new Exception("Request not available or mismatch.");
      }

      $requesterId = (int)$reqData['user_id'];
      $bloodGroup  = $reqData['blood_group'];
      $location    = $reqData['hospital_location'];

      // 2️⃣ Update request status
      $up = $conn->prepare("
        UPDATE blood_requests
        SET status='Accepted'
        WHERE id=? AND status='Open'
      ");
      if (!$up) throw new Exception($conn->error);

      $up->bind_param("i", $request_id);
      $up->execute();

      if ($up->affected_rows !== 1) {
        throw new Exception("Request already accepted.");
      }
      $up->close();

      // 3️⃣ Insert into request_matches
      $ins = $conn->prepare("
        INSERT INTO request_matches
        (request_id, donor_id, status, accepted_at)
        VALUES (?, ?, 'Accepted', NOW())
      ");
      if (!$ins) throw new Exception($conn->error);

      $ins->bind_param("ii", $request_id, $uid);
      $ins->execute();
      $ins->close();

      // 4️⃣ Notify REQUESTER
      $notifyRequester = $conn->prepare("
        INSERT INTO notifications
        (user_id, type, title, message, link, is_read, created_at)
        VALUES (?, 'request',
                'Donor Accepted Your Request',
                ?, ?, 0, NOW())
      ");
      if (!$notifyRequester) throw new Exception($conn->error);

      $msgRequester = "A donor has accepted your {$bloodGroup} blood request at {$location}.";
      $linkRequester = "my-requests.php";

      $notifyRequester->bind_param("iss", $requesterId, $msgRequester, $linkRequester);
      $notifyRequester->execute();
      $notifyRequester->close();

      // 5️⃣ Notify DONOR
      $notifyDonor = $conn->prepare("
        INSERT INTO notifications
        (user_id, type, title, message, link, is_read, created_at)
        VALUES (?, 'donation',
                'You Accepted a Blood Request',
                ?, ?, 0, NOW())
      ");
      if (!$notifyDonor) throw new Exception($conn->error);

      $msgDonor = "You accepted a {$bloodGroup} blood request at {$location}. Please contact hospital.";
      $linkDonor = "donor-form.php";

      $notifyDonor->bind_param("iss", $uid, $msgDonor, $linkDonor);
      $notifyDonor->execute();
      $notifyDonor->close();

      $conn->commit();
      $success = "Request accepted successfully!";

    } catch (Throwable $e) {
      $conn->rollback();
      $error = "Accept failed: " . $e->getMessage();
    }
  }
}
    // --------------------------------
    // ✅ 2) Schedule Donation (your form)
    // --------------------------------
    if ($action === 'schedule_donation') {

      $full_name     = clean($_POST["full_name"] ?? "");
      $blood_group   = clean($_POST["blood_group"] ?? "");
      $contact       = clean($_POST["contact"] ?? "");
      $email         = strtolower(clean($_POST["email"] ?? ""));
      $hospital_id   = (int)($_POST["hospital_id"] ?? 0);
      $donation_date = clean($_POST["donation_date"] ?? "");
      $donation_time = clean($_POST["donation_time"] ?? "");
      $availability  = clean($_POST["availability"] ?? "Available");
      $checks        = $_POST["health_checks"] ?? [];

      // Basic validation
      if ($full_name === "" || $blood_group === "" || $contact === "" || $email === "" ||
          $hospital_id < 1 || $donation_date === "" || $donation_time === "") {
        $error = "Please fill all required fields.";
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
      } elseif (!in_array($availability, ["Available", "Emergency Only"], true)) {
        $error = "Invalid availability option.";
      } else {

        // Normalize checkboxes to JSON
        if (!is_array($checks)) $checks = [];
        $checks_json = json_encode(array_values($checks), JSON_UNESCAPED_UNICODE);

        // Insert
        $stmt = $conn->prepare("
          INSERT INTO donations
          (user_id, full_name, blood_group, contact, email, hospital_id, preferred_date, preferred_time, availability, health_confirmations)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
          $error = "DB Error: " . $conn->error;
        } else {
          // NOTE: user_id is required integer here (donor is logged in)
          $stmt->bind_param(
            "issssissss",
            $uid,
            $full_name,
            $blood_group,
            $contact,
            $email,
            $hospital_id,
            $donation_date,
            $donation_time,
            $availability,
            $checks_json
          );

          if ($stmt->execute()) {
            $success = "Donation schedule submitted successfully!";
            $_POST = []; // clear form after success
          } else {
            $error = "Failed to submit: " . $stmt->error;
          }
          $stmt->close();
        }
      }
    }
  }
}

// ==============================
// ✅ Fetch matching blood requests (Open + donor blood group)
// ==============================
$requests = [];
if ($donorBloodGroup !== "") {
  $rq = $conn->prepare("
    SELECT id, blood_group, quantity, hospital_location, urgency,
           needed_date, needed_time, patient_notes, status, created_at
    FROM blood_requests
    WHERE status='Open' AND blood_group=?
    ORDER BY FIELD(urgency,'Emergency','Normal'), created_at DESC
  ");
  if ($rq) {
    $rq->bind_param("s", $donorBloodGroup);
    if ($rq->execute()) {
      $rs = $rq->get_result();
      while ($row = $rs->fetch_assoc()) $requests[] = $row;
    }
    $rq->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Donor Dashboard | RaktaBindu</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

  <style>
    :root{
      --red:#c62828;
      --dark:#0b1220;
      --bg:#f6f7fb;
      --card:#ffffff;
      --text:#1f2430;
      --muted:#667085;
      --line:rgba(0,0,0,.08);
      --shadow:0 18px 50px rgba(0,0,0,.10);
      --shadow2:0 10px 28px rgba(0,0,0,.08);
      --radius:18px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Segoe UI",system-ui,Arial;background:var(--bg);color:var(--text);}

    header{
      position:sticky;top:0;z-index:50;background:#fff;
      border-bottom:1px solid var(--line);
      box-shadow:0 8px 30px rgba(0,0,0,.06);
    }
    .container{width:min(1180px, 92%);margin:0 auto;}
    .topbar{display:flex;align-items:center;justify-content:space-between;padding:14px 0;gap:14px;}
    .brand{display:flex;align-items:center;gap:10px;font-weight:900;letter-spacing:.2px;}
    .brand-badge{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:rgba(198,40,40,.10);color:var(--red);}
    .brand span b{color:var(--red)}
    nav ul{list-style:none;display:flex;gap:26px;align-items:center;flex-wrap:wrap;}
    nav a{text-decoration:none;color:#384152;font-weight:800;font-size:13px;letter-spacing:.3px;padding:10px 12px;border-radius:12px;transition:.2s ease;display:inline-flex;align-items:center;gap:8px;}
    nav a:hover{background:rgba(198,40,40,.08); color:var(--red)}
    nav a.active{color:var(--red); position:relative;}
    nav a.active::after{content:"";position:absolute;left:12px;right:12px;bottom:4px;height:2px;background:var(--red);border-radius:999px;}

    .userbox{display:flex;align-items:center;gap:10px;}
    .bell{width:38px;height:38px;border-radius:12px;border:1px solid var(--line);background:#fff;display:grid;place-items:center;color:#667085;cursor:pointer;}
    .profile{display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid var(--line);border-radius:14px;background:#fff;}
    .avatar{width:34px;height:34px;border-radius:12px;background:rgba(198,40,40,.10);color:var(--red);display:grid;place-items:center;font-weight:900;}
    .pname{font-weight:900; font-size:13px; line-height:1.1}
    .prole{font-size:11px; color:var(--muted); margin-top:2px}
    .logout{margin-left:10px;padding:10px 14px;border-radius:14px;background:var(--red);color:#fff;text-decoration:none;font-weight:900;display:inline-flex;align-items:center;gap:8px;}
    .logout:hover{background:#b71c1c}

    .banner{
      margin:22px auto 0;width:min(1180px, 92%);
      border-radius:22px;
      background: linear-gradient(135deg, #e53935 0%, #c62828 55%, #b71c1c 100%);
      color:#fff;box-shadow: var(--shadow);overflow:hidden;position:relative;
    }
    .banner::before,.banner::after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.12);}
    .banner::before{width:340px;height:340px;right:-110px;top:-130px;}
    .banner::after{width:220px;height:220px;right:120px;bottom:-120px;background:rgba(255,255,255,.08);}
    .banner-inner{padding:28px 28px;display:flex;align-items:flex-start;justify-content:space-between;gap:18px;position:relative;}
    .banner h1{font-size:28px;letter-spacing:-.2px;display:flex;align-items:center;gap:10px;}
    .banner p{margin-top:8px;opacity:.95;font-size:13.5px;line-height:1.7;max-width:60ch;}

    .main{
      width:min(1180px, 92%);margin:18px auto 40px;
      display:grid;grid-template-columns: 1.55fr .95fr;gap:18px;align-items:start;
    }
    .card{background:var(--card);border:1px solid var(--line);border-radius:22px;box-shadow: var(--shadow2);}
    .form-card{ padding:18px; }

    .card-title{display:flex;align-items:center;justify-content:space-between;padding:6px 4px 12px;}
    .card-title h2{font-size:14px;font-weight:1000;letter-spacing:.2px;}
    .card-title p{font-size:12px;color:var(--muted);margin-top:4px;}
    .title-left{display:flex;flex-direction:column;}
    .title-icon{width:34px;height:34px;border-radius:12px;display:grid;place-items:center;background:rgba(198,40,40,.10);color:var(--red);border:1px solid rgba(198,40,40,.18);}

    .section{border-top:1px solid rgba(0,0,0,.06);padding-top:14px;margin-top:12px;}
    .section-head{display:flex;align-items:center;gap:10px;font-weight:1000;font-size:12px;color:#2b3445;margin-bottom:12px;}
    .section-head i{ color:var(--red); }

    .grid2{display:grid;grid-template-columns: 1fr 1fr;gap:12px;}
    .field label{font-size:11px;font-weight:900;color:#475467;display:block;margin:0 0 8px 2px;}
    .control{ position:relative; }
    .control i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#98a2b3;font-size:14px;}
    input, select{
      width:100%;height:44px;border-radius:12px;border:1px solid rgba(0,0,0,.10);
      padding:0 12px 0 38px;outline:none;font-size:14px;background:#fff;color:#1f2430;
    }
    input:focus, select:focus{border-color: rgba(198,40,40,.35);box-shadow: 0 0 0 5px rgba(198,40,40,.10);}
    .hint{font-size:11px;color:var(--muted);margin:8px 0 0 2px;}

    .toggle-row{display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;}
    .pill{
      flex:1;min-width:170px;border:1px solid rgba(0,0,0,.10);background:#fff;color:#1f2430;
      padding:12px 14px;border-radius:14px;display:flex;align-items:center;gap:10px;
      font-weight:900;cursor:pointer;transition:.2s ease;justify-content:center;
    }
    .pill input{display:none;}
    .pill.active{border-color: rgba(198,40,40,.40);background: rgba(198,40,40,.06);color: var(--red);}

    .checks{display:flex;flex-direction:column;gap:10px;margin-top:10px;}
    .check{display:flex;gap:10px;align-items:flex-start;font-size:12px;color:#344054;}
    .check input{width:16px;height:16px;margin-top:2px;accent-color: var(--red);}

    .actions{display:flex;gap:12px;margin-top:16px;flex-wrap:wrap;}
    .btn{
      height:46px;border-radius:14px;border:none;cursor:pointer;font-weight:1000;padding:0 16px;
      display:inline-flex;align-items:center;justify-content:center;gap:10px;transition:.2s ease;
    }
    .btn-primary{background:var(--red);color:#fff;flex:1;box-shadow:0 16px 30px rgba(198,40,40,.22);}
    .btn-primary:hover{background:#b71c1c; transform:translateY(-1px);}
    .btn-ghost{background:#fff;border:1px solid rgba(0,0,0,.12);color:#344054;min-width:160px;}
    .btn-ghost:hover{transform:translateY(-1px); box-shadow:0 14px 30px rgba(0,0,0,.10);}

    .msg{padding:12px 14px;border-radius:14px;font-size:13px;font-weight:800;margin-bottom:12px;border:1px solid;}
    .msg.ok{background:#e8f5e9;border-color:#c8e6c9;color:#2e7d32;}
    .msg.bad{background:#ffebee;border-color:#ffcdd2;color:#b71c1c;}

    .side{display:flex;flex-direction:column;gap:14px;}
    .side-card{padding:16px;}
    .side-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
    .side-title h3{font-size:13px;font-weight:1000;display:flex;align-items:center;gap:10px;}
    .side-title i{ color:var(--red); }
    .badge-small{width:26px;height:26px;border-radius:10px;display:grid;place-items:center;background:rgba(198,40,40,.10);color:var(--red);border:1px solid rgba(198,40,40,.18);font-size:12px;}
    .side-list{display:flex;flex-direction:column;gap:10px;margin-top:6px;}
    .side-item{display:flex;gap:10px;align-items:flex-start;font-size:12px;color:#344054;line-height:1.6;}
    .dot{width:26px;height:26px;border-radius:10px;display:grid;place-items:center;background:rgba(198,40,40,.08);color:var(--red);flex:0 0 auto;border:1px solid rgba(198,40,40,.14);font-size:12px;}

    .danger{background: linear-gradient(145deg,#d12a2a 0%,#b81b1b 55%,#941010 100%);color:#fff;border:none;}
    .danger .side-item{color: rgba(255,255,255,.92);}
    .danger .dot{background: rgba(255,255,255,.16);border: 1px solid rgba(255,255,255,.18);color:#fff;}

    .help-card{text-align:center;padding:16px;}
    .help-badge{width:44px;height:44px;border-radius:16px;display:grid;place-items:center;margin:0 auto 10px;background: rgba(198,40,40,.10);color: var(--red);border: 1px solid rgba(198,40,40,.18);}
    .help-card p{font-size:12px;color:var(--muted);line-height:1.7;margin:0}

    footer{background:#111;color:#aaa;padding:60px 0 30px;margin-top: 28px;}
    .footer-content{width:min(1180px,92%);margin:0 auto;display:flex;justify-content:space-between;flex-wrap:wrap;gap:40px;}
    .footer-logo{display:flex;align-items:center;font-size:22px;font-weight:1000;color:#fff;margin-bottom:14px;gap:10px;}
    .footer-logo .drop{width:32px;height:40px;background:var(--red);border-radius:50% 50% 50% 50% / 60% 60% 40% 40%;}
    .footer-col h4{color:#fff;margin-bottom:16px;font-size:15px}
    .footer-col ul{list-style:none}
    .footer-col ul li{margin-bottom:10px;font-size:13px}
    .footer-col a{color:#aaa;text-decoration:none}
    .footer-col a:hover{color:#fff}
    .social-icons{display:flex;gap:12px;margin-top:14px}
    .social-icons a{width:36px;height:36px;border-radius:50%;background:#222;display:flex;align-items:center;justify-content:center;color:#aaa;text-decoration:none;transition:.2s;}
    .social-icons a:hover{background:var(--red); color:#fff; transform:translateY(-2px);}
    .footer-bottom{width:min(1180px,92%);margin:24px auto 0;padding-top:18px;border-top:1px solid #333;font-size:13px;text-align:center;}

    @media (max-width: 980px){
      .main{grid-template-columns: 1fr;}
      .banner-inner{flex-direction:column;}
      .topbar{flex-wrap:wrap;}
      nav ul{gap:12px;}
    }
  </style>
</head>

<body>

<header>
  <div class="container">
    <div class="topbar">
      <div class="brand">
        <span class="brand-badge"><i class="fa-solid fa-droplet"></i></span>
        <span>Rakta.<b>Bindu</b></span>
      </div>

      <nav>
        <ul>
          <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
          <li><a href="#"><i class="fa-solid fa-magnifying-glass"></i> Find Blood</a></li>
          <li><a class="active" href="donor-form.php"><i class="fa-solid fa-hand-holding-droplet"></i> Donor</a></li>
          <li><a href="#"><i class="fa-solid fa-flag"></i> Campaigns</a></li>
          <li><a href="#"><i class="fa-solid fa-users"></i> About</a></li>
        </ul>
      </nav>

      <div class="userbox">
        <button class="bell" title="Notifications"><i class="fa-regular fa-bell"></i></button>

        <div class="profile">
          <div class="avatar"><i class="fa-regular fa-user"></i></div>
          <div>
            <div class="pname"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
            <div class="prole">
              <i class="fa-regular fa-circle-check"></i>
              Verified Donor<?php if ($donorBloodGroup) echo " • " . htmlspecialchars($donorBloodGroup); ?>
            </div>
          </div>
        </div>

        <a class="logout" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>
  </div>
</header>

<section class="banner">
  <div class="banner-inner">
    <div>
      <h1><i class="fa-solid fa-heart-circle-plus"></i> Donor Dashboard</h1>
      <p>See matching blood requests and schedule your donation appointment.</p>
    </div>
  </div>
</section>

<section class="main">
  <div class="card form-card">
    <?php if ($success): ?>
      <div class="msg ok"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="msg bad"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- ========================= -->
    <!-- ✅ Requests list section -->
    <!-- ========================= -->
    <div class="section" style="border-top:none; padding-top:0; margin-top:0;">
      <div class="section-head"><i class="fa-solid fa-hand-holding-droplet"></i> Blood Requests Matching You</div>

      <?php if ($donorBloodGroup === ""): ?>
        <div class="msg bad">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Your blood group is missing in your profile. Add it in your profile to see requests.
        </div>
      <?php elseif (!$requests): ?>
        <div class="hint">
          No <b>Open</b> requests for <b><?php echo htmlspecialchars($donorBloodGroup); ?></b> right now.
        </div>
      <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:12px; margin-top:10px;">
          <?php foreach ($requests as $r): ?>
            <?php
              $neededDate = ($r['needed_date'] === '0000-00-00' || $r['needed_date'] === '') ? 'N/A' : $r['needed_date'];
              $neededTime = ($r['needed_time'] ?? '');
            ?>
            <div class="card" style="border-radius:18px; padding:14px; box-shadow:none;">
              <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                <div style="min-width:240px;">
                  <div style="font-weight:1000; font-size:14px;">
                    <?php echo htmlspecialchars($r['blood_group']); ?> • <?php echo (int)$r['quantity']; ?> unit(s)
                    • <span style="color:var(--red)"><?php echo htmlspecialchars($r['urgency']); ?></span>
                  </div>

                  <div class="hint" style="margin-top:6px;">
                    <i class="fa-solid fa-location-dot"></i>
                    <?php echo htmlspecialchars($r['hospital_location']); ?>
                    &nbsp; • &nbsp;
                    <i class="fa-regular fa-calendar"></i>
                    <?php echo htmlspecialchars($neededDate); ?>
                    <?php if ($neededTime): ?>
                      &nbsp; • &nbsp;<i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars($neededTime); ?>
                    <?php endif; ?>
                  </div>

                  <?php if (!empty($r['patient_notes'])): ?>
                    <div class="hint" style="margin-top:6px;">
                      <i class="fa-regular fa-note-sticky"></i>
                      <?php echo htmlspecialchars($r['patient_notes']); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <form method="POST" style="margin:0;">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                  <input type="hidden" name="action" value="accept_request">
                  <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-primary" type="submit" style="height:40px; border-radius:12px; padding:0 14px;">
                    <i class="fa-solid fa-check"></i> Accept
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ========================= -->
    <!-- ✅ Donation schedule form -->
    <!-- ========================= -->
    <div class="card-title" style="margin-top:18px;">
      <div class="title-left">
        <h2>Schedule Donation</h2>
        <p>Fill in the information below to schedule your appointment</p>
      </div>
      <div class="title-icon"><i class="fa-regular fa-calendar-check"></i></div>
    </div>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
      <input type="hidden" name="action" value="schedule_donation">

      <div class="section">
        <div class="section-head"><i class="fa-regular fa-user"></i> Personal Information</div>

        <div class="grid2">
          <div class="field">
            <label>Full Name</label>
            <div class="control">
              <i class="fa-regular fa-id-badge"></i>
              <input name="full_name" type="text"
                value="<?php echo htmlspecialchars($_POST['full_name'] ?? $prefillName); ?>"
                placeholder="Enter full name" required>
            </div>
          </div>

          <div class="field">
            <label>Blood Group</label>
            <div class="control">
              <i class="fa-solid fa-droplet"></i>
              <select name="blood_group" required>
                <option value="">Select blood group</option>
                <?php
                  $bg = $_POST['blood_group'] ?? $donorBloodGroup;
                  $groups = ["A+","A-","B+","B-","AB+","AB-","O+","O-"];
                  foreach($groups as $g){
                    $sel = ($bg === $g) ? "selected" : "";
                    echo "<option value='".htmlspecialchars($g)."' $sel>".htmlspecialchars($g)."</option>";
                  }
                ?>
              </select>
            </div>
          </div>

          <div class="field">
            <label>Contact Number</label>
            <div class="control">
              <i class="fa-solid fa-phone"></i>
              <input name="contact" type="text"
                value="<?php echo htmlspecialchars($_POST['contact'] ?? ""); ?>"
                placeholder="+977 98XXXXXXXX" required>
            </div>
          </div>

          <div class="field">
            <label>Email Address</label>
            <div class="control">
              <i class="fa-regular fa-envelope"></i>
              <input name="email" type="email"
                value="<?php echo htmlspecialchars($_POST['email'] ?? $prefillEmail); ?>"
                placeholder="you@example.com" required>
            </div>
          </div>
        </div>
      </div>

      <div class="section">
        <div class="section-head"><i class="fa-regular fa-clock"></i> Donation Schedule</div>

        <div class="grid2">
          <div class="field">
            <label>Select Blood Center / Hospital</label>
            <div class="control">
              <i class="fa-solid fa-hospital"></i>
              <select name="hospital_id" required>
                <option value="">Select a location</option>
                <?php
                  $selectedHospital = (int)($_POST["hospital_id"] ?? 0);
                  foreach ($hospitals as $row) {
                    $id = (int)$row["id"];
                    $label = $row["city"] . " - " . $row["name"];
                    $sel = ($selectedHospital === $id) ? "selected" : "";
                    echo "<option value='{$id}' {$sel}>".htmlspecialchars($label)."</option>";
                  }
                ?>
              </select>
            </div>
            <div class="hint"><i class="fa-solid fa-location-dot"></i> Choose a nearby center.</div>
          </div>

          <div class="field">
            <label>Preferred Date</label>
            <div class="control">
              <i class="fa-regular fa-calendar"></i>
              <input name="donation_date" type="date"
                value="<?php echo htmlspecialchars($_POST['donation_date'] ?? ""); ?>" required>
            </div>
          </div>

          <div class="field">
            <label>Preferred Time</label>
            <div class="control">
              <i class="fa-regular fa-clock"></i>
              <input name="donation_time" type="time"
                value="<?php echo htmlspecialchars($_POST['donation_time'] ?? ""); ?>" required>
            </div>
          </div>

          <div class="field">
            <label>Availability Status</label>

            <div class="toggle-row" id="availabilityWrap">
              <?php $av = $_POST['availability'] ?? "Available"; ?>

              <label class="pill <?php echo ($av==="Available")?"active":""; ?>">
                <input type="radio" name="availability" value="Available" <?php echo ($av==="Available")?"checked":""; ?>>
                <i class="fa-solid fa-circle-check"></i> Available
              </label>

              <label class="pill <?php echo ($av==="Emergency Only")?"active":""; ?>">
                <input type="radio" name="availability" value="Emergency Only" <?php echo ($av==="Emergency Only")?"checked":""; ?>>
                <i class="fa-solid fa-bolt"></i> Emergency Only
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="section">
        <div class="section-head"><i class="fa-solid fa-shield-heart"></i> Health Confirmation</div>

        <div class="checks">
          <?php
            $checksSelected = $_POST["health_checks"] ?? [];
            $list = [
              "I am feeling healthy and well today with no signs of illness",
              "I have not donated blood in the last 3 months (90 days)",
              "I am between 18–65 years of age and weight at least 50 kg",
              "I have not taken any medication or antibiotics in the last 48 hours",
              "I have had adequate sleep and have eaten a proper meal today"
            ];
            foreach($list as $i=>$text){
              $checked = (is_array($checksSelected) && in_array((string)$i, $checksSelected, true)) ? "checked" : "";
              echo '<label class="check">
                      <input type="checkbox" name="health_checks[]" value="'.$i.'" '.$checked.'>
                      <span>'.htmlspecialchars($text).'</span>
                    </label>';
            }
          ?>
        </div>

        <div class="actions">
          <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-circle-check"></i> Confirm Donation
          </button>
          <button class="btn btn-ghost" type="reset">
            <i class="fa-solid fa-rotate-left"></i> Reset Form
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- Right side (same as yours) -->
  <aside class="side">
    <div class="card side-card">
      <div class="side-title">
        <h3><i class="fa-solid fa-circle-info"></i> Eligibility Guide</h3>
        <span class="badge-small"><i class="fa-solid fa-info"></i></span>
      </div>

      <div class="side-list">
        <div class="side-item">
          <div class="dot"><i class="fa-solid fa-user-check"></i></div>
          <div><b>Age Requirement</b><br>Must be between 18–65 years old</div>
        </div>
        <div class="side-item">
          <div class="dot"><i class="fa-solid fa-weight-scale"></i></div>
          <div><b>Weight Requirement</b><br>Minimum weight: 50 kg</div>
        </div>
        <div class="side-item">
          <div class="dot"><i class="fa-solid fa-clock-rotate-left"></i></div>
          <div><b>Donation Interval</b><br>Wait at least 3 months between donations</div>
        </div>
        <div class="side-item">
          <div class="dot"><i class="fa-solid fa-heart-pulse"></i></div>
          <div><b>Health Status</b><br>Must be in good general health</div>
        </div>
      </div>
    </div>

    <div class="card side-card danger">
      <div class="side-title">
        <h3 style="color:#fff;"><i class="fa-solid fa-circle-exclamation" style="color:#fff;"></i> Before You Donate</h3>
        <span class="badge-small" style="background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.18);color:#fff;">
          <i class="fa-solid fa-bell"></i>
        </span>
      </div>

      <div class="side-list">
        <div class="side-item"><div class="dot"><i class="fa-solid fa-utensils"></i></div><div>Drink plenty of water before donation</div></div>
        <div class="side-item"><div class="dot"><i class="fa-solid fa-ban-smoking"></i></div><div>Avoid alcohol 24 hours before</div></div>
        <div class="side-item"><div class="dot"><i class="fa-solid fa-bed"></i></div><div>Get a good night’s sleep (7–8 hours)</div></div>
        <div class="side-item"><div class="dot"><i class="fa-solid fa-id-card"></i></div><div>Bring a valid ID and donor card</div></div>
      </div>
    </div>

    <div class="card help-card">
      <div class="help-badge"><i class="fa-solid fa-headset"></i></div>
      <h3 style="font-size:13px;font-weight:1000;margin-bottom:6px;">Need Help?</h3>
      <p>Our support team is here to assist you.</p>
      <p style="margin-top:10px;font-size:12px;color:#344054;font-weight:900;">
        <i class="fa-solid fa-phone"></i> +977 980-XXX-XXXX &nbsp;&nbsp;
        <i class="fa-regular fa-envelope"></i> support@raktabindu.org
      </p>
    </div>
  </aside>
</section>

<footer>
  <div class="footer-content">
    <div class="footer-col">
      <div class="footer-logo">
        <div class="drop"></div>
        Rakta.<span style="color:var(--red)">Bindu</span>
      </div>
      <p style="max-width:260px;line-height:1.7;">
        Connecting donors and recipients<br>
        in real-time to save lives across<br>
        the nation.
      </p>
    </div>

    <div class="footer-col">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="#">About Us</a></li>
        <li><a href="#">How It Works</a></li>
        <li><a href="#">Find Donors</a></li>
        <li><a href="#">Hospitals</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Resources</h4>
      <ul>
        <li><a href="#">Eligibility Criteria</a></li>
        <li><a href="#">Donation Process</a></li>
        <li><a href="#">FAQs</a></li>
        <li><a href="#">Contact Support</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Connect With Us</h4>
      <div class="social-icons">
        <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
        <a href="#"><i class="fa-brands fa-x-twitter"></i></a>
        <a href="#"><i class="fa-brands fa-instagram"></i></a>
        <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    © 2025 RaktaBindu. All rights reserved.
  </div>
</footer>

<script>
  // ✅ Availability pills highlight
  const wrap = document.getElementById("availabilityWrap");
  if (wrap) {
    wrap.querySelectorAll('input[type="radio"][name="availability"]').forEach(r => {
      r.addEventListener("change", () => {
        wrap.querySelectorAll(".pill").forEach(p => p.classList.remove("active"));
        r.closest(".pill")?.classList.add("active");
      });
    });
  }
</script>

</body>
</html>