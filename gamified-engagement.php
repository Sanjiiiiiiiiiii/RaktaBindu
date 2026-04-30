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
$userName = $_SESSION['user_name'] ?? 'Donor';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function calculateLevel(int $points): string {
    if ($points >= 2000) return "Legend Donor";
    if ($points >= 1200) return "Life Saver";
    if ($points >= 700)  return "Hero Donor";
    if ($points >= 300)  return "Active Donor";
    if ($points >= 100)  return "Supporter";
    return "New Donor";
}

function levelProgress(int $points): int {
    if ($points >= 2000) return 100;

    if ($points >= 1200) {
        return (int) min(100, (($points - 1200) / 800) * 100);
    }
    if ($points >= 700) {
        return (int) min(100, (($points - 700) / 500) * 100);
    }
    if ($points >= 300) {
        return (int) min(100, (($points - 300) / 400) * 100);
    }
    if ($points >= 100) {
        return (int) min(100, (($points - 100) / 200) * 100);
    }

    return (int) min(100, ($points / 100) * 100);
}

function nextLevelName(int $points): string {
    if ($points < 100) return "Supporter";
    if ($points < 300) return "Active Donor";
    if ($points < 700) return "Hero Donor";
    if ($points < 1200) return "Life Saver";
    if ($points < 2000) return "Legend Donor";
    return "Max Level";
}

function nextLevelTarget(int $points): int {
    if ($points < 100) return 100;
    if ($points < 300) return 300;
    if ($points < 700) return 700;
    if ($points < 1200) return 1200;
    if ($points < 2000) return 2000;
    return 2000;
}

/*
|--------------------------------------------------------------------------
| Ensure required user columns exist logically in code
| Assumes these columns exist in users table:
| points, level, streak_days
|--------------------------------------------------------------------------
*/

$points = 0;
$level = "New Donor";
$levelProgress = 0;
$streakDays = 0;
$rank = 0;
$totalDonations = 0;
$totalLivesImpacted = 0;
$emergencyDonations = 0;
$totalAcceptedRequests = 0;
$profileCompleted = false;

/*
|--------------------------------------------------------------------------
| Load current user basic gamification data
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT id, firstName, lastName, points, level, streak_days, bloodType, email, phone, location
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user) {
    $points = (int)($user['points'] ?? 0);
    $level = trim((string)($user['level'] ?? ''));
    $streakDays = (int)($user['streak_days'] ?? 0);

    $requiredProfileFields = [
        trim((string)($user['firstName'] ?? '')),
        trim((string)($user['lastName'] ?? '')),
        trim((string)($user['bloodType'] ?? '')),
        trim((string)($user['email'] ?? '')),
        trim((string)($user['phone'] ?? '')),
        trim((string)($user['location'] ?? '')),
    ];

    $profileCompleted = true;
    foreach ($requiredProfileFields as $field) {
        if ($field === '') {
            $profileCompleted = false;
            break;
        }
    }

    $calculatedLevel = calculateLevel($points);

    if ($level === '' || $level !== $calculatedLevel) {
        $level = $calculatedLevel;
        $up = $conn->prepare("UPDATE users SET level = ? WHERE id = ?");
        $up->bind_param("si", $level, $uid);
        $up->execute();
        $up->close();
    }
}

$levelProgress = levelProgress($points);

/*
|--------------------------------------------------------------------------
| Count donations
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM donations
    WHERE user_id = ?
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$totalDonations = (int)($res['total'] ?? 0);

/*
|--------------------------------------------------------------------------
| Count emergency donations
| Assumes donations.availability contains 'Emergency Only'
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM donations
    WHERE user_id = ? AND availability = 'Emergency Only'
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$emergencyDonations = (int)($res['total'] ?? 0);

/*
|--------------------------------------------------------------------------
| Count accepted blood requests handled by donor
| Assumes request_matches has donor_id and status
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM request_matches
    WHERE donor_id = ? AND status = 'Accepted'
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$totalAcceptedRequests = (int)($res['total'] ?? 0);

/*
|--------------------------------------------------------------------------
| Estimated lives impacted
|--------------------------------------------------------------------------
*/
$totalLivesImpacted = $totalDonations * 3;

