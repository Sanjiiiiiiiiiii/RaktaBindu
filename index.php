<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");

// ✅ PUBLIC homepage: login is optional
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? htmlspecialchars($_SESSION['user_name'] ?? 'User') : 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Rakta.Bindu - Every Drop Counts. Every Life Matters.</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    :root{
      --red:#c4161c;
      --red2:#b31218;
      --dark:#0f172a;
      --text:#1f2937;
      --muted:#6b7280;
      --bg:#ffffff;
      --soft:#f5f6f8;
      --card:#ffffff;
      --shadow:0 18px 40px rgba(15,23,42,.08);
      --shadow2:0 10px 24px rgba(0,0,0,.08);
      --radius:16px;
    }
    *{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
    body{background:var(--bg);color:var(--text);line-height:1.55}
    a{color:inherit;text-decoration:none}
    .wrap{max-width:1120px;margin:0 auto;padding:0 18px}

    header{
      background:#fff;
      border-bottom:1px solid rgba(0,0,0,.06);
      position:sticky;top:0;z-index:1000;
    }
    .topbar{
      height:56px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
    }
    .brand{
      display:flex;align-items:center;gap:10px;font-weight:800;
      letter-spacing:.2px;
    }
    .drop{
      width:12px;height:18px;background:var(--red);
      border-radius:10px 10px 14px 14px;
      position:relative;
    }
    .brand span{color:var(--red)}
    nav ul{list-style:none;display:flex;gap:22px;align-items:center}
    nav a{
      font-size:13px;
      color:#111827;
      font-weight:600;
      opacity:.85;
    }
    nav a:hover{opacity:1;color:var(--red)}
    .right-actions{display:flex;align-items:center;gap:12px}
    .hello{
      font-size:13px;color:#374151;font-weight:700;
      display:flex;align-items:center;gap:8px;
    }
    .btn{
      border:none;cursor:pointer;font-weight:800;
      border-radius:999px;
      padding:10px 14px;
      font-size:13px;
      display:inline-flex;align-items:center;gap:8px;
      transition:.2s ease;
      white-space:nowrap;
    }
    .btn-primary{background:var(--red);color:#fff}
    .btn-primary:hover{background:var(--red2)}
    .btn-outline{
      background:#fff;color:var(--red);
      border:2px solid rgba(196,22,28,.25);
    }
    .btn-outline:hover{border-color:rgba(196,22,28,.45)}

    .hero{
      padding:40px 0 22px;
      background:#fff;
    }
    .hero-grid{
      display:grid;
      grid-template-columns: 1.1fr .9fr;
      gap:22px;
      align-items:center;
    }
    .pill{
      display:inline-flex;align-items:center;gap:10px;
      font-size:12px;font-weight:800;color:var(--red);
      background:rgba(196,22,28,.06);
      border:1px solid rgba(196,22,28,.10);
      padding:8px 12px;border-radius:999px;
      margin-bottom:14px;
    }
    .hero h1{
      font-size:44px;
      line-height:1.08;
      font-weight:900;
      letter-spacing:-.6px;
      margin-bottom:12px;
    }
    .hero h1 .red{color:var(--red)}
    .hero p{
      color:var(--muted);
      max-width:560px;
      font-size:14px;
      margin-bottom:18px;
    }
    .hero-actions{display:flex;gap:12px;flex-wrap:wrap}
    .hero-actions .btn{padding:12px 16px}

    .hero-illus{
      height:210px;
      display:flex;
      align-items:center;
      justify-content:center;
      position:relative;
    }
    .heart-float{
      position:absolute;
      top:10px; left:56%;
      color:var(--red);
      font-size:14px;
      transform:translateX(-50%);
      opacity:.9;
    }
    .medical-card{
      width:220px;height:160px;
      display:flex;align-items:center;justify-content:center;
      background:var(--red);
      border-radius:26px;
      box-shadow:var(--shadow2);
      position:relative;
    }
    .medical-cross{
      width:58px;height:58px;border-radius:14px;
      background:#fff;
      display:flex;align-items:center;justify-content:center;
    }
    .medical-cross i{color:var(--red);font-size:30px}
    .family{
      position:absolute;
      right:-6px; bottom:-10px;
      color:var(--red);
      font-size:26px;
      background:#fff;
      width:62px;height:62px;border-radius:18px;
      display:flex;align-items:center;justify-content:center;
      box-shadow:0 10px 22px rgba(0,0,0,.08);
      border:1px solid rgba(0,0,0,.04);
    }

    .urgent{
      background:var(--red);
      color:#fff;
      padding:14px 0;
    }
    .urgent-row{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:14px;
    }
    .urgent h3{
      font-size:14px;
      font-weight:900;
      margin:0;
      display:flex;align-items:center;gap:10px;
    }
    .urgent p{
      margin:4px 0 0;
      font-size:12px;
      opacity:.9;
    }
    .urgent .btn-outline{
      border-color:rgba(255,255,255,.55);
      color:#fff;background:transparent;
      padding:10px 14px;
    }
    .urgent .btn-outline:hover{border-color:rgba(255,255,255,.8)}

    .section{padding:48px 0;}
    .section .kicker{
      text-align:center;font-weight:900;color:#111827;
      font-size:16px;margin-bottom:4px;
    }
    .section .sub{
      text-align:center;color:var(--muted);
      font-size:12px;margin-bottom:18px;
    }

    .steps{
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap:16px;
      margin-top:14px;
    }
    .step{
      background:#fff;
      border:1px solid rgba(0,0,0,.06);
      border-radius:14px;
      padding:16px 16px 18px;
      box-shadow:0 10px 22px rgba(0,0,0,.06);
    }
    .step-top{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
    .icon-dot{
      width:34px;height:34px;border-radius:999px;
      background:rgba(196,22,28,.12);
      display:flex;align-items:center;justify-content:center;
      color:var(--red);font-size:14px;font-weight:900;
    }
    .step h4{font-size:14px;font-weight:900;}
    .step p{font-size:12px;color:var(--muted);margin-top:6px;}
    .step .num{color:var(--red);font-weight:900;font-size:12px;}

    .compat-card{
      background:#fff;border-radius:14px;
      border:1px solid rgba(0,0,0,.06);
      box-shadow:0 14px 30px rgba(0,0,0,.08);
      padding:18px;max-width:820px;margin:18px auto 0;
    }
    table{width:100%;border-collapse:collapse}
    th{
      text-align:left;font-size:12px;color:#111827;
      padding:12px 10px;border-bottom:1px solid rgba(0,0,0,.08);
    }
    td{
      padding:10px 10px;font-size:12px;color:#111827;
      border-bottom:1px solid rgba(0,0,0,.06);vertical-align:middle;
    }
    .blood-badge{display:inline-flex;align-items:center;gap:8px;font-weight:900;}
    .sq{
      width:18px;height:18px;border-radius:6px;
      background:var(--red);
      display:inline-flex;align-items:center;justify-content:center;
      color:#fff;font-size:10px;
    }

    .why-grid{
      display:grid;
      grid-template-columns:repeat(4, 1fr);
      gap:14px;margin-top:18px;
    }
    .why{
      background:#fff;border:1px solid rgba(0,0,0,.06);
      border-radius:14px;padding:16px;
      box-shadow:0 10px 22px rgba(0,0,0,.06);
      text-align:center;
    }
    .why .why-icon{
      width:38px;height:38px;border-radius:999px;
      margin:0 auto 10px;
      display:flex;align-items:center;justify-content:center;
      font-size:14px;font-weight:900;color:#fff;
    }
    .i-red{background:var(--red)}
    .i-green{background:#16a34a}
    .why h4{font-size:13px;font-weight:900;margin-bottom:6px}
    .why p{font-size:12px;color:var(--muted)}

    .impact{
      background:var(--red);color:#fff;
      padding:44px 0 52px;
    }
    .impact .kicker{color:#fff}
    .impact .sub{color:rgba(255,255,255,.85)}
    .stats{
      display:grid;
      grid-template-columns:repeat(3, 1fr);
      gap:14px;margin-top:18px;
    }
    .stat{
      background:rgba(0,0,0,.08);
      border:1px solid rgba(255,255,255,.18);
      border-radius:14px;padding:18px;text-align:center;
    }
    .stat .num{font-size:26px;font-weight:950;letter-spacing:.4px;}
    .stat .lbl{margin-top:6px;font-size:12px;opacity:.9;font-weight:800;}

    .testimonials{
      display:grid;
      grid-template-columns:repeat(2, 1fr);
      gap:14px;margin-top:16px;
    }
    .tcard{
      background:rgba(0,0,0,.08);
      border:1px solid rgba(255,255,255,.18);
      border-radius:14px;padding:14px;
    }
    .tcard .top{display:flex;gap:10px;align-items:flex-start;}
    .avatar{
      width:36px;height:36px;border-radius:999px;
      background:rgba(255,255,255,.22);
      display:flex;align-items:center;justify-content:center;
      font-weight:900;
    }
    .tcard p{
      font-size:12px;color:rgba(255,255,255,.88);
      margin-top:8px;
    }
    .tcard strong{
      display:block;margin-top:10px;
      font-size:12px;font-weight:900;
    }

    .cta{
      background:#f2f3f6;
      padding:46px 0;
      text-align:center;
    }
    .cta .kicker{font-size:20px}
    .cta .sub{max-width:680px;margin:0 auto 14px}
    .cta .cta-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:12px}

    footer{
      background:#0b1220;color:rgba(255,255,255,.85);
      padding:44px 0;
    }
    .foot{
      display:grid;
      grid-template-columns:1.2fr 1fr 1fr 1fr;
      gap:18px;
    }
    .foot h5{font-size:13px;font-weight:900;margin-bottom:10px;color:#fff}
    .foot a{display:block;font-size:12px;opacity:.85;margin:7px 0}
    .foot a:hover{opacity:1}
    .foot .mini{font-size:12px;opacity:.85;line-height:1.7}
    .social{display:flex;gap:10px;margin-top:10px}
    .social a{
      width:34px;height:34px;border-radius:10px;
      background:rgba(255,255,255,.08);
      display:flex;align-items:center;justify-content:center;
    }
    .copy{
      margin-top:18px;padding-top:16px;
      border-top:1px solid rgba(255,255,255,.10);
      text-align:center;font-size:12px;opacity:.8;
    }

    @media (max-width: 980px){
      .hero-grid{grid-template-columns:1fr}
      .hero-illus{justify-content:flex-start}
      .steps{grid-template-columns:1fr}
      .why-grid{grid-template-columns:1fr 1fr}
      .stats{grid-template-columns:1fr}
      .testimonials{grid-template-columns:1fr}
      .foot{grid-template-columns:1fr 1fr}
      nav ul{gap:14px}
    }
    @media (max-width: 560px){
      .topbar{height:auto;padding:12px 0;flex-wrap:wrap}
      nav ul{flex-wrap:wrap}
      .hero h1{font-size:34px}
      .medical-card{width:200px}
      .foot{grid-template-columns:1fr}
    }
  </style>
</head>

<body>

<header>
  <div class="wrap">
    <div class="topbar">
      <div class="brand">
        <div class="drop"></div>
        Rakta.<span>Bindu</span>
      </div>

   <nav>
  <ul>
    <li><a href="index.php">Home</a></li>
    <li><a href="#how">How It Works</a></li>
    <li><a href="donor-form.php">Donate</a></li>

    <?php if ($isLoggedIn): ?>
      <li><a href="donation_history.php">Donation History</a></li>
    <?php endif; ?>

    <li><a href="#contact">Contact</a></li>
    <li><a href="#about">About</a></li>
  </ul>
</nav>


      <div class="right-actions">
        <?php if ($isLoggedIn): ?>
          <a href="profile.php" class="hello" title="My Profile">
  <i class="fa-regular fa-user"></i> <?= $userName ?>
</a>
<a class="btn btn-outline" href="donation_history.php">
  <i class="fa-solid fa-clock-rotate-left"></i> History
</a>

          <a class="btn btn-primary" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Log out</a>
        <?php else: ?>
          <a class="btn btn-outline" href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
          <a class="btn btn-primary" href="signup.php"><i class="fa-solid fa-user-plus"></i> Sign up</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<section class="hero">
  <div class="wrap">
    <div class="hero-grid">
      <div>
        <div class="pill"><i class="fa-solid fa-bolt"></i> Real-Time Blood Donation Platform</div>

        <h1>Every Drop Counts.<br><span class="red">Every Life Matters.</span></h1>

        <p>
          Connect with verified blood donors in real-time. Save lives instantly through our emergency-focused platform
          that bridges donors and recipients seamlessly.
        </p>

        <div class="hero-actions">
          <button class="btn btn-primary" onclick="location.href='donor-form.php'">
            <i class="fa-solid fa-hand-holding-droplet"></i> Become a Donor
          </button>
          <button class="btn btn-outline" onclick="location.href='request-blood.php'">
            <i class="fa-solid fa-droplet"></i> Request Blood
          </button>
        </div>
      </div>

      <div class="hero-illus">
        <div class="heart-float"><i class="fa-solid fa-heart"></i></div>
        <div class="medical-card">
          <div class="medical-cross"><i class="fa-solid fa-plus"></i></div>
          <div class="family"><i class="fa-solid fa-people-group"></i></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="urgent">
  <div class="wrap">
    <div class="urgent-row">
      <div>
        <h3><i class="fa-solid fa-triangle-exclamation"></i> Urgent Blood Requests Near You</h3>
        <p>12 emergency requests in your area need immediate attention</p>
      </div>
      <button class="btn btn-outline" onclick="location.href='request-blood.php'">
        View Emergency Requests
      </button>
    </div>
  </div>
</section>

<section class="section" id="how">
  <div class="wrap">
    <div class="kicker">How It Works</div>
    <div class="sub">Three simple steps to save a life</div>

    <div class="steps">
      <div class="step">
        <div class="step-top">
          <div class="icon-dot"><i class="fa-regular fa-id-card"></i></div>
          <div>
            <div class="num">01</div>
            <h4>Register</h4>
          </div>
        </div>
        <p>Create your profile as a donor or recipient. Verify your details securely.</p>
      </div>

      <div class="step">
        <div class="step-top">
          <div class="icon-dot"><i class="fa-solid fa-link"></i></div>
          <div>
            <div class="num">02</div>
            <h4>Connect</h4>
          </div>
        </div>
        <p>Request or accept donations. Get matched with compatible donors instantly.</p>
      </div>

      <div class="step">
        <div class="step-top">
          <div class="icon-dot"><i class="fa-solid fa-heart-pulse"></i></div>
          <div>
            <div class="num">03</div>
            <h4>Save lives</h4>
          </div>
        </div>
        <p>Donate at verified hospitals. Track your impact and help save precious lives.</p>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:10px;">
  <div class="wrap">
    <div class="kicker">Blood Group Compatibility</div>
    <div class="sub">Know who can donate to whom</div>

    <div class="compat-card">
      <table>
        <thead>
          <tr>
            <th style="width:140px;">Blood Type</th>
            <th>Can Donate To</th>
            <th>Can Receive From</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><span class="blood-badge"><span class="sq">A+</span> A+</span></td>
            <td>A+, AB+</td>
            <td>A+, A-, O+, O-</td>
          </tr>
          <tr>
            <td><span class="blood-badge"><span class="sq">A-</span> A-</span></td>
            <td>A+, A-, AB+, AB-</td>
            <td>A-, O-</td>
          </tr>
          <tr>
            <td><span class="blood-badge"><span class="sq">B+</span> B+</span></td>
            <td>B+, AB+</td>
            <td>B+, B-, O+, O-</td>
          </tr>
          <tr>
            <td><span class="blood-badge"><span class="sq">B-</span> B-</span></td>
            <td>B+, B-, AB+, AB-</td>
            <td>B-, O-</td>
          </tr>
          <tr>
            <td><span class="blood-badge"><span class="sq">AB+</span> AB+</span></td>
            <td>AB+</td>
            <td>All Blood Types</td>
          </tr>
          <tr>
            <td><span class="blood-badge"><span class="sq">AB-</span> AB-</span></td>
            <td>AB+, AB-</td>
            <td>AB-, A-, B-, O-</td>
          </tr>
          <tr>
            <td><span class="blood-badge"><span class="sq">O+</span> O+</span></td>
            <td>A+, B+, AB+, O+</td>
            <td>O+, O-</td>
          </tr>
          <tr>
            <td><span class="blood-badge"><span class="sq">O-</span> O-</span></td>
            <td>All Blood Types</td>
            <td>O-</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="section" id="about">
  <div class="wrap">
    <div class="kicker">Why Rakta.Bindu</div>
    <div class="sub">The most trusted blood donation platform</div>

    <div class="why-grid">
      <div class="why">
        <div class="why-icon i-red"><i class="fa-solid fa-bolt"></i></div>
        <h4>Real-Time Matching</h4>
        <p>Instantly connect donors and recipients in your area</p>
      </div>
      <div class="why">
        <div class="why-icon i-green"><i class="fa-solid fa-circle-check"></i></div>
        <h4>Verified Network</h4>
        <p>All donors and recipients verified for safety and reliability</p>
      </div>
      <div class="why">
        <div class="why-icon i-red"><i class="fa-solid fa-droplet"></i></div>
        <h4>Emergency Focused</h4>
        <p>Prioritize urgent requirements and save critical lives</p>
      </div>
      <div class="why">
        <div class="why-icon i-green"><i class="fa-solid fa-shield-heart"></i></div>
        <h4>Secure & Reliable</h4>
        <p>Your data protected with strong security measures</p>
      </div>
    </div>
  </div>
</section>

<section class="impact">
  <div class="wrap">
    <div class="kicker">Our Impact</div>
    <div class="sub">Making a difference, one donation at a time</div>

    <div class="stats">
      <div class="stat">
        <div class="num">8,547</div>
        <div class="lbl">Lives Saved</div>
      </div>
      <div class="stat">
        <div class="num">12,384</div>
        <div class="lbl">Active Donors</div>
      </div>
      <div class="stat">
        <div class="num">342</div>
        <div class="lbl">Hospitals Connected</div>
      </div>
    </div>

    <div class="testimonials">
      <div class="tcard">
        <div class="top">
          <div class="avatar">P</div>
          <div>
            <strong>Priya Sharma</strong>
            <div style="font-size:12px;opacity:.9;">Blood Seeker</div>
          </div>
        </div>
        <p>"I needed blood urgently for my mother. Rakta.Bindu connected us with a donor in less than 2 hours."</p>
      </div>

      <div class="tcard">
        <div class="top">
          <div class="avatar">R</div>
          <div>
            <strong>Rajesh Kumar</strong>
            <div style="font-size:12px;opacity:.9;">Regular Donor</div>
          </div>
        </div>
        <p>"Donating through Rakta.Bindu feels safe and organized. I've donated 4 times and know my blood has saved lives."</p>
      </div>
    </div>
  </div>
</section>

<section class="cta" id="contact">
  <div class="wrap">
    <div class="kicker">Ready to Make a Difference?</div>
    <div class="sub">
      Join thousands of donors and recipients who trust Rakta.Bindu to save lives every day.
    </div>

    <div class="cta-actions">
      <button class="btn btn-primary" onclick="location.href='donor-form.php'">
        <i class="fa-solid fa-hand-holding-droplet"></i> Register as Donor
      </button>
      <button class="btn btn-outline" onclick="location.href='request-blood.php'">
        <i class="fa-solid fa-droplet"></i> Find Blood Now
      </button>
    </div>
  </div>
</section>

<footer>
  <div class="wrap">
    <div class="foot">
      <div>
        <div class="brand" style="color:#fff;">
          <div class="drop"></div>
          Rakta.<span>Bindu</span>
        </div>
        <div class="mini" style="margin-top:10px;">
          Connecting donors and recipients in real-time to save lives.
        </div>
      </div>

      <div>
        <h5>QUICK LINKS</h5>
        <a href="#how">How it Works</a>
        <a href="donor-form.php">Find Donors</a>
        <a href="signup.php">Register</a>
        <a href="request-blood.php">Urgent Requests</a>
      </div>

      <div>
        <h5>SUPPORT</h5>
        <a href="#">Privacy Policy</a>
        <a href="#">Terms & Conditions</a>
        <a href="#contact">Contact Us</a>
      </div>

      <div>
        <h5>CONTACT</h5>
        <div class="mini">
          support@raktabindu.com<br>
          +91 880 000 1234
        </div>
        <div class="social">
          <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="#" aria-label="Twitter"><i class="fa-brands fa-x-twitter"></i></a>
          <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
          <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
        </div>
      </div>
    </div>

    <div class="copy">
      © 2025 RaktaBindu. All rights reserved. Saving lives, one drop at a time.
    </div>
  </div>
</footer>

</body>
</html>
