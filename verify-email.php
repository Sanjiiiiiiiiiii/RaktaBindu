<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/db.php";
date_default_timezone_set("Asia/Kathmandu");

$error = "";
$success = "";

$email = trim(strtolower($_GET['email'] ?? ''));

if ($email === "") {
    header("Location: signup.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim(strtolower($_POST['email'] ?? ''));
    $otp   = preg_replace('/\D/', '', $_POST['otp'] ?? '');

    if ($email === "" || $otp === "") {
        $error = "Please enter the verification code.";
    } elseif (strlen($otp) !== 6) {
        $error = "OTP must be exactly 6 digits.";
    } else {

        $stmt = $conn->prepare("
            SELECT verification_code, verification_expires, is_verified
            FROM users
            WHERE TRIM(LOWER(email)) = ?
            LIMIT 1
        ");
        if (!$stmt) die("Prepare failed: " . $conn->error);

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows === 1) {

            $user = $res->fetch_assoc();

            if ((int)$user['is_verified'] === 1) {
                header("Location: login.php?verified=success");
                exit();
            }

            $savedOtp = (string)($user['verification_code'] ?? '');
            $expiry   = (string)($user['verification_expires'] ?? '');

            if ($savedOtp === "" || $expiry === "") {
                $error = "OTP not found. Please request a new one.";
            } elseif ($otp !== $savedOtp) {
                $error = "Invalid OTP. Please check and try again.";
            } elseif (strtotime($expiry) < time()) {
                $error = "OTP has expired. Please request a new one.";
            } else {

                // ✅ Mark account verified
                $upd = $conn->prepare("
                    UPDATE users
                    SET is_verified = 1,
                        verification_code = NULL,
                        verification_expires = NULL
                    WHERE TRIM(LOWER(email)) = ?
                    LIMIT 1
                ");
                if (!$upd) die("Prepare failed: " . $conn->error);

                $upd->bind_param("s", $email);
                $upd->execute();

                header("Location: login.php?verified=success");
                exit();
            }

        } else {
            $error = "Account not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify Email | RaktaBindu</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{box-sizing:border-box;font-family:'Segoe UI',system-ui,-apple-system,sans-serif}
body{
  margin:0;
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  background:linear-gradient(135deg,#b71c1c,#f44336);
}
.card{
  width:100%;
  max-width:460px;
  background:#fff;
  padding:44px 38px;
  border-radius:18px;
  box-shadow:0 18px 50px rgba(0,0,0,.35);
  animation:fadeUp .6s ease;
}
@keyframes fadeUp{
  from{opacity:0;transform:translateY(20px)}
  to{opacity:1;transform:translateY(0)}
}
.logo{
  text-align:center;
  font-size:30px;
  font-weight:900;
  color:#c62828;
  margin-bottom:6px;
}
.subtitle{
  text-align:center;
  color:#666;
  font-size:15px;
  margin-bottom:26px;
}
.otp-box{
  display:flex;
  justify-content:center;
  gap:12px;
  margin-bottom:18px;
}
.otp-box input{
  width:52px;
  height:58px;
  text-align:center;
  font-size:22px;
  font-weight:800;
  border-radius:12px;
  border:1px solid #ddd;
}
.otp-box input:focus{
  outline:none;
  border-color:#c62828;
  box-shadow:0 0 0 4px rgba(198,40,40,.15);
}
button{
  width:100%;
  background:#c62828;
  color:#fff;
  border:none;
  height:56px;
  border-radius:14px;
  font-size:16px;
  font-weight:900;
  cursor:pointer;
  box-shadow:0 12px 28px rgba(198,40,40,.35);
}
button:hover{background:#b71c1c}
.msg{
  padding:14px 16px;
  border-radius:12px;
  font-size:14px;
  margin-bottom:18px;
  text-align:center;
  font-weight:700;
}
.err{background:#ffebee;color:#c62828}
.ok{background:#e8f5e9;color:#2e7d32}
.links{
  margin-top:18px;
  text-align:center;
  font-size:14px;
}
.links a{
  color:#c62828;
  font-weight:800;
  text-decoration:none;
}
.links a:hover{text-decoration:underline}
.hidden{display:none}
</style>
</head>

<body>

<div class="card">
  <div class="logo">🩸 RaktaBindu</div>
  <div class="subtitle">Enter the 6-digit code sent to your email</div>

  <?php if ($error): ?>
    <div class="msg err"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="msg ok"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

    <div class="otp-box">
      <input type="text" maxlength="1" inputmode="numeric">
      <input type="text" maxlength="1" inputmode="numeric">
      <input type="text" maxlength="1" inputmode="numeric">
      <input type="text" maxlength="1" inputmode="numeric">
      <input type="text" maxlength="1" inputmode="numeric">
      <input type="text" maxlength="1" inputmode="numeric">
    </div>

    <input type="hidden" name="otp" id="otpFinal">

    <button type="submit">Verify Email</button>
  </form>

  <div class="links">
    Didn’t get code?
    <a href="resend-signup-otp.php?email=<?php echo urlencode($email); ?>">Resend OTP</a><br><br>
    <a href="login.php">← Back to Login</a>
  </div>
</div>

<script>
const inputs = document.querySelectorAll('.otp-box input');
const hidden = document.getElementById('otpFinal');

inputs.forEach((inp, i) => {
  inp.addEventListener('input', () => {
    inp.value = inp.value.replace(/\D/g,'');
    if (inp.value && inputs[i+1]) inputs[i+1].focus();
    update();
  });
  inp.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !inp.value && inputs[i-1]) {
      inputs[i-1].focus();
    }
  });
});

function update(){
  hidden.value = Array.from(inputs).map(i=>i.value).join('');
}
</script>

</body>
</html>