/*
|--------------------------------------------------------------------------
| Compute rank
|--------------------------------------------------------------------------
*/
$result = $conn->query("
    SELECT id, points
    FROM users
    ORDER BY points DESC, id ASC
");

$rankCounter = 0;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rankCounter++;
        if ((int)$row['id'] === $uid) {
            $rank = $rankCounter;
            break;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Badges
|--------------------------------------------------------------------------
*/
$badges = [
    [
        "title" => "First Drop",
        "icon" => "fa-droplet",
        "earned" => ($totalDonations >= 1)
    ],
    [
        "title" => "Hero Donor",
        "icon" => "fa-medal",
        "earned" => ($totalDonations >= 5)
    ],
    [
        "title" => "Emergency Responder",
        "icon" => "fa-bolt",
        "earned" => ($emergencyDonations >= 3)
    ],
    [
        "title" => "7 Day Streak",
        "icon" => "fa-fire",
        "earned" => ($streakDays >= 7)
    ],
    [
        "title" => "Community Champion",
        "icon" => "fa-users",
        "earned" => ($points >= 1000)
    ],
    [
        "title" => "Legend Donor",
        "icon" => "fa-crown",
        "earned" => ($totalDonations >= 10 && $points >= 1200)
    ],
];

/*
|--------------------------------------------------------------------------
| Missions
|--------------------------------------------------------------------------
*/
$missions = [
    [
        "title" => "Complete your first blood donation",
        "progress" => ($totalDonations >= 1) ? 100 : 0,
        "reward" => 100
    ],
    [
        "title" => "Reach 5 total donations",
        "progress" => min(100, (int)(($totalDonations / 5) * 100)),
        "reward" => 250
    ],
    [
        "title" => "Complete 3 emergency donations",
        "progress" => min(100, (int)(($emergencyDonations / 3) * 100)),
        "reward" => 300
    ],
    [
        "title" => "Maintain 7-day engagement streak",
        "progress" => min(100, (int)(($streakDays / 7) * 100)),
        "reward" => 120
    ],
];

/*
|--------------------------------------------------------------------------
| Leaderboard - top 5 users
|--------------------------------------------------------------------------
*/
$leaderboard = [];
$sql = "
    SELECT 
        u.id,
        u.firstName,
        u.lastName,
        u.points,
        (
            SELECT COUNT(*) 
            FROM donations d
            WHERE d.user_id = u.id
        ) AS donations_count
    FROM users u
    ORDER BY u.points DESC, u.id ASC
    LIMIT 5
";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $fullName = trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? ''));
        if ($fullName === '') {
            $fullName = 'Donor';
        }

        $leaderboard[] = [
            "name" => $fullName,
            "points" => (int)($row['points'] ?? 0),
            "donations" => (int)($row['donations_count'] ?? 0),
            "me" => ((int)$row['id'] === $uid)
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Rewards store
|--------------------------------------------------------------------------
*/
$rewards = [
    ["title" => "Bronze Donor Certificate", "cost" => 300, "icon" => "fa-certificate"],
    ["title" => "Hero Profile Frame", "cost" => 500, "icon" => "fa-image"],
    ["title" => "Priority Event Invite", "cost" => 800, "icon" => "fa-ticket"],
    ["title" => "Gold Donor Recognition", "cost" => 1200, "icon" => "fa-trophy"],
];

$nextLevel = nextLevelName($points);
$nextTarget = nextLevelTarget($points);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gamified Engagement | RaktaBindu</title>
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
      --gold:#f4b400;
      --green:#16a34a;
      --shadow:0 14px 34px rgba(0,0,0,.08);
      --radius:20px;
    }

    *{box-sizing:border-box;margin:0;padding:0}
    body{
      font-family:"Segoe UI",system-ui,Arial,sans-serif;
      background:var(--bg);
      color:var(--text);
    }

    .container{
      width:min(1180px, 92%);
      margin:0 auto;
    }

    header{
      background:#fff;
      border-bottom:1px solid var(--line);
      position:sticky;
      top:0;
      z-index:100;
      box-shadow:0 8px 30px rgba(0,0,0,.05);
    }

    .topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:18px;
      padding:14px 0;
      flex-wrap:wrap;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:1000;
      font-size:20px;
    }

    .brand-badge{
      width:36px;height:36px;
      border-radius:12px;
      display:grid;place-items:center;
      background:rgba(198,40,40,.10);
      color:var(--red);
    }

    .brand span b{color:var(--red)}

    nav ul{
      display:flex;
      gap:12px;
      list-style:none;
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
      margin:24px auto 18px;
      width:min(1180px,92%);
      background:linear-gradient(135deg,#e53935 0%, #c62828 58%, #9f1717 100%);
      color:#fff;
      border-radius:26px;
      box-shadow:0 18px 42px rgba(198,40,40,.22);
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

    .hero::before{width:280px;height:280px;top:-100px;right:-80px}
    .hero::after{width:180px;height:180px;bottom:-70px;right:100px;background:rgba(255,255,255,.08)}

    .hero-inner{
      padding:28px;
      display:grid;
      grid-template-columns:1.3fr .9fr;
      gap:18px;
      align-items:center;
      position:relative;
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
      max-width:62ch;
      line-height:1.7;
      font-size:14px;
      opacity:.95;
    }

    .hero-stats{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:12px;
    }

    .hero-stat{
      background:rgba(255,255,255,.14);
      border:1px solid rgba(255,255,255,.18);
      border-radius:18px;
      padding:16px;
      backdrop-filter:blur(8px);
    }

    .hero-stat .label{
      font-size:12px;
      opacity:.9;
      font-weight:800;
    }

    .hero-stat .value{
      font-size:26px;
      font-weight:1000;
      margin-top:6px;
    }

    .grid{
      width:min(1180px,92%);
      margin:0 auto 36px;
      display:grid;
      grid-template-columns:1.2fr .8fr;
      gap:18px;
      align-items:start;
    }

    .left-col,
    .right-col{
      display:flex;
      flex-direction:column;
      gap:18px;
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
    }

    .level-card{
      display:grid;
      grid-template-columns:110px 1fr;
      gap:16px;
      align-items:center;
    }

    .level-badge{
      width:110px;height:110px;
      border-radius:28px;
      display:grid;
      place-items:center;
      background:linear-gradient(145deg,#ffe4b5,#f4b400);
      color:#7a4a00;
      font-size:40px;
      box-shadow:0 12px 28px rgba(244,180,0,.25);
    }

    .level-name{
      font-size:24px;
      font-weight:1000;
    }

    .point-line{
      margin-top:8px;
      display:flex;
      justify-content:space-between;
      gap:8px;
      font-size:13px;
      font-weight:900;
      color:#344054;
      flex-wrap:wrap;
    }

    .progress{
      margin-top:10px;
      width:100%;
      height:12px;
      border-radius:999px;
      background:#eceff4;
      overflow:hidden;
    }

    .progress > span{
      display:block;
      height:100%;
      width:0;
      background:linear-gradient(90deg,var(--red),#ef5350);
      border-radius:999px;
    }

    .mini-stats{
      margin-top:16px;
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:12px;
    }

    .mini{
      background:#fafbff;
      border:1px solid rgba(0,0,0,.06);
      border-radius:16px;
      padding:14px;
      text-align:center;
    }

    .mini .n{
      font-size:22px;
      font-weight:1000;
      color:var(--red);
    }

    .mini .t{
      margin-top:5px;
      font-size:12px;
      color:var(--muted);
      font-weight:800;
    }

    .badges{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:12px;
    }

    .badge-item{
      border:1px solid rgba(0,0,0,.08);
      border-radius:18px;
      padding:16px 12px;
      text-align:center;
      background:#fff;
      transition:.2s ease;
    }

    .badge-item.earned{
      background:linear-gradient(180deg,#fff8ef,#fff);
      border-color:rgba(244,180,0,.35);
    }

    .badge-item .icon{
      width:52px;height:52px;
      margin:0 auto 10px;
      border-radius:16px;
      display:grid;place-items:center;
      font-size:20px;
      background:rgba(198,40,40,.08);
      color:var(--red);
    }

    .badge-item.earned .icon{
      background:rgba(244,180,0,.16);
      color:#b77900;
    }

    .badge-item .name{
      font-weight:1000;
      font-size:13px;
    }

    .badge-item .state{
      margin-top:6px;
      font-size:11px;
      font-weight:900;
      color:var(--muted);
    }

    .mission-list{
      display:flex;
      flex-direction:column;
      gap:14px;
    }

    .mission{
      border:1px solid rgba(0,0,0,.08);
      border-radius:18px;
      padding:14px;
      background:#fff;
    }

    .mission-top{
      display:flex;
      justify-content:space-between;
      gap:10px;
      align-items:center;
      flex-wrap:wrap;
    }

    .mission-title{
      font-weight:1000;
      font-size:13px;
    }

    .reward{
      font-size:12px;
      font-weight:1000;
      color:var(--red);
      background:rgba(198,40,40,.08);
      padding:8px 10px;
      border-radius:999px;
    }

    .mission .progress{
      margin-top:10px;
      height:10px;
    }

    .progress-text{
      margin-top:8px;
      font-size:11px;
      color:var(--muted);
      font-weight:900;
    }

    .leaderboard{
      display:flex;
      flex-direction:column;
      gap:10px;
    }

    .leader{
      display:grid;
      grid-template-columns:44px 1fr auto;
      gap:12px;
      align-items:center;
      border:1px solid rgba(0,0,0,.08);
      border-radius:16px;
      padding:12px;
      background:#fff;
    }

    .leader.me{
      border-color:rgba(198,40,40,.25);
      background:rgba(198,40,40,.04);
    }

    .rank-badge{
      width:44px;height:44px;
      border-radius:14px;
      display:grid;place-items:center;
      font-weight:1000;
      background:#f4f6fb;
      color:#344054;
    }

    .leader .name{
      font-weight:1000;
      font-size:13px;
    }

    .leader .meta{
      margin-top:4px;
      color:var(--muted);
      font-size:11px;
      font-weight:800;
    }

    .score{
      text-align:right;
      font-weight:1000;
      color:var(--red);
      font-size:14px;
    }

    .score small{
      display:block;
      color:var(--muted);
      font-size:11px;
      margin-top:3px;
    }

    .reward-grid{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:12px;
    }

    .reward-card{
      border:1px solid rgba(0,0,0,.08);
      border-radius:18px;
      padding:16px;
      background:#fff;
    }

    .reward-card .r-icon{
      width:48px;height:48px;
      border-radius:16px;
      display:grid;place-items:center;
      background:rgba(198,40,40,.08);
      color:var(--red);
      margin-bottom:10px;
    }

    .reward-card h3{
      font-size:13px;
      font-weight:1000;
    }

    .reward-card p{
      color:var(--muted);
      font-size:11px;
      margin-top:6px;
      line-height:1.5;
    }

    .cost{
      margin-top:10px;
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 10px;
      border-radius:999px;
      background:rgba(244,180,0,.14);
      color:#8a5c00;
      font-size:11px;
      font-weight:1000;
    }

    .btn{
      margin-top:12px;
      height:40px;
      border:none;
      border-radius:12px;
      background:var(--red);
      color:#fff;
      padding:0 14px;
      font-weight:1000;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      gap:8px;
    }

    .btn:hover{
      background:var(--red-dark);
    }

    @media (max-width: 980px){
      .hero-inner,
      .grid{
        grid-template-columns:1fr;
      }
      .badges,
      .reward-grid,
      .mini-stats{
        grid-template-columns:1fr 1fr;
      }
    }

    @media (max-width: 640px){
      .badges,
      .reward-grid,
      .mini-stats,
      .hero-stats{
        grid-template-columns:1fr;
      }
      .level-card{
        grid-template-columns:1fr;
      }
      .level-badge{
        margin:0 auto;
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
          <li><a href="donor-form.php"><i class="fa-solid fa-hand-holding-droplet"></i> Donor</a></li>
          <li><a class="active" href="gamified-engagement.php"><i class="fa-solid fa-trophy"></i> Gamified Engagement</a></li>
          <li><a href="my-requests.php"><i class="fa-regular fa-file-lines"></i> My Requests</a></li>
        </ul>
      </nav>
    </div>
  </div>
</header>

<section class="hero">
  <div class="hero-inner">
    <div>
      <h1><i class="fa-solid fa-trophy"></i> Gamified Engagement Hub</h1>
      <p>
        Welcome back, <?php echo e($userName); ?>. Your journey is powered by real donation activity,
        earned points, unlocked badges, and your impact on saving lives through RaktaBindu.
      </p>
    </div>

    <div class="hero-stats">
      <div class="hero-stat">
        <div class="label">Your Points</div>
        <div class="value"><?php echo e($points); ?></div>
      </div>
      <div class="hero-stat">
        <div class="label">Current Rank</div>
        <div class="value"><?php echo $rank > 0 ? '#' . e($rank) : '-'; ?></div>
      </div>
      <div class="hero-stat">
        <div class="label">Donation Streak</div>
        <div class="value"><?php echo e($streakDays); ?> days</div>
      </div>
      <div class="hero-stat">
        <div class="label">Lives Impacted</div>
        <div class="value"><?php echo e($totalLivesImpacted); ?></div>
      </div>
    </div>
  </div>
</section>

<section class="grid">
  <div class="left-col">

    <div class="card">
      <div class="card-head">
        <div>
          <h2><i class="fa-solid fa-star"></i> Your Donor Journey</h2>
          <div class="sub">Track your real progress based on actual activity</div>
        </div>
      </div>

      <div class="level-card">
        <div class="level-badge">
          <i class="fa-solid fa-crown"></i>
        </div>

        <div>
          <div class="level-name"><?php echo e($level); ?></div>
          <div class="point-line">
            <span><?php echo e($points); ?> XP collected</span>
            <span>
              <?php echo $points >= 2000 ? 'Maximum level reached' : e($levelProgress) . '% to ' . e($nextLevel); ?>
            </span>
          </div>
          <div class="progress"><span style="width:<?php echo (int)$levelProgress; ?>%"></span></div>

          <div class="point-line" style="margin-top:10px;">
            <span>Next target: <?php echo e($nextTarget); ?> XP</span>
            <span><?php echo $points >= 2000 ? 'Top donor status unlocked' : e($nextLevel); ?></span>
          </div>

          <div class="mini-stats">
            <div class="mini">
              <div class="n"><?php echo e($totalDonations); ?></div>
              <div class="t">Donations</div>
            </div>
            <div class="mini">
              <div class="n"><?php echo e($emergencyDonations); ?></div>
              <div class="t">Emergency Donations</div>
            </div>
            <div class="mini">
              <div class="n"><?php echo e($totalLivesImpacted); ?></div>
              <div class="t">Lives Helped</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div>
          <h2><i class="fa-solid fa-award"></i> Badges & Achievements</h2>
          <div class="sub">Unlocked automatically from real donation milestones</div>
        </div>
      </div>

      <div class="badges">
        <?php foreach($badges as $b): ?>
          <div class="badge-item <?php echo $b['earned'] ? 'earned' : ''; ?>">
            <div class="icon"><i class="fa-solid <?php echo e($b['icon']); ?>"></i></div>
            <div class="name"><?php echo e($b['title']); ?></div>
            <div class="state"><?php echo $b['earned'] ? 'Unlocked' : 'Locked'; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div>
          <h2><i class="fa-solid fa-list-check"></i> Weekly Missions</h2>
          <div class="sub">Complete real milestones to gain engagement rewards</div>
        </div>
      </div>

      <div class="mission-list">
        <?php foreach($missions as $m): ?>
          <div class="mission">
            <div class="mission-top">
              <div class="mission-title"><?php echo e($m['title']); ?></div>
              <div class="reward"><i class="fa-solid fa-coins"></i> +<?php echo e($m['reward']); ?> XP</div>
            </div>
            <div class="progress"><span style="width:<?php echo (int)$m['progress']; ?>%"></span></div>
            <div class="progress-text"><?php echo (int)$m['progress']; ?>% completed</div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <div class="right-col">

    <div class="card">
      <div class="card-head">
        <div>
          <h2><i class="fa-solid fa-ranking-star"></i> Community Leaderboard</h2>
          <div class="sub">Top donors ranked by real XP points</div>
        </div>
      </div>

      <div class="leaderboard">
        <?php if (!empty($leaderboard)): ?>
          <?php foreach($leaderboard as $i => $l): ?>
            <div class="leader <?php echo !empty($l['me']) ? 'me' : ''; ?>">
              <div class="rank-badge">#<?php echo $i + 1; ?></div>
              <div>
                <div class="name"><?php echo e($l['name']); ?></div>
                <div class="meta"><?php echo e($l['donations']); ?> donations recorded</div>
              </div>
              <div class="score">
                <?php echo e($l['points']); ?> XP
                <small><?php echo !empty($l['me']) ? 'You' : 'Member'; ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="sub">No leaderboard data available yet.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div>
          <h2><i class="fa-solid fa-gift"></i> Rewards Store</h2>
          <div class="sub">Redeem recognition rewards using earned points</div>
        </div>
      </div>

      <div class="reward-grid">
        <?php foreach($rewards as $r): ?>
          <div class="reward-card">
            <div class="r-icon"><i class="fa-solid <?php echo e($r['icon']); ?>"></i></div>
            <h3><?php echo e($r['title']); ?></h3>
            <p>
              <?php if ($points >= (int)$r['cost']): ?>
                You have got enough points to redeem this reward.
              <?php else: ?>
                Earn more points through donations and engagement to unlock this reward.
              <?php endif; ?>
            </p>
            <div class="cost"><i class="fa-solid fa-coins"></i> <?php echo e($r['cost']); ?> XP</div>
            <button class="btn" <?php echo $points < (int)$r['cost'] ? 'disabled style="opacity:.6;cursor:not-allowed;"' : ''; ?>>
              <i class="fa-solid fa-unlock"></i>
              <?php echo $points >= (int)$r['cost'] ? 'Redeem' : 'Locked'; ?>
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</section>

</body>
</html>