<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/db.php";
date_default_timezone_set("Asia/Kathmandu");

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === "" || $password === "") {
        $error = "Please enter email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {

        // ✅ fetch is_verified too
        $stmt = $conn->prepare("SELECT id, firstName, password, is_verified FROM users WHERE TRIM(LOWER(email)) = ? LIMIT 1");
        if (!$stmt) die("Prepare failed: " . $conn->error);

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {

                // ✅ BLOCK if email not verified
                if ((int)$user['is_verified'] !== 1) {
                    header("Location: verify-email.php?email=" . urlencode($email));
                    exit();
                }

                // ✅ login success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['firstName'];
                header("Location: index.php");
                exit();
            }
        }

        $error = "Invalid email or password!";
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | RaktaBindu</title>

<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
:root {
    --red: #c62828;
    --light-red: #ffebee;
    --dark-red: #b71c1c;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f8f9fa;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ===== MAIN CONTAINER ===== */
.login-container {
    display: flex;
    width: 90%;
    max-width: 1100px;
    background: white;
    border-radius: 30px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.18);
}

/* ===== LEFT PANEL ===== */
.left-panel {
    background: var(--red);
    color: white;
    width: 45%;
    padding: 70px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    border-radius: 30px 0 0 30px;
    position: relative;
    overflow: hidden;
}

.left-panel::before {
    content: '';
    position: absolute;
    top: -20%;
    right: -20%;
    width: 420px;
    height: 520px;
    background: rgba(255,255,255,0.12);
    border-radius: 50%;
}

.logo {
    display: flex;
    align-items: center;
    font-size: 30px;
    font-weight: bold;
    margin-bottom: 50px;
    position: relative;
    z-index: 2;
}

.logo .drop {
    width: 36px;
    height: 48px;
    background: white;
    border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
    margin-right: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--red);
}

.left-panel h1 {
    font-size: 40px;
    margin-bottom: 15px;
    position: relative;
    z-index: 2;
}

.subtitle {
    font-size: 18px;
    margin-bottom: 60px;
    opacity: 0.95;
    position: relative;
    z-index: 2;
}

.features {
    list-style: none;
    position: relative;
    z-index: 2;
}

.features li {
    display: flex;
    align-items: flex-start;
    gap: 18px;
    margin-bottom: 35px;
}

.features .icon {
    background: rgba(255,255,255,0.2);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.features .icon i {
    color: white;
    font-size: 18px;
}

.features strong {
    font-size: 18px;
    display: block;
    margin-bottom: 5px;
}

.features p {
    font-size: 15px;
    opacity: 0.9;
}

/* ===== RIGHT PANEL ===== */
.right-panel {
    width: 55%;
    padding: 70px 60px;
    background: white;
    border-radius: 0 30px 30px 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-box {
    width: 100%;
    max-width: 400px;
}

.login-box h2 {
    font-size: 32px;
    text-align: center;
    margin-bottom: 8px;
}

.form-subtitle {
    text-align: center;
    color: #888;
    margin-bottom: 35px;
    font-size: 16px;
}

/* ===== FORM ===== */
.input-group { margin-bottom: 24px; }

.input-group label {
    display: block;
    margin-bottom: 10px;
    color: #444;
    font-weight: 500;
    font-size: 15px;
}

/* INPUT + ICON ALIGNMENT */
.input-wrapper { position: relative; }

.input-wrapper i {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    color: #888;
}

.input-wrapper .fa-envelope,
.input-wrapper .fa-lock { left: 16px; }

.input-wrapper { position: relative; }
.eye-icon{
  position:absolute;
  right:16px;
  top:50%;
  transform:translateY(-50%);
  cursor:pointer;
  color:#9aa1ad;
  font-size:16px;
}


.input-wrapper input {
    width: 100%;
    padding: 16px 48px;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    outline: none;
    transition: border 0.3s;
}

.input-wrapper input:focus { border-color: var(--red); }

/* OPTIONS */
.options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0 30px;
    font-size: 14px;
}

.checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    color: #666;
}

.checkmark {
    width: 18px;
    height: 18px;
    border: 2px solid #ddd;
    border-radius: 4px;
    display: inline-block;
}

.checkbox input { display: none; }

.checkbox input:checked + .checkmark {
    background: var(--red);
    border-color: var(--red);
}

.forgot {
    color: var(--red);
    text-decoration: none;
    font-weight: 500;
}

.forgot i { margin-right: 6px; }

