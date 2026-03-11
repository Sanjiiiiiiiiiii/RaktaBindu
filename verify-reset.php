<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/db.php";

$error="";
$email = trim(strtolower($_GET['email'] ?? ''));

if ($email === "") {
    header("Location: forgot-password.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $code  = preg_replace('/\D/', '', $_POST['code'] ?? '');

    if ($code === "" || strlen($code) !== 6) {
        $error = "Enter 6-digit OTP.";
    } else {

        $stmt = $conn->prepare("
          SELECT id FROM users
          WHERE TRIM(LOWER(email))=?
            AND reset_code=?
            AND reset_expires > NOW()
          LIMIT 1
        ");
        $stmt->bind_param("ss", $email, $code);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows === 1) {
            header("Location: reset-password.php?email=" . urlencode($email));
            exit();
        } else {
            $error = "Invalid or expired OTP.";
        }
    }
}
?>
<!-- keep your same verify-reset HTML, ensure input name="code" -->

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify Email | RaktaBindu</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
body{background:linear-gradient(135deg,#b71c1c,#f44336);min-height:100vh;display:flex;justify-content:center;align-items:center;margin:0}
.card{background:#fff;width:100%;max-width:440px;padding:42px;border-radius:16px;box-shadow:0 15px 40px rgba(0,0,0,.3)}
h1{margin:0 0 8px;color:#c62828;font-size:28px;font-weight:900;text-align:center}
p{margin:0 0 22px;color:#555;text-align:center}
label{display:block;font-weight:700;font-size:14px;margin-bottom:8px}
input{width:100%;padding:13px 14px;border-radius:10px;border:1px solid #ccc;font-size:16px;text-align:center;letter-spacing:6px}
input:focus{outline:none;border-color:#c62828;box-shadow:0 0 0 4px rgba(198,40,40,.12)}
button{width:100%;background:#c62828;color:#fff;border:none;padding:14px;font-size:16px;border-radius:10px;cursor:pointer;font-weight:800;margin-top:14px}
button:hover{background:#b71c1c}
.error{background:#ffebee;color:#c62828;padding:12px;border-radius:10px;font-size:14px;margin:0 0 16px;text-align:center;font-weight:600}
.success{background:#e8f5e9;color:#2e7d32;padding:12px;border-radius:10px;font-size:14px;margin:0 0 16px;text-align:center;font-weight:600;border:1px solid #c8e6c9}
.small{margin-top:16px;text-align:center;font-size:14px}
.small a{color:#c62828;text-decoration:none;font-weight:700}
.small a:hover{text-decoration:underline}
</style>
</head>
<body>
  <div class="card">
    <h1>Verify Email</h1>
    <p>Enter the 6-digit code sent to your email</p>

    <?php if (!empty($resentMsg)): ?>
      <div class="success"><?php echo htmlspecialchars($resentMsg); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
      <label>Verification Code</label>
      <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" required>
      <button type="submit">Verify</button>
    </form>

    <div class="small">
      Didn’t get the code?
      <a href="resend-signup-otp.php?email=<?php echo urlencode($email); ?>">Resend OTP</a>
      <br><br>
      <a href="login.php">← Back to Login</a>
    </div>
  </div>
</body>
</html>
