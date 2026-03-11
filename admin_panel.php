<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/db.php";
date_default_timezone_set("Asia/Kathmandu");

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
| Put your real admin email here if role column is not ready yet.
*/
$allowedAdminEmails = ['admin@raktabindu.com'];

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $sql = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0;
}

function getCount(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_row()) {
        return (int)($row[0] ?? 0);
    }
    return 0;
}

function safeQuery(mysqli $conn, string $sql) {
    $result = $conn->query($sql);
    return $result ?: false;
}

/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/
if (!tableExists($conn, 'users')) {
    die("Users table not found. Please create the users table first.");
}

$userSql = "SELECT * FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($userSql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$userResult = $stmt->get_result();

if (!$userResult || $userResult->num_rows !== 1) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$currentUser = $userResult->fetch_assoc();

$firstName = $currentUser['firstName'] ?? 'Admin';
$email     = strtolower(trim($currentUser['email'] ?? ''));
$role      = strtolower(trim($currentUser['role'] ?? ''));

/*
|--------------------------------------------------------------------------
| ADMIN CHECK
|--------------------------------------------------------------------------
| Professional way: role='admin'
| Temporary fallback: admin email in allowed list
*/
$isAdmin = false;

if (columnExists($conn, 'users', 'role') && $role === 'admin') {
    $isAdmin = true;
}

if (in_array($email, array_map('strtolower', $allowedAdminEmails), true)) {
    $isAdmin = true;
}

if (!$isAdmin) {
    header("Location: index.php");
    exit();
}

/* Keep session synced */
$_SESSION['user_name'] = $firstName;
$_SESSION['role'] = 'admin';

/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/
$totalUsers = 0;
$totalDonors = 0;
$totalRecipients = 0;
$totalHospitals = 0;
$totalRequests = 0;
$urgentRequests = 0;
$pendingRequests = 0;
$totalDonations = 0;

if (tableExists($conn, 'users')) {
    $totalUsers = getCount($conn, "SELECT COUNT(*) FROM users");

    if (columnExists($conn, 'users', 'role')) {
        $totalDonors = getCount($conn, "SELECT COUNT(*) FROM users WHERE role='donor'");
        $totalRecipients = getCount($conn, "SELECT COUNT(*) FROM users WHERE role='recipient'");
    }
}

if (tableExists($conn, 'hospitals')) {
    $totalHospitals = getCount($conn, "SELECT COUNT(*) FROM hospitals");
}

if (tableExists($conn, 'blood_requests')) {
    $totalRequests = getCount($conn, "SELECT COUNT(*) FROM blood_requests");

    if (columnExists($conn, 'blood_requests', 'urgency')) {
        $urgentRequests = getCount($conn, "SELECT COUNT(*) FROM blood_requests WHERE urgency='Urgent'");
    }

    if (columnExists($conn, 'blood_requests', 'status')) {
        $pendingRequests = getCount($conn, "SELECT COUNT(*) FROM blood_requests WHERE status='Pending'");
    }
}

if (tableExists($conn, 'donations')) {
    $totalDonations = getCount($conn, "SELECT COUNT(*) FROM donations");
}

