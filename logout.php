<?php
session_start();

// Clear session
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Logged Out | RaktaBindu</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{
  box-sizing:border-box;
  font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
}
body{
  background:linear-gradient(135deg,#b71c1c,#f44336);
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  margin:0;
}

.logout-card{
  background:#fff;
  width:100%;
  max-width:420px;
  padding:46px;
  border-radius:18px;
  text-align:center;
  box-shadow:0 20px 50px rgba(0,0,0,.35);
  animation:fadeIn .6s ease;
}

@keyframes fadeIn{
  from{opacity:0;transform:translateY(20px)}
  to{opacity:1;transform:translateY(0)}
}

.icon{
  width:90px;
  height:90px;
  border-radius:50%;
  background:#ffebee;
  display:flex;
  align-items:center;
  justify-content:center;
  margin:0 auto 20px;
  color:#c62828;
  font-size:42px;
}

h1{
  font-size:26px;
  color:#c62828;
  margin-bottom:10px;
  font-weight:900;
}

p{
  color:#555;
  font-size:15px;
  margin-bottom:30px;
  line-height:1.6;
}

.btn{
  display:inline-block;
  padding:14px 34px;
  border-radius:30px;
  background:#c62828;
  color:#fff;
  text-decoration:none;
  font-weight:800;
  font-size:15px;
  transition:.2s ease;
  box-shadow:0 10px 25px rgba(198,40,40,.35);
}

.btn:hover{
  background:#b71c1c;
  transform:translateY(-1px);
}

.timer{
  margin-top:18px;
  font-size:13px;
  color:#888;
}
</style>
</head>

<body>

<div class="logout-card">
  <div class="icon">
    <i class="fa-solid fa-right-from-bracket"></i>
  </div>

  <h1>You’ve been logged out</h1>
  <p>
    Your session has ended successfully.<br>
    Thank you for supporting <strong>RaktaBindu</strong>.
  </p>

  <a href="login.php" class="btn">
    <i class="fa-solid fa-right-to-bracket"></i> Go to Login
  </a>

  <div class="timer">
    Redirecting to login in <span id="count">5</span> seconds…
  </div>
</div>

<script>
let sec = 5;
const el = document.getElementById('count');
setInterval(() => {
  sec--;
  el.textContent = sec;
  if (sec <= 0) {
    window.location.href = "login.php";
  }
}, 1000);
</script>

</body>
</html>
