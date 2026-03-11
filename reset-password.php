<?php
require_once __DIR__ . "/db.php";

$error = "";
$success = "";
$email = trim(strtolower($_GET['email'] ?? ''));

// 🚫 Block direct access
if ($email === "") {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email    = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password === "" || $confirm === "") {
        $error = "Please fill all fields.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            UPDATE users
            SET password = ?
            WHERE TRIM(LOWER(email)) = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $hash, $email);
        $stmt->execute();

        if ($stmt->affected_rows === 1) {
            header("Location: login.php?reset=success");
            exit();
        } else {
            $error = "Unable to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password | RaktaBindu</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
body{
    background:linear-gradient(135deg,#b71c1c,#f44336);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0;
}
.reset-box{
    background:#fff;
    width:100%;
    max-width:440px;
    padding:42px;
    border-radius:18px;
    box-shadow:0 18px 45px rgba(0,0,0,.3);
}
.logo{
    text-align:center;
    font-size:28px;
    font-weight:900;
    color:#c62828;
    margin-bottom:6px;
}
.subtitle{
    text-align:center;
    color:#666;
    font-size:14px;
    margin-bottom:28px;
}
.field{
    margin-bottom:18px;
}
.field label{
    display:block;
    font-weight:700;
    font-size:14px;
    margin-bottom:8px;
}
.control{
    position:relative;
}
.control i.left{
    position:absolute;
    left:14px;
    top:50%;
    transform:translateY(-50%);
    color:#999;
}
.control i.eye{
    position:absolute;
    right:14px;
    top:50%;
    transform:translateY(-50%);
    cursor:pointer;
    color:#999;
}
input{
    width:100%;
    padding:14px 44px;
    border-radius:10px;
    border:1px solid #ccc;
    font-size:15px;
}
input:focus{
    outline:none;
    border-color:#c62828;
    box-shadow:0 0 0 4px rgba(198,40,40,.12);
}
.btn{
    width:100%;
    background:#c62828;
    color:#fff;
    border:none;
    padding:15px;
    font-size:16px;
    border-radius:10px;
    cursor:pointer;
    font-weight:900;
}
.btn:hover{background:#b71c1c}
.error{
    background:#ffebee;
    color:#c62828;
    padding:12px;
    border-radius:10px;
    font-size:14px;
    margin-bottom:16px;
    text-align:center;
    font-weight:600;
}
.back{
    text-align:center;
    margin-top:20px;
}
.back a{
    color:#c62828;
    text-decoration:none;
    font-weight:700;
}
</style>
</head>

<body>

<div class="reset-box">
    <div class="logo">Reset Password</div>
    <div class="subtitle">Create a new secure password</div>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

        <div class="field">
            <label>New Password</label>
            <div class="control">
                <i class="fa-solid fa-lock left"></i>
                <input type="password" id="pw" name="password" placeholder="Enter new password" required>
                <i class="fa-regular fa-eye eye" data-target="pw"></i>
            </div>
        </div>

        <div class="field">
            <label>Confirm Password</label>
            <div class="control">
                <i class="fa-solid fa-lock left"></i>
                <input type="password" id="cpw" name="confirm_password" placeholder="Confirm password" required>
            </div>
        </div>

        <button class="btn" type="submit">Reset Password</button>
    </form>

    <div class="back">
        <a href="login.php">← Back to Login</a>
    </div>
</div>

<script>
document.querySelectorAll('.eye').forEach(icon => {
    icon.addEventListener('click', () => {
        const input = document.getElementById(icon.dataset.target);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });
});
</script>

</body>
</html>
