<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");

require_once __DIR__ . "/db.php";

$success = "";
$error = "";
$matches = [];
$requestId = null;

// ✅ PUBLIC page
$isLoggedIn = isset($_SESSION['user_id']);
$uid = $isLoggedIn ? (int)$_SESSION['user_id'] : null;

// Helpers
function clean($v) { return trim((string)$v); }
function likeEscape($s) {
  $s = str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $s);
  return $s;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $blood_group = clean($_POST["blood_group"] ?? "");
  $quantity = (int)($_POST["quantity"] ?? 1);
  $hospital_location = clean($_POST["hospital_location"] ?? "");
  $urgency = clean($_POST["urgency"] ?? "Normal");
  $needed_date = clean($_POST["needed_date"] ?? "");
  $needed_time = clean($_POST["needed_time"] ?? "");
  $patient_notes = clean($_POST["patient_notes"] ?? "");

  if ($quantity < 1) $quantity = 1;
  if ($quantity > 10) $quantity = 10;

  // Validate
  $validGroups = ["A+","A-","B+","B-","O+","O-","AB+","AB-"];
  $validUrg = ["Normal","Emergency"];

  if ($blood_group === "" || !in_array($blood_group, $validGroups, true)) {
    $error = "Please select blood group required.";
  } elseif ($hospital_location === "") {
    $error = "Please enter hospital / location.";
  } elseif (!in_array($urgency, $validUrg, true)) {
    $error = "Invalid urgency level.";
  } elseif ($needed_date === "" || $needed_time === "") {
    $error = "Please select needed date and time.";
  } else {

    // ✅ Insert request (Option A: guest allowed)
    if ($isLoggedIn) {
      $ins = $conn->prepare("
        INSERT INTO blood_requests
        (user_id, blood_group, quantity, hospital_location, urgency, needed_date, needed_time, patient_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ");
      if (!$ins) {
        $error = "DB Error: " . $conn->error;
      } else {
        $ins->bind_param(
          "isisssss",
          $uid,
          $blood_group,
          $quantity,
          $hospital_location,
          $urgency,
          $needed_date,
          $needed_time,
          $patient_notes
        );
      }
    } else {
      // Guest insert without user_id
      $ins = $conn->prepare("
        INSERT INTO blood_requests
        (blood_group, quantity, hospital_location, urgency, needed_date, needed_time, patient_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");
      if (!$ins) {
        $error = "DB Error: " . $conn->error;
      } else {
        $ins->bind_param(
          "sisssss",
          $blood_group,
          $quantity,
          $hospital_location,
          $urgency,
          $needed_date,
          $needed_time,
          $patient_notes
        );
      }
    }

    if (!$error && isset($ins)) {
      if ($ins->execute()) {
        $requestId = $conn->insert_id;
        $success = "Request submitted! Showing matching donors near your location.";

        // ✅ Donor matching
        $likeLoc = "%" . likeEscape($hospital_location) . "%";

        $q = $conn->prepare("
          SELECT id, firstName, lastName, email, phone, bloodType, location, availability
          FROM users
          WHERE is_verified = 1
            AND TRIM(bloodType) = ?
            AND TRIM(availability) <> 'Not available currently'
            AND (location LIKE ? ESCAPE '\\\\' OR ? = '')
          ORDER BY
            CASE availability
              WHEN 'Available anytime' THEN 1
              WHEN 'Available in emergencies only' THEN 2
              WHEN 'Available on weekends' THEN 3
              ELSE 4
            END,
            id DESC
          LIMIT 12
        ");
        if ($q) {
          $locParam = $hospital_location;
          $q->bind_param("sss", $blood_group, $likeLoc, $locParam);
          $q->execute();
          $res = $q->get_result();
          while ($row = $res->fetch_assoc()) $matches[] = $row;
        }

        $_POST = [];
      } else {
        $error = "Failed to submit request: " . $ins->error;
      }
    }
  }
}

// Header name/avatar (guest-safe)
$userName = $isLoggedIn ? ($_SESSION['user_name'] ?? "User") : "Guest";
$avatarLetter = strtoupper(mb_substr($userName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Request Blood | RaktaBindu</title>

  <!-- Font Awesome -->
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
      --shadow:0 16px 50px rgba(0,0,0,.10);
      --shadow2:0 10px 30px rgba(0,0,0,.08);
      --r:18px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Segoe UI",system-ui,Arial;background:var(--bg);color:var(--text);}
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

    /* top nav */
    .topnav{
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:14px 18px;
      border-bottom:1px solid rgba(0,0,0,.06);
      background:#fff;
      gap:12px;
    }
    .brand{
      display:flex;align-items:center;gap:10px;font-weight:1000;
    }
    .brand .logo{
      width:34px;height:34px;border-radius:10px;background:rgba(198,40,40,.10);
      display:grid;place-items:center;color:var(--red);
    }
    .brand small{display:block;color:#98a2b3;font-weight:800;font-size:11px;margin-top:-2px}
    .brand .text b{color:var(--red)}
    .links{display:flex;gap:22px;align-items:center;flex-wrap:wrap}
    .links a{
      text-decoration:none;color:#667085;font-weight:900;font-size:12px;
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

    /* layout */
    .content{
      padding:18px;
      display:grid;
      grid-template-columns: 1fr 1.4fr;
      gap:16px;
      background:linear-gradient(#fff, #fff);
    }

    .card{
      background:var(--card);
      border:1px solid rgba(0,0,0,.08);
      border-radius:16px;
      box-shadow:var(--shadow2);
    }
    .left{padding:16px;}
    .right{padding:16px; display:flex; flex-direction:column; justify-content:center; min-height:420px;}

    .head{
      display:flex; gap:10px; align-items:flex-start; margin-bottom:12px;
    }
    .badge{
      width:40px;height:40px;border-radius:12px;background:rgba(198,40,40,.10);
      color:var(--red);display:grid;place-items:center;border:1px solid rgba(198,40,40,.16);
    }
    .head h2{font-size:16px;font-weight:1000;line-height:1.2}
    .head p{font-size:12px;color:var(--muted);margin-top:4px}

    .msg{
      padding:12px 14px;border-radius:14px;font-size:13px;font-weight:900;
      border:1px solid;margin-bottom:12px;
      display:flex; gap:10px; align-items:flex-start;
    }
    .msg.ok{background:#e8f5e9;border-color:#c8e6c9;color:#2e7d32}
    .msg.bad{background:#ffebee;border-color:#ffcdd2;color:#b71c1c}

    .section{margin-top:14px}
    .label{
      font-size:11px;font-weight:1000;color:#475467;margin:0 0 8px 2px;display:flex;gap:6px;align-items:center
    }
    .req{color:var(--red)}
    .grid-groups{
      display:grid;
      grid-template-columns: repeat(4, 1fr);
      gap:10px;
    }
    .gbtn{
      height:40px;border-radius:10px;border:1px solid rgba(0,0,0,.10);
      background:#fff;font-weight:1000;cursor:pointer;
      display:flex;align-items:center;justify-content:center;
      transition:.15s ease;
    }
    .gbtn:hover{transform:translateY(-1px); box-shadow:0 10px 20px rgba(0,0,0,.08)}
    .gbtn.active{
      border-color:rgba(198,40,40,.45);
      background:rgba(198,40,40,.06);
      color:var(--red);
    }

    .qty{
      display:flex; gap:10px; align-items:center;
    }
    .qbtn{
      width:42px;height:42px;border-radius:10px;border:1px solid rgba(0,0,0,.10);
      background:#fff;cursor:pointer;font-weight:1000;font-size:18px;
      display:grid;place-items:center;
    }
    .qinput{
      flex:1;height:42px;border-radius:10px;border:1px solid rgba(0,0,0,.10);
      text-align:center;font-weight:1000;font-size:14px;outline:none;
    }

    .input{
      width:100%;height:42px;border-radius:10px;border:1px solid rgba(0,0,0,.10);
      padding:0 12px 0 38px;outline:none;
      font-size:13px;
    }
    .control{position:relative;}
    .control i{
      position:absolute; left:12px; top:50%; transform:translateY(-50%);
      color:#98a2b3;
    }

    .urgency{
      display:grid; grid-template-columns: 1fr 1fr; gap:10px;
    }
    .ubox{
      border:1px solid rgba(0,0,0,.10);
      border-radius:12px;
      padding:12px;
      cursor:pointer;
      display:flex; flex-direction:column; gap:6px;
      align-items:center; justify-content:center;
      text-align:center;
      transition:.15s ease;
      background:#fff;
      min-height:76px;
      font-weight:1000;
    }
    .ubox small{font-weight:800;color:#98a2b3}
    .ubox i{font-size:18px}
    .ubox.active{
      border-color:rgba(198,40,40,.45);
      background:rgba(198,40,40,.06);
      color:var(--red);
    }

    .row2{display:grid; grid-template-columns: 1fr 1fr; gap:10px;}

    textarea{
      width:100%;
      min-height:92px;
      border-radius:12px;
      border:1px solid rgba(0,0,0,.10);
      padding:12px;
      font-size:13px;
      outline:none;
      resize:none;
    }
    textarea:focus,.input:focus,.qinput:focus{
      border-color:rgba(198,40,40,.35);
      box-shadow:0 0 0 5px rgba(198,40,40,.10);
    }

    .submit{
      width:100%;
      height:48px;
      border:none;
      border-radius:12px;
      background:var(--red);
      color:#fff;
      font-weight:1000;
      cursor:pointer;
      display:flex; align-items:center; justify-content:center; gap:10px;
      box-shadow:0 16px 30px rgba(198,40,40,.18);
      margin-top:12px;
      transition:.15s ease;
    }
    .submit:hover{background:#b71c1c; transform:translateY(-1px)}

    /* Right side */
    .center-hero{
      text-align:center;
    }
    .bigicon{
      width:70px;height:70px;border-radius:50%;
      background:rgba(0,0,0,.04);
      display:grid;place-items:center;
      margin:0 auto 14px;
      color:#98a2b3;
      border:1px solid rgba(0,0,0,.06);
    }
    .center-hero h3{font-size:14px;font-weight:1000;margin-bottom:6px}
    .center-hero p{font-size:12px;color:#98a2b3;max-width:44ch;margin:0 auto}
    .chips{
      display:flex;justify-content:center;gap:18px;flex-wrap:wrap;
      margin-top:14px;
      font-size:11px;
      color:#667085;
      font-weight:900;
    }
    .chip{display:flex;align-items:center;gap:8px}
    .chip i{color:#22c55e}

    .match-wrap{margin-top:16px}
    .match-title{
      display:flex; align-items:center; justify-content:space-between;
      margin-bottom:10px;
    }
    .match-title h4{font-size:12px;font-weight:1000;color:#344054}
    .pillcount{
      font-size:11px;font-weight:1000;color:#667085;
      padding:6px 10px;border-radius:999px;border:1px solid rgba(0,0,0,.10);
      background:#fff;
    }
    .donor-list{
      display:flex; flex-direction:column; gap:10px;
      max-height:280px; overflow:auto; padding-right:4px;
    }
    .donor{
      border:1px solid rgba(0,0,0,.08);
      border-radius:14px;
      padding:12px;
      display:flex; gap:12px; align-items:center;
      background:#fff;
    }
    .donor .dava{
      width:40px;height:40px;border-radius:14px;
      background:rgba(198,40,40,.10);
      border:1px solid rgba(198,40,40,.16);
      color:var(--red);
      display:grid;place-items:center;
      font-weight:1000;
    }
    .donor b{font-size:13px}
    .donor small{display:block;font-size:11px;color:#98a2b3;margin-top:2px;line-height:1.4}
    .donor .actions{
      margin-left:auto; display:flex; gap:8px; flex-wrap:wrap;
    }
    .act{
      height:34px; padding:0 10px; border-radius:10px;
      border:1px solid rgba(0,0,0,.10);
      background:#fff; cursor:pointer;
      font-size:11px; font-weight:1000; color:#344054;
      display:inline-flex; align-items:center; gap:7px;
    }
    .act.primary{
      background:rgba(198,40,40,.10);
      border-color:rgba(198,40,40,.20);
      color:var(--red);
    }
    .act:hover{transform:translateY(-1px); box-shadow:0 10px 20px rgba(0,0,0,.08)}

    @media (max-width: 980px){
      .content{grid-template-columns:1fr;}
      .right{min-height:auto}
      .links{gap:10px}
    }
  </style>
</head>

<body>
  <div class="page-title">Request Blood</div>

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
        <a class="active" href="request-blood.php"><i class="fa-solid fa-droplet"></i> Request Blood</a>
        <a href="my-requests.php"><i class="fa-regular fa-file-lines"></i> My Requests</a>
        <a href="donors.php"><i class="fa-solid fa-users"></i> Donors</a>
      </div>

      <div class="rightbar">
        <button class="bell" title="Notifications">
          <i class="fa-regular fa-bell"></i>
          <span class="dot-red"></span>
        </button>
        <div class="avatar" title="<?php echo htmlspecialchars($userName); ?>">
          <?php echo htmlspecialchars($avatarLetter); ?>
        </div>
      </div>
    </div>

    <div class="content">
      <!-- LEFT: Form -->
      <div class="card left">
        <div class="head">
          <div class="badge"><i class="fa-solid fa-hand-holding-heart"></i></div>
          <div>
            <h2>Request Blood</h2>
            <p>Fill in the details to find donors</p>
          </div>
        </div>

        <?php if ($success): ?>
          <div class="msg ok"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="msg bad"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" id="requestForm">
          <!-- Blood group -->
          <div class="section">
            <div class="label">Blood Group Required <span class="req">*</span></div>

            <input type="hidden" name="blood_group" id="blood_group" value="<?php echo htmlspecialchars($_POST['blood_group'] ?? ''); ?>">

            <div class="grid-groups" id="groupGrid">
              <?php
                $selected = $_POST['blood_group'] ?? "";
                $groups = ["A+","A-","B+","B-","O+","O-","AB+","AB-"];
                foreach($groups as $g){
                  $active = ($selected === $g) ? "active" : "";
                  echo '<button type="button" class="gbtn '.$active.'" data-group="'.htmlspecialchars($g).'">'.htmlspecialchars($g).'</button>';
                }
              ?>
            </div>
          </div>

          <!-- Quantity -->
          <div class="section">
            <div class="label">Quantity (Units) <span class="req">*</span></div>
            <div class="qty">
              <button type="button" class="qbtn" id="minusBtn">−</button>
              <input class="qinput" name="quantity" id="qtyInput" type="number" min="1" max="10"
                     value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>">
              <button type="button" class="qbtn" id="plusBtn">+</button>
            </div>
          </div>

          <!-- Location -->
          <div class="section">
            <div class="label">Hospital / Location <span class="req">*</span></div>
            <div class="control">
              <i class="fa-solid fa-hospital"></i>
              <input class="input" name="hospital_location" type="text"
                     placeholder="Enter hospital name or address"
                     value="<?php echo htmlspecialchars($_POST['hospital_location'] ?? ''); ?>" required>
            </div>
          </div>

          <!-- Urgency -->
          <div class="section">
            <div class="label">Urgency Level <span class="req">*</span></div>
            <input type="hidden" name="urgency" id="urgency" value="<?php echo htmlspecialchars($_POST['urgency'] ?? 'Normal'); ?>">

            <?php $urg = $_POST['urgency'] ?? 'Normal'; ?>
            <div class="urgency" id="urgencyWrap">
              <button type="button" class="ubox <?php echo ($urg==='Normal')?'active':''; ?>" data-urg="Normal">
                <i class="fa-solid fa-circle-info" style="color:#3b82f6"></i>
                Normal
                <small>Within 24-48 hours</small>
              </button>

              <button type="button" class="ubox <?php echo ($urg==='Emergency')?'active':''; ?>" data-urg="Emergency">
                <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444"></i>
                Emergency
                <small>Immediate need</small>
              </button>
            </div>
          </div>

          <!-- Needed date/time -->
          <div class="section">
            <div class="row2">
              <div>
                <div class="label">Needed Date <span class="req">*</span></div>
                <div class="control">
                  <i class="fa-regular fa-calendar"></i>
                  <input class="input" type="date" name="needed_date"
                         value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required>
                </div>
              </div>

              <div>
                <div class="label">Needed Time <span class="req">*</span></div>
                <div class="control">
                  <i class="fa-regular fa-clock"></i>
                  <input class="input" type="time" name="needed_time"
                         value="<?php echo htmlspecialchars($_POST['needed_time'] ?? ''); ?>" required>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="section">
            <div class="label">Patient Notes (Optional)</div>
            <textarea name="patient_notes" placeholder="Add any additional information about the patient or requirements..."><?php echo htmlspecialchars($_POST['patient_notes'] ?? ''); ?></textarea>
          </div>

          <button class="submit" type="submit">
            <i class="fa-solid fa-magnifying-glass"></i> Find Available Donors
          </button>
        </form>
      </div>

      <!-- RIGHT: Matching donors -->
      <div class="card right">
        <div class="center-hero">
          <div class="bigicon"><i class="fa-solid fa-users"></i></div>
          <h3>Find Matching Donors</h3>
          <p>Submit your blood request to see available donors near your location in real-time</p>

          <div class="chips">
            <div class="chip"><i class="fa-solid fa-circle-check"></i> Real-time matching</div>
            <div class="chip"><i class="fa-solid fa-circle-check"></i> Verified donors</div>
            <div class="chip"><i class="fa-solid fa-circle-check"></i> Instant contact</div>
          </div>
        </div>

        <?php if ($success): ?>
          <div class="match-wrap">
            <div class="match-title">
              <h4><i class="fa-solid fa-filter"></i> Matching Donors</h4>
              <span class="pillcount"><?php echo count($matches); ?> found</span>
            </div>

            <?php if (count($matches) === 0): ?>
              <div style="margin-top:10px;color:#98a2b3;font-size:12px;font-weight:900;text-align:center;">
                No matching donors found (try broader location text).
              </div>
            <?php else: ?>
              <div class="donor-list">
                <?php foreach($matches as $d): ?>
                  <?php
                    $dn = trim($d["firstName"]." ".$d["lastName"]);
                    $initial = strtoupper(mb_substr($d["firstName"] ?: "D", 0, 1));
                    $loc = $d["location"] ?? "";
                    $avail = $d["availability"] ?? "";
                    $phone = $d["phone"] ?? "";
                    $email = $d["email"] ?? "";
                  ?>
                  <div class="donor">
                    <div class="dava"><?php echo htmlspecialchars($initial); ?></div>
                    <div>
                      <b><?php echo htmlspecialchars($dn ?: "Donor"); ?> • <?php echo htmlspecialchars($d["bloodType"] ?? ""); ?></b>
                      <small>
                        <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($loc); ?><br>
                        <i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars($avail); ?>
                      </small>
                    </div>

                    <div class="actions">
                      <button type="button" class="act primary" onclick="alert('Call: <?php echo htmlspecialchars($phone); ?>')">
                        <i class="fa-solid fa-phone"></i> Call
                      </button>
                      <button type="button" class="act" onclick="alert('Email: <?php echo htmlspecialchars($email); ?>')">
                        <i class="fa-regular fa-envelope"></i> Email
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

<script>
  // ✅ Blood group buttons
  const groupGrid = document.getElementById("groupGrid");
  const bloodInput = document.getElementById("blood_group");
  if (groupGrid && bloodInput) {
    groupGrid.querySelectorAll(".gbtn").forEach(btn => {
      btn.addEventListener("click", () => {
        groupGrid.querySelectorAll(".gbtn").forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        bloodInput.value = btn.dataset.group || "";
      });
    });
  }

  // ✅ Quantity +/-
  const qty = document.getElementById("qtyInput");
  const minus = document.getElementById("minusBtn");
  const plus = document.getElementById("plusBtn");

  function clampQty(v){
    v = parseInt(v || "1", 10);
    if (isNaN(v)) v = 1;
    if (v < 1) v = 1;
    if (v > 10) v = 10;
    return v;
  }

  if (qty) {
    qty.value = clampQty(qty.value);
    qty.addEventListener("input", () => qty.value = clampQty(qty.value));
  }
  if (minus && qty) minus.addEventListener("click", () => qty.value = clampQty((+qty.value || 1) - 1));
  if (plus && qty) plus.addEventListener("click", () => qty.value = clampQty((+qty.value || 1) + 1));

  // ✅ Urgency toggle
  const urgWrap = document.getElementById("urgencyWrap");
  const urgInput = document.getElementById("urgency");
  if (urgWrap && urgInput) {
    urgWrap.querySelectorAll(".ubox").forEach(btn => {
      btn.addEventListener("click", () => {
        urgWrap.querySelectorAll(".ubox").forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        urgInput.value = btn.dataset.urg || "Normal";
      });
    });
  }

  // ✅ Simple client-side required checks
  const form = document.getElementById("requestForm");
  if (form) {
    form.addEventListener("submit", (e) => {
      const bg = (bloodInput?.value || "").trim();
      if (!bg) {
        e.preventDefault();
        alert("Please select blood group required.");
        return;
      }
    });
  }
</script>

</body>
</html>