/* BUTTON */
.signin-btn {
    width: 100%;
    padding: 16px;
    background: var(--red);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    margin-bottom: 30px;
    transition: background 0.3s;
}

.signin-btn:hover { background: var(--dark-red); }

/* DIVIDER */
.divider {
    text-align: center;
    margin: 30px 0;
    color: #aaa;
    position: relative;
    font-size: 14px;
}

.divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #eee;
}

.divider span {
    background: white;
    padding: 0 20px;
    position: relative;
    z-index: 1;
}

/* SOCIAL */
.social-login {
    display: flex;
    gap: 15px;
    margin-bottom: 35px;
}

.social-login button {
    flex: 1;
    padding: 14px;
    border: 1px solid #ddd;
    border-radius: 12px;
    background: white;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    transition: all 0.3s;
}

.social-login button:hover {
    border-color: var(--red);
    box-shadow: 0 4px 12px rgba(198,40,40,0.1);
}

/* SIGNUP LINK */
.signup-link {
    text-align: center;
    color: #888;
    font-size: 15px;
}

.signup-link a {
    color: var(--red);
    text-decoration: none;
    font-weight: 600;
}

/* RESPONSIVE */
@media (max-width: 992px) {
    .login-container { flex-direction: column; border-radius: 24px; }
    .left-panel, .right-panel { width: 100%; }
    .left-panel { padding: 50px; border-radius: 24px 24px 0 0; }
    .right-panel { padding: 50px; border-radius: 0 0 24px 24px; }
}
</style>
</head>

<body>

<div class="login-container">

    <!-- LEFT PANEL -->
    <div class="left-panel">
        <div class="logo">
            <div class="drop"><i class="fa-solid fa-droplet"></i></div>
            RaktaBindu
        </div>

        <h1>Welcome Back!</h1>
        <p class="subtitle">Sign in to continue saving lives through blood donation</p>

        <ul class="features">
            <li>
                <span class="icon"><i class="fa-solid fa-circle-check"></i></span>
                <div>
                    <strong>Find Donors Instantly</strong>
                    <p>Connect with verified donors near you</p>
                </div>
            </li>

            <li>
                <span class="icon"><i class="fa-solid fa-circle-check"></i></span>
                <div>
                    <strong>Save Lives</strong>
                    <p>Every donation can save up to three lives</p>
                </div>
            </li>

            <li>
                <span class="icon"><i class="fa-solid fa-circle-check"></i></span>
                <div>
                    <strong>Hospital Network</strong>
                    <p>Connected with trusted hospitals</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
        <div class="login-box">

            <h2>Login</h2>
            <p class="form-subtitle">Enter your credentials to access your account</p>

            <form action="login.php" method="POST" autocomplete="off" novalidate>

                <div class="input-group">
                    <label>Email</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-wrapper">
  <i class="fa-solid fa-lock"></i>
  <input id="password" type="password" name="password" placeholder="Enter your password" required>
  <i id="togglePassword" class="fa-regular fa-eye-slash eye-icon" title="Show/Hide"></i>

</div>


                <?php if (!empty($error)): ?>
                    <p style="color:#c62828;font-size:14px;margin-top:8px;">
                        <?php echo htmlspecialchars($error); ?>
                    </p>
                <?php endif; ?>

                <div class="options">
                    <label class="checkbox">
                        <input type="checkbox" name="remember">
                        <span class="checkmark"></span>
                        Remember me
                    </label>

                    <a href="forgot-password.php" class="forgot">
                        <i class="fa-solid fa-key"></i> Forgot Password?
                    </a>
                </div>

                <button type="submit" class="signin-btn">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In
                </button>
            </form>

            <div class="divider"><span>Or continue with</span></div>

            <div class="social-login">
                <button type="button" class="google">
                    <i class="fa-brands fa-google"></i> Google
                </button>
                <button type="button" class="facebook">
                    <i class="fa-brands fa-facebook-f"></i> Facebook
                </button>
            </div>

            <p class="signup-link">
                Don’t have an account? <a href="signup.php">Sign Up</a>
            </p>

        </div>
    </div>
</div>
<script>
  const pw = document.getElementById("password");
  const toggle = document.getElementById("togglePassword");

  toggle.addEventListener("click", () => {
    const isHidden = pw.type === "password";
    pw.type = isHidden ? "text" : "password";

    // swap icon
    toggle.classList.toggle("fa-eye");
    toggle.classList.toggle("fa-eye-slash");
  });
</script>

</body>
</html>
