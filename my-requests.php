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
$success = "";
$error = "";

function esc($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
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
    return $ts ? date("M d, Y • h:i A", $ts) : esc($dt);
}

function badgeClass(string $status): string {
    $s = strtolower(trim($status));
    if ($s === "approved" || $s === "accepted" || $s === "completed") return "badge badge-ok";
    if ($s === "pending" || $s === "") return "badge badge-warn";
    if ($s === "cancelled" || $s === "closed") return "badge badge-bad";
    return "badge badge-warn";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["request_id"])) {
    $action = trim((string)$_POST["action"]);
    $rid = (int)($_POST["request_id"] ?? 0);

    if ($action === "cancel" && $rid > 0) {
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
                $error = "Unable to cancel (maybe already accepted/completed).";
            }
            $up->close();
        }
    }
}

$requests = [];
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

if (!$stmt) {
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
        while ($row = $res->fetch_assoc()) {
            $requests[] = $row;
        }
        $stmt2->close();
    }
} else {
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
}

$acceptedDonors = [];
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Requests | RaktaBindu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

    <style>
        :root{
            --primary:#c62828;
            --primary-dark:#a61b1b;
            --primary-soft:#fff1f1;
            --green:#166534;
            --green-soft:#dcfce7;
            --yellow:#92400e;
            --yellow-soft:#fef3c7;
            --red:#991b1b;
            --red-soft:#fee2e2;
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
            width:min(1180px,92%);
            margin:10px auto 10px;
            color:#98a2b3;
            font-weight:900;
            font-size:13px;
        }

        .shell{
            width:min(1180px,92%);
            margin:0 auto 30px;
        }

        .hero{
            background:linear-gradient(135deg,#d32f2f 0%,#c62828 55%,#a61b1b 100%);
            color:#fff;
            border-radius:26px;
            padding:28px;
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
            max-width:64ch;
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
            grid-template-columns:1.12fr .88fr;
            gap:18px;
            align-items:start;
        }

        .stack{
            display:flex;
            flex-direction:column;
            gap:18px;
        }

        .card{
            background:var(--card);
            border:1px solid var(--line);
            border-radius:18px;
            box-shadow:var(--shadow2);
        }

        .card-body{
            padding:18px;
        }

        .section-head{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:14px;
        }

        .section-head-left{
            display:flex;
            gap:12px;
            align-items:flex-start;
        }

        .badge-icon{
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

        .section-head h2{
            font-size:18px;
            font-weight:1000;
            line-height:1.2;
        }

        .section-head p{
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
            background:#e8f5e9;
            border-color:#c8e6c9;
            color:#2e7d32;
        }

        .msg.bad{
            background:#ffebee;
            border-color:#ffcdd2;
            color:#b71c1c;
        }

        .btn{
            height:36px;
            padding:0 12px;
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

        .btn.red{
            background:rgba(198,40,40,.10);
            border-color:rgba(198,40,40,.20);
            color:var(--primary);
        }

        .btn.green{
            background:var(--green-soft);
            border-color:rgba(22,101,52,.15);
            color:var(--green);
        }

        .btn:hover{
            transform:translateY(-1px);
            box-shadow:0 10px 20px rgba(0,0,0,.08);
        }

        .empty{
            color:#98a2b3;
            font-weight:1000;
            font-size:13px;
            line-height:1.8;
            padding:16px 2px 4px;
        }

        .request-list,
        .contact-list{
            display:flex;
            flex-direction:column;
            gap:12px;
        }

        .request-card,
        .contact-card{
            border:1px solid rgba(0,0,0,.08);
            border-radius:16px;
            padding:14px;
            background:#fff;
        }

        .request-top,
        .contact-title{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            font-size:14px;
            font-weight:1000;
            line-height:1.5;
        }

        .meta{
            margin-top:8px;
            font-size:12px;
            color:var(--muted);
            line-height:1.8;
        }

        .notes{
            margin-top:10px;
            padding:10px 12px;
            border-radius:12px;
            background:#f8fafc;
            border:1px solid rgba(0,0,0,.06);
            font-size:12px;
            color:#475467;
            line-height:1.7;
        }

        .pill{
            display:inline-flex;
            align-items:center;
            padding:6px 10px;
            border-radius:999px;
            font-weight:1000;
            font-size:11px;
            white-space:nowrap;
        }

        .pill-status-ok{
            background:rgba(34,197,94,.10);
            color:#166534;
            border:1px solid rgba(34,197,94,.25);
        }

        .pill-status-warn{
            background:rgba(245,158,11,.10);
            color:#92400e;
            border:1px solid rgba(245,158,11,.25);
        }

        .pill-status-bad{
            background:rgba(239,68,68,.10);
            color:#991b1b;
            border:1px solid rgba(239,68,68,.25);
        }

        .pill-emergency{
            background:#fee2e2;
            color:#991b1b;
            border:1px solid rgba(239,68,68,.22);
        }

        .pill-normal{
            background:#fef3c7;
            color:#92400e;
            border:1px solid rgba(245,158,11,.22);
        }

        .actions{
            margin-top:12px;
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }

        .contact-meta{
            display:grid;
            grid-template-columns:repeat(2,minmax(180px,1fr));
            gap:10px 16px;
            font-size:12px;
            color:#667085;
            margin:12px 0 14px;
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

            .contact-meta{
                grid-template-columns:1fr;
            }
        }

        @media (max-width: 640px){
            .actions{
                width:100%;
            }

            .actions .btn{
                flex:1;
                justify-content:center;
            }
        }
    </style>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="page-title">My Blood Requests</div>

<div class="shell">
    <section class="hero">
        <div class="hero-inner">
            <div>
                <h1><i class="fa-regular fa-file-lines"></i> My Requests</h1>
                <p>
                    Track all of your submitted blood requests, monitor their status,
                    cancel pending ones when needed, and contact donors who have accepted.
                </p>
            </div>

            <div class="hero-badges">
                <div class="hero-badge"><i class="fa-solid fa-chart-line"></i> Status Tracking</div>
                <div class="hero-badge"><i class="fa-solid fa-phone"></i> Donor Contact</div>
                <div class="hero-badge"><i class="fa-solid fa-shield-heart"></i> Safer Coordination</div>
            </div>
        </div>
    </section>

    <div class="content">
        <div class="card">
            <div class="card-body">
                <div class="section-head">
                    <div class="section-head-left">
                        <div class="badge-icon"><i class="fa-solid fa-droplet"></i></div>
                        <div>
                            <h2>Your Requests</h2>
                            <p>Track request status and manage pending requests.</p>
                        </div>
                    </div>

                    <a class="btn" href="request-blood.php">
                        <i class="fa-solid fa-plus"></i> New Request
                    </a>
                </div>

                <?php if ($success): ?>
                    <div class="msg ok">
                        <i class="fa-solid fa-circle-check"></i>
                        <?php echo esc($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="msg bad">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <?php echo esc($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$requests): ?>
                    <div class="empty">No requests yet. Create your first request from “Request Blood”.</div>
                <?php else: ?>
                    <div class="request-list">
                        <?php foreach ($requests as $r): ?>
                            <?php
                            $status = trim((string)($r["status"] ?? "Pending"));
                            $urgency = trim((string)($r["urgency"] ?? "Normal"));
                            $canCancel = (strtolower($status) === "pending" || $status === "");

                            $urgencyClass = strtolower($urgency) === 'emergency' ? 'pill pill-emergency' : 'pill pill-normal';
                            $statusClass = badgeClass($status);
                            ?>
                            <div class="request-card">
                                <div class="request-top">
                                    <span><?php echo esc($r["blood_group"] ?? "—"); ?> Blood Request</span>
                                    <span class="<?php echo esc($urgencyClass); ?>"><?php echo esc($urgency); ?></span>
                                    <span class="<?php echo esc($statusClass); ?>"><?php echo esc($status ?: 'Pending'); ?></span>
                                </div>

                                <div class="meta">
                                    <i class="fa-solid fa-droplet"></i> <?php echo esc($r["quantity"] ?? "—"); ?> unit(s)
                                    &nbsp; • &nbsp;
                                    <i class="fa-solid fa-location-dot"></i> <?php echo esc($r["hospital_location"] ?? "—"); ?>
                                    &nbsp; • &nbsp;
                                    <i class="fa-regular fa-calendar"></i> <?php echo esc(formatDateSafe($r["needed_date"] ?? "")); ?>
                                    <?php if (!empty($r["needed_time"])): ?>
                                        &nbsp; • &nbsp;
                                        <i class="fa-regular fa-clock"></i> <?php echo esc($r["needed_time"]); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($r["created_at"])): ?>
                                        &nbsp; • &nbsp;
                                        <i class="fa-solid fa-calendar-plus"></i> <?php echo esc(formatDateTimeSafe($r["created_at"])); ?>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($r["patient_notes"])): ?>
                                    <div class="notes">
                                        <i class="fa-regular fa-note-sticky"></i>
                                        <?php echo esc($r["patient_notes"]); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="actions">
                                    <button
                                        class="btn"
                                        type="button"
                                        onclick="alert('Notes: <?php echo esc($r['patient_notes'] ?? 'No notes available'); ?>');"
                                    >
                                        <i class="fa-regular fa-note-sticky"></i> Notes
                                    </button>

                                    <?php if ($canCancel): ?>
                                        <form method="POST" onsubmit="return confirm('Cancel this request?');">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                                            <button class="btn red" type="submit">
                                                <i class="fa-solid fa-ban"></i> Cancel
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#98a2b3;font-weight:1000;font-size:11px;display:inline-flex;align-items:center;">
                                            Locked
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-body">
                    <div class="section-head">
                        <div class="section-head-left">
                            <div class="badge-icon"><i class="fa-solid fa-address-book"></i></div>
                            <div>
                                <h2>Accepted Donors</h2>
                                <p>When a donor accepts your request, their contact details appear here.</p>
                            </div>
                        </div>
                    </div>

                    <?php if (!$acceptedDonors): ?>
                        <div class="empty">No donor has accepted your request yet.</div>
                    <?php else: ?>
                        <div class="contact-list">
                            <?php foreach ($acceptedDonors as $d): ?>
                                <div class="contact-card">
                                    <div class="contact-title">
                                        <span><?php echo esc($d['blood_group']); ?> Request</span>
                                        <span class="pill pill-status-ok">Accepted</span>
                                    </div>

                                    <div class="contact-meta">
                                        <div><i class="fa-solid fa-user"></i> <?php echo esc(trim($d['donor_name']) ?: 'Donor'); ?></div>
                                        <div><i class="fa-solid fa-location-dot"></i> <?php echo esc($d['hospital_location']); ?></div>
                                        <div><i class="fa-regular fa-envelope"></i> <?php echo esc($d['donor_email'] ?: 'Not available'); ?></div>
                                        <div><i class="fa-solid fa-phone"></i> <?php echo esc($d['donor_phone'] ?: 'Not available'); ?></div>
                                    </div>

                                    <div class="actions" style="margin-top:0;">
                                        <?php if (!empty($d['donor_phone'])): ?>
                                            <a class="btn green" href="tel:<?php echo esc($d['donor_phone']); ?>">
                                                <i class="fa-solid fa-phone"></i> Call Donor
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($d['donor_email'])): ?>
                                            <a class="btn" href="mailto:<?php echo esc($d['donor_email']); ?>">
                                                <i class="fa-regular fa-envelope"></i> Email
                                            </a>
                                        <?php endif; ?>

                                        <a class="btn" href="chat.php?request_id=<?php echo (int)$d['request_id']; ?>&user_id=<?php echo (int)$d['donor_id']; ?>">
                                            <i class="fa-regular fa-comment-dots"></i> Message
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="section-head">
                        <div class="section-head-left">
                            <div class="badge-icon"><i class="fa-solid fa-circle-info"></i></div>
                            <div>
                                <h2>Request Tips</h2>
                                <p>Helpful reminders while managing blood requests.</p>
                            </div>
                        </div>
                    </div>

                    <div class="request-list">
                        <div class="request-card">
                            <div class="request-top">Keep location specific</div>
                            <div class="meta">Use a clear hospital name or exact place so donors can respond faster.</div>
                        </div>

                        <div class="request-card">
                            <div class="request-top">Emergency requests matter most</div>
                            <div class="meta">Use Emergency only for urgent situations so the system can prioritize properly.</div>
                        </div>

                        <div class="request-card">
                            <div class="request-top">Contact accepted donors quickly</div>
                            <div class="meta">Once a donor accepts, call or message them as soon as possible for coordination.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>