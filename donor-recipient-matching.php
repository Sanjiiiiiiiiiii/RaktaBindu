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
$userName = $_SESSION['user_name'] ?? "User";

$success = "";
$error = "";

/* =========================
   CSRF
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

function csrf_ok(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function clean($v): string {
    return trim((string)$v);
}

/* =========================
   GET REQUEST ID
========================= */
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)($_POST['request_id'] ?? $request_id);
}

if ($request_id < 1) {
    header("Location: donor-recipient-matching.php");
    exit();
}

/* =========================
   FETCH REQUEST
========================= */
$request = null;

$rq = $conn->prepare("
    SELECT
        id,
        user_id,
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
    WHERE id=? AND user_id=?
    LIMIT 1
");
if (!$rq) {
    die("DB Error: " . $conn->error);
}
$rq->bind_param("ii", $request_id, $uid);
$rq->execute();
$request = $rq->get_result()->fetch_assoc();
$rq->close();

if (!$request) {
    die("Request not found or access denied.");
}

/* =========================
   SEND REQUEST TO DONOR
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_request') {

    if (!csrf_ok()) {
        $error = "Security check failed. Please refresh and try again.";
    } else {

        $donor_id = (int)($_POST['donor_id'] ?? 0);

        if ($donor_id < 1) {
            $error = "Invalid donor.";
        } elseif (strtolower((string)$request['status']) === 'cancelled') {
            $error = "This request has been cancelled.";
        } else {

            $conn->begin_transaction();

            try {
                // Check donor exists and is available
                $dq = $conn->prepare("
                    SELECT
                        d.user_id,
                        d.full_name,
                        d.blood_group,
                        d.contact,
                        d.email,
                        d.availability
                    FROM donations d
                    WHERE d.user_id=?
                      AND d.blood_group=?
                      AND d.availability IN ('Available','Emergency Only')
                    ORDER BY d.id DESC
                    LIMIT 1
                ");
                if (!$dq) {
                    throw new Exception("DB Error: " . $conn->error);
                }

                $blood_group = $request['blood_group'];
                $dq->bind_param("is", $donor_id, $blood_group);
                $dq->execute();
                $donor = $dq->get_result()->fetch_assoc();
                $dq->close();

                if (!$donor) {
                    throw new Exception("This donor is no longer available.");
                }

                // Prevent duplicate match
                $check = $conn->prepare("
                    SELECT id
                    FROM request_matches
                    WHERE request_id=? AND donor_id=?
                    LIMIT 1
                ");
                if (!$check) {
                    throw new Exception("DB Error: " . $conn->error);
                }

                $check->bind_param("ii", $request_id, $donor_id);
                $check->execute();
                $exists = $check->get_result()->fetch_assoc();
                $check->close();

                if ($exists) {
                    throw new Exception("You already sent a request to this donor.");
                }

                // Insert match
                $ins = $conn->prepare("
                    INSERT INTO request_matches
                    (request_id, donor_id, status, accepted_at)
                    VALUES (?, ?, 'Pending', NOW())
                ");
                if (!$ins) {
                    throw new Exception("DB Error: " . $conn->error);
                }

                $ins->bind_param("ii", $request_id, $donor_id);
                $ins->execute();
                $ins->close();

                // Notify donor
                $title = "New Blood Request";
                $message = "A recipient needs {$request['blood_group']} blood at {$request['hospital_location']}. Please review the request.";
                $link = "donor-form.php";

                $nn = $conn->prepare("
                    INSERT INTO notifications
                    (user_id, type, title, message, link, is_read, created_at)
                    VALUES (?, 'request', ?, ?, ?, 0, NOW())
                ");
                if (!$nn) {
                    throw new Exception("DB Error: " . $conn->error);
                }

                $nn->bind_param("isss", $donor_id, $title, $message, $link);
                $nn->execute();
                $nn->close();

                $conn->commit();
                $success = "Donation request sent successfully to the donor.";

            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

/* =========================
   MATCH DONORS
========================= */
$matches = [];
$blood_group = $request['blood_group'];

$sql = "
    SELECT
        d.id AS donation_id,
        d.user_id,
        d.full_name,
        d.blood_group,
        d.contact,
        d.email,
        d.preferred_date,
        d.preferred_time,
        d.availability,
        h.name AS hospital_name,
        h.city AS hospital_city,
        rm.id AS already_requested
    FROM donations d
    LEFT JOIN hospitals h
        ON h.id = d.hospital_id
    LEFT JOIN request_matches rm
        ON rm.request_id = ?
       AND rm.donor_id = d.user_id
    WHERE d.blood_group = ?
      AND d.availability IN ('Available','Emergency Only')
    ORDER BY
      CASE d.availability
        WHEN 'Available' THEN 1
        WHEN 'Emergency Only' THEN 2
        ELSE 3
      END,
      d.preferred_date ASC,
      d.preferred_time ASC,
      d.id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("DB Error: " . $conn->error);
}
$stmt->bind_param("is", $request_id, $blood_group);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    // simple matching score
    $score = 75;
    if (($row['availability'] ?? '') === 'Available') $score += 15;
    if (!empty($row['preferred_date']) && $row['preferred_date'] <= $request['needed_date']) $score += 10;
    $row['match_score'] = min($score, 100);

    $matches[] = $row;
}
$stmt->close();

