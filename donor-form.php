<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");
require_once __DIR__ . "/db.php";

// ==============================
// AUTH GUARD
// ==============================
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$uid = (int)($_SESSION['user_id'] ?? 0);
$success = "";
$error   = "";

// ==============================
// CSRF
// ==============================
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

function csrf_ok(): bool {
  return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function clean($v): string {
  return trim((string)$v);
}

// ==============================
// PREFILL SESSION DATA
// ==============================
$prefillName  = $_SESSION['user_name'] ?? "";
$prefillEmail = $_SESSION['user_email'] ?? "";

// ==============================
// FETCH DONOR INFO
// ==============================
$donorBloodGroup = "";
$userStmt = $conn->prepare("SELECT bloodType FROM users WHERE id = ? LIMIT 1");
if ($userStmt) {
  $userStmt->bind_param("i", $uid);
  $userStmt->execute();
  $userRow = $userStmt->get_result()->fetch_assoc();
  $donorBloodGroup = clean($userRow['bloodType'] ?? "");
  $userStmt->close();
}

// ==============================
// FETCH HOSPITALS
// ==============================
$hospitals = [];
$h = $conn->prepare("
  SELECT id, name, city
  FROM hospitals
  WHERE is_active = 1
  ORDER BY city, name
");
if ($h && $h->execute()) {
  $res = $h->get_result();
  while ($row = $res->fetch_assoc()) {
    $hospitals[] = $row;
  }
  $h->close();
}

// ==============================
// HANDLE POST ACTIONS
// ==============================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if (!csrf_ok()) {
    $error = "Security check failed. Please refresh and try again.";
  } else {

    $action = clean($_POST['action'] ?? '');

    // ==========================================
    // ACCEPT REQUEST
    // ==========================================
    if ($action === 'accept_request') {
      $request_id = (int)($_POST['request_id'] ?? 0);

      if ($request_id < 1) {
        $error = "Invalid request.";
      } elseif ($donorBloodGroup === "") {
        $error = "Your blood group is missing. Please update your profile first.";
      } else {
        $conn->begin_transaction();

        try {
          $getReq = $conn->prepare("
            SELECT id, user_id, blood_group, hospital_location
            FROM blood_requests
            WHERE id = ? AND status = 'Open' AND blood_group = ?
            LIMIT 1
          ");
          if (!$getReq) {
            throw new Exception($conn->error);
          }

          $getReq->bind_param("is", $request_id, $donorBloodGroup);
          $getReq->execute();
          $reqData = $getReq->get_result()->fetch_assoc();
          $getReq->close();

          if (!$reqData) {
            throw new Exception("Request not available or does not match your blood group.");
          }

          $checkDup = $conn->prepare("
            SELECT id
            FROM request_matches
            WHERE request_id = ? AND donor_id = ?
            LIMIT 1
          ");
          if (!$checkDup) {
            throw new Exception($conn->error);
          }

          $checkDup->bind_param("ii", $request_id, $uid);
          $checkDup->execute();
          $dup = $checkDup->get_result()->fetch_assoc();
          $checkDup->close();

          if ($dup) {
            throw new Exception("You already reviewed this request.");
          }

          $requesterId = (int)($reqData['user_id'] ?? 0);
          $bloodGroup  = clean($reqData['blood_group'] ?? '');
          $location    = clean($reqData['hospital_location'] ?? '');

          $up = $conn->prepare("
            UPDATE blood_requests
            SET status = 'Accepted'
            WHERE id = ? AND status = 'Open'
          ");
          if (!$up) {
            throw new Exception($conn->error);
          }

          $up->bind_param("i", $request_id);
          $up->execute();

          if ($up->affected_rows !== 1) {
            $up->close();
            throw new Exception("Request already accepted or unavailable.");
          }
          $up->close();

          $ins = $conn->prepare("
            INSERT INTO request_matches
            (request_id, donor_id, status, accepted_at, donor_hidden)
            VALUES (?, ?, 'Accepted', NOW(), 0)
          ");
          if (!$ins) {
            throw new Exception($conn->error);
          }

          $ins->bind_param("ii", $request_id, $uid);
          $ins->execute();
          $ins->close();

          if ($requesterId > 0) {
            $notifyRequester = $conn->prepare("
              INSERT INTO notifications
              (user_id, type, title, message, link, is_read, created_at)
              VALUES (?, 'request', 'Donor Accepted Your Request', ?, ?, 0, NOW())
            ");
            if ($notifyRequester) {
              $msgRequester  = "A donor has accepted your {$bloodGroup} blood request at {$location}.";
              $linkRequester = "my-requests.php";
              $notifyRequester->bind_param("iss", $requesterId, $msgRequester, $linkRequester);
              $notifyRequester->execute();
              $notifyRequester->close();
            }
          }

          $notifyDonor = $conn->prepare("
            INSERT INTO notifications
            (user_id, type, title, message, link, is_read, created_at)
            VALUES (?, 'donation', 'You Accepted a Blood Request', ?, ?, 0, NOW())
          ");
          if ($notifyDonor) {
            $msgDonor  = "You accepted a {$bloodGroup} blood request at {$location}. Please proceed with donation coordination.";
            $linkDonor = "donor-form.php";
            $notifyDonor->bind_param("iss", $uid, $msgDonor, $linkDonor);
            $notifyDonor->execute();
            $notifyDonor->close();
          }

          $conn->commit();
          $success = "Request accepted successfully.";

        } catch (Throwable $e) {
          $conn->rollback();
          $error = "Accept failed: " . $e->getMessage();
        }
      }
    }

    // ==========================================
    // DECLINE REQUEST
    // ==========================================
    elseif ($action === 'decline_request') {
      $request_id = (int)($_POST['request_id'] ?? 0);

      if ($request_id < 1) {
        $error = "Invalid request.";
      } elseif ($donorBloodGroup === "") {
        $error = "Your blood group is missing. Please update your profile first.";
      } else {
        $conn->begin_transaction();

        try {
          $getReq = $conn->prepare("
            SELECT id, user_id, blood_group, hospital_location
            FROM blood_requests
            WHERE id = ? AND status = 'Open' AND blood_group = ?
            LIMIT 1
          ");
          if (!$getReq) {
            throw new Exception($conn->error);
          }

          $getReq->bind_param("is", $request_id, $donorBloodGroup);
          $getReq->execute();
          $reqData = $getReq->get_result()->fetch_assoc();
          $getReq->close();

          if (!$reqData) {
            throw new Exception("Request not available or does not match your blood group.");
          }

          $checkDup = $conn->prepare("
            SELECT id
            FROM request_matches
            WHERE request_id = ? AND donor_id = ?
            LIMIT 1
          ");
          if (!$checkDup) {
            throw new Exception($conn->error);
          }

          $checkDup->bind_param("ii", $request_id, $uid);
          $checkDup->execute();
          $dup = $checkDup->get_result()->fetch_assoc();
          $checkDup->close();

          if ($dup) {
            throw new Exception("You already reviewed this request.");
          }

          $bloodGroup = clean($reqData['blood_group'] ?? '');
          $location   = clean($reqData['hospital_location'] ?? '');

          $ins = $conn->prepare("
            INSERT INTO request_matches
            (request_id, donor_id, status, accepted_at, donor_hidden)
            VALUES (?, ?, 'Declined', NOW(), 0)
          ");
          if (!$ins) {
            throw new Exception($conn->error);
          }

          $ins->bind_param("ii", $request_id, $uid);
          $ins->execute();
          $ins->close();

          $notifyDonor = $conn->prepare("
            INSERT INTO notifications
            (user_id, type, title, message, link, is_read, created_at)
            VALUES (?, 'donation', 'Request Declined', ?, ?, 0, NOW())
          ");
          if ($notifyDonor) {
            $msgDonor  = "You declined a {$bloodGroup} blood request at {$location}.";
            $linkDonor = "donor-form.php";
            $notifyDonor->bind_param("iss", $uid, $msgDonor, $linkDonor);
            $notifyDonor->execute();
            $notifyDonor->close();
          }

          $conn->commit();
          $success = "Request declined successfully.";

        } catch (Throwable $e) {
          $conn->rollback();
          $error = "Decline failed: " . $e->getMessage();
        }
      }
    }

    // ==========================================
    // HIDE ACTIVITY FROM DASHBOARD ONLY
    // ==========================================
    elseif ($action === 'hide_review') {
      $match_id = (int)($_POST['match_id'] ?? 0);

      if ($match_id < 1) {
        $error = "Invalid activity item.";
      } else {
        $stmt = $conn->prepare("
          UPDATE request_matches
          SET donor_hidden = 1
          WHERE id = ? AND donor_id = ?
          LIMIT 1
        ");

        if (!$stmt) {
          $error = "DB Error: " . $conn->error;
        } else {
          $stmt->bind_param("ii", $match_id, $uid);

          if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = "Activity removed from dashboard.";
          } else {
            $error = "Unable to remove activity.";
          }
          $stmt->close();
        }
      }
    }

    // ==========================================
    // SCHEDULE DONATION
    // ==========================================
    elseif ($action === 'schedule_donation') {
      $full_name     = clean($_POST["full_name"] ?? "");
      $blood_group   = clean($_POST["blood_group"] ?? "");
      $contact       = clean($_POST["contact"] ?? "");
      $email         = strtolower(clean($_POST["email"] ?? ""));
      $hospital_id   = (int)($_POST["hospital_id"] ?? 0);
      $donation_date = clean($_POST["donation_date"] ?? "");
      $donation_time = clean($_POST["donation_time"] ?? "");
      $availability  = clean($_POST["availability"] ?? "Available");
      $checks        = $_POST["health_checks"] ?? [];

      if (
        $full_name === "" ||
        $blood_group === "" ||
        $contact === "" ||
        $email === "" ||
        $hospital_id < 1 ||
        $donation_date === "" ||
        $donation_time === ""
      ) {
        $error = "Please fill all required fields.";
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
      } elseif (!in_array($availability, ["Available", "Emergency Only"], true)) {
        $error = "Invalid availability option.";
      } else {
        if (!is_array($checks)) {
          $checks = [];
        }

        $checks_json = json_encode(array_values($checks), JSON_UNESCAPED_UNICODE);

        $stmt = $conn->prepare("
          INSERT INTO donations
          (user_id, full_name, blood_group, contact, email, hospital_id, preferred_date, preferred_time, availability, health_confirmations)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
          $error = "DB Error: " . $conn->error;
        } else {
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
            $success = "Donation schedule submitted successfully.";
            $_POST = [];
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
// FETCH OPEN NEW REQUESTS ONLY
// ==============================
$requests = [];
if ($donorBloodGroup !== "") {
  $rq = $conn->prepare("
    SELECT br.id, br.blood_group, br.quantity, br.hospital_location, br.urgency,
           br.needed_date, br.needed_time, br.patient_notes, br.status, br.created_at
    FROM blood_requests br
    LEFT JOIN request_matches rm
      ON br.id = rm.request_id AND rm.donor_id = ?
    WHERE br.status = 'Open'
      AND br.blood_group = ?
      AND rm.id IS NULL
    ORDER BY FIELD(br.urgency, 'Emergency', 'Normal'), br.created_at DESC
  ");
  if ($rq) {
    $rq->bind_param("is", $uid, $donorBloodGroup);
    if ($rq->execute()) {
      $rs = $rq->get_result();
      while ($row = $rs->fetch_assoc()) {
        $requests[] = $row;
      }
    }
    $rq->close();
  }
}

// ==============================
// FETCH RECENT VISIBLE ACTIVITY
// ==============================
$reviewedRequests = [];
$hist = $conn->prepare("
  SELECT br.id, br.blood_group, br.quantity, br.hospital_location, br.urgency,
         br.needed_date, br.needed_time, br.patient_notes,
         rm.status AS donor_response, rm.accepted_at, rm.id AS match_id
  FROM request_matches rm
  INNER JOIN blood_requests br ON br.id = rm.request_id
  WHERE rm.donor_id = ?
    AND rm.donor_hidden = 0
  ORDER BY rm.accepted_at DESC, rm.id DESC
  LIMIT 6
");
if ($hist) {
  $hist->bind_param("i", $uid);
  if ($hist->execute()) {
    $res = $hist->get_result();
    while ($row = $res->fetch_assoc()) {
      $reviewedRequests[] = $row;
    }
  }
  $hist->close();
}

// ==============================
// FETCH ACCEPTED REQUESTER CONTACTS
// ==============================
$acceptedContacts = [];
$ac = $conn->prepare("
  SELECT
    br.id AS blood_request_id,
    br.blood_group,
    br.hospital_location,
    br.needed_date,
    br.needed_time,
    rm.accepted_at,
    u.id AS requester_id,
    CONCAT(TRIM(COALESCE(u.firstName,'')), ' ', TRIM(COALESCE(u.lastName,''))) AS requester_name,
    u.email AS requester_email,
    u.phone AS requester_phone
  FROM request_matches rm
  INNER JOIN blood_requests br ON br.id = rm.request_id
  INNER JOIN users u ON u.id = br.user_id
  WHERE rm.donor_id = ?
    AND rm.status = 'Accepted'
  ORDER BY rm.accepted_at DESC, rm.id DESC
");
if ($ac) {
  $ac->bind_param("i", $uid);
  if ($ac->execute()) {
    $res = $ac->get_result();
    while ($row = $res->fetch_assoc()) {
      $acceptedContacts[] = $row;
    }
  }
  $ac->close();
}

// ==============================
// COUNTS
// ==============================
$totalRequests  = count($requests);
$emergencyCount = 0;
$acceptedCount  = 0;
$declinedCount  = 0;

foreach ($requests as $req) {
  if (($req['urgency'] ?? '') === 'Emergency') {
    $emergencyCount++;
  }
}

$countStmt = $conn->prepare("
  SELECT
    SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) AS accepted_count,
    SUM(CASE WHEN status = 'Declined' THEN 1 ELSE 0 END) AS declined_count
  FROM request_matches
  WHERE donor_id = ?
");
if ($countStmt) {
  $countStmt->bind_param("i", $uid);
  $countStmt->execute();
  $countRow = $countStmt->get_result()->fetch_assoc();
  $acceptedCount = (int)($countRow['accepted_count'] ?? 0);
  $declinedCount = (int)($countRow['declined_count'] ?? 0);
  $countStmt->close();
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
      --primary:#c62828;
      --primary-dark:#a61b1b;
      --primary-soft:#fff1f1;
      --green:#157347;
      --green-soft:#ecfdf3;
      --yellow:#b54708;
      --yellow-soft:#fff7e6;
      --red:#b42318;
      --red-soft:#fef3f2;
      --text:#101828;
      --muted:#667085;
      --border:#eaecf0;
      --bg:#f8fafc;
      --card:#ffffff;
      --shadow:0 10px 25px rgba(16,24,40,.06);
      --radius:20px;
    }

    *{box-sizing:border-box;margin:0;padding:0}
    body{
      font-family:"Segoe UI",system-ui,Arial,sans-serif;
      background:var(--bg);
      color:var(--text);
    }

    .hero{
      width:min(1200px,92%);
      margin:24px auto 18px;
      border-radius:28px;
      background:linear-gradient(135deg,#d32f2f 0%,#c62828 55%,#a61b1b 100%);
      color:#fff;
      position:relative;
      overflow:hidden;
      box-shadow:0 20px 40px rgba(198,40,40,.18);
    }

    .hero::before{
      content:"";
      position:absolute;
      width:260px;
      height:260px;
      border-radius:50%;
      background:rgba(255,255,255,.10);
      top:-80px;
      right:-80px;
    }

    .hero::after{
      content:"";
      position:absolute;
      width:180px;
      height:180px;
      border-radius:50%;
      background:rgba(255,255,255,.07);
      bottom:-70px;
      right:140px;
    }

    .hero-inner{
      position:relative;
      padding:30px;
      display:flex;
      justify-content:space-between;
      gap:20px;
      align-items:flex-start;
      flex-wrap:wrap;
    }

    .hero h1{
      font-size:30px;
      font-weight:1000;
      margin-bottom:10px;
      display:flex;
      align-items:center;
      gap:12px;
    }

    .hero p{
      max-width:60ch;
      line-height:1.75;
      font-size:14px;
      opacity:.95;
    }

    .hero-chip{
      padding:10px 14px;
      border-radius:999px;
      background:rgba(255,255,255,.16);
      border:1px solid rgba(255,255,255,.18);
      font-size:13px;
      font-weight:900;
      white-space:nowrap;
    }

    .dashboard{
      width:min(1200px,92%);
      margin:0 auto 40px;
      display:grid;
      grid-template-columns:1.45fr .9fr;
      gap:20px;
      align-items:start;
    }

    .stack{
      display:flex;
      flex-direction:column;
      gap:18px;
    }

    .card{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
    }

    .card-body{padding:20px;}

    .alert{
      padding:14px 16px;
      border-radius:14px;
      font-size:13px;
      font-weight:800;
      display:flex;
      gap:10px;
      line-height:1.6;
      border:1px solid;
    }

    .alert.success{
      background:var(--green-soft);
      color:var(--green);
      border-color:#b7ebc6;
    }

    .alert.error{
      background:var(--red-soft);
      color:var(--red);
      border-color:#f7c1bc;
    }

    .summary-grid{
      display:grid;
      grid-template-columns:repeat(4,1fr);
      gap:14px;
    }

    .summary-box{
      border:1px solid var(--border);
      border-radius:18px;
      padding:18px;
      background:#fff;
    }

    .summary-top{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
    }

    .summary-label{
      font-size:12px;
      color:var(--muted);
      font-weight:800;
    }

    .summary-icon{
      width:36px;
      height:36px;
      border-radius:12px;
      display:grid;
      place-items:center;
      background:#f8fafc;
      color:var(--primary);
      border:1px solid var(--border);
    }

    .summary-value{
      margin-top:14px;
      font-size:30px;
      font-weight:1000;
      line-height:1;
      color:var(--text);
    }

    .section-title{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      margin-bottom:8px;
    }

    .section-title h2,
    .section-title h3{
      font-size:18px;
      font-weight:1000;
      display:flex;
      align-items:center;
      gap:10px;
    }

    .section-title i{color:var(--primary)}

    .section-sub{
      font-size:13px;
      color:var(--muted);
      line-height:1.6;
      margin-bottom:16px;
    }

    .form-grid{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:14px;
    }

    .field{
      display:flex;
      flex-direction:column;
      gap:8px;
    }

    .field.full{grid-column:1/-1;}

    .field label{
      font-size:12px;
      font-weight:900;
      color:#344054;
    }

    .control{
      position:relative;
    }

    .control i{
      position:absolute;
      left:14px;
      top:50%;
      transform:translateY(-50%);
      color:#98a2b3;
      font-size:14px;
    }

    input, select{
      width:100%;
      height:46px;
      border-radius:14px;
      border:1px solid #d0d5dd;
      background:#fff;
      padding:0 14px 0 42px;
      outline:none;
      font-size:14px;
      color:var(--text);
    }

    input:focus, select:focus{
      border-color:#f04438;
      box-shadow:0 0 0 4px rgba(240,68,56,.10);
    }

    .hint{
      font-size:12px;
      color:var(--muted);
      line-height:1.6;
    }

    .toggle-row{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
    }

    .pill{
      flex:1;
      min-width:180px;
      padding:13px 14px;
      border-radius:14px;
      border:1px solid #d0d5dd;
      background:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      font-weight:900;
      color:#344054;
      cursor:pointer;
      transition:.2s ease;
    }

    .pill input{display:none;}

    .pill.active{
      background:var(--primary-soft);
      color:var(--primary);
      border-color:#f3b2b2;
    }

    .checks{
      display:flex;
      flex-direction:column;
      gap:10px;
    }

    .check{
      display:flex;
      gap:10px;
      align-items:flex-start;
      padding:12px 14px;
      border-radius:14px;
      border:1px solid var(--border);
      background:#fff;
      font-size:13px;
      color:#344054;
      line-height:1.6;
    }

    .check input{
      width:16px;
      height:16px;
      margin-top:3px;
      accent-color:var(--primary);
    }

    .actions{
      display:flex;
      gap:12px;
      flex-wrap:wrap;
      margin-top:18px;
    }

    .btn{
      height:44px;
      border-radius:12px;
      border:none;
      cursor:pointer;
      font-weight:1000;
      padding:0 16px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      transition:.2s ease;
      font-size:13px;
      text-decoration:none;
    }

    .btn-primary{
      background:var(--primary);
      color:#fff;
    }

    .btn-primary:hover{background:var(--primary-dark)}

    .btn-light{
      background:#fff;
      border:1px solid var(--border);
      color:#344054;
    }

    .btn-light:hover{background:#f9fafb}

    .request-list,
    .activity-list{
      display:flex;
      flex-direction:column;
      gap:14px;
    }

    .request-card{
      border:1px solid var(--border);
      border-radius:18px;
      padding:18px;
      background:#fff;
    }

    .request-head{
      display:flex;
      justify-content:space-between;
      gap:18px;
      align-items:flex-start;
      flex-wrap:wrap;
    }

    .request-info{
      flex:1;
      min-width:250px;
    }

    .request-title{
      font-size:16px;
      font-weight:1000;
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      line-height:1.5;
    }

    .unit-pill{
      padding:6px 10px;
      border-radius:999px;
      background:#f2f4f7;
      color:#344054;
      font-size:12px;
      font-weight:900;
    }

    .urgency-pill{
      padding:6px 10px;
      border-radius:999px;
      font-size:12px;
      font-weight:900;
      display:inline-flex;
      align-items:center;
      gap:6px;
    }

    .urgency-emergency{
      background:var(--red-soft);
      color:var(--red);
    }

    .urgency-normal{
      background:var(--yellow-soft);
      color:var(--yellow);
    }

    .request-meta{
      display:grid;
      grid-template-columns:repeat(2,minmax(180px,1fr));
      gap:10px 16px;
      margin-top:12px;
      font-size:13px;
      color:var(--muted);
    }

    .request-meta div{
      display:flex;
      align-items:center;
      gap:8px;
    }

    .request-notes{
      margin-top:14px;
      padding:12px 14px;
      border-radius:14px;
      background:#f8fafc;
      border:1px solid var(--border);
      font-size:13px;
      color:#475467;
      line-height:1.6;
    }

    .request-actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:center;
      margin-top:14px;
    }

    .empty{
      border:1px dashed #d0d5dd;
      border-radius:16px;
      padding:20px;
      text-align:center;
      color:var(--muted);
      background:#fcfcfd;
      line-height:1.7;
      font-size:14px;
    }

    .activity-item{
      border:1px solid var(--border);
      border-radius:16px;
      padding:14px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      background:#fff;
    }

    .activity-left{
      display:flex;
      gap:12px;
      align-items:flex-start;
      flex:1;
      min-width:0;
    }

    .activity-icon{
      width:38px;
      height:38px;
      border-radius:12px;
      display:grid;
      place-items:center;
      flex:0 0 auto;
    }

    .activity-icon.accepted{
      background:var(--green-soft);
      color:var(--green);
    }

    .activity-icon.declined{
      background:var(--red-soft);
      color:var(--red);
    }

    .activity-title{
      font-size:14px;
      font-weight:1000;
      line-height:1.4;
    }

    .activity-meta{
      margin-top:4px;
      font-size:12px;
      color:var(--muted);
      line-height:1.6;
    }

    .status-pill{
      padding:6px 10px;
      border-radius:999px;
      font-size:11px;
      font-weight:1000;
      white-space:nowrap;
    }

    .status-accepted{
      background:var(--green-soft);
      color:var(--green);
    }

    .status-declined{
      background:var(--red-soft);
      color:var(--red);
    }

    .mini-remove{
      width:34px;
      height:34px;
      border-radius:10px;
      border:1px solid var(--border);
      background:#fff;
      color:#667085;
      display:grid;
      place-items:center;
      cursor:pointer;
    }

    .mini-remove:hover{
      color:var(--red);
      background:#f9fafb;
    }

    .side-block{padding:20px;}

    .side-list{
      display:flex;
      flex-direction:column;
      gap:12px;
    }

    .side-item{
      display:flex;
      gap:12px;
      align-items:flex-start;
      font-size:13px;
      line-height:1.6;
      color:#344054;
    }

    .side-icon{
      width:36px;
      height:36px;
      border-radius:12px;
      display:grid;
      place-items:center;
      background:var(--primary-soft);
      color:var(--primary);
      flex:0 0 auto;
    }

    .danger-card{
      background:linear-gradient(135deg,#d32f2f 0%,#b71c1c 100%);
      border:none;
      color:#fff;
    }

    .danger-card .section-title h3,
    .danger-card .section-title i,
    .danger-card .side-item{
      color:#fff;
    }

    .danger-card .side-icon{
      background:rgba(255,255,255,.15);
      color:#fff;
    }

    .help-card{
      text-align:center;
      padding:20px;
    }

    .help-badge{
      width:52px;
      height:52px;
      border-radius:18px;
      display:grid;
      place-items:center;
      margin:0 auto 12px;
      background:var(--primary-soft);
      color:var(--primary);
      font-size:20px;
    }

    .help-card p{
      font-size:13px;
      color:var(--muted);
      line-height:1.7;
      margin-bottom:10px;
    }

    footer{
      background:#111827;
      color:#d0d5dd;
      margin-top:20px;
      padding:50px 0 24px;
    }

    .footer-grid{
      width:min(1200px,92%);
      margin:0 auto;
      display:grid;
      grid-template-columns:1.1fr .8fr .8fr .8fr;
      gap:30px;
    }

    .footer-brand{
      font-size:24px;
      font-weight:1000;
      color:#fff;
      margin-bottom:12px;
    }

    .footer-brand span{color:#ef4444}

    .footer-text{
      max-width:280px;
      line-height:1.8;
      font-size:14px;
    }

    .footer-col h4{
      color:#fff;
      margin-bottom:14px;
      font-size:15px;
    }

    .footer-col ul{
      list-style:none;
      display:flex;
      flex-direction:column;
      gap:10px;
    }

    .footer-col a{
      color:#d0d5dd;
      text-decoration:none;
      font-size:14px;
    }

    .footer-col a:hover{color:#fff}

    .socials{
      display:flex;
      gap:10px;
      margin-top:10px;
    }

    .socials a{
      width:38px;
      height:38px;
      border-radius:50%;
      display:grid;
      place-items:center;
      background:#1f2937;
      color:#fff;
      text-decoration:none;
    }

    .socials a:hover{background:#ef4444}

    .footer-bottom{
      width:min(1200px,92%);
      margin:24px auto 0;
      padding-top:20px;
      border-top:1px solid rgba(255,255,255,.10);
      text-align:center;
      font-size:13px;
      color:#98a2b3;
    }

    @media (max-width: 1024px){
      .dashboard{grid-template-columns:1fr;}
      .footer-grid{grid-template-columns:1fr 1fr;}
    }

    @media (max-width: 760px){
      .summary-grid{grid-template-columns:1fr 1fr;}
      .form-grid{grid-template-columns:1fr;}
      .field.full{grid-column:auto;}
      .request-meta{grid-template-columns:1fr;}
      .footer-grid{grid-template-columns:1fr;}
    }

    @media (max-width: 560px){
      .summary-grid{grid-template-columns:1fr;}
      .request-head,
      .activity-item{
        flex-direction:column;
        align-items:flex-start;
      }
      .request-actions{
        width:100%;
      }
      .request-actions form{
        width:100%;
      }
      .request-actions .btn{
        width:100%;
      }
    }
  </style>
</head>
<body>

<?php include "navbar.php"; ?>

<section class="hero">
  <div class="hero-inner">
    <div>
      <h1><i class="fa-solid fa-heart-circle-plus"></i> Donor Dashboard</h1>
      <p>Manage your donation schedule, respond to new matching blood requests, and contact requesters after acceptance.</p>
    </div>
    <div class="hero-chip">
      <i class="fa-solid fa-droplet"></i>
      Blood Group: <?php echo $donorBloodGroup ? htmlspecialchars($donorBloodGroup) : 'Not Set'; ?>
    </div>
  </div>
</section>

<section class="dashboard">
  <div class="stack">

    <?php if ($success): ?>
      <div class="alert success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="summary-grid">
          <div class="summary-box">
            <div class="summary-top">
              <div class="summary-label">New Requests</div>
              <div class="summary-icon"><i class="fa-regular fa-envelope-open"></i></div>
            </div>
            <div class="summary-value"><?php echo (int)$totalRequests; ?></div>
          </div>

          <div class="summary-box">
            <div class="summary-top">
              <div class="summary-label">Emergency</div>
              <div class="summary-icon"><i class="fa-solid fa-bolt"></i></div>
            </div>
            <div class="summary-value"><?php echo (int)$emergencyCount; ?></div>
          </div>

          <div class="summary-box">
            <div class="summary-top">
              <div class="summary-label">Accepted</div>
              <div class="summary-icon"><i class="fa-solid fa-circle-check"></i></div>
            </div>
            <div class="summary-value"><?php echo (int)$acceptedCount; ?></div>
          </div>

          <div class="summary-box">
            <div class="summary-top">
              <div class="summary-label">Declined</div>
              <div class="summary-icon"><i class="fa-solid fa-circle-xmark"></i></div>
            </div>
            <div class="summary-value"><?php echo (int)$declinedCount; ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="section-title">
          <h2><i class="fa-regular fa-calendar-check"></i> Schedule Donation</h2>
        </div>
        <div class="section-sub">Plan your donation appointment before checking new requests.</div>

        <form method="POST" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
          <input type="hidden" name="action" value="schedule_donation">

          <div class="form-grid">
            <div class="field">
              <label>Full Name</label>
              <div class="control">
                <i class="fa-regular fa-id-badge"></i>
                <input name="full_name" type="text" value="<?php echo htmlspecialchars($_POST['full_name'] ?? $prefillName); ?>" placeholder="Enter full name" required>
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
                    foreach ($groups as $g) {
                      $sel = ($bg === $g) ? "selected" : "";
                      echo "<option value='" . htmlspecialchars($g) . "' $sel>" . htmlspecialchars($g) . "</option>";
                    }
                  ?>
                </select>
              </div>
            </div>

            <div class="field">
              <label>Contact Number</label>
              <div class="control">
                <i class="fa-solid fa-phone"></i>
                <input name="contact" type="text" value="<?php echo htmlspecialchars($_POST['contact'] ?? ""); ?>" placeholder="+977 98XXXXXXXX" required>
              </div>
            </div>

            <div class="field">
              <label>Email Address</label>
              <div class="control">
                <i class="fa-regular fa-envelope"></i>
                <input name="email" type="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $prefillEmail); ?>" placeholder="you@example.com" required>
              </div>
            </div>

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
                      $label = ($row["city"] ?? '') . " - " . ($row["name"] ?? '');
                      $sel = ($selectedHospital === $id) ? "selected" : "";
                      echo "<option value='{$id}' {$sel}>".htmlspecialchars($label)."</option>";
                    }
                  ?>
                </select>
              </div>
              <div class="hint">Choose a nearby center for your donation appointment.</div>
            </div>

            <div class="field">
              <label>Preferred Date</label>
              <div class="control">
                <i class="fa-regular fa-calendar"></i>
                <input name="donation_date" type="date" value="<?php echo htmlspecialchars($_POST['donation_date'] ?? ""); ?>" required>
              </div>
            </div>

            <div class="field">
              <label>Preferred Time</label>
              <div class="control">
                <i class="fa-regular fa-clock"></i>
                <input name="donation_time" type="time" value="<?php echo htmlspecialchars($_POST['donation_time'] ?? ""); ?>" required>
              </div>
            </div>

            <div class="field">
              <label>Availability Status</label>
              <div class="toggle-row" id="availabilityWrap">
                <?php $av = $_POST['availability'] ?? "Available"; ?>

                <label class="pill <?php echo ($av === "Available") ? "active" : ""; ?>">
                  <input type="radio" name="availability" value="Available" <?php echo ($av === "Available") ? "checked" : ""; ?>>
                  <i class="fa-solid fa-circle-check"></i> Available
                </label>

                <label class="pill <?php echo ($av === "Emergency Only") ? "active" : ""; ?>">
                  <input type="radio" name="availability" value="Emergency Only" <?php echo ($av === "Emergency Only") ? "checked" : ""; ?>>
                  <i class="fa-solid fa-bolt"></i> Emergency Only
                </label>
              </div>
            </div>

            <div class="field full">
              <label>Health Confirmation</label>
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

                  foreach ($list as $i => $text) {
                    $checked = (is_array($checksSelected) && in_array((string)$i, $checksSelected, true)) ? "checked" : "";
                    echo '<label class="check">
                            <input type="checkbox" name="health_checks[]" value="'.$i.'" '.$checked.'>
                            <span>'.htmlspecialchars($text).'</span>
                          </label>';
                  }
                ?>
              </div>
            </div>
          </div>

          <div class="actions">
            <button class="btn btn-primary" type="submit">
              <i class="fa-solid fa-circle-check"></i> Confirm Donation
            </button>
            <button class="btn btn-light" type="reset">
              <i class="fa-solid fa-rotate-left"></i> Reset Form
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="section-title">
          <h2><i class="fa-solid fa-hand-holding-droplet"></i> New Blood Requests</h2>
        </div>
        <div class="section-sub">Requests matching your blood group that still need your response.</div>

        <?php if ($donorBloodGroup === ""): ?>
          <div class="alert error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Your blood group is missing in your profile. Add it first to see requests.
          </div>
        <?php elseif (!$requests): ?>
          <div class="empty">
            No open requests for <strong><?php echo htmlspecialchars($donorBloodGroup); ?></strong> right now.
          </div>
        <?php else: ?>
          <div class="request-list">
            <?php foreach ($requests as $r): ?>
              <?php
                $neededDate = ($r['needed_date'] === '0000-00-00' || $r['needed_date'] === '') ? 'N/A' : $r['needed_date'];
                $neededTime = clean($r['needed_time'] ?? '');
                $isEmergency = (($r['urgency'] ?? '') === 'Emergency');
              ?>
              <div class="request-card">
                <div class="request-head">
                  <div class="request-info">
                    <div class="request-title">
                      <span><?php echo htmlspecialchars($r['blood_group']); ?> Blood Request</span>
                      <span class="unit-pill"><?php echo (int)$r['quantity']; ?> unit(s)</span>

                      <?php if ($isEmergency): ?>
                        <span class="urgency-pill urgency-emergency">
                          <i class="fa-solid fa-bolt"></i> Emergency
                        </span>
                      <?php else: ?>
                        <span class="urgency-pill urgency-normal">
                          <i class="fa-regular fa-clock"></i> Normal
                        </span>
                      <?php endif; ?>
                    </div>

                    <div class="request-meta">
                      <div><i class="fa-solid fa-location-dot"></i><?php echo htmlspecialchars($r['hospital_location']); ?></div>
                      <div><i class="fa-regular fa-calendar"></i><?php echo htmlspecialchars($neededDate); ?></div>
                      <?php if ($neededTime !== ""): ?>
                        <div><i class="fa-regular fa-clock"></i><?php echo htmlspecialchars($neededTime); ?></div>
                      <?php endif; ?>
                      <div><i class="fa-regular fa-clock"></i>Posted: <?php echo htmlspecialchars($r['created_at']); ?></div>
                    </div>

                    <?php if (!empty($r['patient_notes'])): ?>
                      <div class="request-notes">
                        <i class="fa-regular fa-note-sticky"></i>
                        <?php echo htmlspecialchars($r['patient_notes']); ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="request-actions">
                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                      <input type="hidden" name="action" value="accept_request">
                      <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                      <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-check"></i> Accept
                      </button>
                    </form>

                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                      <input type="hidden" name="action" value="decline_request">
                      <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                      <button class="btn btn-light" type="submit">
                        <i class="fa-solid fa-xmark"></i> Decline
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="section-title">
          <h2><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</h2>
        </div>
        <div class="section-sub">Only visible dashboard activity is shown here. Removing it will not affect your total counts.</div>

        <?php if (!$reviewedRequests): ?>
          <div class="empty">No activity yet.</div>
        <?php else: ?>
          <div class="activity-list">
            <?php foreach ($reviewedRequests as $rv): ?>
              <?php
                $isAccepted = (($rv['donor_response'] ?? '') === 'Accepted');
                $reviewedOn = $rv['accepted_at'] ?? '';
              ?>
              <div class="activity-item">
                <div class="activity-left">
                  <div class="activity-icon <?php echo $isAccepted ? 'accepted' : 'declined'; ?>">
                    <i class="fa-solid <?php echo $isAccepted ? 'fa-check' : 'fa-xmark'; ?>"></i>
                  </div>

                  <div>
                    <div class="activity-title">
                      <?php echo htmlspecialchars($rv['blood_group']); ?> • <?php echo (int)$rv['quantity']; ?> unit(s)
                    </div>
                    <div class="activity-meta">
                      <?php echo htmlspecialchars($rv['hospital_location']); ?>
                      • <?php echo htmlspecialchars($rv['urgency']); ?>
                      • <?php echo htmlspecialchars(($rv['needed_date'] ?: 'N/A')); ?>
                      <?php if (!empty($rv['needed_time'])): ?>
                        • <?php echo htmlspecialchars($rv['needed_time']); ?>
                      <?php endif; ?>
                      <?php if ($reviewedOn !== ""): ?>
                        • Reviewed on <?php echo htmlspecialchars($reviewedOn); ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                  <?php if ($isAccepted): ?>
                    <span class="status-pill status-accepted">Accepted</span>
                  <?php else: ?>
                    <span class="status-pill status-declined">Declined</span>
                  <?php endif; ?>

                  <form method="POST" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                    <input type="hidden" name="action" value="hide_review">
                    <input type="hidden" name="match_id" value="<?php echo (int)$rv['match_id']; ?>">
                    <button type="submit" class="mini-remove" title="Remove from dashboard">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="section-title">
          <h2><i class="fa-solid fa-address-book"></i> Accepted Requests - Contact Requester</h2>
        </div>
        <div class="section-sub">After you accept a request, requester contact details appear here.</div>

        <?php if (!$acceptedContacts): ?>
          <div class="empty">No accepted requester contacts yet.</div>
        <?php else: ?>
          <div class="request-list">
            <?php foreach ($acceptedContacts as $c): ?>
              <div class="request-card">
                <div class="request-info">
                  <div class="request-title">
                    <span><?php echo htmlspecialchars($c['blood_group']); ?> Request</span>
                    <span class="unit-pill">Accepted</span>
                  </div>

                  <div class="request-meta">
                    <div><i class="fa-solid fa-user"></i><?php echo htmlspecialchars(trim($c['requester_name']) ?: 'Requester'); ?></div>
                    <div><i class="fa-solid fa-location-dot"></i><?php echo htmlspecialchars($c['hospital_location']); ?></div>
                    <div><i class="fa-regular fa-envelope"></i><?php echo htmlspecialchars($c['requester_email'] ?: 'Not available'); ?></div>
                    <div><i class="fa-solid fa-phone"></i><?php echo htmlspecialchars($c['requester_phone'] ?: 'Not available'); ?></div>
                  </div>

                  <div class="request-actions">
                    <?php if (!empty($c['requester_phone'])): ?>
                      <a class="btn btn-primary" href="tel:<?php echo htmlspecialchars($c['requester_phone']); ?>">
                        <i class="fa-solid fa-phone"></i> Call
                      </a>
                    <?php endif; ?>

                    <?php if (!empty($c['requester_email'])): ?>
                      <a class="btn btn-light" href="mailto:<?php echo htmlspecialchars($c['requester_email']); ?>">
                        <i class="fa-regular fa-envelope"></i> Email
                      </a>
                    <?php endif; ?>

                    <a class="btn btn-light" href="chat.php?request_id=<?php echo (int)$c['blood_request_id']; ?>&user_id=<?php echo (int)$c['requester_id']; ?>">
                      <i class="fa-regular fa-comment-dots"></i> Message
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <aside class="stack">
    <div class="card">
      <div class="side-block">
        <div class="section-title">
          <h3><i class="fa-solid fa-circle-info"></i> Eligibility Guide</h3>
        </div>

        <div class="side-list">
          <div class="side-item">
            <div class="side-icon"><i class="fa-solid fa-user-check"></i></div>
            <div><b>Age Requirement</b><br>Must be between 18–65 years old.</div>
          </div>

          <div class="side-item">
            <div class="side-icon"><i class="fa-solid fa-weight-scale"></i></div>
            <div><b>Weight Requirement</b><br>Minimum weight should be 50 kg.</div>
          </div>

          <div class="side-item">
            <div class="side-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div><b>Donation Interval</b><br>Wait at least 3 months between donations.</div>
          </div>

          <div class="side-item">
            <div class="side-icon"><i class="fa-solid fa-heart-pulse"></i></div>
            <div><b>Health Status</b><br>You should be in good general health before donating.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card danger-card">
      <div class="side-block">
        <div class="section-title">
          <h3><i class="fa-solid fa-circle-exclamation"></i> Before You Donate</h3>
        </div>

        <div class="side-list">
          <div class="side-item"><div class="side-icon"><i class="fa-solid fa-glass-water"></i></div><div>Drink plenty of water before donation.</div></div>
          <div class="side-item"><div class="side-icon"><i class="fa-solid fa-ban-smoking"></i></div><div>Avoid alcohol 24 hours before donation.</div></div>
          <div class="side-item"><div class="side-icon"><i class="fa-solid fa-bed"></i></div><div>Sleep properly the night before donating.</div></div>
          <div class="side-item"><div class="side-icon"><i class="fa-solid fa-id-card"></i></div><div>Bring valid ID and donor card if available.</div></div>
        </div>
      </div>
    </div>

    <div class="card help-card">
      <div class="help-badge"><i class="fa-solid fa-headset"></i></div>
      <h3 style="font-size:16px;font-weight:1000;margin-bottom:8px;">Need Help?</h3>
      <p>Our support team is here to help you with requests, donation scheduling, and dashboard issues.</p>
      <p style="font-weight:900;color:var(--text);">
        <i class="fa-solid fa-phone"></i> +977 980-XXX-XXXX<br>
        <i class="fa-regular fa-envelope"></i> support@raktabindu.org
      </p>
    </div>
  </aside>
</section>

<footer>
  <div class="footer-grid">
    <div class="footer-col">
      <div class="footer-brand">Rakta.<span>Bindu</span></div>
      <div class="footer-text">
        Connecting donors and recipients in real-time to help save lives faster and more safely.
      </div>
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
      <div class="socials">
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