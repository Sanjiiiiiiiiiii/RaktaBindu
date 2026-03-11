<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");
require_once __DIR__ . "/db.php";

// Optional login check
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$uid = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'Donor';

// ===============================
// Demo / fallback values
// Replace these later with DB queries
// ===============================
$points = 1240;
$level = "Life Saver";
$levelProgress = 72; // percent
$streakDays = 9;
$rank = 4;
$totalDonations = 6;
$totalLivesImpacted = 18;

$badges = [
  ["title" => "First Drop", "icon" => "fa-droplet", "earned" => true],
  ["title" => "Hero Donor", "icon" => "fa-medal", "earned" => true],
  ["title" => "Emergency Responder", "icon" => "fa-bolt", "earned" => true],
  ["title" => "3 Month Streak", "icon" => "fa-fire", "earned" => false],
  ["title" => "Community Champion", "icon" => "fa-users", "earned" => false],
  ["title" => "Legend Donor", "icon" => "fa-crown", "earned" => false],
];

$missions = [
  ["title" => "Complete 1 blood donation", "progress" => 100, "reward" => 200],
  ["title" => "Update donor profile", "progress" => 100, "reward" => 50],
  ["title" => "Maintain 7-day activity streak", "progress" => 85, "reward" => 120],
  ["title" => "Respond to an emergency request", "progress" => 40, "reward" => 250],
];

$leaderboard = [
  ["name" => "Aarav Shrestha", "points" => 1680, "donations" => 9],
  ["name" => "Sanjana Rai", "points" => 1540, "donations" => 8],
  ["name" => "Rohan Karki", "points" => 1410, "donations" => 7],
  ["name" => $userName, "points" => $points, "donations" => $totalDonations, "me" => true],
  ["name" => "Nisha Gurung", "points" => 1180, "donations" => 5],
];

$rewards = [
  ["title" => "Bronze Donor Certificate", "cost" => 300, "icon" => "fa-certificate"],
  ["title" => "Hero Profile Frame", "cost" => 500, "icon" => "fa-image"],
  ["title" => "Priority Event Invite", "cost" => 800, "icon" => "fa-ticket"],
  ["title" => "Gold Donor Recognition", "cost" => 1200, "icon" => "fa-trophy"],
];

function e($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
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
        Stay motivated, earn recognition, complete missions, and grow your impact.
        Every donation, every response, and every act of consistency makes you a stronger hero in the RaktaBindu community.
      </p>
    </div>

    <div class="hero-stats">
      <div class="hero-stat">
        <div class="label">Your Points</div>
        <div class="value"><?php echo e($points); ?></div>
      </div>
      <div class="hero-stat">
        <div class="label">Current Rank</div>
        <div class="value">#<?php echo e($rank); ?></div>
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
          <div class="sub">Track your level, progress, and community contribution</div>
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
            <span><?php echo e($levelProgress); ?>% to next level</span>
          </div>
          <div class="progress"><span style="width:<?php echo (int)$levelProgress; ?>%"></span></div>

          <div class="mini-stats">
            <div class="mini">
              <div class="n"><?php echo e($totalDonations); ?></div>
              <div class="t">Donations</div>
            </div>
            <div class="mini">
              <div class="n"><?php echo e($streakDays); ?></div>
              <div class="t">Day Streak</div>
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
          <div class="sub">Earn special recognition through impact and consistency</div>
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
          <div class="sub">Complete tasks to gain bonus points and unlock rewards</div>
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
          <div class="sub">Top contributors in the RaktaBindu network</div>
        </div>
      </div>

      <div class="leaderboard">
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
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div>
          <h2><i class="fa-solid fa-gift"></i> Rewards Store</h2>
          <div class="sub">Redeem your points for recognition and perks</div>
        </div>
      </div>

      <div class="reward-grid">
        <?php foreach($rewards as $r): ?>
          <div class="reward-card">
            <div class="r-icon"><i class="fa-solid <?php echo e($r['icon']); ?>"></i></div>
            <h3><?php echo e($r['title']); ?></h3>
            <p>Use your engagement points to unlock this community reward.</p>
            <div class="cost"><i class="fa-solid fa-coins"></i> <?php echo e($r['cost']); ?> XP</div>
            <button class="btn"><i class="fa-solid fa-unlock"></i> Redeem</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</section>

</body>
</html>