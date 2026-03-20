<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");
require_once __DIR__ . "/db.php";

$success = "";
$error = "";
$matches = [];
$myRequests = [];
$acceptedDonors = [];
$requestId = null;

$isLoggedIn = isset($_SESSION['user_id']);
$uid = $isLoggedIn ? (int)($_SESSION['user_id'] ?? 0) : null;

function clean($v): string {
    return trim((string)$v);
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function likeEscape(string $s): string {
    return str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $s);
}

function formatDateSafe(?string $date): string {
    if (!$date || $date === '0000-00-00') {
        return 'N/A';
    }
    $ts = strtotime($date);
    return $ts ? date("M d, Y", $ts) : 'N/A';
}

function formatDateTimeSafe(?string $dt): string {
    if (!$dt) {
        return 'N/A';
    }
    $ts = strtotime($dt);
    return $ts ? date("M d, Y • h:i A", $ts) : e($dt);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $blood_group       = clean($_POST["blood_group"] ?? "");
    $quantity          = (int)($_POST["quantity"] ?? 1);
    $hospital_location = clean($_POST["hospital_location"] ?? "");
    $urgency           = clean($_POST["urgency"] ?? "Normal");
    $needed_date       = clean($_POST["needed_date"] ?? "");
    $needed_time       = clean($_POST["needed_time"] ?? "");
    $patient_notes     = clean($_POST["patient_notes"] ?? "");

    if ($quantity < 1) $quantity = 1;
    if ($quantity > 10) $quantity = 10;

    $validGroups = ["A+","A-","B+","B-","O+","O-","AB+","AB-"];
    $validUrgency = ["Normal", "Emergency"];

    if ($blood_group === "" || !in_array($blood_group, $validGroups, true)) {
        $error = "Please select the required blood group.";
    } elseif ($hospital_location === "") {
        $error = "Please enter hospital or location.";
    } elseif (!in_array($urgency, $validUrgency, true)) {
        $error = "Invalid urgency level.";
    } elseif ($needed_date === "" || $needed_time === "") {
        $error = "Please select needed date and time.";
    } elseif ($needed_date < date('Y-m-d')) {
        $error = "Needed date cannot be in the past.";
    } else {
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
                $requestId = (int)$conn->insert_id;
                $success = "Request submitted successfully. Matching donors are shown below.";

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
                    while ($row = $res->fetch_assoc()) {
                        $matches[] = $row;
                    }
                    $q->close();
                }

                $_POST = [];
            } else {
                $error = "Failed to submit request: " . $ins->error;
            }

            $ins->close();
        }
    }
}

