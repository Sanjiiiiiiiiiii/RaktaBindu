<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/db.php";

$isLoggedIn   = isset($_SESSION['user_id']);
$userId       = (int)($_SESSION['user_id'] ?? 0);
$userNameRaw  = trim((string)($_SESSION['user_name'] ?? 'User'));
$userName     = htmlspecialchars($userNameRaw, ENT_QUOTES, 'UTF-8');
$avatarLetter = strtoupper(substr($userNameRaw !== '' ? $userNameRaw : 'U', 0, 1));
$currentPage  = basename($_SERVER['PHP_SELF']);

function navActive(array $pages, string $currentPage): string {
    return in_array($currentPage, $pages, true) ? 'active' : '';
}

$unreadCount = 0;

if ($isLoggedIn && isset($conn) && $conn instanceof mysqli) {
    $notifStmt = $conn->prepare("
        SELECT COUNT(*) AS unread_count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");

    if ($notifStmt) {
        $notifStmt->bind_param("i", $userId);
        $notifStmt->execute();
        $notifRes = $notifStmt->get_result()->fetch_assoc();
        $unreadCount = (int)($notifRes['unread_count'] ?? 0);
        $notifStmt->close();
    }
}
?>

<style>
    .site-navbar-wrap{
        width:min(1180px,92%);
        margin:22px auto 14px;
    }

    .site-navbar{
        background:#fff;
        border:1px solid rgba(0,0,0,.08);
        border-radius:18px;
        box-shadow:0 12px 40px rgba(0,0,0,.06);
        padding:14px 18px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        position:relative;
        flex-wrap:wrap;
    }

    .site-brand{
        display:flex;
        align-items:center;
        gap:10px;
        text-decoration:none;
        color:#1f2430;
        font-weight:1000;
        flex-shrink:0;
    }

    .site-brand-logo{
        width:38px;
        height:38px;
        border-radius:12px;
        background:rgba(198,40,40,.10);
        color:#c62828;
        display:grid;
        place-items:center;
        border:1px solid rgba(198,40,40,.18);
        font-size:16px;
    }

    .site-brand-text{
        line-height:1.08;
    }

    .site-brand-text b{
        color:#c62828;
    }

    .site-brand-text small{
        display:block;
        color:#98a2b3;
        font-size:11px;
        font-weight:800;
        margin-top:3px;
    }

    .site-nav-center{
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
        flex:1;
        justify-content:center;
    }

    .site-nav-link{
        text-decoration:none;
        color:#667085;
        font-size:13px;
        font-weight:900;
        padding:10px 14px;
        border-radius:12px;
        display:inline-flex;
        align-items:center;
        gap:8px;
        transition:.2s ease;
        white-space:nowrap;
    }

    .site-nav-link:hover{
        background:rgba(198,40,40,.08);
        color:#c62828;
    }

    .site-nav-link.active{
        background:rgba(198,40,40,.08);
        color:#c62828;
    }

    .site-nav-right{
        display:flex;
        align-items:center;
        gap:10px;
        flex-shrink:0;
    }

    .site-icon-btn{
        position:relative;
        width:40px;
        height:40px;
        border-radius:12px;
        border:1px solid rgba(0,0,0,.08);
        background:#fff;
        color:#667085;
        display:grid;
        place-items:center;
        text-decoration:none;
        transition:.2s ease;
    }

    .site-icon-btn:hover{
        background:rgba(198,40,40,.08);
        color:#c62828;
    }

    .site-badge-dot{
        position:absolute;
        top:7px;
        right:7px;
        min-width:18px;
        height:18px;
        padding:0 5px;
        border-radius:999px;
        background:#c62828;
        color:#fff;
        font-size:10px;
        font-weight:1000;
        display:flex;
        align-items:center;
        justify-content:center;
        border:2px solid #fff;
        line-height:1;
    }

    .site-user-menu{
        position:relative;
    }

    .site-user-btn{
        display:flex;
        align-items:center;
        gap:10px;
        border:1px solid rgba(0,0,0,.08);
        background:#fff;
        border-radius:14px;
        padding:6px 10px 6px 6px;
        cursor:pointer;
        transition:.2s ease;
    }

    .site-user-btn:hover{
        background:rgba(198,40,40,.04);
    }

    .site-avatar{
        width:36px;
        height:36px;
        border-radius:50%;
        background:rgba(198,40,40,.10);
        color:#c62828;
        display:grid;
        place-items:center;
        font-weight:1000;
        border:1px solid rgba(198,40,40,.18);
        font-size:14px;
    }

    .site-user-meta{
        display:flex;
        flex-direction:column;
        align-items:flex-start;
        line-height:1.1;
    }

    .site-user-name{
        font-size:12px;
        font-weight:900;
        color:#1f2430;
        max-width:120px;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .site-user-role{
        font-size:11px;
        color:#98a2b3;
        font-weight:800;
        margin-top:2px;
    }

    .site-dropdown{
        position:absolute;
        top:calc(100% + 10px);
        right:0;
        width:230px;
        background:#fff;
        border:1px solid rgba(0,0,0,.08);
        border-radius:16px;
        box-shadow:0 20px 40px rgba(0,0,0,.10);
        padding:8px;
        display:none;
        z-index:2000;
    }

    .site-dropdown.show{
        display:block;
    }

    .site-dropdown a{
        display:flex;
        align-items:center;
        gap:10px;
        padding:11px 12px;
        border-radius:12px;
        text-decoration:none;
        color:#344054;
        font-size:13px;
        font-weight:800;
    }

    .site-dropdown a:hover{
        background:rgba(198,40,40,.08);
        color:#c62828;
    }

    .site-mobile-toggle{
        display:none;
        width:42px;
        height:42px;
        border-radius:12px;
        border:1px solid rgba(0,0,0,.08);
        background:#fff;
        color:#667085;
        cursor:pointer;
    }

    .site-mobile-panel{
        display:none;
        width:100%;
        margin-top:12px;
        border-top:1px solid rgba(0,0,0,.06);
        padding-top:12px;
    }

    .site-mobile-panel.show{
        display:block;
    }

    .site-mobile-links{
        display:flex;
        flex-direction:column;
        gap:8px;
    }

    .site-mobile-links a,
    .site-mobile-user a{
        text-decoration:none;
        color:#667085;
        font-size:13px;
        font-weight:900;
        padding:12px 14px;
        border-radius:12px;
        display:flex;
        align-items:center;
        gap:8px;
    }

    .site-mobile-links a:hover,
    .site-mobile-links a.active,
    .site-mobile-user a:hover{
        background:rgba(198,40,40,.08);
        color:#c62828;
    }

    .site-mobile-user{
        margin-top:12px;
        display:flex;
        flex-direction:column;
        gap:8px;
        padding-top:12px;
        border-top:1px solid rgba(0,0,0,.06);
    }

    @media (max-width: 1080px){
        .site-nav-center{
            gap:6px;
        }

        .site-nav-link{
            padding:9px 11px;
            font-size:12px;
        }
    }

    @media (max-width: 980px){
        .site-nav-center,
        .site-nav-right{
            display:none;
        }

        .site-mobile-toggle{
            display:block;
        }

        .site-navbar{
            justify-content:space-between;
        }
    }
</style>

<div class="site-navbar-wrap">
    <nav class="site-navbar">
        <a href="index.php" class="site-brand">
            <div class="site-brand-logo"><i class="fa-solid fa-droplet"></i></div>
            <div class="site-brand-text">
                Rakta.<b>Bindu</b>
                <small>Save Lives Together</small>
            </div>
        </a>

        <div class="site-nav-center">
            <a class="site-nav-link <?php echo navActive(['index.php'], $currentPage); ?>" href="index.php">
                <i class="fa-solid fa-house"></i> Home
            </a>

            <a class="site-nav-link <?php echo navActive(['donor-form.php'], $currentPage); ?>" href="donor-form.php">
                <i class="fa-solid fa-hand-holding-droplet"></i> Donate
            </a>

            <a class="site-nav-link <?php echo navActive(['request-blood.php'], $currentPage); ?>" href="request-blood.php">
                <i class="fa-solid fa-droplet"></i> Request Blood
            </a>

           
            <a class="site-nav-link <?php echo navActive(['donation_history.php'], $currentPage); ?>" href="donation_history.php">
                <i class="fa-solid fa-clock-rotate-left"></i> History
            </a>

            
            <a class="site-nav-link <?php echo navActive(['about.php'], $currentPage); ?>" href="about.php">
                <i class="fa-solid fa-circle-info"></i> About
            </a>

            <a class="site-nav-link <?php echo navActive(['contact.php'], $currentPage); ?>" href="contact.php">
                <i class="fa-solid fa-envelope"></i> Contact
            </a>

            <a class="site-nav-link <?php echo navActive(['faq-chatbot.php'], $currentPage); ?>" href="faq-chatbot.php">
                <i class="fa-solid fa-robot"></i> FAQ
            </a>
        </div>

        <div class="site-nav-right">
            <?php if ($isLoggedIn): ?>
                <a class="site-icon-btn" href="notifications.php" title="Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="site-badge-dot"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
                    <?php endif; ?>
                </a>

                <div class="site-user-menu">
                    <button type="button" class="site-user-btn" id="siteUserBtn">
                        <div class="site-avatar"><?php echo htmlspecialchars($avatarLetter, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="site-user-meta">
                            <span class="site-user-name"><?php echo $userName; ?></span>
                            <span class="site-user-role">Logged In</span>
                        </div>
                        <i class="fa-solid fa-chevron-down" style="font-size:11px;color:#98a2b3;"></i>
                    </button>

                    <div class="site-dropdown" id="siteDropdown">
                        <a href="profile.php"><i class="fa-regular fa-user"></i> Profile</a>
                        <a href="my-requests.php"><i class="fa-regular fa-file-lines"></i> My Requests</a>
                        <a href="donation_history.php"><i class="fa-regular fa-clock"></i> Donation History</a>
                        <a href="notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a>
                        <a href="about.php"><i class="fa-solid fa-circle-info"></i> About</a>
                        <a href="contact.php"><i class="fa-solid fa-envelope"></i> Contact</a>
                        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a class="site-nav-link <?php echo navActive(['login.php'], $currentPage); ?>" href="login.php">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </a>

                <a class="site-nav-link <?php echo navActive(['signup.php'], $currentPage); ?>" href="signup.php">
                    <i class="fa-solid fa-user-plus"></i> Sign Up
                </a>
            <?php endif; ?>
        </div>

        <button type="button" class="site-mobile-toggle" id="siteMobileToggle">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="site-mobile-panel" id="siteMobilePanel">
            <div class="site-mobile-links">
                <a class="<?php echo navActive(['index.php'], $currentPage); ?>" href="index.php">
                    <i class="fa-solid fa-house"></i> Home
                </a>
                <a class="<?php echo navActive(['donor-form.php'], $currentPage); ?>" href="donor-form.php">
                    <i class="fa-solid fa-hand-holding-droplet"></i> Donate
                </a>
                <a class="<?php echo navActive(['request-blood.php'], $currentPage); ?>" href="request-blood.php">
                    <i class="fa-solid fa-droplet"></i> Request Blood
                </a>
                <a class="<?php echo navActive(['my-requests.php'], $currentPage); ?>" href="my-requests.php">
                    <i class="fa-regular fa-file-lines"></i> My Requests
                </a>
                <a class="<?php echo navActive(['donation_history.php'], $currentPage); ?>" href="donation_history.php">
                    <i class="fa-solid fa-clock-rotate-left"></i> History
                </a>
                <a class="<?php echo navActive(['donors.php'], $currentPage); ?>" href="donors.php">
                    <i class="fa-solid fa-users"></i> Donors
                </a>
                <a class="<?php echo navActive(['about.php'], $currentPage); ?>" href="about.php">
                    <i class="fa-solid fa-circle-info"></i> About
                </a>
                <a class="<?php echo navActive(['contact.php'], $currentPage); ?>" href="contact.php">
                    <i class="fa-solid fa-envelope"></i> Contact
                </a>
                <a class="<?php echo navActive(['faq-chatbot.php'], $currentPage); ?>" href="faq-chatbot.php">
                    <i class="fa-solid fa-robot"></i> FAQ
                </a>
            </div>

            <?php if ($isLoggedIn): ?>
                <div class="site-mobile-user">
                    <a href="profile.php"><i class="fa-regular fa-user"></i> Profile</a>
                    <a href="notifications.php">
                        <i class="fa-regular fa-bell"></i> Notifications
                        <?php if ($unreadCount > 0): ?>
                            <span style="margin-left:auto;font-weight:1000;color:#c62828;"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                </div>
            <?php else: ?>
                <div class="site-mobile-user">
                    <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
                    <a href="signup.php"><i class="fa-solid fa-user-plus"></i> Sign Up</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
</div>

<script>
(function () {
    const userBtn = document.getElementById('siteUserBtn');
    const dropdown = document.getElementById('siteDropdown');
    const mobileToggle = document.getElementById('siteMobileToggle');
    const mobilePanel = document.getElementById('siteMobilePanel');

    if (userBtn && dropdown) {
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && !userBtn.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    }

    if (mobileToggle && mobilePanel) {
        mobileToggle.addEventListener('click', function () {
            mobilePanel.classList.toggle('show');
        });
    }
})();
</script>