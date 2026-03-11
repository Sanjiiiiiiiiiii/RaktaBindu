<?php
require_once "db.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/PHPMailer/src/PHPMailer.php";
require __DIR__ . "/PHPMailer/src/SMTP.php";
require __DIR__ . "/PHPMailer/src/Exception.php";

$error="";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim(strtolower($_POST['email'] ?? ''));

    $stmt = $conn->prepare("SELECT id FROM users WHERE TRIM(LOWER(email))=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {

        $otp = (string)rand(100000,999999);

        $upd = $conn->prepare("
          UPDATE users
          SET reset_code=?, reset_expires=DATE_ADD(NOW(), INTERVAL 10 MINUTE)
          WHERE TRIM(LOWER(email))=?
          LIMIT 1
        ");
        $upd->bind_param("ss", $otp, $email);
        $upd->execute();

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = "smtp.gmail.com";
            $mail->SMTPAuth = true;
            $mail->Username = "sanjiwanikarki@gmail.com";
            $mail->Password = "qcymjgvfcoyyeyir";
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom("sanjiwanikarki@gmail.com", "RaktaBindu");
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = "Password Reset OTP - RaktaBindu";
            $mail->Body = "<h2>Password Reset</h2><h1>$otp</h1><p>Valid 10 minutes.</p>";
            $mail->send();

            header("Location: verify-reset.php?email=" . urlencode($email));
            exit();

        } catch (Exception $e) {
            $error = "OTP saved, but mail failed: " . $mail->ErrorInfo;
        }

    } else {
        $error="Email not registered!";
    }
}
?>
<!-- keep your HTML -->


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password | RaktaBindu</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* {
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background: linear-gradient(135deg, #b71c1c, #f44336);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0;
}

.forgot-container {
    background: #fff;
    width: 100%;
    max-width: 420px;
    padding: 40px;
    border-radius: 14px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
    animation: fadeIn 0.6s ease-in-out;
}

@keyframes fadeIn {
    from {opacity:0; transform: translateY(20px);}
    to {opacity:1; transform: translateY(0);}
}

.logo {
    text-align: center;
    font-size: 26px;
    font-weight: bold;
    color: #c62828;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    color: #555;
    font-size: 14px;
    margin-bottom: 30px;
}

.input-group {
    margin-bottom: 20px;
}

.input-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 6px;
}

.input-group input {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 15px;
}

.input-group input:focus {
    outline: none;
    border-color: #c62828;
}

.send-btn {
    width: 100%;
    background: #c62828;
    color: white;
    border: none;
    padding: 14px;
    font-size: 16px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

.send-btn:hover {
    background: #b71c1c;
}

.error {
    background: #ffebee;
    color: #c62828;
    padding: 10px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 15px;
    text-align: center;
}

.back-link {
    text-align: center;
    margin-top: 20px;
}

.back-link a {
    color: #c62828;
    text-decoration: none;
    font-weight: 600;
}

.back-link a:hover {
    text-decoration: underline;
}
</style>
</head>

<body>

<div class="forgot-container">

    <div class="logo">🔑 RaktaBindu</div>
    <div class="subtitle">
        Enter your registered email to receive an OTP
    </div>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="input-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="Enter your email" required>
        </div>

        <button type="submit" class="send-btn">
            Send OTP
        </button>

    </form>

    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>

</div>

</body>
</html>