if ($isLoggedIn && $uid > 0) {
    $mr = $conn->prepare("
        SELECT id, blood_group, quantity, hospital_location, urgency,
               needed_date, needed_time, patient_notes, status, created_at
        FROM blood_requests
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 5
    ");

    if ($mr) {
        $mr->bind_param("i", $uid);
        if ($mr->execute()) {
            $res = $mr->get_result();
            while ($row = $res->fetch_assoc()) {
                $myRequests[] = $row;
            }
        }
        $mr->close();
    }

    $ad = $conn->prepare("
        SELECT
            br.id AS request_id,
            br.blood_group,
            br.hospital_location,
            rm.accepted_at,
            u.id AS donor_id,
            CONCAT(TRIM(COALESCE(u.firstName,'')), ' ', TRIM(COALESCE(u.lastName,''))) AS donor_name,
            u.email AS donor_email,
            u.phone AS donor_phone
        FROM blood_requests br
        INNER JOIN request_matches rm ON rm.request_id = br.id
        INNER JOIN users u ON u.id = rm.donor_id
        WHERE br.user_id = ?
          AND rm.status = 'Accepted'
        ORDER BY rm.accepted_at DESC, br.id DESC
    ");

    if ($ad) {
        $ad->bind_param("i", $uid);
        if ($ad->execute()) {
            $res = $ad->get_result();
            while ($row = $res->fetch_assoc()) {
                $acceptedDonors[] = $row;
            }
        }
        $ad->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Request Blood | RaktaBindu</title>
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
            --blue:#175cd3;
            --blue-soft:#eff8ff;
            --text:#1f2430;
            --muted:#667085;
            --line:rgba(0,0,0,.08);
            --bg:#f6f7fb;
            --card:#ffffff;
            --shadow2:0 10px 30px rgba(0,0,0,.08);
        }

        *{box-sizing:border-box;margin:0;padding:0}

        body{
            font-family:"Segoe UI",system-ui,Arial,sans-serif;
            background:var(--bg);
            color:var(--text);
        }

        .page-title{
            width:min(1100px,92%);
            margin:10px auto 10px;
            color:#98a2b3;
            font-weight:900;
            font-size:13px;
        }

        .shell{
            width:min(1100px,92%);
            margin:0 auto 30px;
        }

        .hero{
            background:linear-gradient(135deg,#d32f2f 0%,#c62828 55%,#a61b1b 100%);
            color:#fff;
            border-radius:24px;
            padding:26px;
            box-shadow:0 20px 40px rgba(198,40,40,.16);
            margin-bottom:18px;
            position:relative;
            overflow:hidden;
        }

        .hero::before{
            content:"";
            position:absolute;
            top:-70px;
            right:-70px;
            width:220px;
            height:220px;
            border-radius:50%;
            background:rgba(255,255,255,.10);
        }

        .hero::after{
            content:"";
            position:absolute;
            bottom:-60px;
            right:120px;
            width:150px;
            height:150px;
            border-radius:50%;
            background:rgba(255,255,255,.08);
        }

        .hero-inner{
            position:relative;
            display:flex;
            justify-content:space-between;
            gap:16px;
            flex-wrap:wrap;
            align-items:flex-start;
        }

        .hero h1{
            font-size:30px;
            font-weight:1000;
            display:flex;
            align-items:center;
            gap:12px;
            margin-bottom:10px;
        }

        .hero p{
            max-width:62ch;
            line-height:1.8;
            font-size:14px;
            opacity:.96;
        }

        .hero-badges{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }

        .hero-badge{
            padding:10px 14px;
            border-radius:999px;
            background:rgba(255,255,255,.15);
            border:1px solid rgba(255,255,255,.16);
            font-size:12px;
            font-weight:900;
            white-space:nowrap;
        }

        .content{
            display:grid;
            grid-template-columns:1fr 1.2fr;
            gap:18px;
            align-items:start;
        }

        .card{
            background:var(--card);
            border:1px solid var(--line);
            border-radius:18px;
            box-shadow:var(--shadow2);
        }

        .left,
        .right,
        .my-requests-card{
            padding:18px;
        }

        .head{
            display:flex;
            gap:12px;
            align-items:flex-start;
            margin-bottom:14px;
        }

        .badge{
            width:42px;
            height:42px;
            border-radius:12px;
            background:rgba(198,40,40,.10);
            color:var(--primary);
            display:grid;
            place-items:center;
            border:1px solid rgba(198,40,40,.16);
            flex:0 0 auto;
        }

        .head h2{
            font-size:18px;
            font-weight:1000;
            line-height:1.2;
        }

        .head p{
            font-size:12px;
            color:var(--muted);
            margin-top:5px;
            line-height:1.6;
        }

        .msg{
            padding:13px 14px;
            border-radius:14px;
            font-size:13px;
            font-weight:900;
            border:1px solid;
            margin-bottom:14px;
            display:flex;
            gap:10px;
            align-items:flex-start;
            line-height:1.6;
        }

        .msg.ok{
            background:var(--green-soft);
            border-color:#c8e6c9;
            color:var(--green);
        }

        .msg.bad{
            background:var(--red-soft);
            border-color:#ffcdd2;
            color:#b71c1c;
        }

        .section{
            margin-top:16px;
        }

        .label{
            font-size:12px;
            font-weight:1000;
            color:#475467;
            margin:0 0 8px 2px;
            display:flex;
            gap:6px;
            align-items:center;
        }

        .req{color:var(--primary)}

        .grid-groups{
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:10px;
        }

        .gbtn{
            height:42px;
            border-radius:12px;
            border:1px solid rgba(0,0,0,.10);
            background:#fff;
            font-weight:1000;
            cursor:pointer;
            display:flex;
            align-items:center;
            justify-content:center;
            transition:.15s ease;
            font-size:13px;
        }

        .gbtn:hover{
            transform:translateY(-1px);
            box-shadow:0 10px 20px rgba(0,0,0,.06);
        }

        .gbtn.active{
            border-color:rgba(198,40,40,.45);
            background:rgba(198,40,40,.06);
            color:var(--primary);
        }

        .qty{
            display:flex;
            gap:10px;
            align-items:center;
        }

        .qbtn{
            width:44px;
            height:44px;
            border-radius:12px;
            border:1px solid rgba(0,0,0,.10);
            background:#fff;
            cursor:pointer;
            font-weight:1000;
            font-size:18px;
            display:grid;
            place-items:center;
        }

        .qinput{
            flex:1;
            height:44px;
            border-radius:12px;
            border:1px solid rgba(0,0,0,.10);
            text-align:center;
            font-weight:1000;
            font-size:14px;
            outline:none;
        }

        .control{
            position:relative;
        }

        .control i{
            position:absolute;
            left:12px;
            top:50%;
            transform:translateY(-50%);
            color:#98a2b3;
        }

        .input{
            width:100%;
            height:44px;
            border-radius:12px;
            border:1px solid rgba(0,0,0,.10);
            padding:0 12px 0 40px;
            outline:none;
            font-size:13px;
            background:#fff;
        }

        .urgency{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:10px;
        }

        .ubox{
            border:1px solid rgba(0,0,0,.10);
            border-radius:14px;
            padding:13px;
            cursor:pointer;
            display:flex;
            flex-direction:column;
            gap:6px;
            align-items:center;
            justify-content:center;
            text-align:center;
            transition:.15s ease;
            background:#fff;
            min-height:82px;
            font-weight:1000;
            font-size:13px;
        }

        .ubox small{
            font-weight:800;
            color:#98a2b3;
            font-size:11px;
        }

        .ubox i{font-size:18px}

        .ubox.active{
            border-color:rgba(198,40,40,.45);
            background:rgba(198,40,40,.06);
            color:var(--primary);
        }

        .row2{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:10px;
        }

        textarea{
            width:100%;
            min-height:100px;
            border-radius:14px;
            border:1px solid rgba(0,0,0,.10);
            padding:12px;
            font-size:13px;
            outline:none;
            resize:vertical;
            font-family:inherit;
        }

        textarea:focus,
        .input:focus,
        .qinput:focus{
            border-color:rgba(198,40,40,.35);
            box-shadow:0 0 0 5px rgba(198,40,40,.10);
        }

        .submit{
            width:100%;
            height:48px;
            border:none;
            border-radius:12px;
            background:var(--primary);
            color:#fff;
            font-weight:1000;
            cursor:pointer;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
            box-shadow:0 16px 30px rgba(198,40,40,.18);
            margin-top:14px;
            transition:.15s ease;
            font-size:14px;
        }

        .submit:hover{
            background:var(--primary-dark);
            transform:translateY(-1px);
        }

        .right{
            min-height:420px;
            display:flex;
            flex-direction:column;
        }

        .center-hero{
            text-align:center;
        }

        .bigicon{
            width:72px;
            height:72px;
            border-radius:50%;
            background:rgba(0,0,0,.04);
            display:grid;
            place-items:center;
            margin:0 auto 14px;
            color:#98a2b3;
            border:1px solid rgba(0,0,0,.06);
            font-size:22px;
        }

        .center-hero h3{
            font-size:16px;
            font-weight:1000;
            margin-bottom:6px;
        }

        .center-hero p{
            font-size:13px;
            color:#98a2b3;
            max-width:44ch;
            margin:0 auto;
            line-height:1.7;
        }

        .chips{
            display:flex;
            justify-content:center;
            gap:16px;
            flex-wrap:wrap;
            margin-top:16px;
            font-size:11px;
            color:#667085;
            font-weight:900;
        }

        .chip{
            display:flex;
            align-items:center;
            gap:8px;
        }

        .chip i{color:#22c55e}

        .match-wrap{margin-top:18px}

        .match-title{
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:10px;
            gap:10px;
        }

        .match-title h4{
            font-size:13px;
            font-weight:1000;
            color:#344054;
        }

        .pillcount{
            font-size:11px;
            font-weight:1000;
            color:#667085;
            padding:6px 10px;
            border-radius:999px;
            border:1px solid rgba(0,0,0,.10);
            background:#fff;
        }

        .donor-list,
        .request-history,
        .contact-list{
            display:flex;
            flex-direction:column;
            gap:10px;
        }

        .donor-list{
            max-height:320px;
            overflow:auto;
            padding-right:4px;
        }

        .donor,
        .request-item,
        .contact-card{
            border:1px solid rgba(0,0,0,.08);
            border-radius:16px;
            padding:14px;
            background:#fff;
        }

        .donor{
            display:flex;
            gap:12px;
            align-items:center;
        }

        .dava{
            width:42px;
            height:42px;
            border-radius:14px;
            background:rgba(198,40,40,.10);
            border:1px solid rgba(198,40,40,.16);
            color:var(--primary);
            display:grid;
            place-items:center;
            font-weight:1000;
            flex:0 0 auto;
        }

        .donor b{
            font-size:13px;
            line-height:1.4;
        }

        .donor small{
            display:block;
            font-size:11px;
            color:#98a2b3;
            margin-top:3px;
            line-height:1.5;
        }

        .actions{
            margin-left:auto;
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }

        .act{
            height:34px;
            padding:0 10px;
            border-radius:10px;
            border:1px solid rgba(0,0,0,.10);
            background:#fff;
            cursor:pointer;
            font-size:11px;
            font-weight:1000;
            color:#344054;
            display:inline-flex;
            align-items:center;
            gap:7px;
            text-decoration:none;
        }

        .act.primary{
            background:rgba(198,40,40,.10);
            border-color:rgba(198,40,40,.20);
            color:var(--primary);
        }

        .act:hover{
            transform:translateY(-1px);
            box-shadow:0 10px 20px rgba(0,0,0,.06);
        }

        .empty-match{
            margin-top:12px;
            color:#98a2b3;
            font-size:12px;
            font-weight:900;
            text-align:center;
            line-height:1.7;
            padding:18px;
            border:1px dashed rgba(0,0,0,.10);
            border-radius:14px;
            background:#fcfcfd;
        }

        .my-requests-card{
            margin-top:18px;
        }

        .request-item{
            display:flex;
            justify-content:space-between;
            gap:14px;
            align-items:flex-start;
            flex-wrap:wrap;
        }

        .request-item-left{
            flex:1;
            min-width:240px;
        }

        .request-item-title,
        .contact-title{
            font-size:14px;
            font-weight:1000;
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            line-height:1.5;
        }

        .request-item-meta,
        .contact-meta{
            margin-top:8px;
            font-size:12px;
            color:var(--muted);
            line-height:1.7;
        }

        .request-item-notes{
            margin-top:10px;
            padding:10px 12px;
            border-radius:12px;
            background:#f8fafc;
            border:1px solid rgba(0,0,0,.06);
            font-size:12px;
            color:#475467;
            line-height:1.7;
        }

        .req-pill{
            padding:6px 10px;
            border-radius:999px;
            font-size:11px;
            font-weight:1000;
            white-space:nowrap;
        }

        .req-open{
            background:var(--blue-soft);
            color:var(--blue);
        }

        .req-accepted{
            background:var(--green-soft);
            color:var(--green);
        }

        .req-closed{
            background:var(--red-soft);
            color:var(--red);
        }

        .req-emergency{
            background:var(--red-soft);
            color:var(--red);
        }

        .req-normal{
            background:var(--yellow-soft);
            color:var(--yellow);
        }

        .section-topbar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:14px;
        }

        .contact-meta{
            display:grid;
            grid-template-columns:repeat(2,minmax(180px,1fr));
            gap:10px 16px;
            margin-bottom:14px;
        }

        .contact-meta div{
            display:flex;
            align-items:center;
            gap:8px;
        }

        @media (max-width: 980px){
            .content{
                grid-template-columns:1fr;
            }
            .right{
                min-height:auto;
            }
            .contact-meta{
                grid-template-columns:1fr;
            }
        }

        @media (max-width: 640px){
            .grid-groups{
                grid-template-columns:repeat(2,1fr);
            }

            .row2,
            .urgency{
                grid-template-columns:1fr;
            }

            .donor,
            .request-item{
                flex-direction:column;
                align-items:flex-start;
            }

            .actions{
                margin-left:0;
                width:100%;
            }

            .actions .act{
                flex:1;
                justify-content:center;
            }
        }
    </style>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="page-title">Request Blood</div>

<div class="shell">

    <section class="hero">
        <div class="hero-inner">
            <div>
                <h1><i class="fa-solid fa-hand-holding-heart"></i> Request Blood</h1>
                <p>
                    Submit a blood request with urgency, location, and timing details.
                    RaktaBindu will help show matching verified donors based on blood group and availability.
                </p>
            </div>

            <div class="hero-badges">
                <div class="hero-badge"><i class="fa-solid fa-users"></i> Verified Donors</div>
                <div class="hero-badge"><i class="fa-solid fa-clock"></i> Quick Matching</div>
                <div class="hero-badge"><i class="fa-solid fa-location-dot"></i> Location Based</div>
            </div>
        </div>
    </section>

    <div class="content">
        <div class="card left">
            <div class="head">
                <div class="badge"><i class="fa-solid fa-droplet"></i></div>
                <div>
                    <h2>Blood Request Form</h2>
                    <p>Fill in the details carefully to find suitable donors.</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="msg ok">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php echo e($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="msg bad">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" id="requestForm">
                <div class="section">
                    <div class="label">Blood Group Required <span class="req">*</span></div>
                    <input type="hidden" name="blood_group" id="blood_group" value="<?php echo e($_POST['blood_group'] ?? ''); ?>">

                    <div class="grid-groups" id="groupGrid">
                        <?php
                        $selected = $_POST['blood_group'] ?? "";
                        $groups = ["A+","A-","B+","B-","O+","O-","AB+","AB-"];
                        foreach ($groups as $g) {
                            $active = ($selected === $g) ? "active" : "";
                            echo '<button type="button" class="gbtn '.$active.'" data-group="'.e($g).'">'.e($g).'</button>';
                        }
                        ?>
                    </div>
                </div>

                <div class="section">
                    <div class="label">Quantity (Units) <span class="req">*</span></div>
                    <div class="qty">
                        <button type="button" class="qbtn" id="minusBtn">−</button>
                        <input class="qinput" name="quantity" id="qtyInput" type="number" min="1" max="10" value="<?php echo e($_POST['quantity'] ?? '1'); ?>">
                        <button type="button" class="qbtn" id="plusBtn">+</button>
                    </div>
                </div>

                <div class="section">
                    <div class="label">Hospital / Location <span class="req">*</span></div>
                    <div class="control">
                        <i class="fa-solid fa-hospital"></i>
                        <input class="input" name="hospital_location" type="text" placeholder="Enter hospital name or address" value="<?php echo e($_POST['hospital_location'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="section">
                    <div class="label">Urgency Level <span class="req">*</span></div>
                    <input type="hidden" name="urgency" id="urgency" value="<?php echo e($_POST['urgency'] ?? 'Normal'); ?>">

                    <?php $urg = $_POST['urgency'] ?? 'Normal'; ?>
                    <div class="urgency" id="urgencyWrap">
                        <button type="button" class="ubox <?php echo ($urg === 'Normal') ? 'active' : ''; ?>" data-urg="Normal">
                            <i class="fa-solid fa-circle-info" style="color:#3b82f6"></i>
                            Normal
                            <small>Within 24-48 hours</small>
                        </button>

                        <button type="button" class="ubox <?php echo ($urg === 'Emergency') ? 'active' : ''; ?>" data-urg="Emergency">
                            <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444"></i>
                            Emergency
                            <small>Immediate need</small>
                        </button>
                    </div>
                </div>

                <div class="section">
                    <div class="row2">
                        <div>
                            <div class="label">Needed Date <span class="req">*</span></div>
                            <div class="control">
                                <i class="fa-regular fa-calendar"></i>
                                <input class="input" type="date" name="needed_date" min="<?php echo e(date('Y-m-d')); ?>" value="<?php echo e($_POST['needed_date'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div>
                            <div class="label">Needed Time <span class="req">*</span></div>
                            <div class="control">
                                <i class="fa-regular fa-clock"></i>
                                <input class="input" type="time" name="needed_time" value="<?php echo e($_POST['needed_time'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="label">Patient Notes (Optional)</div>
                    <textarea name="patient_notes" placeholder="Add any additional information about the patient or requirements..."><?php echo e($_POST['patient_notes'] ?? ''); ?></textarea>
                </div>

                <button class="submit" type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i> Find Available Donors
                </button>
            </form>
        </div>

        <div class="card right">
            <div class="center-hero">
                <div class="bigicon"><i class="fa-solid fa-users"></i></div>
                <h3>Find Matching Donors</h3>
                <p>Submit your request to view verified donors based on blood group, availability, and nearby location.</p>

                <div class="chips">
                    <div class="chip"><i class="fa-solid fa-circle-check"></i> Real-time matching</div>
                    <div class="chip"><i class="fa-solid fa-circle-check"></i> Verified donors</div>
                    <div class="chip"><i class="fa-solid fa-circle-check"></i> Quick contact</div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="match-wrap">
                    <div class="match-title">
                        <h4><i class="fa-solid fa-filter"></i> Matching Donors</h4>
                        <span class="pillcount"><?php echo count($matches); ?> found</span>
                    </div>

                    <?php if (!$matches): ?>
                        <div class="empty-match">
                            No matching donors found right now. Try using a broader location name or check again later.
                        </div>
                    <?php else: ?>
                        <div class="donor-list">
                            <?php foreach ($matches as $d): ?>
                                <?php
                                $dn = trim(($d["firstName"] ?? "") . " " . ($d["lastName"] ?? ""));
                                $initial = strtoupper(mb_substr(($d["firstName"] ?? "D"), 0, 1));
                                $loc = $d["location"] ?? "";
                                $avail = $d["availability"] ?? "";
                                $phone = $d["phone"] ?? "";
                                $email = $d["email"] ?? "";
                                ?>
                                <div class="donor">
                                    <div class="dava"><?php echo e($initial); ?></div>

                                    <div>
                                        <b><?php echo e($dn ?: "Donor"); ?> • <?php echo e($d["bloodType"] ?? ""); ?></b>
                                        <small>
                                            <i class="fa-solid fa-location-dot"></i> <?php echo e($loc); ?><br>
                                            <i class="fa-regular fa-clock"></i> <?php echo e($avail); ?>
                                        </small>
                                    </div>

                                    <div class="actions">
                                        <button type="button" class="act primary" onclick="alert('Call: <?php echo e($phone); ?>')">
                                            <i class="fa-solid fa-phone"></i> Call
                                        </button>

                                        <button type="button" class="act" onclick="alert('Email: <?php echo e($email); ?>')">
                                            <i class="fa-regular fa-envelope"></i> Email
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-match" style="margin-top:20px;">
                    Submit a blood request to see matching donors here.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isLoggedIn): ?>
        <div class="card my-requests-card">
            <div class="section-topbar">
                <div class="head" style="margin-bottom:0;">
                    <div class="badge"><i class="fa-regular fa-file-lines"></i></div>
                    <div>
                        <h2>My Recent Requests</h2>
                        <p>Track your latest blood requests directly from this page.</p>
                    </div>
                </div>

                <a href="my-requests.php" class="act primary">
                    <i class="fa-regular fa-eye"></i> View All Requests
                </a>
            </div>

            <?php if (!$myRequests): ?>
                <div class="empty-match">
                    You have not created any blood requests yet.
                </div>
            <?php else: ?>
                <div class="request-history">
                    <?php foreach ($myRequests as $r): ?>
                        <?php
                        $status = trim((string)($r['status'] ?? 'Open'));
                        $urgencyClass = (($r['urgency'] ?? '') === 'Emergency') ? 'req-emergency' : 'req-normal';

                        $statusClass = 'req-open';
                        if ($status === 'Accepted') {
                            $statusClass = 'req-accepted';
                        } elseif ($status === 'Closed' || $status === 'Cancelled') {
                            $statusClass = 'req-closed';
                        }
                        ?>
                        <div class="request-item">
                            <div class="request-item-left">
                                <div class="request-item-title">
                                    <span><?php echo e($r['blood_group']); ?> Blood Request</span>
                                    <span class="req-pill <?php echo e($urgencyClass); ?>"><?php echo e($r['urgency']); ?></span>
                                    <span class="req-pill <?php echo e($statusClass); ?>"><?php echo e($status); ?></span>
                                </div>

                                <div class="request-item-meta">
                                    <i class="fa-solid fa-droplet"></i> <?php echo (int)$r['quantity']; ?> unit(s)
                                    &nbsp; • &nbsp;
                                    <i class="fa-solid fa-location-dot"></i> <?php echo e($r['hospital_location']); ?>
                                    &nbsp; • &nbsp;
                                    <i class="fa-regular fa-calendar"></i> <?php echo e(formatDateSafe($r['needed_date'] ?? '')); ?>
                                    &nbsp; • &nbsp;
                                    <i class="fa-regular fa-clock"></i> <?php echo e($r['needed_time']); ?>
                                    &nbsp; • &nbsp;
                                    <i class="fa-solid fa-calendar-plus"></i> <?php echo e(formatDateTimeSafe($r['created_at'] ?? '')); ?>
                                </div>

                                <?php if (!empty($r['patient_notes'])): ?>
                                    <div class="request-item-notes">
                                        <i class="fa-regular fa-note-sticky"></i>
                                        <?php echo e($r['patient_notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card my-requests-card">
            <div class="section-topbar">
                <div class="head" style="margin-bottom:0;">
                    <div class="badge"><i class="fa-solid fa-address-book"></i></div>
                    <div>
                        <h2>Accepted Donors - Contact Now</h2>
                        <p>When a donor accepts your request, their contact details appear here.</p>
                    </div>
                </div>

                <a href="my-requests.php" class="act primary">
                    <i class="fa-regular fa-eye"></i> Open Full Page
                </a>
            </div>

            <?php if (!$acceptedDonors): ?>
                <div class="empty-match">No donor has accepted your request yet.</div>
            <?php else: ?>
                <div class="contact-list">
                    <?php foreach ($acceptedDonors as $d): ?>
                        <div class="contact-card">
                            <div class="contact-title">
                                <span><?php echo e($d['blood_group']); ?> Request</span>
                                <span class="req-pill req-accepted">Accepted</span>
                            </div>

                            <div class="contact-meta">
                                <div><i class="fa-solid fa-user"></i> <?php echo e(trim($d['donor_name']) ?: 'Donor'); ?></div>
                                <div><i class="fa-solid fa-location-dot"></i> <?php echo e($d['hospital_location']); ?></div>
                                <div><i class="fa-regular fa-envelope"></i> <?php echo e($d['donor_email'] ?: 'Not available'); ?></div>
                                <div><i class="fa-solid fa-phone"></i> <?php echo e($d['donor_phone'] ?: 'Not available'); ?></div>
                            </div>

                            <div class="actions" style="margin-left:0;">
                                <?php if (!empty($d['donor_phone'])): ?>
                                    <a class="act primary" href="tel:<?php echo e($d['donor_phone']); ?>">
                                        <i class="fa-solid fa-phone"></i> Call Donor
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($d['donor_email'])): ?>
                                    <a class="act" href="mailto:<?php echo e($d['donor_email']); ?>">
                                        <i class="fa-regular fa-envelope"></i> Email
                                    </a>
                                <?php endif; ?>

                                <a class="act" href="chat.php?request_id=<?php echo (int)$d['request_id']; ?>&user_id=<?php echo (int)$d['donor_id']; ?>">
                                    <i class="fa-regular fa-comment-dots"></i> Message
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
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

    const qty = document.getElementById("qtyInput");
    const minus = document.getElementById("minusBtn");
    const plus = document.getElementById("plusBtn");

    function clampQty(v) {
        v = parseInt(v || "1", 10);
        if (isNaN(v)) v = 1;
        if (v < 1) v = 1;
        if (v > 10) v = 10;
        return v;
    }

    if (qty) {
        qty.value = clampQty(qty.value);
        qty.addEventListener("input", () => {
            qty.value = clampQty(qty.value);
        });
    }

    if (minus && qty) {
        minus.addEventListener("click", () => {
            qty.value = clampQty((+qty.value || 1) - 1);
        });
    }

    if (plus && qty) {
        plus.addEventListener("click", () => {
            qty.value = clampQty((+qty.value || 1) + 1);
        });
    }

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

    const form = document.getElementById("requestForm");
    if (form) {
        form.addEventListener("submit", (e) => {
            const bg = (bloodInput?.value || "").trim();
            if (!bg) {
                e.preventDefault();
                alert("Please select blood group required.");
            }
        });
    }
</script>

</body>
</html>