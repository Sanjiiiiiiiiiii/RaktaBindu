<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Asia/Kathmandu");
require_once __DIR__ . "/db.php";

/* ✅ Profile page MUST be logged-in */
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$uid = (int)$_SESSION['user_id'];
$success = "";
$error   = "";

/* ---------------- Helpers ---------------- */
function clean($v){ return trim((string)$v); }

/* ✅ Read users table columns so this file works even if your column names differ */
$cols = [];
$colRes = $conn->query("SHOW COLUMNS FROM users");
if ($colRes) {
  while ($r = $colRes->fetch_assoc()) $cols[$r['Field']] = true;
}
function hasCol($name, $cols){ return isset($cols[$name]); }
function firstExistingCol($candidates, $cols){
  foreach ($candidates as $c) if (hasCol($c, $cols)) return $c;
  return null;
}

/* ✅ Map your DB columns (auto-detect) */
$COL_ID   = firstExistingCol(['id','user_id'], $cols) ?? 'id';
$COL_FN   = firstExistingCol(['firstName','firstname','first_name'], $cols);
$COL_LN   = firstExistingCol(['lastName','lastname','last_name'], $cols);
$COL_FULL = firstExistingCol(['full_name','name','user_name','username'], $cols);
$COL_EMAIL= firstExistingCol(['email','user_email'], $cols);
$COL_PHONE= firstExistingCol(['phone','contact','contact_number','mobile'], $cols);
$COL_BG   = firstExistingCol(['bloodType','blood_group','bloodtype'], $cols);
$COL_LOC  = firstExistingCol(['location','address','city'], $cols);
$COL_AV   = firstExistingCol(['availability','available_status'], $cols);
$COL_VER  = firstExistingCol(['is_verified','verified'], $cols);

/* ✅ Build SELECT safely */
$selectCols = [$COL_ID];
if ($COL_FN)   $selectCols[] = $COL_FN;
if ($COL_LN)   $selectCols[] = $COL_LN;
if ($COL_FULL) $selectCols[] = $COL_FULL;
if ($COL_EMAIL)$selectCols[] = $COL_EMAIL;
if ($COL_PHONE)$selectCols[] = $COL_PHONE;
if ($COL_BG)   $selectCols[] = $COL_BG;
if ($COL_LOC)  $selectCols[] = $COL_LOC;
if ($COL_AV)   $selectCols[] = $COL_AV;
if ($COL_VER)  $selectCols[] = $COL_VER;

$selectSql = "SELECT " . implode(", ", array_map(fn($c)=>"`$c`", array_unique($selectCols))) . " FROM users WHERE `$COL_ID` = ? LIMIT 1";
$stmt = $conn->prepare($selectSql);
if (!$stmt) die("DB Error: ".$conn->error);
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) die("User not found.");

/* ✅ Create display name */
$displayName = "User";
if ($COL_FN || $COL_LN) {
  $fn = $COL_FN ? ($user[$COL_FN] ?? "") : "";
  $ln = $COL_LN ? ($user[$COL_LN] ?? "") : "";
  $displayName = trim($fn . " " . $ln) ?: "User";
} elseif ($COL_FULL) {
  $displayName = trim($user[$COL_FULL] ?? "") ?: "User";
} elseif (isset($_SESSION['user_name'])) {
  $displayName = $_SESSION['user_name'];
}
$avatarLetter = strtoupper(mb_substr($displayName, 0, 1));

$isVerified = false;
if ($COL_VER) $isVerified = ((int)($user[$COL_VER] ?? 0) === 1);

/* ✅ Options */
$validGroups = ["A+","A-","B+","B-","O+","O-","AB+","AB-"];
$validAvail  = ["Available anytime","Available in emergencies only","Available on weekends","Not available currently"];