$neededDate = ($request['needed_date'] === '0000-00-00' || $request['needed_date'] === '') ? 'N/A' : $request['needed_date'];
$neededTime = clean($request['needed_time'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Donor–Recipient Matching | RaktaBindu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

    <style>
        :root{
            --red:#c62828;
            --red-dark:#a61e1e;
            --bg:#f6f7fb;
            --card:#ffffff;
            --text:#1f2430;
            --muted:#667085;
            --line:rgba(0,0,0,.08);
            --green:#16a34a;
            --amber:#d97706;
            --blue:#2563eb;
            --shadow:0 14px 34px rgba(0,0,0,.08);
            --radius:22px;
        }

        *{box-sizing:border-box;margin:0;padding:0}

        body{
            font-family:"Segoe UI",system-ui,Arial,sans-serif;
            background:var(--bg);
            color:var(--text);
        }

        .container{
            width:min(1180px,92%);
            margin:0 auto;
        }

        header{
            background:#fff;
            border-bottom:1px solid var(--line);
            box-shadow:0 8px 28px rgba(0,0,0,.05);
            position:sticky;
            top:0;
            z-index:50;
        }

        .topbar{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:14px;
            padding:14px 0;
            flex-wrap:wrap;
        }

        .brand{
            display:flex;
            align-items:center;
            gap:10px;
            font-weight:1000;
        }

        .brand-badge{
            width:36px;height:36px;
            border-radius:12px;
            display:grid;
            place-items:center;
            background:rgba(198,40,40,.10);
            color:var(--red);
        }

        .brand b{color:var(--red)}

        nav ul{
            list-style:none;
            display:flex;
            gap:14px;
            flex-wrap:wrap;
        }

        nav a{
            text-decoration:none;
            color:#394150;
            font-size:13px;
            font-weight:900;
            padding:10px 12px;
            border-radius:12px;
            display:inline-flex;
            align-items:center;
            gap:8px;
        }

        nav a:hover,
        nav a.active{
            background:rgba(198,40,40,.08);
            color:var(--red);
        }

        .hero{
            width:min(1180px,92%);
            margin:24px auto 18px;
            border-radius:28px;
            background:linear-gradient(135deg,#e53935 0%, #c62828 58%, #a11414 100%);
            color:#fff;
            box-shadow:0 20px 44px rgba(198,40,40,.20);
            overflow:hidden;
            position:relative;
        }

        .hero::before,
        .hero::after{
            content:"";
            position:absolute;
            border-radius:50%;
            background:rgba(255,255,255,.12);
        }

        .hero::before{width:280px;height:280px;top:-100px;right:-70px}
        .hero::after{width:180px;height:180px;bottom:-60px;right:110px;background:rgba(255,255,255,.08)}

        .hero-inner{
            position:relative;
            padding:28px;
            display:grid;
            grid-template-columns:1.15fr .85fr;
            gap:20px;
            align-items:center;
        }

        .hero h1{
            font-size:30px;
            font-weight:1000;
            display:flex;
            align-items:center;
            gap:12px;
        }

        .hero p{
            margin-top:10px;
            line-height:1.7;
            font-size:14px;
            max-width:65ch;
            opacity:.96;
        }

        .hero-side{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:12px;
        }

        .hero-box{
            background:rgba(255,255,255,.15);
            border:1px solid rgba(255,255,255,.20);
            border-radius:18px;
            padding:16px;
            backdrop-filter:blur(8px);
        }

        .hero-box .label{
            font-size:12px;
            font-weight:800;
            opacity:.92;
        }

        .hero-box .value{
            margin-top:6px;
            font-size:24px;
            font-weight:1000;
        }

        .layout{
            width:min(1180px,92%);
            margin:0 auto 38px;
            display:grid;
            grid-template-columns:.95fr 1.35fr;
            gap:18px;
            align-items:start;
        }

        .card{
            background:var(--card);
            border:1px solid var(--line);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:18px;
        }

        .card-head{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            margin-bottom:14px;
            flex-wrap:wrap;
        }

        .card-head h2{
            font-size:15px;
            font-weight:1000;
            display:flex;
            align-items:center;
            gap:10px;
        }

        .sub{
            color:var(--muted);
            font-size:12px;
            font-weight:800;
            margin-top:-4px;
            margin-bottom:14px;
        }

        .msg{
            padding:12px 14px;
            border-radius:14px;
            font-size:13px;
            font-weight:900;
            margin-bottom:14px;
            border:1px solid;
        }

        .ok{
            background:#e8f5e9;
            border-color:#c8e6c9;
            color:#2e7d32;
        }

        .bad{
            background:#ffebee;
            border-color:#ffcdd2;
            color:#b71c1c;
        }

        .info-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:12px;
        }

        .info{
            border:1px solid rgba(0,0,0,.08);
            border-radius:18px;
            padding:14px;
            background:#fff;
        }

        .info.full{grid-column:1/-1}

        .label{
            font-size:11px;
            font-weight:900;
            color:var(--muted);
            margin-bottom:6px;
        }

        .value{
            font-size:14px;
            font-weight:1000;
            line-height:1.5;
        }

        .urgency{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 10px;
            border-radius:999px;
            font-size:11px;
            font-weight:1000;
        }

        .urgency.emergency{
            color:#fff;
            background:linear-gradient(135deg,#ef4444,#b91c1c);
        }

        .urgency.normal{
            color:#8a5c00;
            background:rgba(245,158,11,.14);
        }

        .match-list{
            display:flex;
            flex-direction:column;
            gap:14px;
        }

        .match{
            border:1px solid rgba(0,0,0,.08);
            border-radius:20px;
            padding:16px;
            background:#fff;
        }

        .match-top{
            display:grid;
            grid-template-columns:1fr auto;
            gap:16px;
            align-items:start;
        }

        .donor-name{
            font-size:16px;
            font-weight:1000;
        }

        .donor-line{
            margin-top:10px;
            display:flex;
            flex-wrap:wrap;
            gap:8px;
        }

        .chip{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 10px;
            border-radius:999px;
            font-size:11px;
            font-weight:1000;
            border:1px solid rgba(0,0,0,.08);
            background:#fafbff;
            color:#344054;
        }

        .chip.red{
            background:rgba(198,40,40,.08);
            color:var(--red);
            border-color:rgba(198,40,40,.16);
        }

        .chip.green{
            background:rgba(22,163,74,.10);
            color:#166534;
            border-color:rgba(22,163,74,.18);
        }

        .score-box{
            min-width:120px;
            text-align:center;
            border:1px solid rgba(0,0,0,.08);
            border-radius:18px;
            padding:14px;
            background:#fafbff;
        }

        .score-box .num{
            font-size:26px;
            font-weight:1000;
            color:var(--red);
        }

        .score-box .txt{
            margin-top:5px;
            font-size:11px;
            color:var(--muted);
            font-weight:900;
        }

        .contact-grid{
            margin-top:14px;
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:12px;
        }

        .contact-box{
            border:1px solid rgba(0,0,0,.08);
            border-radius:16px;
            padding:12px;
            background:#fff;
        }

        .contact-box .t{
            font-size:11px;
            color:var(--muted);
            font-weight:900;
        }

        .contact-box .v{
            margin-top:6px;
            font-size:13px;
            font-weight:1000;
            color:#344054;
            word-break:break-word;
        }

        .actions{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            margin-top:14px;
        }

        .btn{
            height:42px;
            border:none;
            border-radius:12px;
            padding:0 14px;
            font-weight:1000;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            gap:8px;
            text-decoration:none;
        }

        .btn-primary{
            background:var(--red);
            color:#fff;
        }

        .btn-primary:hover{background:var(--red-dark)}

        .btn-ghost{
            background:#fff;
            color:#344054;
            border:1px solid rgba(0,0,0,.12);
        }

        .btn-disabled{
            background:#eef1f6;
            color:#98a2b3;
            cursor:not-allowed;
        }

        .empty{
            padding:18px;
            text-align:center;
            color:#98a2b3;
            font-weight:1000;
            border:1px dashed rgba(0,0,0,.12);
            border-radius:18px;
            background:#fff;
        }

        @media (max-width: 980px){
            .hero-inner,
            .layout{
                grid-template-columns:1fr;
            }

            .hero-side,
            .info-grid,
            .contact-grid{
                grid-template-columns:1fr;
            }

            .match-top{
                grid-template-columns:1fr;
            }
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
                    <li><a href="request-blood.php"><i class="fa-solid fa-droplet"></i> Request Blood</a></li>
                    <li><a class="active" href="#"><i class="fa-solid fa-users"></i> Matching</a></li>
                    <li><a href="my-requests.php"><i class="fa-regular fa-file-lines"></i> My Requests</a></li>
                </ul>
            </nav>
        </div>
    </div>
</header>

<section class="hero">
    <div class="hero-inner">
        <div>
            <h1><i class="fa-solid fa-heart-circle-check"></i> Donor–Recipient Matching</h1>
            <p>
                We found compatible donors for your request. Review the donor profiles below, compare availability,
                and send donation requests to the most suitable donors for faster blood coordination.
            </p>
        </div>

        <div class="hero-side">
            <div class="hero-box">
                <div class="label">Required Blood</div>
                <div class="value"><?= e($request['blood_group']) ?></div>
            </div>
            <div class="hero-box">
                <div class="label">Units Needed</div>
                <div class="value"><?= (int)$request['quantity'] ?></div>
            </div>
            <div class="hero-box">
                <div class="label">Urgency</div>
                <div class="value"><?= e($request['urgency']) ?></div>
            </div>
            <div class="hero-box">
                <div class="label">Matched Donors</div>
                <div class="value"><?= count($matches) ?></div>
            </div>
        </div>
    </div>
</section>

<section class="layout">
    <div class="card">
        <div class="card-head">
            <div>
                <h2><i class="fa-solid fa-file-medical"></i> Request Overview</h2>
                <div class="sub">Important details about the blood request</div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="msg ok"><i class="fa-solid fa-circle-check"></i> <?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="msg bad"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info">
                <div class="label">Blood Group</div>
                <div class="value"><?= e($request['blood_group']) ?></div>
            </div>

            <div class="info">
                <div class="label">Quantity Needed</div>
                <div class="value"><?= (int)$request['quantity'] ?> unit(s)</div>
            </div>

            <div class="info">
                <div class="label">Needed Date</div>
                <div class="value"><?= e($neededDate) ?></div>
            </div>

            <div class="info">
                <div class="label">Needed Time</div>
                <div class="value"><?= e($neededTime ?: 'Not specified') ?></div>
            </div>

            <div class="info full">
                <div class="label">Hospital / Location</div>
                <div class="value"><?= e($request['hospital_location']) ?></div>
            </div>

            <div class="info full">
                <div class="label">Urgency</div>
                <div class="value">
                    <span class="urgency <?= strtolower($request['urgency']) === 'emergency' ? 'emergency' : 'normal' ?>">
                        <i class="fa-solid <?= strtolower($request['urgency']) === 'emergency' ? 'fa-bolt' : 'fa-circle-info' ?>"></i>
                        <?= e($request['urgency']) ?>
                    </span>
                </div>
            </div>

            <div class="info full">
                <div class="label">Patient Notes</div>
                <div class="value"><?= e($request['patient_notes'] ?: 'No extra notes provided.') ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2><i class="fa-solid fa-user-group"></i> Matching Donors</h2>
                <div class="sub">Available and compatible donors for this recipient request</div>
            </div>
        </div>

        <?php if (!$matches): ?>
            <div class="empty">
                No matching donors are available at the moment.
            </div>
        <?php else: ?>
            <div class="match-list">
                <?php foreach ($matches as $m): ?>
                    <?php
                        $alreadySent = !empty($m['already_requested']);
                        $hospitalText = trim(($m['hospital_city'] ?? '') . ' - ' . ($m['hospital_name'] ?? ''), ' -');
                    ?>
                    <div class="match">
                        <div class="match-top">
                            <div>
                                <div class="donor-name"><?= e($m['full_name'] ?: 'Donor') ?></div>

                                <div class="donor-line">
                                    <span class="chip red"><i class="fa-solid fa-droplet"></i> <?= e($m['blood_group']) ?></span>
                                    <span class="chip green"><i class="fa-solid fa-circle-check"></i> <?= e($m['availability']) ?></span>
                                    <?php if ($hospitalText): ?>
                                        <span class="chip"><i class="fa-solid fa-hospital"></i> <?= e($hospitalText) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="contact-grid">
                                    <div class="contact-box">
                                        <div class="t">Phone</div>
                                        <div class="v"><?= e($m['contact'] ?: 'N/A') ?></div>
                                    </div>

                                    <div class="contact-box">
                                        <div class="t">Email</div>
                                        <div class="v"><?= e($m['email'] ?: 'N/A') ?></div>
                                    </div>

                                    <div class="contact-box">
                                        <div class="t">Preferred Date</div>
                                        <div class="v"><?= e($m['preferred_date'] ?: 'Not set') ?></div>
                                    </div>

                                    <div class="contact-box">
                                        <div class="t">Preferred Time</div>
                                        <div class="v"><?= e($m['preferred_time'] ?: 'Not set') ?></div>
                                    </div>
                                </div>

                                <div class="actions">
                                    <?php if ($alreadySent): ?>
                                        <button class="btn btn-disabled" type="button">
                                            <i class="fa-solid fa-paper-plane"></i> Request Already Sent
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= e($CSRF) ?>">
                                            <input type="hidden" name="action" value="send_request">
                                            <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
                                            <input type="hidden" name="donor_id" value="<?= (int)$m['user_id'] ?>">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fa-solid fa-paper-plane"></i> Send Donation Request
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!empty($m['contact'])): ?>
                                        <a class="btn btn-ghost" href="tel:<?= e($m['contact']) ?>">
                                            <i class="fa-solid fa-phone"></i> Call
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($m['email'])): ?>
                                        <a class="btn btn-ghost" href="mailto:<?= e($m['email']) ?>">
                                            <i class="fa-regular fa-envelope"></i> Email
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="score-box">
                                <div class="num"><?= (int)$m['match_score'] ?>%</div>
                                <div class="txt">Match Score</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

</body>
</html>