<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");

if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

if (!isset($_SESSION['chat_role'])) {
    $_SESSION['chat_role'] = 'Donor';
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function add_message(string $sender, string $message): void {
    $_SESSION['chat_history'][] = [
        'sender' => $sender,
        'message' => $message,
        'time' => date('H:i')
    ];
}

function normalize_text(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/\s+/', ' ', $text);
    return $text;
}

function contains_any(string $text, array $keywords): bool {
    foreach ($keywords as $word) {
        if (strpos($text, $word) !== false) {
            return true;
        }
    }
    return false;
}

function bot_reply(string $role, string $message): string {
    $msg = normalize_text($message);

    // greetings
    if (contains_any($msg, ['hi', 'hello', 'hey', 'namaste'])) {
        return "Hello! I’m the RaktaBindu FAQ & Safety Chatbot. I can help with donation safety, request guidance, hospital coordination, eligibility, emergencies, and blood matching basics. Tell me your question.";
    }

    // emergency
    if (contains_any($msg, ['emergency', 'urgent', 'critical', 'immediately'])) {
        return "For an emergency blood need, use the emergency request flow, contact the hospital or blood bank directly, and notify verified matching donors immediately. Do not rely only on chat in a life-threatening situation. Always confirm blood type, hospital location, and required units before proceeding.";
    }

    // donor eligibility
    if (contains_any($msg, ['eligible', 'eligibility', 'can i donate', 'am i eligible'])) {
        return "General donor eligibility usually includes: age 18–65, minimum weight around 50 kg, good health, and no recent donation within about 3 months. Donors should also avoid donating when sick, severely sleep deprived, or taking disqualifying medication. Final screening should always be done by a healthcare professional or blood center.";
    }

    // safety
    if (contains_any($msg, ['safe', 'safety', 'risk', 'dangerous'])) {
        return "Safety comes first. Donors should donate only through trusted hospitals or blood centers, answer health questions honestly, eat properly before donation, stay hydrated, and rest afterward. Receivers and hospitals must verify donor identity, blood type, screening, and collection procedure before transfusion.";
    }

    // donation aftercare
    if (contains_any($msg, ['after donation', 'aftercare', 'after donating', 'what should i do after'])) {
        return "After donation: rest for a short period, drink water, avoid heavy lifting for the day, eat a healthy meal, and seek medical advice if dizziness, bleeding, or weakness continues. If symptoms feel severe, contact the nearest hospital immediately.";
    }

    // blood groups
    if (contains_any($msg, ['blood group', 'blood type', 'compatibility', 'compatible'])) {
        return "Blood compatibility should always be medically confirmed before transfusion. A software system can assist with matching, but the final decision must be verified by healthcare staff. Exact blood group matching is the safest starting rule for a student project unless a full clinical compatibility matrix is validated.";
    }

    // hospital role
    if ($role === 'Hospital') {
        if (contains_any($msg, ['verify donor', 'verification', 'screen donor'])) {
            return "Hospitals should verify donor identity, confirm blood group, check screening requirements, record contact details, confirm donation appointment time, and ensure safe collection procedures. The hospital should also update request status clearly so donors and receivers know the current progress.";
        }

        if (contains_any($msg, ['manage request', 'request status', 'track request'])) {
            return "Hospitals should track each request with clear statuses such as Open, Pending, Accepted, Completed, or Cancelled. Every accepted donor should be linked to the request, and timestamps should be logged for accountability and coordination.";
        }
    }

    // donor role
    if ($role === 'Donor') {
        if (contains_any($msg, ['when can i donate again', 'donate again', 'next donation'])) {
            return "A donor is commonly advised to wait around 3 months between whole blood donations, though exact rules depend on medical guidance and local standards. Always follow the hospital or blood center’s recommendation.";
        }

        if (contains_any($msg, ['i feel sick', 'not feeling well', 'fever', 'cold'])) {
            return "If you are feeling sick, have fever, infection, weakness, or any concerning symptoms, you should not donate until medically fit. It is safer to postpone than to risk your health or the receiver’s safety.";
        }
    }

    // receiver role
    if ($role === 'Receiver') {
        if (contains_any($msg, ['find donor', 'how to find donor', 'matching donor'])) {
            return "To find a donor, create a blood request with the correct blood group, hospital location, urgency, required units, and needed date/time. The system should then show matching available donors and notify them. Always let hospital staff verify the final donor before transfusion.";
        }

        if (contains_any($msg, ['what details', 'what information', 'request details'])) {
            return "A good blood request should include: blood group, quantity needed, hospital or location, urgency level, date and time needed, and any relevant patient notes. Clear information helps the system match donors faster.";
        }
    }

    // hospitals, donors, receivers all
    if (contains_any($msg, ['otp', 'verification code', 'account verification'])) {
        return "OTP or account verification helps confirm that users are real and reduces misuse. It should be used during registration, password recovery, and sensitive account actions.";
    }

    if (contains_any($msg, ['privacy', 'data', 'security'])) {
        return "User privacy is important. The system should protect names, contacts, and request details using secure login, verified access, proper database design, and limited data exposure. Sensitive health-related decisions should always remain under medical supervision.";
    }

    if (contains_any($msg, ['faq', 'help topics', 'what can you do'])) {
        return "I can help with: donor eligibility, blood request guidance, emergency handling, hospital verification steps, donor safety, aftercare, request tracking, privacy, and basic matching guidance.";
    }

    // fallback
    return "I understand your question is about RaktaBindu support. Please share a little more detail, such as whether your issue is about donor eligibility, blood request creation, emergency support, hospital verification, safety, matching, or account help.";
}

// reset chat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_chat'])) {
    $_SESSION['chat_history'] = [];
    $_SESSION['chat_role'] = 'Donor';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// change role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role'])) {
    $role = trim($_POST['role']);
    $allowed = ['Donor', 'Hospital', 'Receiver'];
    if (in_array($role, $allowed, true)) {
        $_SESSION['chat_role'] = $role;
    }
}

