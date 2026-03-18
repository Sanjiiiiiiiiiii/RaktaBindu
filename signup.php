<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/db.php";
date_default_timezone_set("Asia/Kathmandu");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";
require "PHPMailer/src/Exception.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $firstName = trim($_POST['firstName'] ?? '');
    $lastName  = trim($_POST['lastName'] ?? '');
    $email     = trim(strtolower($_POST['email'] ?? ''));
    $phone     = trim($_POST['phone'] ?? '');
    $bloodType = trim($_POST['bloodType'] ?? '');
    $age       = (int)($_POST['age'] ?? 0);
    $location  = trim($_POST['location'] ?? '');
    $availability = trim($_POST['availability'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {

        // check existing email
        $check = $conn->prepare("SELECT id FROM users WHERE LOWER(email)=? LIMIT 1");
        $check->bind_param("s",$email);
        $check->execute();
        $res = $check->get_result();

        if ($res && $res->num_rows > 0) {

            $error = "Email already registered.";

        } else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // generate OTP
            $otp = random_int(100000,999999);

            $stmt = $conn->prepare("
                INSERT INTO users
                (firstName,lastName,email,phone,bloodType,age,location,availability,password,verification_code,verification_expires,is_verified)
                VALUES (?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),0)
            ");

            $stmt->bind_param(
                "sssssissss",
                $firstName,$lastName,$email,$phone,$bloodType,
                $age,$location,$availability,$hashedPassword,$otp
            );

            if ($stmt->execute()) {

                $mail = new PHPMailer(true);

                try {

                    $mail->CharSet = 'UTF-8';

                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'sanjiwanikarki@gmail.com';
                    $mail->Password = 'gomaomapzrtuimbl';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('sanjiwanikarki@gmail.com','RaktaBindu Verification');
                    $mail->addReplyTo('sanjiwanikarki@gmail.com','RaktaBindu Support');

                    $mail->addAddress($email);

                    $mail->isHTML(true);

                    $mail->Subject = "Verify your RaktaBindu account";

                    $mail->Body = "
                    <div style='font-family:Segoe UI,Arial,sans-serif'>
                        <h2 style='color:#c62828'>RaktaBindu Email Verification</h2>

                        <p>Hello <strong>$firstName</strong>,</p>

                        <p>Your verification code is:</p>

                        <h1 style='letter-spacing:6px;color:#c62828'>$otp</h1>

                        <p>This code is valid for <strong>10 minutes</strong>.</p>

                        <p>If you did not create this account, ignore this email.</p>
                    </div>
                    ";

                    $mail->AltBody = "Your RaktaBindu verification code is: $otp";

                    $mail->send();

                    header("Location: verify-email.php?email=" . urlencode($email));
                    exit();

                }
                catch (Exception $e) {

                    // delete unverified account if mail fails
                    $del = $conn->prepare("
                        DELETE FROM users
                        WHERE LOWER(email)=?
                        AND is_verified=0
                        LIMIT 1
                    ");

                    if($del){
                        $del->bind_param("s",$email);
                        $del->execute();
                    }

                    $error = "Verification email could not be sent. Please try again.";

                }

            }
            else {

                $error = "Signup failed: " . $stmt->error;

            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign Up | RaktaBindu</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    /* ✅ keep your same premium UI (your CSS) */
    :root{
      --red:#c62828; --muted:#6f7682; --line:#e6e9ef;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Segoe UI",system-ui,Arial;background:#000;min-height:100vh;display:flex}
    .wrap{width:100%;height:100vh;background:#111;overflow:hidden}
    .grid{width:100%;height:100vh;display:flex}
    .left{width:44%;position:relative;padding:86px 78px;color:#fff;background:linear-gradient(145deg,#d12a2a 0%,#b81b1b 55%,#941010 100%);display:flex;flex-direction:column}
    .bubble{position:absolute;border:1px solid rgba(255,255,255,.35);border-radius:50%;opacity:.45}
    .bubble.b1{width:120px;height:120px;top:70px;right:90px}
    .bubble.b2{width:130px;height:130px;bottom:70px;left:70px}
    .left-content{margin-top:auto;padding-bottom:18px}
    .brand{display:flex;align-items:center;gap:18px;margin-bottom:22px}
    .brand-icon{width:64px;height:64px;border-radius:16px;background:#fff;display:grid;place-items:center;color:var(--red);box-shadow:0 14px 34px rgba(0,0,0,.22)}
    .brand-icon i{font-size:28px}
    .brand-name{font-weight:900;font-size:34px}
    .left p.lead{max-width:460px;font-size:19px;line-height:1.75;opacity:.95;margin-bottom:34px}
    .features{list-style:none;display:flex;flex-direction:column;gap:20px}
    .features li{display:flex;align-items:center;gap:16px;font-size:16px;opacity:.96}
    .f-ico{width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.18);display:grid;place-items:center;border:1px solid rgba(255,255,255,.16)}
    .f-ico i{font-size:16px;color:#fff}

    .right{width:56%;background:#f6f7fb;padding:58px 52px;overflow-y:auto}
    .formCard{width:min(560px,100%);margin:0 auto;text-align:center}
    .formCard h1{font-size:34px;font-weight:900;color:#1f2430;margin-bottom:10px}
    .formCard .sub{font-size:16px;color:var(--muted);margin-bottom:26px}

    .error{background:#fff;border:1px solid #ffd0d0;color:#b01212;padding:14px 16px;border-radius:14px;font-size:14px;margin-bottom:18px;text-align:left}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:18px}
    .field{text-align:left;margin-bottom:16px}
    .field label{display:block;font-size:14px;font-weight:800;color:#5b6270;margin:0 0 10px 2px}
    .control{position:relative}
    .control i.leftico{position:absolute;left:16px;top:50%;transform:translateY(-50%);font-size:16px;color:#9aa1ad;pointer-events:none}
    input,select{width:100%;height:56px;padding:0 16px 0 46px;border:1px solid var(--line);background:#fff;border-radius:14px;outline:none;font-size:16px;color:#1f2430}
    input:focus,select:focus{border-color:#d7a4a4;box-shadow:0 0 0 5px rgba(198,40,40,.12)}
    .eye{position:absolute;right:16px;top:50%;transform:translateY(-50%);font-size:16px;color:#9aa1ad;cursor:pointer}
    .terms{margin:18px 0 22px;font-size:14px;color:#6f7682;display:flex;gap:10px;align-items:center}
    .terms input{width:18px;height:18px;accent-color:var(--red)}
    .terms a{color:var(--red);text-decoration:none;font-weight:900}
    .btn{width:100%;height:60px;border:none;border-radius:14px;background:#e12424;color:#fff;font-weight:900;font-size:16px;cursor:pointer;box-shadow:0 14px 30px rgba(225,36,36,.26)}
    .bottom{margin-top:22px;font-size:14px;color:#6f7682;line-height:1.7}
    .bottom a{color:var(--red);text-decoration:none;font-weight:900}

    @media(max-width:900px){
      .grid{flex-direction:column;height:auto}
      .left,.right{width:100%}
      .left{padding:54px 26px}
      .right{padding:38px 18px}
      .row{grid-template-columns:1fr}
    }
  </style>
</head>

<body>
<div class="wrap">
  <div class="grid">

    <div class="left">
      <span class="bubble b1"></span>
      <span class="bubble b2"></span>

      <div class="left-content">
        <div class="brand">
          <div class="brand-icon"><i class="fa-solid fa-droplet"></i></div>
          <div class="brand-name">RaktaBindu</div>
        </div>

        <p class="lead">Join our community of life-savers. Every drop counts in saving lives.</p>

        <ul class="features">
          <li><span class="f-ico"><i class="fa-solid fa-shield-halved"></i></span> Secure &amp; verified platform</li>
          <li><span class="f-ico"><i class="fa-solid fa-location-dot"></i></span> Connect with donors nearby</li>
          <li><span class="f-ico"><i class="fa-solid fa-bolt"></i></span> Quick emergency response</li>
        </ul>
      </div>
    </div>

    <div class="right">
      <div class="formCard">
        <h1>Create Account</h1>
        <div class="sub">Join our blood donation community</div>

        <?php if (!empty($error)) : ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="signup.php" method="POST" id="signupForm" novalidate>

          <div class="row">
            <div class="field">
              <label>First Name</label>
              <div class="control">
                <i class="fa-regular fa-user leftico"></i>
                <input type="text" name="firstName" required>
              </div>
            </div>

            <div class="field">
              <label>Last Name</label>
              <div class="control">
                <i class="fa-regular fa-user leftico"></i>
                <input type="text" name="lastName" required>
              </div>
            </div>
          </div>

          <div class="field">
            <label>Email Address</label>
            <div class="control">
              <i class="fa-regular fa-envelope leftico"></i>
              <input type="email" name="email" required>
            </div>
          </div>

          <div class="field">
            <label>Phone Number</label>
            <div class="control">
              <i class="fa-solid fa-phone leftico"></i>
              <input type="tel" name="phone" required>
            </div>
          </div>

          <div class="row">
            <div class="field">
              <label>Blood Type</label>
              <div class="control">
                <i class="fa-solid fa-droplet leftico"></i>
                <select name="bloodType" required>
                  <option value="" disabled selected>Select blood type</option>
                  <option>A+</option><option>A-</option>
                  <option>B+</option><option>B-</option>
                  <option>AB+</option><option>AB-</option>
                  <option>O+</option><option>O-</option>
                </select>
              </div>
            </div>

            <div class="field">
              <label>Age</label>
              <div class="control">
                <i class="fa-regular fa-calendar leftico"></i>
                <input type="number" name="age" min="18" max="65" required>
              </div>
            </div>
          </div>

          <div class="field">
            <label>Location</label>
            <div class="control">
              <i class="fa-solid fa-location-dot leftico"></i>
              <input type="text" name="location" required>
            </div>
          </div>

          <div class="field">
            <label>Availability</label>
            <div class="control">
              <i class="fa-regular fa-clock leftico"></i>
              <select name="availability" required>
                <option value="" disabled selected>Select availability</option>
                <option>Available anytime</option>
                <option>Available on weekends</option>
                <option>Available in emergencies only</option>
                <option>Not available currently</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label>Password</label>
            <div class="control">
              <i class="fa-solid fa-lock leftico"></i>
              <input id="pw" type="password" name="password" required>
              <i class="fa-regular fa-eye eye" data-target="pw"></i>
            </div>
          </div>

          <div class="field">
            <label>Confirm Password</label>
            <div class="control">
              <i class="fa-solid fa-lock leftico"></i>
              <input id="cpw" type="password" name="confirm_password" required>
              <i class="fa-regular fa-eye eye" data-target="cpw"></i>
            </div>
          </div>

          <div class="terms">
            <input type="checkbox" name="terms" required>
            <div>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></div>
          </div>

          <button class="btn" type="submit">Create Account</button>

          <div class="bottom">
            Already have an account?<br>
            <a href="login.php">Sign In</a>
          </div>
        </form>

      </div>
    </div>

  </div>
</div>

<script>
  // ✅ eye toggle correct (eye open = show)
  document.querySelectorAll('.eye').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-target');
      const el = document.getElementById(id);
      if (!el) return;

      const hidden = el.type === 'password';
      el.type = hidden ? 'text' : 'password';

      btn.classList.toggle('fa-eye', !hidden);
      btn.classList.toggle('fa-eye-slash', hidden);
    });
  });
</script>
</body>
</html>
