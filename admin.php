<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/db.php";
date_default_timezone_set("Asia/Kathmandu");

// =====================================
// ADMIN AUTH GUARD
// =====================================
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['user_name'] ?? 'Admin';
$success = "";
$error = "";

// =====================================
// CSRF
// =====================================
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

// =====================================
// ACTIVE SECTION
// =====================================
$section = clean($_GET['section'] ?? 'dashboard');
$allowedSections = ['dashboard', 'users', 'requests', 'donations', 'hospitals', 'matches', 'notifications'];
if (!in_array($section, $allowedSections, true)) {
    $section = 'dashboard';
}

// =====================================
// HANDLE POST ACTIONS
// =====================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_ok()) {
        $error = "Security check failed. Please refresh and try again.";
    } else {
        $action = clean($_POST['action'] ?? '');

        // -------------------------
        // VERIFY / UNVERIFY USER
        // -------------------------
        if ($action === 'toggle_user_verification') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $new_status = (int)($_POST['new_status'] ?? 0);

            if ($user_id < 1) {
                $error = "Invalid user.";
            } else {
                $stmt = $conn->prepare("
                    UPDATE users
                    SET is_verified = ?
                    WHERE id = ?
                    LIMIT 1
                ");
                if (!$stmt) {
                    $error = "DB Error: " . $conn->error;
                } else {
                    $stmt->bind_param("ii", $new_status, $user_id);
                    if ($stmt->execute()) {
                        $success = $new_status === 1 ? "User verified successfully." : "User verification removed.";
                    } else {
                        $error = "Failed to update user verification.";
                    }
                    $stmt->close();
                }
            }
        }

        // -------------------------
        // CHANGE USER ROLE
        // -------------------------
        elseif ($action === 'change_user_role') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $new_role = clean($_POST['new_role'] ?? 'user');

            if ($user_id < 1 || !in_array($new_role, ['user', 'admin'], true)) {
                $error = "Invalid role update.";
            } else {
                $stmt = $conn->prepare("
                    UPDATE users
                    SET role = ?
                    WHERE id = ?
                    LIMIT 1
                ");
                if (!$stmt) {
                    $error = "DB Error: " . $conn->error;
                } else {
                    $stmt->bind_param("si", $new_role, $user_id);
                    if ($stmt->execute()) {
                        $success = "User role updated successfully.";
                    } else {
                        $error = "Failed to update role.";
                    }
                    $stmt->close();
                }
            }
        }

        // -------------------------
        // UPDATE BLOOD REQUEST STATUS
        // -------------------------
        elseif ($action === 'update_request_status') {
            $request_id = (int)($_POST['request_id'] ?? 0);
            $new_status = clean($_POST['new_status'] ?? '');

            $validStatuses = ['Open', 'Accepted', 'Fulfilled', 'Closed'];

            if ($request_id < 1 || !in_array($new_status, $validStatuses, true)) {
                $error = "Invalid request update.";
            } else {
                $stmt = $conn->prepare("
                    UPDATE blood_requests
                    SET status = ?
                    WHERE id = ?
                    LIMIT 1
                ");
                if (!$stmt) {
                    $error = "DB Error: " . $conn->error;
                } else {
                    $stmt->bind_param("si", $new_status, $request_id);
                    if ($stmt->execute()) {
                        $success = "Blood request status updated.";
                    } else {
                        $error = "Failed to update request.";
                    }
                    $stmt->close();
                }
            }
        }

        // -------------------------
        // TOGGLE HOSPITAL ACTIVE
        // -------------------------
        elseif ($action === 'toggle_hospital_status') {
            $hospital_id = (int)($_POST['hospital_id'] ?? 0);
            $new_status = (int)($_POST['new_status'] ?? 0);

            if ($hospital_id < 1) {
                $error = "Invalid hospital.";
            } else {
                $stmt = $conn->prepare("
                    UPDATE hospitals
                    SET is_active = ?
                    WHERE id = ?
                    LIMIT 1
                ");
                if (!$stmt) {
                    $error = "DB Error: " . $conn->error;
                } else {
                    $stmt->bind_param("ii", $new_status, $hospital_id);
                    if ($stmt->execute()) {
                        $success = $new_status === 1 ? "Hospital activated." : "Hospital deactivated.";
                    } else {
                        $error = "Failed to update hospital.";
                    }
                    $stmt->close();
                }
            }
        }

        // -------------------------
        // ADD HOSPITAL
        // -------------------------
        elseif ($action === 'add_hospital') {
            $name = clean($_POST['name'] ?? '');
            $city = clean($_POST['city'] ?? '');

            if ($name === '' || $city === '') {
                $error = "Please enter hospital name and city.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO hospitals (name, city, is_active)
                    VALUES (?, ?, 1)
                ");
                if (!$stmt) {
                    $error = "DB Error: " . $conn->error;
                } else {
                    $stmt->bind_param("ss", $name, $city);
                    if ($stmt->execute()) {
                        $success = "Hospital added successfully.";
                    } else {
                        $error = "Failed to add hospital.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// =====================================
// SUMMARY COUNTS
// =====================================
function fetch_count(mysqli $conn, string $sql): int {
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_row()) {
        return (int)$row[0];
    }
    return 0;
}

$totalUsers       = fetch_count($conn, "SELECT COUNT(*) FROM users");
$verifiedUsers    = fetch_count($conn, "SELECT COUNT(*) FROM users WHERE is_verified = 1");
$totalRequests    = fetch_count($conn, "SELECT COUNT(*) FROM blood_requests");
$openRequests     = fetch_count($conn, "SELECT COUNT(*) FROM blood_requests WHERE status = 'Open'");
$totalDonations   = fetch_count($conn, "SELECT COUNT(*) FROM donations");
$totalHospitals   = fetch_count($conn, "SELECT COUNT(*) FROM hospitals");
$activeHospitals  = fetch_count($conn, "SELECT COUNT(*) FROM hospitals WHERE is_active = 1");
$totalMatches     = fetch_count($conn, "SELECT COUNT(*) FROM request_matches");
$totalNotify      = fetch_count($conn, "SELECT COUNT(*) FROM notifications");

// =====================================
// FETCH DATA LISTS
// =====================================
$users = [];
$requests = [];
$donations = [];
$hospitals = [];
$matches = [];
$notifications = [];

// USERS
$uq = $conn->query("
    SELECT id, firstName, lastName, email, phone, bloodType, location, availability, is_verified, role
    FROM users
    ORDER BY id DESC
    LIMIT 100
");
if ($uq) {
    while ($row = $uq->fetch_assoc()) $users[] = $row;
}

// REQUESTS
$rq = $conn->query("
    SELECT id, user_id, blood_group, quantity, hospital_location, urgency,
           needed_date, needed_time, patient_notes, status, created_at
    FROM blood_requests
    ORDER BY id DESC
    LIMIT 100
");
if ($rq) {
    while ($row = $rq->fetch_assoc()) $requests[] = $row;
}

// DONATIONS
$dq = $conn->query("
    SELECT id, user_id, full_name, blood_group, contact, email, hospital_id,
           preferred_date, preferred_time, availability
    FROM donations
    ORDER BY id DESC
    LIMIT 100
");
if ($dq) {
    while ($row = $dq->fetch_assoc()) $donations[] = $row;
}

// HOSPITALS
$hq = $conn->query("
    SELECT id, name, city, is_active
    FROM hospitals
    ORDER BY id DESC
    LIMIT 100
");
if ($hq) {
    while ($row = $hq->fetch_assoc()) $hospitals[] = $row;
}

// MATCHES
$mq = $conn->query("
    SELECT rm.id, rm.request_id, rm.donor_id, rm.status, rm.accepted_at,
           br.blood_group, br.hospital_location
    FROM request_matches rm
    LEFT JOIN blood_requests br ON br.id = rm.request_id
    ORDER BY rm.id DESC
    LIMIT 100
");
if ($mq) {
    while ($row = $mq->fetch_assoc()) $matches[] = $row;
}

// NOTIFICATIONS
$nq = $conn->query("
    SELECT id, user_id, type, title, message, link, is_read, created_at
    FROM notifications
    ORDER BY id DESC
    LIMIT 100
");
if ($nq) {
    while ($row = $nq->fetch_assoc()) $notifications[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | RaktaBindu</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

<style>
:root{
    --red:#c62828;
    --red-dark:#b71c1c;
    --bg:#f6f7fb;
    --card:#ffffff;
    --text:#1f2430;
    --muted:#667085;
    --line:rgba(0,0,0,.08);
    --shadow:0 18px 50px rgba(0,0,0,.10);
    --shadow2:0 10px 28px rgba(0,0,0,.08);
    --green:#2e7d32;
    --orange:#ef6c00;
}

*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:"Segoe UI", Arial, sans-serif;
    background:var(--bg);
    color:var(--text);
}

.wrapper{
    display:grid;
    grid-template-columns:280px 1fr;
    min-height:100vh;
}

.sidebar{
    background:var(--red-dark);
    color:#fff;
    padding:24px 18px;
    position:sticky;
    top:0;
    height:100vh;
}

.logo{
    display:flex;
    align-items:center;
    gap:12px;
    font-weight:900;
    font-size:24px;
    margin-bottom:30px;
}
.logo-badge{
    width:40px;
    height:40px;
    border-radius:12px;
    background:rgba(255,255,255,.12);
    display:grid;
    place-items:center;
}
.logo b{color:#ff8a80}

.admin-mini{
    padding:14px;
    border:1px solid rgba(255,255,255,.12);
    border-radius:16px;
    background:rgba(255,255,255,.04);
    margin-bottom:20px;
    font-size:14px;
}
.admin-mini .name{
    font-weight:900;
    margin-bottom:4px;
}
.admin-mini .role{
    color:#cbd5e1;
    font-size:12px;
}

.side-links{
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-top:8px;
}

.side-links a{
    color:#d1d5db;
    text-decoration:none;
    padding:12px 14px;
    border-radius:14px;
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:700;
    transition:.2s ease;
}

.side-links a:hover,
.side-links a.active{
    background:rgba(198,40,40,.16);
    color:#fff;
}

.side-bottom{
    margin-top:24px;
}
.side-bottom a{
    display:inline-flex;
    align-items:center;
    gap:8px;
    text-decoration:none;
    background:var(--red);
    color:#fff;
    padding:12px 14px;
    border-radius:14px;
    font-weight:800;
}
.side-bottom a:hover{background:var(--red-dark)}

.content{
    padding:24px;
}

.topbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    margin-bottom:18px;
}

.title-wrap h1{
    font-size:28px;
    font-weight:1000;
}
.title-wrap p{
    margin-top:6px;
    color:var(--muted);
    font-size:14px;
}

.header-actions{
    display:flex;
    gap:10px;
    align-items:center;
}

.badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:14px;
    background:#fff;
    border:1px solid var(--line);
    font-weight:800;
    color:#475467;
}

.msg{
    padding:12px 14px;
    border-radius:14px;
    font-size:13px;
    font-weight:800;
    margin-bottom:14px;
    border:1px solid;
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

.summary-grid{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:14px;
    margin-bottom:18px;
}

.summary-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:20px;
    box-shadow:var(--shadow2);
    padding:18px;
}
.summary-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:10px;
}
.summary-top i{
    color:var(--red);
    font-size:18px;
}
.summary-label{
    color:var(--muted);
    font-size:12px;
    font-weight:800;
}
.summary-value{
    font-size:28px;
    font-weight:1000;
    color:var(--text);
}
.summary-sub{
    margin-top:6px;
    font-size:12px;
    color:var(--muted);
}

.card{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:22px;
    box-shadow:var(--shadow2);
    padding:18px;
    margin-bottom:18px;
}

.card-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:14px;
}
.card-head h2{
    font-size:18px;
    font-weight:1000;
}
.card-head p{
    margin-top:4px;
    color:var(--muted);
    font-size:13px;
}

.table-wrap{
    overflow:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:900px;
}

th, td{
    padding:12px 10px;
    border-bottom:1px solid #eef2f7;
    text-align:left;
    vertical-align:top;
    font-size:13px;
}

th{
    color:#475467;
    font-size:12px;
    font-weight:900;
    background:#fafbfc;
}

td strong{font-weight:900}

.pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    border-radius:999px;
    padding:6px 10px;
    font-size:11px;
    font-weight:900;
    border:1px solid rgba(0,0,0,.08);
    white-space:nowrap;
}
.pill.green{
    color:var(--green);
    background:rgba(46,125,50,.08);
    border-color:rgba(46,125,50,.18);
}
.pill.red{
    color:#b71c1c;
    background:rgba(183,28,28,.08);
    border-color:rgba(183,28,28,.16);
}
.pill.orange{
    color:var(--orange);
    background:rgba(239,108,0,.08);
    border-color:rgba(239,108,0,.16);
}
.pill.gray{
    color:#475467;
    background:#f8fafc;
    border-color:#e5e7eb;
}

.inline-form{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
}

select, input[type="text"]{
    height:38px;
    border:1px solid rgba(0,0,0,.12);
    border-radius:10px;
    padding:0 12px;
    background:#fff;
    font-size:13px;
}

.btn{
    height:38px;
    border:none;
    border-radius:10px;
    padding:0 12px;
    cursor:pointer;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    gap:8px;
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

.muted{
    color:var(--muted);
    font-size:12px;
    line-height:1.6;
}

.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr auto;
    gap:12px;
}

.empty{
    padding:18px;
    border:1px dashed #d0d5dd;
    border-radius:16px;
    color:var(--muted);
    background:#fafafa;
    font-size:13px;
}

.quick-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

.list-stack{
    display:flex;
    flex-direction:column;
    gap:10px;
}

.list-item{
    border:1px solid var(--line);
    border-radius:14px;
    padding:12px 14px;
    background:#fff;
}
.list-item .top{
    display:flex;
    justify-content:space-between;
    gap:12px;
    margin-bottom:6px;
}
.list-item .meta{
    color:var(--muted);
    font-size:12px;
    line-height:1.6;
}

@media (max-width: 1100px){
    .wrapper{
        grid-template-columns:1fr;
    }
    .sidebar{
        position:relative;
        height:auto;
    }
    .summary-grid{
        grid-template-columns:repeat(2, 1fr);
    }
    .quick-grid{
        grid-template-columns:1fr;
    }
}

@media (max-width: 640px){
    .summary-grid{
        grid-template-columns:1fr;
    }
    .form-grid{
        grid-template-columns:1fr;
    }
}
</style>
</head>
<body>

<div class="wrapper">

    <aside class="sidebar">
        <div class="logo">
            <div class="logo-badge"><i class="fa-solid fa-droplet"></i></div>
            Rakta<b>Bindu</b>
        </div>

        <div class="admin-mini">
            <div class="name"><?php echo htmlspecialchars($adminName); ?></div>
            <div class="role">Administrator Panel</div>
        </div>

        <nav class="side-links">
            <a href="admin.php?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
            <a href="admin.php?section=users" class="<?php echo $section === 'users' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i> Users
            </a>
            <a href="admin.php?section=requests" class="<?php echo $section === 'requests' ? 'active' : ''; ?>">
                <i class="fa-solid fa-hand-holding-droplet"></i> Blood Requests
            </a>
            <a href="admin.php?section=donations" class="<?php echo $section === 'donations' ? 'active' : ''; ?>">
                <i class="fa-solid fa-heart-circle-check"></i> Donations
            </a>
            <a href="admin.php?section=hospitals" class="<?php echo $section === 'hospitals' ? 'active' : ''; ?>">
                <i class="fa-solid fa-hospital"></i> Hospitals
            </a>
            <a href="admin.php?section=matches" class="<?php echo $section === 'matches' ? 'active' : ''; ?>">
                <i class="fa-solid fa-link"></i> Matches
            </a>
            <a href="admin.php?section=notifications" class="<?php echo $section === 'notifications' ? 'active' : ''; ?>">
                <i class="fa-solid fa-bell"></i> Notifications
            </a>
        </nav>

        <div class="side-bottom">
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </aside>

    <main class="content">

        <div class="topbar">
            <div class="title-wrap">
                <h1>Admin Dashboard</h1>
                <p>Manage users, blood requests, donations, hospitals, and system activity.</p>
            </div>
            <div class="header-actions">
                <div class="badge"><i class="fa-solid fa-user-shield"></i> Admin Access</div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="msg ok"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="msg bad"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-top">
                    <span class="summary-label">Total Users</span>
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="summary-value"><?php echo $totalUsers; ?></div>
                <div class="summary-sub"><?php echo $verifiedUsers; ?> verified accounts</div>
            </div>

            <div class="summary-card">
                <div class="summary-top">
                    <span class="summary-label">Blood Requests</span>
                    <i class="fa-solid fa-hand-holding-droplet"></i>
                </div>
                <div class="summary-value"><?php echo $totalRequests; ?></div>
                <div class="summary-sub"><?php echo $openRequests; ?> currently open</div>
            </div>

            <div class="summary-card">
                <div class="summary-top">
                    <span class="summary-label">Donations</span>
                    <i class="fa-solid fa-heart-circle-check"></i>
                </div>
                <div class="summary-value"><?php echo $totalDonations; ?></div>
                <div class="summary-sub"><?php echo $totalMatches; ?> request matches</div>
            </div>

            <div class="summary-card">
                <div class="summary-top">
                    <span class="summary-label">Hospitals</span>
                    <i class="fa-solid fa-hospital"></i>
                </div>
                <div class="summary-value"><?php echo $totalHospitals; ?></div>
                <div class="summary-sub"><?php echo $activeHospitals; ?> active hospitals</div>
            </div>
        </div>

        <?php if ($section === 'dashboard'): ?>

            <div class="quick-grid">
                <div class="card">
                    <div class="card-head">
                        <div>
                            <h2>Latest Blood Requests</h2>
                            <p>Recently submitted requests.</p>
                        </div>
                    </div>

                    <?php if (!$requests): ?>
                        <div class="empty">No blood requests found.</div>
                    <?php else: ?>
                        <div class="list-stack">
                            <?php foreach (array_slice($requests, 0, 6) as $r): ?>
                                <div class="list-item">
                                    <div class="top">
                                        <strong><?php echo htmlspecialchars($r['blood_group']); ?> • <?php echo (int)$r['quantity']; ?> unit(s)</strong>
                                        <span class="pill <?php echo $r['status'] === 'Open' ? 'orange' : ($r['status'] === 'Fulfilled' ? 'green' : 'gray'); ?>">
                                            <?php echo htmlspecialchars($r['status']); ?>
                                        </span>
                                    </div>
                                    <div class="meta">
                                        <?php echo htmlspecialchars($r['hospital_location']); ?> •
                                        <?php echo htmlspecialchars($r['urgency']); ?> •
                                        <?php echo htmlspecialchars($r['needed_date'] ?: 'N/A'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-head">
                        <div>
                            <h2>Latest Notifications</h2>
                            <p>Recent system activity alerts.</p>
                        </div>
                    </div>

                    <?php if (!$notifications): ?>
                        <div class="empty">No notifications found.</div>
                    <?php else: ?>
                        <div class="list-stack">
                            <?php foreach (array_slice($notifications, 0, 6) as $n): ?>
                                <div class="list-item">
                                    <div class="top">
                                        <strong><?php echo htmlspecialchars($n['title'] ?: 'Notification'); ?></strong>
                                        <span class="pill <?php echo (int)$n['is_read'] === 1 ? 'gray' : 'orange'; ?>">
                                            <?php echo (int)$n['is_read'] === 1 ? 'Read' : 'Unread'; ?>
                                        </span>
                                    </div>
                                    <div class="meta">
                                        User #<?php echo (int)$n['user_id']; ?> • <?php echo htmlspecialchars($n['type'] ?: 'general'); ?><br>
                                        <?php echo htmlspecialchars($n['message'] ?: ''); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($section === 'users'): ?>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>Manage Users</h2>
                        <p>Verify users and control admin/user roles.</p>
                    </div>
                </div>

                <?php if (!$users): ?>
                    <div class="empty">No users found.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email / Phone</th>
                                    <th>Blood / Location</th>
                                    <th>Availability</th>
                                    <th>Verified</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>#<?php echo (int)$u['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '')) ?: ($u['firstName'] ?? 'User')); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($u['email'] ?? ''); ?><br>
                                        <span class="muted"><?php echo htmlspecialchars($u['phone'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($u['bloodType'] ?? 'N/A'); ?><br>
                                        <span class="muted"><?php echo htmlspecialchars($u['location'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['availability'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ((int)$u['is_verified'] === 1): ?>
                                            <span class="pill green"><i class="fa-solid fa-circle-check"></i> Verified</span>
                                        <?php else: ?>
                                            <span class="pill red"><i class="fa-solid fa-circle-xmark"></i> Not Verified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($u['role'] ?? 'user') === 'admin'): ?>
                                            <span class="pill orange"><i class="fa-solid fa-user-shield"></i> Admin</span>
                                        <?php else: ?>
                                            <span class="pill gray"><i class="fa-regular fa-user"></i> User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="inline-form" style="margin-bottom:8px;">
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                                                <input type="hidden" name="action" value="toggle_user_verification">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo (int)$u['is_verified'] === 1 ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-ghost">
                                                    <?php echo (int)$u['is_verified'] === 1 ? 'Unverify' : 'Verify'; ?>
                                                </button>
                                            </form>
                                        </div>

                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                                            <input type="hidden" name="action" value="change_user_role">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <select name="new_role">
                                                <option value="user" <?php echo (($u['role'] ?? 'user') === 'user') ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?php echo (($u['role'] ?? 'user') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary">Save Role</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($section === 'requests'): ?>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>Manage Blood Requests</h2>
                        <p>Track request urgency and update request status.</p>
                    </div>
                </div>

                <?php if (!$requests): ?>
                    <div class="empty">No blood requests found.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Blood</th>
                                    <th>Location</th>
                                    <th>Date / Time</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                   
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td>#<?php echo (int)$r['id']; ?></td>
                                    <td>User #<?php echo (int)($r['user_id'] ?? 0); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['blood_group']); ?></strong><br>
                                        <span class="muted"><?php echo (int)$r['quantity']; ?> unit(s)</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['hospital_location']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($r['needed_date'] ?: 'N/A'); ?><br>
                                        <span class="muted"><?php echo htmlspecialchars($r['needed_time'] ?: 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <?php if (($r['urgency'] ?? '') === 'Emergency'): ?>
                                            <span class="pill red">Emergency</span>
                                        <?php else: ?>
                                            <span class="pill gray">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            $statusClass = 'gray';
                                            if (($r['status'] ?? '') === 'Open') $statusClass = 'orange';
                                            elseif (($r['status'] ?? '') === 'Fulfilled') $statusClass = 'green';
                                            elseif (($r['status'] ?? '') === 'Accepted') $statusClass = 'red';
                                        ?>
                                        <span class="pill <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($r['status'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="muted"><?php echo htmlspecialchars($r['patient_notes'] ?: 'No notes'); ?></td>
                                    <td>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                                            <input type="hidden" name="action" value="update_request_status">
                                            <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                                            <select name="new_status">
                                                <?php
                                                $statuses = ['Open','Accepted','Fulfilled','Closed'];
                                                foreach ($statuses as $st) {
                                                    $sel = (($r['status'] ?? '') === $st) ? 'selected' : '';
                                                    echo "<option value=\"".htmlspecialchars($st)."\" $sel>".htmlspecialchars($st)."</option>";
                                                }
                                                ?>
                                            </select>
                                            
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($section === 'donations'): ?>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>Manage Donations</h2>
                        <p>Review scheduled donor appointments.</p>
                    </div>
                </div>

                <?php if (!$donations): ?>
                    <div class="empty">No donations found.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Name</th>
                                    <th>Blood</th>
                                    <th>Contact</th>
                                    <th>Hospital</th>
                                    <th>Date / Time</th>
                                    <th>Availability</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($donations as $d): ?>
                                <tr>
                                    <td>#<?php echo (int)$d['id']; ?></td>
                                    <td>User #<?php echo (int)($d['user_id'] ?? 0); ?></td>
                                    <td><strong><?php echo htmlspecialchars($d['full_name'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($d['blood_group'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($d['contact'] ?? 'N/A'); ?><br>
                                        <span class="muted"><?php echo htmlspecialchars($d['email'] ?? ''); ?></span>
                                    </td>
                                    <td>Hospital #<?php echo (int)($d['hospital_id'] ?? 0); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($d['preferred_date'] ?? 'N/A'); ?><br>
                                        <span class="muted"><?php echo htmlspecialchars($d['preferred_time'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($d['availability'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($section === 'hospitals'): ?>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>Add Hospital</h2>
                        <p>Create new hospital or blood center entry.</p>
                    </div>
                </div>

                <form method="POST" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                    <input type="hidden" name="action" value="add_hospital">

                    <input type="text" name="name" placeholder="Hospital name" required>
                    <input type="text" name="city" placeholder="City" required>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Hospital</button>
                </form>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>Manage Hospitals</h2>
                        <p>Activate or deactivate hospital availability.</p>
                    </div>
                </div>

                <?php if (!$hospitals): ?>
                    <div class="empty">No hospitals found.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>City</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($hospitals as $h): ?>
                                <tr>
                                    <td>#<?php echo (int)$h['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($h['name'] ?? ''); ?></strong></td>
                                    <td><?php echo htmlspecialchars($h['city'] ?? ''); ?></td>
                                    <td>
                                        <?php if ((int)$h['is_active'] === 1): ?>
                                            <span class="pill green">Active</span>
                                        <?php else: ?>
                                            <span class="pill red">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                                            <input type="hidden" name="action" value="toggle_hospital_status">
                                            <input type="hidden" name="hospital_id" value="<?php echo (int)$h['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo (int)$h['is_active'] === 1 ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-ghost">
                                                <?php echo (int)$h['is_active'] === 1 ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($section === 'matches'): ?>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>Request Matches</h2>
                        <p>Track donor responses to blood requests.</p>
                    </div>
                </div>

                <?php if (!$matches): ?>
                    <div class="empty">No matches found.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Request</th>
                                    <th>Donor</th>
                                    <th>Blood</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Accepted At</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($matches as $m): ?>
                                <tr>
                                    <td>#<?php echo (int)$m['id']; ?></td>
                                    <td>Request #<?php echo (int)($m['request_id'] ?? 0); ?></td>
                                    <td>Donor #<?php echo (int)($m['donor_id'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($m['blood_group'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($m['hospital_location'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (($m['status'] ?? '') === 'Accepted'): ?>
                                            <span class="pill green">Accepted</span>
                                        <?php else: ?>
                                            <span class="pill red">Declined</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($m['accepted_at'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($section === 'notifications'): ?>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>Notifications</h2>
                        <p>Monitor recent notification records.</p>
                    </div>
                </div>

                <?php if (!$notifications): ?>
                    <div class="empty">No notifications found.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Message</th>
                                    <th>Link</th>
                                    <th>Read</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($notifications as $n): ?>
                                <tr>
                                    <td>#<?php echo (int)$n['id']; ?></td>
                                    <td>User #<?php echo (int)($n['user_id'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($n['type'] ?? 'N/A'); ?></td>
                                    <td><strong><?php echo htmlspecialchars($n['title'] ?? ''); ?></strong></td>
                                    <td class="muted"><?php echo htmlspecialchars($n['message'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($n['link'] ?? ''); ?></td>
                                    <td>
                                        <?php if ((int)$n['is_read'] === 1): ?>
                                            <span class="pill green">Read</span>
                                        <?php else: ?>
                                            <span class="pill orange">Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($n['created_at'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </main>
</div>

</body>
</html>