/* ---------------- Update Logic ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  // Inputs
  $firstName = clean($_POST["firstName"] ?? "");
  $lastName  = clean($_POST["lastName"] ?? "");
  $fullName  = clean($_POST["full_name"] ?? "");
  $phone     = clean($_POST["phone"] ?? "");
  $bloodType = clean($_POST["bloodType"] ?? "");
  $location  = clean($_POST["location"] ?? "");
  $availability = clean($_POST["availability"] ?? "");

  // Validation (soft: depends what columns exist)
  if (($COL_FN || $COL_LN) && ($firstName === "" || $lastName === "")) {
    $error = "Please enter your first and last name.";
  } elseif ((!$COL_FN && !$COL_LN && $COL_FULL) && $fullName === "") {
    $error = "Please enter your full name.";
  } elseif ($phone !== "" && strlen($phone) < 7) {
    $error = "Please enter a valid phone number.";
  } elseif ($COL_BG && $bloodType !== "" && !in_array($bloodType, $validGroups, true)) {
    $error = "Invalid blood group.";
  } elseif ($COL_AV && $availability !== "" && !in_array($availability, $validAvail, true)) {
    $error = "Invalid availability option.";
  } else {

    // Build dynamic UPDATE only for columns that exist in your table
    $setParts = [];
    $params   = [];
    $types    = "";

    if ($COL_FN)   { $setParts[] = "`$COL_FN` = ?";   $params[] = $firstName;   $types .= "s"; }
    if ($COL_LN)   { $setParts[] = "`$COL_LN` = ?";   $params[] = $lastName;    $types .= "s"; }
    if ($COL_FULL) { $setParts[] = "`$COL_FULL` = ?"; $params[] = ($COL_FN||$COL_LN) ? trim($firstName." ".$lastName) : $fullName; $types .= "s"; }
    if ($COL_PHONE){ $setParts[] = "`$COL_PHONE` = ?";$params[] = $phone;       $types .= "s"; }
    if ($COL_BG)   { $setParts[] = "`$COL_BG` = ?";   $params[] = $bloodType;   $types .= "s"; }
    if ($COL_LOC)  { $setParts[] = "`$COL_LOC` = ?";  $params[] = $location;    $types .= "s"; }
    if ($COL_AV)   { $setParts[] = "`$COL_AV` = ?";   $params[] = $availability;$types .= "s"; }

    if (count($setParts) === 0) {
      $error = "No editable columns found in your users table. (Check column names.)";
    } else {

      $sql = "UPDATE users SET " . implode(", ", $setParts) . " WHERE `$COL_ID` = ? LIMIT 1";
      $up = $conn->prepare($sql);
      if (!$up) {
        $error = "DB Error: " . $conn->error;
      } else {

        $params[] = $uid;
        $types   .= "i";

        // Dynamic bind_param for mysqli
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        call_user_func_array([$up, 'bind_param'], $bind);

        if ($up->execute()) {
          $success = "Profile updated successfully.";

          // Update session name for header (best effort)
          if ($COL_FN || $COL_LN) {
            $_SESSION["user_name"] = trim($firstName . " " . $lastName);
          } elseif ($COL_FULL) {
            $_SESSION["user_name"] = ($COL_FN||$COL_LN) ? trim($firstName." ".$lastName) : $fullName;
          }

          // Re-fetch updated user
          $stmt->execute();
          $user = $stmt->get_result()->fetch_assoc();

          // Refresh display name
          if ($COL_FN || $COL_LN) {
            $fn = $COL_FN ? ($user[$COL_FN] ?? "") : "";
            $ln = $COL_LN ? ($user[$COL_LN] ?? "") : "";
            $displayName = trim($fn . " " . $ln) ?: "User";
          } elseif ($COL_FULL) {
            $displayName = trim($user[$COL_FULL] ?? "") ?: "User";
          } else {
            $displayName = $_SESSION["user_name"] ?? "User";
          }
          $avatarLetter = strtoupper(mb_substr($displayName, 0, 1));
          if ($COL_VER) $isVerified = ((int)($user[$COL_VER] ?? 0) === 1);

        } else {
          $error = "Failed to update profile: " . $up->error;
        }
      }
    }
  }
}

/* Values for form */
$valFirst = $COL_FN ? ($user[$COL_FN] ?? "") : "";
$valLast  = $COL_LN ? ($user[$COL_LN] ?? "") : "";
$valFull  = $COL_FULL ? ($user[$COL_FULL] ?? "") : "";
$valEmail = $COL_EMAIL ? ($user[$COL_EMAIL] ?? "") : ($_SESSION['user_email'] ?? "");
$valPhone = $COL_PHONE ? ($user[$COL_PHONE] ?? "") : "";
$valBG    = $COL_BG ? ($user[$COL_BG] ?? "") : "";
$valLoc   = $COL_LOC ? ($user[$COL_LOC] ?? "") : "";
$valAvail = $COL_AV ? ($user[$COL_AV] ?? "Available anytime") : "Available anytime";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile | Rakta.Bindu</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

  <style>
    :root{
      --red:#c62828;
      --red2:#b71c1c;
      --bg:#f6f7fb;
      --card:#ffffff;
      --text:#1f2430;
      --muted:#667085;
      --line:rgba(0,0,0,.08);
      --shadow2:0 10px 30px rgba(0,0,0,.08);
      --r:18px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Segoe UI",system-ui,Arial;background:var(--bg);color:var(--text);}
    a{text-decoration:none;color:inherit}

    .shell{
      width:min(1100px,92%);
      margin:22px auto 28px;
      background:#fff;
      border:1px solid var(--line);
      border-radius:14px;
      box-shadow:0 12px 40px rgba(0,0,0,.06);
      overflow:hidden;
    }

    .topnav{
      display:flex; align-items:center; justify-content:space-between;
      padding:14px 18px; border-bottom:1px solid rgba(0,0,0,.06); background:#fff; gap:12px;
    }
    .brand{display:flex;align-items:center;gap:10px;font-weight:1000}
    .brand .logo{
      width:34px;height:34px;border-radius:10px;background:rgba(198,40,40,.10);
      display:grid;place-items:center;color:var(--red);
    }
    .brand small{display:block;color:#98a2b3;font-weight:800;font-size:11px;margin-top:-2px}
    .brand .text b{color:var(--red)}
    .links{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
    .links a{
      color:#667085;font-weight:900;font-size:12px;
      padding:10px 12px;border-radius:12px;display:inline-flex;gap:8px;align-items:center;
    }
    .links a:hover{background:rgba(198,40,40,.08);color:var(--red)}
    .links a.active{color:var(--red);background:rgba(198,40,40,.06)}
    .rightbar{display:flex;align-items:center;gap:10px}
    .avatar{
      width:38px;height:38px;border-radius:50%;
      background:rgba(198,40,40,.10); color:var(--red);
      display:grid;place-items:center; font-weight:1000;
      border:1px solid rgba(198,40,40,.18);
    }
    .logout{
      padding:10px 12px;border-radius:12px;
      background:var(--red);color:#fff;font-weight:1000;font-size:12px;
      display:inline-flex;align-items:center;gap:8px;
    }
    .logout:hover{background:var(--red2)}

    .content{
      padding:18px;
      display:grid;
      grid-template-columns: 1.2fr .8fr;
      gap:16px;
      background:#fff;
    }

    .card{
      background:var(--card);
      border:1px solid rgba(0,0,0,.08);
      border-radius:16px;
      box-shadow:var(--shadow2);
      padding:16px;
    }

    .head{display:flex; gap:10px; align-items:flex-start; margin-bottom:12px;}
    .badge{
      width:40px;height:40px;border-radius:12px;background:rgba(198,40,40,.10);
      color:var(--red);display:grid;place-items:center;border:1px solid rgba(198,40,40,.16);
    }
    .head h2{font-size:16px;font-weight:1000;line-height:1.2}
    .head p{font-size:12px;color:var(--muted);margin-top:4px}

    .msg{
      padding:12px 14px;border-radius:14px;font-size:13px;font-weight:900;
      border:1px solid;margin-bottom:12px;display:flex;gap:10px;align-items:flex-start;
    }
    .msg.ok{background:#e8f5e9;border-color:#c8e6c9;color:#2e7d32}
    .msg.bad{background:#ffebee;border-color:#ffcdd2;color:#b71c1c}

    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .field{margin-top:12px}
    label{display:block;font-size:11px;font-weight:1000;color:#475467;margin:0 0 8px 2px}
    .control{position:relative}
    .control i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#98a2b3}
    input, select{
      width:100%;height:44px;border-radius:12px;border:1px solid rgba(0,0,0,.10);
      padding:0 12px 0 38px;outline:none;font-size:13px;background:#fff;
    }
    input:focus, select:focus{
      border-color:rgba(198,40,40,.35);
      box-shadow:0 0 0 5px rgba(198,40,40,.10);
    }
    input[readonly]{background:#f7f7fb;color:#667085}

    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
    .btn{
      height:46px;border-radius:12px;border:none;cursor:pointer;font-weight:1000;
      padding:0 14px;display:inline-flex;align-items:center;gap:10px;justify-content:center;
      transition:.15s ease;
    }
    .btn-primary{background:var(--red);color:#fff;flex:1}
    .btn-primary:hover{background:var(--red2);transform:translateY(-1px)}
    .btn-ghost{background:#fff;border:1px solid rgba(0,0,0,.12);color:#344054;min-width:180px}
    .btn-ghost:hover{transform:translateY(-1px);box-shadow:0 14px 30px rgba(0,0,0,.10)}

    .pill{
      display:inline-flex;align-items:center;gap:8px;
      padding:8px 10px;border-radius:999px;font-size:11px;font-weight:1000;
      border:1px solid rgba(0,0,0,.10); background:#fff; color:#344054;
      margin:6px 6px 0 0;
    }
    .pill.ok{border-color:#c8e6c9;background:#e8f5e9;color:#2e7d32}
    .pill.bad{border-color:#ffcdd2;background:#ffebee;color:#b71c1c}

    @media (max-width: 980px){
      .content{grid-template-columns:1fr}
      .links{gap:10px}
    }
  </style>
</head>

<body>
  <div class="shell">
    <div class="topnav">
      <div class="brand">
        <div class="logo"><i class="fa-solid fa-droplet"></i></div>
        <div class="text">
          Rakta.<b>Bindu</b>
          <small>Profile & Settings</small>
        </div>
      </div>

      <div class="links">
        <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
        <a href="request-blood.php"><i class="fa-solid fa-droplet"></i> Request</a>
        <a href="donor-form.php"><i class="fa-solid fa-hand-holding-droplet"></i> Donate</a>
        <a class="active" href="profile.php"><i class="fa-regular fa-user"></i> Profile</a>
      </div>

      <div class="rightbar">
        <div class="avatar" title="<?php echo htmlspecialchars($displayName); ?>">
          <?php echo htmlspecialchars($avatarLetter); ?>
        </div>
        <a class="logout" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>

    <div class="content">
      <!-- LEFT -->
      <div class="card">
        <div class="head">
          <div class="badge"><i class="fa-regular fa-id-card"></i></div>
          <div>
            <h2>My Profile</h2>
            <p>Update your details to help faster matching.</p>
          </div>
        </div>

        <?php if ($success): ?>
          <div class="msg ok"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="msg bad"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
          <?php if ($COL_FN || $COL_LN): ?>
            <div class="grid2">
              <div class="field">
                <label>First Name</label>
                <div class="control">
                  <i class="fa-regular fa-user"></i>
                  <input name="firstName" type="text" required value="<?php echo htmlspecialchars($valFirst); ?>">
                </div>
              </div>

              <div class="field">
                <label>Last Name</label>
                <div class="control">
                  <i class="fa-regular fa-user"></i>
                  <input name="lastName" type="text" required value="<?php echo htmlspecialchars($valLast); ?>">
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="field">
              <label>Full Name</label>
              <div class="control">
                <i class="fa-regular fa-user"></i>
                <input name="full_name" type="text" required value="<?php echo htmlspecialchars($valFull); ?>">
              </div>
            </div>
          <?php endif; ?>

          <div class="field">
            <label>Email (Read Only)</label>
            <div class="control">
              <i class="fa-regular fa-envelope"></i>
              <input type="email" value="<?php echo htmlspecialchars($valEmail); ?>" readonly>
            </div>
          </div>

          <?php if ($COL_PHONE): ?>
            <div class="field">
              <label>Phone</label>
              <div class="control">
                <i class="fa-solid fa-phone"></i>
                <input name="phone" type="text" placeholder="+977 98XXXXXXXX" value="<?php echo htmlspecialchars($valPhone); ?>">
              </div>
            </div>
          <?php endif; ?>

          <?php if ($COL_BG): ?>
            <div class="field">
              <label>Blood Group</label>
              <div class="control">
                <i class="fa-solid fa-droplet"></i>
                <select name="bloodType">
                  <option value="">Select</option>
                  <?php foreach($validGroups as $g): ?>
                    <option value="<?php echo htmlspecialchars($g); ?>" <?php echo ($valBG === $g) ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($g); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($COL_LOC): ?>
            <div class="field">
              <label>Location</label>
              <div class="control">
                <i class="fa-solid fa-location-dot"></i>
                <input name="location" type="text" placeholder="e.g., Jhapa, Dharan, Kathmandu..." value="<?php echo htmlspecialchars($valLoc); ?>">
              </div>
            </div>
          <?php endif; ?>

          <?php if ($COL_AV): ?>
            <div class="field">
              <label>Availability</label>
              <div class="control">
                <i class="fa-regular fa-clock"></i>
                <select name="availability">
                  <?php foreach($validAvail as $a): ?>
                    <option value="<?php echo htmlspecialchars($a); ?>" <?php echo ($valAvail === $a) ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($a); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          <?php endif; ?>

          <div class="actions">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            <a class="btn btn-ghost" href="change-password.php"><i class="fa-solid fa-key"></i> Change Password</a>
          </div>
        </form>
      </div>

      <!-- RIGHT -->
      <div class="card">
        <div class="head">
          <div class="badge"><i class="fa-solid fa-shield-heart"></i></div>
          <div>
            <h2>Account Status</h2>
            <p>Overview of your matching readiness.</p>
          </div>
        </div>

        <?php if ($COL_VER): ?>
          <?php if ($isVerified): ?>
            <span class="pill ok"><i class="fa-solid fa-circle-check"></i> Verified</span>
          <?php else: ?>
            <span class="pill bad"><i class="fa-solid fa-triangle-exclamation"></i> Not Verified</span>
          <?php endif; ?>
        <?php else: ?>
          <span class="pill"><i class="fa-solid fa-circle-info"></i> Verification column not found</span>
        <?php endif; ?>

        <?php if ($COL_BG): ?>
          <span class="pill"><i class="fa-solid fa-droplet"></i> <?php echo htmlspecialchars($valBG ?: "Blood group not set"); ?></span>
        <?php endif; ?>

        <?php if ($COL_LOC): ?>
          <span class="pill"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($valLoc ?: "Location not set"); ?></span>
        <?php endif; ?>

        <?php if ($COL_AV): ?>
          <span class="pill"><i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars($valAvail ?: "Availability not set"); ?></span>
        <?php endif; ?>

        <div style="margin-top:14px;color:var(--muted);font-size:12.5px;line-height:1.7;">
          <b style="color:#111827;">Tips to get matched faster:</b><br>
          • Add accurate <b style="color:#111827;">location</b> (city/area).<br>
          • Keep your <b style="color:#111827;">availability</b> updated.<br>
          • Add a working <b style="color:#111827;">phone</b> so seekers can contact you quickly.
        </div>

        <div style="margin-top:14px;border-top:1px solid rgba(0,0,0,.06);padding-top:14px;">
          <div style="font-weight:1000;font-size:12px;color:#344054;margin-bottom:6px;">
            <i class="fa-solid fa-triangle-exclamation" style="color:var(--red);"></i> Safety Reminder
          </div>
          <div style="font-size:12.5px;color:var(--muted);line-height:1.7;">
            Always confirm donation details with a verified hospital/center and never share sensitive information.
          </div>
        </div>

        <div style="margin-top:14px;border-top:1px solid rgba(0,0,0,.06);padding-top:14px;">
          <div style="font-weight:1000;font-size:12px;color:#344054;margin-bottom:6px;">
            <i class="fa-solid fa-database" style="color:var(--red);"></i> Debug (auto detected)
          </div>
          <div style="font-size:12px;color:var(--muted);line-height:1.7;">
            Detected columns:
            <br>• Name: <?php echo htmlspecialchars(($COL_FN||$COL_LN) ? ($COL_FN." / ".$COL_LN) : ($COL_FULL ?: "not found")); ?>
            <br>• Email: <?php echo htmlspecialchars($COL_EMAIL ?: "not found"); ?>
            <br>• Phone: <?php echo htmlspecialchars($COL_PHONE ?: "not found"); ?>
            <br>• Blood: <?php echo htmlspecialchars($COL_BG ?: "not found"); ?>
            <br>• Location: <?php echo htmlspecialchars($COL_LOC ?: "not found"); ?>
            <br>• Availability: <?php echo htmlspecialchars($COL_AV ?: "not found"); ?>
            <br>• Verified: <?php echo htmlspecialchars($COL_VER ?: "not found"); ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</body>
</html>