// send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_message'])) {
    $role = $_SESSION['chat_role'];
    $userMessage = trim($_POST['chat_message']);

    if ($userMessage !== '') {
        add_message('You', $userMessage);
        $reply = bot_reply($role, $userMessage);
        add_message('RaktaBindu Bot', $reply);
    }
}

$role = $_SESSION['chat_role'];
$history = $_SESSION['chat_history'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>FAQ & Safety Chatbot | RaktaBindu</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

  <style>
    :root{
      --red:#c62828;
      --red-dark:#a61e1e;
      --bg:#f6f7fb;
      --card:#ffffff;
      --text:#1f2430;
      --muted:#667085;
      --line:rgba(0,0,0,.08);
      --shadow:0 14px 34px rgba(0,0,0,.08);
      --radius:22px;
      --green:#15803d;
      --blue:#2563eb;
      --yellow:#b45309;
    }

    *{box-sizing:border-box;margin:0;padding:0}

    body{
      font-family:"Segoe UI",system-ui,Arial,sans-serif;
      background:var(--bg);
      color:var(--text);
    }

    .container{
      width:min(1180px,92%);
      margin:0 auto;
    }

    header{
      background:#fff;
      border-bottom:1px solid var(--line);
      box-shadow:0 8px 30px rgba(0,0,0,.05);
      position:sticky;
      top:0;
      z-index:50;
    }

    .topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:16px;
      padding:14px 0;
      flex-wrap:wrap;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:1000;
      font-size:20px;
    }

    .brand-badge{
      width:36px;height:36px;
      border-radius:12px;
      display:grid;
      place-items:center;
      background:rgba(198,40,40,.10);
      color:var(--red);
    }

    .brand b{color:var(--red)}

    nav ul{
      list-style:none;
      display:flex;
      gap:14px;
      flex-wrap:wrap;
    }

    nav a{
      text-decoration:none;
      color:#394150;
      font-size:13px;
      font-weight:900;
      padding:10px 12px;
      border-radius:12px;
      display:inline-flex;
      align-items:center;
      gap:8px;
    }

    nav a:hover,
    nav a.active{
      background:rgba(198,40,40,.08);
      color:var(--red);
    }

    .hero{
      width:min(1180px,92%);
      margin:24px auto 18px;
      border-radius:28px;
      background:linear-gradient(135deg,#e53935 0%, #c62828 58%, #9f1717 100%);
      color:#fff;
      box-shadow:0 18px 42px rgba(198,40,40,.20);
      overflow:hidden;
      position:relative;
    }

    .hero::before,
    .hero::after{
      content:"";
      position:absolute;
      border-radius:50%;
      background:rgba(255,255,255,.12);
    }

    .hero::before{width:280px;height:280px;top:-100px;right:-70px}
    .hero::after{width:180px;height:180px;bottom:-60px;right:110px;background:rgba(255,255,255,.08)}

    .hero-inner{
      position:relative;
      padding:28px;
      display:grid;
      grid-template-columns:1.2fr .8fr;
      gap:18px;
      align-items:center;
    }

    .hero h1{
      font-size:30px;
      font-weight:1000;
      display:flex;
      align-items:center;
      gap:12px;
    }

    .hero p{
      margin-top:10px;
      line-height:1.7;
      font-size:14px;
      max-width:66ch;
      opacity:.96;
    }

    .hero-boxes{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:12px;
    }

    .hero-box{
      background:rgba(255,255,255,.15);
      border:1px solid rgba(255,255,255,.18);
      border-radius:18px;
      padding:16px;
      backdrop-filter:blur(8px);
    }

    .hero-box .label{
      font-size:12px;
      font-weight:800;
      opacity:.94;
    }

    .hero-box .value{
      margin-top:6px;
      font-size:22px;
      font-weight:1000;
    }

    .layout{
      width:min(1180px,92%);
      margin:0 auto 38px;
      display:grid;
      grid-template-columns:.85fr 1.15fr;
      gap:18px;
      align-items:start;
    }

    .card{
      background:var(--card);
      border:1px solid var(--line);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      padding:18px;
    }

    .card-head{
      margin-bottom:14px;
    }

    .card-head h2{
      font-size:15px;
      font-weight:1000;
      display:flex;
      align-items:center;
      gap:10px;
    }

    .sub{
      color:var(--muted);
      font-size:12px;
      font-weight:800;
      margin-top:6px;
    }

    .role-form{
      display:flex;
      flex-direction:column;
      gap:12px;
    }

    .role-select{
      display:grid;
      grid-template-columns:1fr 1fr 1fr;
      gap:10px;
    }

    .role-btn{
      border:1px solid rgba(0,0,0,.12);
      background:#fff;
      color:#344054;
      border-radius:14px;
      padding:14px 10px;
      font-weight:1000;
      cursor:pointer;
      text-align:center;
      transition:.2s ease;
    }

    .role-btn.active{
      background:rgba(198,40,40,.08);
      border-color:rgba(198,40,40,.22);
      color:var(--red);
    }

    .faq-list{
      display:flex;
      flex-direction:column;
      gap:10px;
      margin-top:14px;
    }

    .faq-btn{
      width:100%;
      text-align:left;
      border:1px solid rgba(0,0,0,.08);
      background:#fff;
      border-radius:14px;
      padding:12px 14px;
      font-size:12px;
      font-weight:900;
      color:#344054;
      cursor:pointer;
      transition:.2s ease;
    }

    .faq-btn:hover{
      background:rgba(198,40,40,.05);
      border-color:rgba(198,40,40,.16);
      color:var(--red);
    }

    .notice{
      margin-top:14px;
      border-radius:16px;
      padding:14px;
      font-size:12px;
      line-height:1.6;
      font-weight:800;
    }

    .notice.red{
      background:#fff3f3;
      color:#9f1d1d;
      border:1px solid #fecaca;
    }

    .notice.blue{
      background:#eff6ff;
      color:#1d4ed8;
      border:1px solid #bfdbfe;
    }

    .chat-shell{
      display:flex;
      flex-direction:column;
      min-height:620px;
    }

    .chat-top{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      margin-bottom:14px;
      flex-wrap:wrap;
    }

    .chat-badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 10px;
      border-radius:999px;
      background:rgba(198,40,40,.08);
      color:var(--red);
      font-size:12px;
      font-weight:1000;
    }

    .chat-box{
      flex:1;
      border:1px solid rgba(0,0,0,.08);
      border-radius:20px;
      background:#fbfcff;
      padding:16px;
      overflow:auto;
      min-height:420px;
      max-height:520px;
    }

    .bubble{
      max-width:85%;
      padding:12px 14px;
      border-radius:18px;
      margin-bottom:12px;
      line-height:1.6;
      font-size:13px;
      box-shadow:0 6px 18px rgba(0,0,0,.04);
    }

    .bubble.user{
      margin-left:auto;
      background:linear-gradient(135deg,#e53935,#c62828);
      color:#fff;
      border-bottom-right-radius:6px;
    }

    .bubble.bot{
      margin-right:auto;
      background:#fff;
      color:#344054;
      border:1px solid rgba(0,0,0,.08);
      border-bottom-left-radius:6px;
    }

    .bubble-time{
      margin-top:6px;
      font-size:10px;
      opacity:.8;
      font-weight:900;
    }

    .empty-chat{
      color:#98a2b3;
      font-weight:1000;
      text-align:center;
      padding:32px 12px;
    }

    .chat-form{
      margin-top:14px;
      display:grid;
      grid-template-columns:1fr auto auto;
      gap:10px;
    }

    .chat-input{
      width:100%;
      height:48px;
      border-radius:14px;
      border:1px solid rgba(0,0,0,.10);
      padding:0 14px;
      font-size:14px;
      outline:none;
      background:#fff;
    }

    .chat-input:focus{
      border-color:rgba(198,40,40,.35);
      box-shadow:0 0 0 5px rgba(198,40,40,.10);
    }

    .btn{
      height:48px;
      border:none;
      border-radius:14px;
      padding:0 16px;
      font-weight:1000;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      gap:8px;
    }

    .btn-primary{
      background:var(--red);
      color:#fff;
    }

    .btn-primary:hover{
      background:var(--red-dark);
    }

    .btn-ghost{
      background:#fff;
      color:#344054;
      border:1px solid rgba(0,0,0,.12);
    }

    @media (max-width: 980px){
      .hero-inner,
      .layout{
        grid-template-columns:1fr;
      }

      .hero-boxes,
      .role-select,
      .chat-form{
        grid-template-columns:1fr;
      }
    }
  </style>
</head>
<body>

<header>
  <div class="container">
    <div class="topbar">
      <div class="brand">
        <span class="brand-badge"><i class="fa-solid fa-droplet"></i></span>
        <span>Rakta.<b>Bindu</b></span>
      </div>

      <nav>
        <ul>
          <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
          <li><a href="request-blood.php"><i class="fa-solid fa-droplet"></i> Request Blood</a></li>
          <li><a href="donor-form.php"><i class="fa-solid fa-hand-holding-droplet"></i> Donor</a></li>
          <li><a class="active" href="faq-safety-chatbot.php"><i class="fa-solid fa-robot"></i> FAQ Chatbot</a></li>
          <li><a href="my-requests.php"><i class="fa-regular fa-file-lines"></i> My Requests</a></li>
        </ul>
      </nav>
    </div>
  </div>
</header>

<section class="hero">
  <div class="hero-inner">
    <div>
      <h1><i class="fa-solid fa-shield-heart"></i> FAQ & Safety Chatbot</h1>
      <p>
        Get quick support for donors, hospitals, and receivers. This chatbot helps answer common questions about
        safety, eligibility, request flow, hospital coordination, emergency handling, and responsible blood donation practices.
      </p>
    </div>

    <div class="hero-boxes">
      <div class="hero-box">
        <div class="label">Active Role</div>
        <div class="value"><?php echo e($role); ?></div>
      </div>
      <div class="hero-box">
        <div class="label">Support Type</div>
        <div class="value">FAQ + Safety</div>
      </div>
      <div class="hero-box">
        <div class="label">Users Supported</div>
        <div class="value">3 Roles</div>
      </div>
      <div class="hero-box">
        <div class="label">Chat History</div>
        <div class="value"><?php echo count($history); ?></div>
      </div>
    </div>
  </div>
</section>

<section class="layout">
  <div class="card">
    <div class="card-head">
      <h2><i class="fa-solid fa-sliders"></i> Chat Controls</h2>
      <div class="sub">Choose your role and use quick FAQ buttons</div>
    </div>

    <form method="POST" class="role-form">
      <div class="role-select">
        <button class="role-btn <?php echo $role === 'Donor' ? 'active' : ''; ?>" type="submit" name="role" value="Donor">
          <i class="fa-solid fa-hand-holding-droplet"></i><br>Donor
        </button>
        <button class="role-btn <?php echo $role === 'Hospital' ? 'active' : ''; ?>" type="submit" name="role" value="Hospital">
          <i class="fa-solid fa-hospital"></i><br>Hospital
        </button>
        <button class="role-btn <?php echo $role === 'Receiver' ? 'active' : ''; ?>" type="submit" name="role" value="Receiver">
          <i class="fa-solid fa-user-injured"></i><br>Receiver
        </button>
      </div>
    </form>

    <div class="faq-list">
      <form method="POST">
        <input type="hidden" name="chat_message" value="Am I eligible to donate blood?">
        <button class="faq-btn" type="submit"><i class="fa-solid fa-circle-question"></i> Am I eligible to donate blood?</button>
      </form>

      <form method="POST">
        <input type="hidden" name="chat_message" value="What should I do in an emergency blood situation?">
        <button class="faq-btn" type="submit"><i class="fa-solid fa-bolt"></i> What should I do in an emergency?</button>
      </form>

      <form method="POST">
        <input type="hidden" name="chat_message" value="Is blood donation safe?">
        <button class="faq-btn" type="submit"><i class="fa-solid fa-shield-heart"></i> Is blood donation safe?</button>
      </form>

      <form method="POST">
        <input type="hidden" name="chat_message" value="What details are needed in a blood request?">
        <button class="faq-btn" type="submit"><i class="fa-solid fa-file-medical"></i> What details are needed in a request?</button>
      </form>

      <form method="POST">
        <input type="hidden" name="chat_message" value="How should hospitals verify a donor?">
        <button class="faq-btn" type="submit"><i class="fa-solid fa-user-check"></i> How should hospitals verify a donor?</button>
      </form>

      <form method="POST">
        <input type="hidden" name="chat_message" value="What should I do after donating blood?">
        <button class="faq-btn" type="submit"><i class="fa-solid fa-bed"></i> What should I do after donating?</button>
      </form>
    </div>

    <div class="notice red">
      <i class="fa-solid fa-triangle-exclamation"></i>
      This chatbot provides support information only. Final blood compatibility, screening, and transfusion decisions must always be confirmed by qualified healthcare professionals.
    </div>

    <div class="notice blue">
      <i class="fa-solid fa-circle-info"></i>
      Best for: donors checking eligibility, hospitals reviewing safe workflow, and receivers understanding how to request blood properly.
    </div>

    <form method="POST" style="margin-top:12px;">
      <button class="btn btn-ghost" type="submit" name="reset_chat" value="1">
        <i class="fa-solid fa-rotate-left"></i> Reset Chat
      </button>
    </form>
  </div>

  <div class="card chat-shell">
    <div class="chat-top">
      <div>
        <div class="card-head" style="margin-bottom:0;">
          <h2><i class="fa-solid fa-comments"></i> Live Support Chat</h2>
        </div>
        <div class="sub">Ask questions related to safety, donation, hospitals, or request handling</div>
      </div>

      <div class="chat-badge">
        <i class="fa-solid fa-user-tag"></i> Role: <?php echo e($role); ?>
      </div>
    </div>

    <div class="chat-box" id="chatBox">
      <?php if (!$history): ?>
        <div class="empty-chat">
          Start by typing a question or clicking one of the quick FAQ buttons.
        </div>
      <?php else: ?>
        <?php foreach ($history as $item): ?>
          <div class="bubble <?php echo $item['sender'] === 'You' ? 'user' : 'bot'; ?>">
            <div><?php echo nl2br(e($item['message'])); ?></div>
            <div class="bubble-time"><?php echo e($item['sender']); ?> • <?php echo e($item['time']); ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <form method="POST" class="chat-form">
      <input
        class="chat-input"
        type="text"
        name="chat_message"
        placeholder="Type your question here..."
        autocomplete="off"
        required
      >
      <button class="btn btn-primary" type="submit">
        <i class="fa-solid fa-paper-plane"></i> Send
      </button>
      <button class="btn btn-ghost" type="submit" name="chat_message" value="What can you do?">
        <i class="fa-solid fa-circle-question"></i> Help
      </button>
    </form>
  </div>
</section>

<script>
  const chatBox = document.getElementById("chatBox");
  if (chatBox) {
    chatBox.scrollTop = chatBox.scrollHeight;
  }
</script>

</body>
</html>