/*
|--------------------------------------------------------------------------
| RECENT USERS
|--------------------------------------------------------------------------
*/
$recentUsers = false;
if (
    tableExists($conn, 'users') &&
    columnExists($conn, 'users', 'id') &&
    columnExists($conn, 'users', 'firstName') &&
    columnExists($conn, 'users', 'email')
) {
    $orderColumn = columnExists($conn, 'users', 'created_at') ? 'created_at' : 'id';

    $recentUsers = safeQuery($conn, "
        SELECT id, firstName, email" .
        (columnExists($conn, 'users', 'role') ? ", role" : "") . "
        FROM users
        ORDER BY $orderColumn DESC
        LIMIT 6
    ");
}

/*
|--------------------------------------------------------------------------
| RECENT BLOOD REQUESTS
|--------------------------------------------------------------------------
*/
$recentRequests = false;
$hasBloodRequestColumns =
    tableExists($conn, 'blood_requests') &&
    columnExists($conn, 'blood_requests', 'request_id') &&
    columnExists($conn, 'blood_requests', 'patient_name') &&
    columnExists($conn, 'blood_requests', 'blood_group');

if ($hasBloodRequestColumns) {
    $hospitalCol = columnExists($conn, 'blood_requests', 'hospital_location') ? "hospital_location" : "NULL AS hospital_location";
    $urgencyCol  = columnExists($conn, 'blood_requests', 'urgency') ? "urgency" : "'Normal' AS urgency";
    $statusCol   = columnExists($conn, 'blood_requests', 'status') ? "status" : "'Pending' AS status";
    $orderColumn = columnExists($conn, 'blood_requests', 'created_at') ? "created_at" : "request_id";

    $recentRequests = safeQuery($conn, "
        SELECT
            request_id,
            patient_name,
            blood_group,
            $hospitalCol,
            $urgencyCol,
            $statusCol
        FROM blood_requests
        ORDER BY $orderColumn DESC
        LIMIT 6
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | RaktaBindu</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root{
            --red:#c62828;
            --dark-red:#b71c1c;
            --light-red:#ffebee;
            --soft:#fff5f5;
            --white:#ffffff;
            --text:#1f2937;
            --muted:#6b7280;
            --line:#e5e7eb;
            --green:#16a34a;
            --orange:#f59e0b;
            --blue:#2563eb;
            --shadow:0 12px 30px rgba(0,0,0,.08);
            --radius:20px;
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family:Segoe UI, Arial, sans-serif;
            background:#f8f9fa;
            color:var(--text);
        }

        a{
            text-decoration:none;
            color:inherit;
        }

        .layout{
            display:flex;
            min-height:100vh;
        }

        .sidebar{
            width:270px;
            background:linear-gradient(180deg, var(--red), var(--dark-red));
            color:#fff;
            padding:26px 18px;
            position:sticky;
            top:0;
            height:100vh;
        }

        .brand{
            display:flex;
            align-items:center;
            gap:12px;
            font-size:24px;
            font-weight:800;
            margin-bottom:28px;
        }

        .drop{
            width:18px;
            height:28px;
            background:#fff;
            border-radius:50% 50% 50% 50% / 60% 60% 40% 40%;
            transform:rotate(45deg);
        }

        .admin-box{
            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.15);
            padding:16px;
            border-radius:16px;
            margin-bottom:24px;
        }

        .admin-box p{
            font-size:13px;
            opacity:.9;
        }

        .admin-box h3{
            margin-top:5px;
            font-size:18px;
        }

        .menu-title{
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:1px;
            margin-bottom:10px;
            opacity:.8;
        }

        .nav{
            display:flex;
            flex-direction:column;
            gap:10px;
        }

        .nav a{
            padding:13px 14px;
            border-radius:12px;
            font-size:14px;
            font-weight:600;
            transition:.2s ease;
        }

        .nav a:hover,
        .nav a.active{
            background:#fff;
            color:var(--red);
        }

        .logout-btn{
            margin-top:22px;
            display:block;
            text-align:center;
            background:#fff;
            color:var(--red);
            padding:13px;
            border-radius:12px;
            font-weight:700;
        }

        .main{
            flex:1;
            padding:28px;
        }

        .topbar{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:15px;
            flex-wrap:wrap;
            margin-bottom:24px;
        }

        .topbar h1{
            font-size:30px;
            margin-bottom:6px;
        }

        .topbar p{
            color:var(--muted);
        }

        .btn{
            display:inline-block;
            padding:12px 18px;
            border-radius:12px;
            font-weight:700;
            font-size:14px;
        }

        .btn-primary{
            background:var(--red);
            color:#fff;
        }

        .btn-primary:hover{
            background:var(--dark-red);
        }

        .btn-light{
            background:#fff;
            border:1px solid var(--line);
        }

        .banner{
            background:linear-gradient(90deg, #fff1f2, #ffe4e6);
            border:1px solid #fecdd3;
            color:#9f1239;
            padding:18px;
            border-radius:16px;
            margin-bottom:24px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:15px;
            flex-wrap:wrap;
            box-shadow:var(--shadow);
        }

        .stats{
            display:grid;
            grid-template-columns:repeat(4, 1fr);
            gap:18px;
            margin-bottom:24px;
        }

        .card{
            background:#fff;
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:22px;
        }

        .stat-label{
            font-size:14px;
            color:var(--muted);
            margin-bottom:8px;
        }

        .stat-value{
            font-size:30px;
            font-weight:800;
        }

        .stat-note{
            margin-top:8px;
            font-size:13px;
            font-weight:600;
        }

        .red{ color:var(--red); }
        .green{ color:var(--green); }
        .orange{ color:var(--orange); }
        .blue{ color:var(--blue); }

        .grid{
            display:grid;
            grid-template-columns:2fr 1fr;
            gap:20px;
            margin-bottom:20px;
        }

        .section-head{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:16px;
            gap:12px;
            flex-wrap:wrap;
        }

        .section-head h3{
            font-size:20px;
        }

        .section-head a{
            color:var(--red);
            font-weight:700;
            font-size:14px;
        }

        table{
            width:100%;
            border-collapse:collapse;
        }

        th, td{
            text-align:left;
            padding:14px 10px;
            border-bottom:1px solid var(--line);
            font-size:14px;
        }

        th{
            color:var(--muted);
            font-size:13px;
        }

        .badge{
            display:inline-block;
            padding:6px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:700;
        }

        .badge-red{ background:#fee2e2; color:#b91c1c; }
        .badge-green{ background:#dcfce7; color:#166534; }
        .badge-orange{ background:#fef3c7; color:#92400e; }
        .badge-blue{ background:#dbeafe; color:#1d4ed8; }

        .quick-actions{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:14px;
        }

        .action-box{
            border:1px solid var(--line);
            border-radius:16px;
            padding:18px;
            background:#fff;
            transition:.2s ease;
        }

        .action-box:hover{
            transform:translateY(-2px);
            box-shadow:var(--shadow);
            border-color:#fca5a5;
        }

        .action-box h4{
            margin-bottom:6px;
            font-size:15px;
        }

        .action-box p{
            color:var(--muted);
            font-size:13px;
            line-height:1.5;
        }

        .bottom-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:20px;
        }

        .mini-list{
            display:flex;
            flex-direction:column;
            gap:14px;
        }

        .mini-item{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            padding:14px;
            border:1px solid var(--line);
            border-radius:14px;
            background:#fcfcfd;
        }

        .mini-item h4{
            font-size:14px;
            margin-bottom:4px;
        }

        .mini-item p{
            font-size:12px;
            color:var(--muted);
        }

        .setup-note{
            background:#fff7ed;
            color:#9a3412;
            border:1px solid #fdba74;
            padding:14px;
            border-radius:14px;
            font-size:14px;
        }

        @media (max-width: 1200px){
            .stats{ grid-template-columns:repeat(2,1fr); }
            .grid, .bottom-grid{ grid-template-columns:1fr; }
        }

        @media (max-width: 900px){
            .layout{ flex-direction:column; }
            .sidebar{
                width:100%;
                height:auto;
                position:relative;
            }
        }

        @media (max-width: 640px){
            .main{ padding:18px; }
            .stats{ grid-template-columns:1fr; }
            .quick-actions{ grid-template-columns:1fr; }
            .topbar h1{ font-size:24px; }
            th, td{ font-size:12px; padding:10px 6px; }
        }
    </style>
</head>
<body>

<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <div class="drop"></div>
            <span>RaktaBindu</span>
        </div>

        <div class="admin-box">
            <p>Welcome back,</p>
            <h3><?php echo e($firstName); ?></h3>
            <p>System Administrator</p>
        </div>

        <div class="menu-title">Main Menu</div>
        <nav class="nav">
            <a href="admin_panel.php" class="active"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a href="manage_users.php"><i class="fa-solid fa-users"></i> Manage Users</a>
            <a href="manage_requests.php"><i class="fa-solid fa-droplet"></i> Blood Requests</a>
            <a href="manage_donations.php"><i class="fa-solid fa-hand-holding-droplet"></i> Donations</a>
            <a href="manage_hospitals.php"><i class="fa-solid fa-hospital"></i> Hospitals</a>
            <a href="notifications.php"><i class="fa-solid fa-bell"></i> Notifications</a>
            <a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a>
        </nav>

        <a href="logout.php" class="logout-btn">Logout</a>
    </aside>

    <main class="main">

        <div class="topbar">
            <div>
                <h1>Admin Dashboard</h1>
                <p>Manage donors, recipients, requests, hospitals, and donation activity.</p>
            </div>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <a href="manage_requests.php" class="btn btn-light">View Requests</a>
                <a href="manage_users.php" class="btn btn-primary">Manage Users</a>
            </div>
        </div>

        <div class="banner">
            <div>
                <strong>Urgent Alert:</strong>
                There are <b><?php echo $urgentRequests; ?></b> urgent blood requests in the system.
            </div>
            <a href="manage_requests.php" class="btn btn-primary">Review Now</a>
        </div>

        <section class="stats">
            <div class="card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo $totalUsers; ?></div>
                <div class="stat-note blue">Registered accounts</div>
            </div>

            <div class="card">
                <div class="stat-label">Total Donors</div>
                <div class="stat-value"><?php echo $totalDonors; ?></div>
                <div class="stat-note green">Available donor profiles</div>
            </div>

            <div class="card">
                <div class="stat-label">Blood Requests</div>
                <div class="stat-value"><?php echo $totalRequests; ?></div>
                <div class="stat-note red"><?php echo $urgentRequests; ?> urgent cases</div>
            </div>

            <div class="card">
                <div class="stat-label">Total Donations</div>
                <div class="stat-value"><?php echo $totalDonations; ?></div>
                <div class="stat-note orange">Recorded donation entries</div>
            </div>

            <div class="card">
                <div class="stat-label">Recipients</div>
                <div class="stat-value"><?php echo $totalRecipients; ?></div>
                <div class="stat-note blue">Recipient accounts</div>
            </div>

            <div class="card">
                <div class="stat-label">Hospitals</div>
                <div class="stat-value"><?php echo $totalHospitals; ?></div>
                <div class="stat-note green">Hospital partners</div>
            </div>

            <div class="card">
                <div class="stat-label">Pending Requests</div>
                <div class="stat-value"><?php echo $pendingRequests; ?></div>
                <div class="stat-note orange">Need review</div>
            </div>

            <div class="card">
                <div class="stat-label">System Status</div>
                <div class="stat-value" style="font-size:24px;">Active</div>
                <div class="stat-note green">Dashboard running</div>
            </div>
        </section>

        <section class="grid">
            <div class="card">
                <div class="section-head">
                    <h3>Recent Blood Requests</h3>
                    <a href="manage_requests.php">See all</a>
                </div>

                <?php if ($recentRequests && $recentRequests->num_rows > 0): ?>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Blood Group</th>
                                    <th>Hospital</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $recentRequests->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo e($row['request_id']); ?></td>
                                        <td><?php echo e($row['patient_name']); ?></td>
                                        <td><strong><?php echo e($row['blood_group']); ?></strong></td>
                                        <td><?php echo e($row['hospital_location'] ?? '-'); ?></td>
                                        <td>
                                            <?php
                                                $urgency = strtolower(trim($row['urgency'] ?? 'normal'));
                                                if ($urgency === 'urgent') {
                                                    echo '<span class="badge badge-red">Urgent</span>';
                                                } elseif ($urgency === 'high') {
                                                    echo '<span class="badge badge-orange">High</span>';
                                                } else {
                                                    echo '<span class="badge badge-blue">Normal</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                $status = strtolower(trim($row['status'] ?? 'pending'));
                                                if ($status === 'completed') {
                                                    echo '<span class="badge badge-green">Completed</span>';
                                                } elseif ($status === 'pending') {
                                                    echo '<span class="badge badge-orange">Pending</span>';
                                                } elseif ($status === 'approved' || $status === 'matched') {
                                                    echo '<span class="badge badge-blue">' . e($row['status']) . '</span>';
                                                } else {
                                                    echo '<span class="badge badge-blue">' . e($row['status']) . '</span>';
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="setup-note">
                        No blood request table data found yet. If you have not created `blood_requests`, create it first.
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="section-head">
                    <h3>Quick Actions</h3>
                </div>

                <div class="quick-actions">
                    <a href="manage_users.php" class="action-box">
                        <h4>Manage Users</h4>
                        <p>View, edit, block, or remove users from the system.</p>
                    </a>

                    <a href="manage_requests.php" class="action-box">
                        <h4>Blood Requests</h4>
                        <p>Review urgent requests and update statuses quickly.</p>
                    </a>

                    <a href="manage_donations.php" class="action-box">
                        <h4>Donation Records</h4>
                        <p>Check completed and scheduled donations.</p>
                    </a>

                    <a href="manage_hospitals.php" class="action-box">
                        <h4>Hospitals</h4>
                        <p>Add or manage hospital listings and contacts.</p>
                    </a>

                    <a href="notifications.php" class="action-box">
                        <h4>Notifications</h4>
                        <p>Send alerts to users when needed.</p>
                    </a>

                    <a href="reports.php" class="action-box">
                        <h4>Reports</h4>
                        <p>Track blood requests and donor activity.</p>
                    </a>
                </div>
            </div>
        </section>

        <section class="bottom-grid">
            <div class="card">
                <div class="section-head">
                    <h3>Recently Registered Users</h3>
                    <a href="manage_users.php">See all</a>
                </div>

                <?php if ($recentUsers && $recentUsers->num_rows > 0): ?>
                    <div class="mini-list">
                        <?php while ($u = $recentUsers->fetch_assoc()): ?>
                            <div class="mini-item">
                                <div>
                                    <h4><?php echo e($u['firstName'] ?? 'User'); ?></h4>
                                    <p><?php echo e($u['email'] ?? ''); ?></p>
                                </div>
                                <div>
                                    <?php
                                        $uRole = strtolower(trim($u['role'] ?? 'user'));
                                        if ($uRole === 'admin') {
                                            echo '<span class="badge badge-red">Admin</span>';
                                        } elseif ($uRole === 'donor') {
                                            echo '<span class="badge badge-green">Donor</span>';
                                        } elseif ($uRole === 'recipient') {
                                            echo '<span class="badge badge-blue">Recipient</span>';
                                        } else {
                                            echo '<span class="badge badge-orange">User</span>';
                                        }
                                    ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="setup-note">
                        No recent users found yet.
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="section-head">
                    <h3>Admin Overview</h3>
                </div>

                <div class="mini-list">
                    <div class="mini-item">
                        <div>
                            <h4>Urgent Cases</h4>
                            <p><?php echo $urgentRequests; ?> urgent requests need immediate attention.</p>
                        </div>
                        <span class="badge badge-red">Critical</span>
                    </div>

                    <div class="mini-item">
                        <div>
                            <h4>Pending Reviews</h4>
                            <p><?php echo $pendingRequests; ?> requests are waiting for review.</p>
                        </div>
                        <span class="badge badge-orange">Pending</span>
                    </div>

                    <div class="mini-item">
                        <div>
                            <h4>Hospital Network</h4>
                            <p><?php echo $totalHospitals; ?> hospitals are currently in the system.</p>
                        </div>
                        <span class="badge badge-blue">Connected</span>
                    </div>

                    <div class="mini-item">
                        <div>
                            <h4>Donation Activity</h4>
                            <p><?php echo $totalDonations; ?> donation records are available.</p>
                        </div>
                        <span class="badge badge-green">Healthy</span>
                    </div>
                </div>
            </div>
        </section>

    </main>
</div>

</body>
</html>