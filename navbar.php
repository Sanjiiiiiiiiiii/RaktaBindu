<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . "/db.php";

$isLoggedIn   = isset($_SESSION['user_id']);
$userName     = $isLoggedIn ? htmlspecialchars($_SESSION['user_name'] ?? 'User') : 'Guest';
$currentPage  = basename($_SERVER['PHP_SELF'] ?? '');
$unreadCount  = 0;

if ($isLoggedIn && isset($conn)) {
  $uid = (int)($_SESSION['user_id'] ?? 0);

  $nq = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE user_id = ? AND is_read = 0
  ");
  if ($nq) {
    $nq->bind_param("i", $uid);
    $nq->execute();
    $nr = $nq->get_result()->fetch_assoc();
    $unreadCount = (int)($nr['unread_count'] ?? 0);
    $nq->close();
  }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
  .rb-navbar{
    background:#fff;
    border-bottom:1px solid rgba(0,0,0,.06);
    position:sticky;
    top:0;
    z-index:1000;
  }

  .rb-wrap{
    max-width:1120px;
    margin:0 auto;
    padding:0 18px;
  }

  .rb-topbar{
    min-height:56px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    padding:10px 0;
  }

  .rb-brand{
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:800;
    letter-spacing:.2px;
    text-decoration:none;
    color:#1f2937;
    white-space:nowrap;
  }

  .rb-drop{
    width:12px;
    height:18px;
    background:#c4161c;
    border-radius:10px 10px 14px 14px;
    position:relative;
    flex:0 0 auto;
  }

  .rb-brand span{
    color:#c4161c;
  }

  .rb-nav ul{
    list-style:none;
    display:flex;
    gap:22px;
    align-items:center;
    margin:0;
    padding:0;
    flex-wrap:wrap;
  }

  .rb-nav a{
    font-size:13px;
    color:#111827;
    font-weight:600;
    opacity:.85;
    text-decoration:none;
    transition:.2s ease;
  }

  .rb-nav a:hover,
  .rb-nav a.rb-active{
    opacity:1;
    color:#c4161c;
  }

  .rb-right-actions{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
  }

  .rb-hello{
    font-size:13px;
    color:#374151;
    font-weight:700;
    display:flex;
    align-items:center;
    gap:8px;
    text-decoration:none;
  }

  .rb-noti{
    position:relative;
    width:40px;
    height:40px;
    border-radius:999px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    color:#374151;
    background:#fff;
    border:2px solid rgba(196,22,28,.14);
    transition:.2s ease;
  }

  .rb-noti:hover{
    color:#c4161c;
    border-color:rgba(196,22,28,.30);
    background:#fff7f7;
  }

  .rb-noti-badge{
    position:absolute;
    top:-4px;
    right:-2px;
    min-width:19px;
    height:19px;
    padding:0 5px;
    border-radius:999px;
    background:#c4161c;
    color:#fff;
    font-size:10px;
    font-weight:800;
    display:flex;
    align-items:center;
    justify-content:center;
    line-height:1;
    border:2px solid #fff;
  }

  .rb-btn{
    border:none;
    cursor:pointer;
    font-weight:800;
    border-radius:999px;
    padding:10px 14px;
    font-size:13px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    transition:.2s ease;
    white-space:nowrap;
    text-decoration:none;
  }

  .rb-btn-primary{
    background:#c4161c;
    color:#fff;
  }

  .rb-btn-primary:hover{
    background:#b31218;
  }

  .rb-btn-outline{
    background:#fff;
    color:#c4161c;
    border:2px solid rgba(196,22,28,.25);
  }

  .rb-btn-outline:hover{
    border-color:rgba(196,22,28,.45);
  }

  @media (max-width: 980px){
    .rb-topbar{
      flex-wrap:wrap;
      justify-content:center;
    }

    .rb-nav ul{
      gap:14px;
      justify-content:center;
    }

    .rb-right-actions{
      justify-content:center;
    }
  }

  @media (max-width: 560px){
    .rb-topbar{
      padding:12px 0;
    }

    .rb-nav ul{
      flex-wrap:wrap;
      justify-content:center;
    }
  }
</style>

<header class="rb-navbar">
  <div class="rb-wrap">
    <div class="rb-topbar">

      <a href="index.php" class="rb-brand">
        <div class="rb-drop"></div>
        Rakta.<span>Bindu</span>
      </a>

      <nav class="rb-nav">
        <ul>
          <li>
            <a href="index.php" class="<?php echo ($currentPage === 'index.php') ? 'rb-active' : ''; ?>">Home</a>
          </li>
          <li>
            <a href="index.php#how">How It Works</a>
          </li>
          <li>
            <a href="donor-form.php" class="<?php echo ($currentPage === 'donor-form.php') ? 'rb-active' : ''; ?>">Donate</a>
          </li>
<li><a href="faq-chatbot.php"></i> FAQ Chatbot</a></li>

          <?php if ($isLoggedIn): ?>
            <li>
              <a href="donation_history.php" class="<?php echo ($currentPage === 'donation_history.php') ? 'rb-active' : ''; ?>">Donation History</a>
            </li>
          <?php endif; ?>
<li>
  <a href="gamified-engagement.php">
    <i class="fa-solid fa-trophy"></i> Gamified Engagement
  </a>
</li>
          <li>
            <a href="index.php#contact">Contact</a>
          </li>
          
        </ul>
      </nav>

      <div class="rb-right-actions">
        <?php if ($isLoggedIn): ?>
          <a href="notifications.php" class="rb-noti" title="Notifications">
            <i class="fa-regular fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
              <span class="rb-noti-badge">
                <?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?>
              </span>
            <?php endif; ?>
          </a>

          <a href="profile.php" class="rb-hello" title="My Profile">
            <i class="fa-regular fa-user"></i> <?= $userName ?>
          </a>

          <a class="rb-btn rb-btn-primary" href="logout.php">
            <i class="fa-solid fa-right-from-bracket"></i> Log out
          </a>
        <?php else: ?>
          <a class="rb-btn rb-btn-outline" href="login.php">
            <i class="fa-solid fa-right-to-bracket"></i> Login
          </a>

          <a class="rb-btn rb-btn-primary" href="signup.php">
            <i class="fa-solid fa-user-plus"></i> Sign up
          </a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</header>