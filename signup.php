<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin')       header("Location: admin/admin-dashboard.php");
    elseif ($_SESSION['role'] === 'repairshop') header("Location: shop-owner/shop-information.php");
    else                                        header("Location: shop-owner/dashboard.php");
    exit();
}

$host   = "localhost";
$dbname = "fixitdavao";
$dbuser = "root";
$dbpass = "";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$conn->query("
    CREATE TABLE IF NOT EXISTS users (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        name            VARCHAR(100) NOT NULL,
        email           VARCHAR(100) UNIQUE NOT NULL,
        password        VARCHAR(255) NOT NULL,
        role            ENUM('customer','repairshop','admin') NOT NULL DEFAULT 'customer',
        status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
        suspend_reason  VARCHAR(255) DEFAULT NULL,
        approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
        rejection_reason VARCHAR(255) DEFAULT NULL,
        approved_at     DATETIME DEFAULT NULL,
        rejected_at     DATETIME DEFAULT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Also add columns if table already exists without them
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','suspended') NOT NULL DEFAULT 'active'");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS suspend_reason VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS rejected_at DATETIME DEFAULT NULL");

$error       = "";
$errorTitle  = "";
$errorType   = "general";
$success     = false;
$displayName = "";
$isPending   = false; // repairshop pending approval

$oldRole     = $_POST['userType']  ?? 'customer';
$oldName     = $_POST['fullName']  ?? '';
$oldShopName = $_POST['shopName']  ?? '';
$oldEmail    = $_POST['email']     ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = $_POST['userType']        ?? 'customer';
    $name     = trim($_POST['fullName']   ?? '');
    $shopName = trim($_POST['shopName']   ?? '');
    $email    = trim($_POST['email']      ?? '');
    $password = $_POST['password']        ?? '';
    $confirm  = $_POST['confirmPassword'] ?? '';
    $terms    = isset($_POST['terms']);

    if ($role === 'customer' && empty($name)) {
        $errorTitle = "Name Required"; $error = "Please enter your full name to continue."; $errorType = "general";
    } elseif ($role === 'repairshop' && empty($shopName)) {
        $errorTitle = "Shop Name Required"; $error = "Please enter your repair shop name to continue."; $errorType = "general";
    } elseif (empty($email) || empty($password) || empty($confirm)) {
        $errorTitle = "Fields Required"; $error = "Please fill in all fields before signing up."; $errorType = "general";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorTitle = "Invalid Email"; $error = "Please enter a valid email address."; $errorType = "general";
    } elseif (strlen($password) < 6) {
        $errorTitle = "Weak Password"; $error = "Your password must be at least 6 characters long."; $errorType = "general";
    } elseif ($password !== $confirm) {
        $errorTitle = "Passwords Don't Match"; $error = "The passwords you entered do not match. Please re-enter them carefully."; $errorType = "mismatch";
    } elseif (!$terms) {
        $errorTitle = "Terms Required"; $error = "You must agree to the Terms & Conditions before signing up."; $errorType = "general";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errorTitle = "Email Already Taken"; $error = "An account with " . htmlspecialchars($email) . " already exists. Try logging in instead."; $errorType = "general";
            $stmt->close();
        } else {
            $stmt->close();
            $finalName = ($role === 'repairshop') ? $shopName : $name;
            $hashed    = password_hash($password, PASSWORD_DEFAULT);

            // Repairshops start as 'pending', customers are 'approved' by default
            $approvalStatus = ($role === 'repairshop') ? 'pending' : 'approved';

            $insert = $conn->prepare("INSERT INTO users (name, email, password, role, approval_status) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param("sssss", $finalName, $email, $hashed, $role, $approvalStatus);

            if ($insert->execute()) {
                $success     = true;
                $displayName = $finalName;
                $isPending   = ($role === 'repairshop');
            } else {
                $errorTitle = "Registration Failed"; $error = "Something went wrong. Please try again."; $errorType = "general";
            }
            $insert->close();
        }
    }
}

$conn->close();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fix It Davao - Sign Up</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png" />
    <link rel="apple-touch-icon" href="assets/images/logo.png" />
    <link rel="shortcut icon" href="assets/images/logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
      *{margin:0;padding:0;box-sizing:border-box;}
      :root{
        --primary:#0f172a;
        --primary-dark:#020617;
        --accent:#f59e0b;
        --accent-light:#fbbf24;
        --accent-dark:#d97706;
        --text-secondary:#64748b;
      }
      html,body{
        font-family:'Outfit',-apple-system,sans-serif;
        background:var(--primary-dark);
        height:100%;
        overflow:hidden;
        position:relative;
      }

      /* ── BACKGROUND FX (same as home.php hero) ── */
      .bg-grid{
        position:fixed;inset:0;z-index:0;
        background-image:linear-gradient(rgba(245,158,11,0.05) 1px,transparent 1px),linear-gradient(90deg,rgba(245,158,11,0.05) 1px,transparent 1px);
        background-size:60px 60px;
      }
      .bg-glow{
        position:fixed;top:-200px;right:-200px;z-index:0;
        width:700px;height:700px;
        background:radial-gradient(circle,rgba(245,158,11,0.14) 0%,transparent 65%);
        pointer-events:none;
      }
      .bg-glow2{
        position:fixed;bottom:-150px;left:-150px;z-index:0;
        width:600px;height:600px;
        background:radial-gradient(circle,rgba(59,130,246,0.1) 0%,transparent 65%);
        pointer-events:none;
      }

      /* ── AUTH SECTION (fills modal, no navbar/badge, no scroll) ── */
      .auth-wrap{
        position:relative;z-index:2;
        height:100vh;
        display:flex;align-items:center;justify-content:center;
        padding:24px 20px;
      }

      /* ── FLOATING CARD (matches home.php .float-card style) ── */
      .auth-card{
        width:100%;
        background:rgba(30,41,59,0.85);
        border:1px solid rgba(245,158,11,0.2);
        border-radius:20px;padding:26px 30px;
        backdrop-filter:blur(14px);
        box-shadow:0 30px 80px rgba(0,0,0,0.4);
        animation:fadeUp 0.6s ease both;
      }
      @keyframes fadeUp{from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);}}

      .auth-card h1{
        font-size:22px;font-weight:900;color:#fff;letter-spacing:-0.5px;margin-bottom:4px;
      }
      .auth-card .subtext{
        font-size:12.5px;color:rgba(255,255,255,0.45);margin-bottom:18px;
      }

      /* Role tabs */
      .section-label{
        display:block;font-size:11px;font-weight:700;letter-spacing:1px;
        color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:8px;
      }
      .user-type-wrapper{margin-bottom:14px;}
      .user-type-selection{display:flex;gap:8px;background:rgba(255,255,255,0.05);padding:5px;border-radius:12px;}
      .user-type{flex:1;position:relative;}
      .user-type input{position:absolute;opacity:0;width:0;height:0;}
      .user-type span{
        display:flex;align-items:center;justify-content:center;
        padding:9px 6px;border-radius:9px;
        font-size:12.5px;font-weight:600;color:rgba(255,255,255,0.5);
        cursor:pointer;transition:all 0.2s;text-align:center;
      }
      .user-type input:checked + span{
        background:linear-gradient(135deg,var(--accent),var(--accent-dark));
        color:#fff;box-shadow:0 4px 14px rgba(245,158,11,0.3);
      }

      /* Form fields */
      .form-group{margin-bottom:11px;}
      .form-group input{
        width:100%;padding:12px 16px;
        background:rgba(255,255,255,0.06);
        border:1.5px solid rgba(255,255,255,0.1);
        border-radius:11px;color:#fff;
        font-family:'Outfit',sans-serif;font-size:14px;
        transition:all 0.2s;
      }
      .form-group input::placeholder{color:rgba(255,255,255,0.35);}
      .form-group input:focus{outline:none;border-color:var(--accent);background:rgba(245,158,11,0.06);}
      .input-error{border-color:#ef4444 !important;background:rgba(239,68,68,0.08) !important;}

      .password-wrapper{position:relative;width:100%;}
      .password-wrapper input{width:100%;padding-right:45px;}
      .toggle-password{
        position:absolute;right:12px;top:50%;transform:translateY(-50%);
        cursor:pointer;width:22px;height:22px;
        display:flex;align-items:center;justify-content:center;
        color:rgba(255,255,255,0.4);transition:color 0.2s;
      }
      .toggle-password:hover{color:var(--accent-light);}
      .toggle-password svg{width:19px;height:19px;display:none;}
      .toggle-password svg:first-of-type{display:block;}

      /* Terms checkbox */
      .remember-me{
        display:flex;align-items:center;gap:8px;margin-bottom:16px;margin-top:2px;
      }
      .remember-me input[type="checkbox"]{
        width:16px;height:16px;accent-color:var(--accent);cursor:pointer;flex-shrink:0;
      }
      .remember-me label{
        font-size:12px;color:rgba(255,255,255,0.55);cursor:pointer;line-height:1.4;
      }

      .sign-in-btn{
        width:100%;padding:14px;
        background:linear-gradient(135deg,var(--accent),var(--accent-dark));
        color:#fff;border:none;border-radius:12px;
        font-size:14.5px;font-weight:700;font-family:'Outfit',sans-serif;
        cursor:pointer;transition:all 0.25s;
        box-shadow:0 8px 24px rgba(245,158,11,0.3);
      }
      .sign-in-btn:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(245,158,11,0.4);}
      .sign-in-btn:disabled{opacity:0.7;cursor:not-allowed;transform:none;}

      .signup-link{text-align:center;margin-top:16px;font-size:13px;color:rgba(255,255,255,0.4);}
      .signup-link a{color:var(--accent-light);font-weight:600;text-decoration:none;}
      .signup-link a:hover{text-decoration:underline;}

      .footer{display:none;}

      /* ─── DIALOG OVERLAY (dark themed) ─── */
      .dialog-overlay { position: fixed; inset: 0; background: rgba(2,6,23,0.82); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; padding: 20px; }
      .dialog-overlay.visible { opacity: 1; pointer-events: all; }
      .dialog-box { background: #0f172a; border: 1px solid rgba(245,158,11,0.2); border-radius: 24px; padding: 0; max-width: 400px; width: 100%; box-shadow: 0 40px 100px rgba(0,0,0,0.5); transform: scale(0.88) translateY(30px); transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s ease; opacity: 0; overflow: hidden; }
      .dialog-overlay.visible .dialog-box { transform: scale(1) translateY(0); opacity: 1; }

      @keyframes shake { 0%,100%{transform:scale(1) translateX(0)} 15%{transform:scale(1) translateX(-8px)} 30%{transform:scale(1) translateX(8px)} 45%{transform:scale(1) translateX(-6px)} 60%{transform:scale(1) translateX(6px)} 75%{transform:scale(1) translateX(-3px)} 90%{transform:scale(1) translateX(3px)} }
      .dialog-box.shake { animation: shake 0.55s cubic-bezier(0.36,0.07,0.19,0.97) both; }

      .dialog-header-success { background: linear-gradient(135deg,#f59e0b,#d97706); padding: 32px 28px 24px; text-align: center; position: relative; }
      .dialog-header-pending { background: linear-gradient(135deg,#3b82f6,#1d4ed8); padding: 32px 28px 24px; text-align: center; position: relative; }
      .dialog-header-error    { background: linear-gradient(135deg,#ff3b3b,#c0392b); padding: 32px 28px 24px; text-align: center; position: relative; }
      .dialog-header-mismatch { background: linear-gradient(135deg,#f97316,#c2410c); padding: 32px 28px 24px; text-align: center; position: relative; }

      .dialog-header-success::after,
      .dialog-header-pending::after,
      .dialog-header-error::after,
      .dialog-header-mismatch::after { content:""; position:absolute; bottom:-1px; left:0; right:0; height:20px; background:#0f172a; border-radius:20px 20px 0 0; }

      .dialog-header-icon { width:64px; height:64px; background:rgba(255,255,255,0.18); border:3px solid rgba(255,255,255,0.4); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; animation:popIn 0.45s cubic-bezier(0.34,1.56,0.64,1) 0.15s both; }
      @keyframes popIn { from{transform:scale(0) rotate(-20deg);opacity:0} to{transform:scale(1) rotate(0);opacity:1} }
      .dialog-header-icon svg { width:30px; height:30px; stroke:#fff; fill:none; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; }
      .check-path { stroke-dasharray:50; stroke-dashoffset:50; animation:drawCheck 0.45s ease 0.4s forwards; }
      @keyframes drawCheck { to{stroke-dashoffset:0} }

      .dialog-header-title { font-family:'Outfit',sans-serif; font-size:22px; font-weight:800; color:#fff; letter-spacing:0.3px; text-shadow:0 2px 8px rgba(0,0,0,0.15); }
      .dialog-body { padding:20px 28px 28px; text-align:center; }
      .dialog-message { font-family:'Outfit',sans-serif; font-size:13px; color:rgba(255,255,255,0.5); line-height:1.6; margin-bottom:6px; }
      .dialog-name    { font-family:'Outfit',sans-serif; font-size:14px; font-weight:700; color:var(--accent-light); margin-bottom:8px; }
      .dialog-pending-note { background:rgba(59,130,246,0.1); border:1.5px solid rgba(59,130,246,0.3); border-radius:12px; padding:12px 14px; margin-bottom:20px; font-family:'Outfit',sans-serif; font-size:12.5px; color:#93c5fd; line-height:1.6; text-align:left; }
      .dialog-pending-note strong { color:#bfdbfe; }

      .dialog-btn-gold   { width:100%; padding:13px; background:linear-gradient(135deg,#f59e0b,#d97706); color:white; border:none; border-radius:12px; font-size:14px; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; box-shadow:0 4px 18px rgba(245,158,11,0.4); transition:all 0.25s ease; }
      .dialog-btn-blue   { width:100%; padding:13px; background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:white; border:none; border-radius:12px; font-size:14px; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; box-shadow:0 4px 18px rgba(59,130,246,0.4); transition:all 0.25s ease; }
      .dialog-btn-gold:hover, .dialog-btn-blue:hover { transform:translateY(-2px); }
      .dialog-sub { margin-top:12px; font-family:'Outfit',sans-serif; font-size:11px; color:rgba(255,255,255,0.3); }

      .dialog-hint-row { display:flex; align-items:flex-start; gap:10px; border-radius:12px; padding:12px 14px; margin-bottom:20px; text-align:left; }
      .dialog-hint-row.red    { background:rgba(239,68,68,0.1); border:1.5px solid rgba(239,68,68,0.3); }
      .dialog-hint-row.orange { background:rgba(249,115,22,0.1); border:1.5px solid rgba(249,115,22,0.3); }
      .dialog-hint-row .hint-icon { font-size:18px; margin-top:1px; flex-shrink:0; }
      .dialog-hint-text { font-family:'Outfit',sans-serif; font-size:13px; line-height:1.6; }
      .dialog-hint-text.red    { color:#fca5a5; }
      .dialog-hint-text.orange { color:#fdba74; }
      .pw-bars { display:flex; gap:5px; margin-bottom:20px; }
      .pw-bar  { flex:1; height:5px; border-radius:4px; background:rgba(239,68,68,0.25); }
      .pw-bar.filled { background:#f97316; }
      .dialog-tips { list-style:none; margin-bottom:22px; text-align:left; }
      .dialog-tips li { font-family:'Outfit',sans-serif; font-size:12.5px; color:rgba(255,255,255,0.45); padding:5px 0; display:flex; align-items:center; gap:8px; border-bottom:1px solid rgba(255,255,255,0.06); }
      .dialog-tips li:last-child { border:none; }
      .dialog-tips li::before { content:"•"; font-size:18px; line-height:1; }
      .dialog-tips.red    li::before { color:#ef4444; }
      .dialog-tips.orange li::before { color:#f97316; }
      .dialog-btn-red    { width:100%; padding:13px; background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; border-radius:12px; font-size:14px; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; box-shadow:0 4px 18px rgba(239,68,68,0.4); transition:all 0.25s ease; }
      .dialog-btn-orange { width:100%; padding:13px; background:linear-gradient(135deg,#f97316,#c2410c); color:white; border:none; border-radius:12px; font-size:14px; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; box-shadow:0 4px 18px rgba(249,115,22,0.4); transition:all 0.25s ease; }
      .dialog-btn-red:hover, .dialog-btn-orange:hover { transform:translateY(-2px); }

      .confetti { position:absolute; width:8px; height:8px; border-radius:50%; opacity:0; }
      .confetti:nth-child(1){background:#fff;top:15%;left:12%;animation:confettiFall 1.2s ease 0.3s forwards;}
      .confetti:nth-child(2){background:#fde68a;top:10%;left:80%;animation:confettiFall 1s ease 0.5s forwards;border-radius:2px;}
      .confetti:nth-child(3){background:#fff;top:20%;left:55%;animation:confettiFall 1.4s ease 0.2s forwards;}
      @keyframes confettiFall{0%{opacity:1;transform:translateY(0) rotate(0deg)}100%{opacity:0;transform:translateY(60px) rotate(360deg)}}

      @media(max-width:480px){
        .auth-card{padding:22px 20px;}
        .auth-card h1{font-size:20px;}
      }
    </style>
  </head>
  <body>
    <div class="bg-grid"></div>
    <div class="bg-glow"></div>
    <div class="bg-glow2"></div>

    <!-- AUTH CARD -->
    <div class="auth-wrap">
      <div style="width:100%;max-width:460px;">

        <div class="auth-card">
          <h1>Create Account</h1>
          <p class="subtext">Join Fix It Davao and get started today.</p>

          <form id="signupForm" method="POST" action="signup.php">
            <div class="user-type-wrapper">
              <label class="section-label">Sign Up As</label>
              <div class="user-type-selection">
                <label class="user-type">
                  <input type="radio" name="userType" value="customer" <?php echo ($oldRole !== 'repairshop') ? 'checked' : ''; ?> />
                  <span>Customer</span>
                </label>
                <label class="user-type">
                  <input type="radio" name="userType" value="repairshop" <?php echo ($oldRole === 'repairshop') ? 'checked' : ''; ?> />
                  <span>Repair Shop</span>
                </label>
              </div>
            </div>

            <div class="form-group" id="fullNameGroup" <?php echo ($oldRole === 'repairshop') ? 'style="display:none"' : ''; ?>>
              <input type="text" id="fullName" name="fullName" placeholder="Full Name"
                value="<?php echo htmlspecialchars($oldName); ?>"
                <?php echo ($oldRole !== 'repairshop') ? 'required' : ''; ?>
                <?php echo (!empty($error) && empty($oldName) && $oldRole !== 'repairshop') ? 'class="input-error"' : ''; ?> />
            </div>

            <div class="form-group" id="shopNameGroup" <?php echo ($oldRole !== 'repairshop') ? 'style="display:none"' : ''; ?>>
              <input type="text" id="shopName" name="shopName" placeholder="Repair Shop Name"
                value="<?php echo htmlspecialchars($oldShopName); ?>"
                <?php echo ($oldRole === 'repairshop') ? 'required' : ''; ?>
                <?php echo (!empty($error) && empty($oldShopName) && $oldRole === 'repairshop') ? 'class="input-error"' : ''; ?> />
            </div>

            <div class="form-group">
              <input type="email" id="email" name="email" placeholder="Email address"
                value="<?php echo htmlspecialchars($oldEmail); ?>" required
                <?php echo (!empty($error) && empty($oldEmail)) ? 'class="input-error"' : ''; ?> />
            </div>

            <div class="form-group">
              <div class="password-wrapper">
                <input type="password" id="password" name="password" placeholder="Password" required
                  <?php echo !empty($error) ? 'class="input-error"' : ''; ?> />
                <span class="toggle-password" onclick="togglePasswordVisibility('password')">
                  <svg id="eyeIcon-password" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg id="eyeSlashIcon-password" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                  </svg>
                </span>
              </div>
            </div>

            <div class="form-group">
              <div class="password-wrapper">
                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password" required
                  <?php echo !empty($error) ? 'class="input-error"' : ''; ?> />
                <span class="toggle-password" onclick="togglePasswordVisibility('confirmPassword')">
                  <svg id="eyeIcon-confirmPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg id="eyeSlashIcon-confirmPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                  </svg>
                </span>
              </div>
            </div>

            <div class="remember-me">
              <input type="checkbox" id="terms" name="terms" required />
              <label for="terms">I agree to the Terms & Conditions</label>
            </div>

            <button type="submit" class="sign-in-btn">Sign Up</button>
            <div class="signup-link">Already have an account? <a href="login.php">Sign In</a></div>
          </form>
        </div>

        <div class="footer">© 2026 All Rights Reserved. | FIX IT DAVAO</div>
      </div>
    </div>

    <!-- ─── SUCCESS DIALOG (customer) ─── -->
    <div class="dialog-overlay" id="successDialog">
      <div class="dialog-box">
        <div class="dialog-header-success">
          <div class="confetti"></div><div class="confetti"></div><div class="confetti"></div>
          <div class="dialog-header-icon">
            <svg viewBox="0 0 24 24"><polyline class="check-path" points="4,13 9,18 20,7" /></svg>
          </div>
          <div class="dialog-header-title">Account Created! 🎉</div>
        </div>
        <div class="dialog-body">
          <div class="dialog-message">Welcome to Fix It Davao,</div>
          <div class="dialog-name" id="dialogName"></div>
          <div class="dialog-message" style="margin-bottom:24px;">Your account has been successfully registered.<br>You can now sign in and start exploring.</div>
          <button class="dialog-btn-gold" id="dialogProceedBtn">Go to Sign In</button>
          <div class="dialog-sub">Redirecting in <span id="countdown">3</span>s…</div>
        </div>
      </div>
    </div>

    <!-- ─── PENDING DIALOG (repairshop) ─── -->
    <div class="dialog-overlay" id="pendingDialog">
      <div class="dialog-box">
        <div class="dialog-header-pending">
          <div class="dialog-header-icon">
            <!-- clock icon -->
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
          </div>
          <div class="dialog-header-title">Registration Submitted! ⏳</div>
        </div>
        <div class="dialog-body">
          <div class="dialog-message" style="margin-bottom:12px;">Your shop <strong id="pendingShopName" style="color:#fbbf24;"></strong> has been registered successfully.</div>
          <div class="dialog-pending-note">
            🔍 <strong>Pending Admin Approval</strong><br>
            Your account is currently under review. You will be able to log in and access your dashboard once an admin approves your registration.<br><br>
            This usually takes <strong>1–2 business days</strong>.
          </div>
          <button class="dialog-btn-blue" id="pendingOkBtn">Got it, I'll wait!</button>
        </div>
      </div>
    </div>

    <!-- ─── ERROR DIALOG ─── -->
    <div class="dialog-overlay" id="errorDialog">
      <div class="dialog-box" id="errorDialogBox">
        <div id="errorDialogHeader" class="dialog-header-error">
          <div class="dialog-header-icon" id="errorDialogIcon">
            <svg id="iconSvg" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </div>
          <div class="dialog-header-title" id="errorTitle">Sign Up Failed</div>
        </div>
        <div class="dialog-body">
          <div class="dialog-hint-row red" id="errorHintRow">
            <span class="hint-icon" id="errorHintIcon">⚠️</span>
            <div class="dialog-hint-text red" id="errorMessage">Something went wrong.</div>
          </div>
          <div class="pw-bars" id="pwBars" style="display:none;">
            <div class="pw-bar filled"></div><div class="pw-bar filled"></div>
            <div class="pw-bar"></div><div class="pw-bar"></div>
          </div>
          <ul class="dialog-tips red" id="errorTips">
            <li>Check that all fields are filled in</li>
          </ul>
          <button class="dialog-btn-red" id="errorCloseBtn">Try Again</button>
        </div>
      </div>
    </div>

    <script>
      <?php if ($success && !$isPending): ?>
      window.addEventListener("DOMContentLoaded", () => showSuccessDialog("<?php echo addslashes(htmlspecialchars($displayName)); ?>"));
      <?php elseif ($success && $isPending): ?>
      window.addEventListener("DOMContentLoaded", () => showPendingDialog("<?php echo addslashes(htmlspecialchars($displayName)); ?>"));
      <?php elseif (!empty($error)): ?>
      window.addEventListener("DOMContentLoaded", () => showErrorDialog("<?php echo addslashes($errorTitle); ?>","<?php echo addslashes($error); ?>","<?php echo $errorType; ?>"));
      <?php endif; ?>

      function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const eye   = document.getElementById("eyeIcon-" + fieldId);
        const slash = document.getElementById("eyeSlashIcon-" + fieldId);
        if (field.type === "password") { field.type = "text"; eye.style.display = "none"; slash.style.display = "block"; }
        else { field.type = "password"; eye.style.display = "block"; slash.style.display = "none"; }
      }

      const radios        = document.querySelectorAll('input[name="userType"]');
      const fullNameGroup = document.getElementById("fullNameGroup");
      const fullNameInput = document.getElementById("fullName");
      const shopNameGroup = document.getElementById("shopNameGroup");
      const shopNameInput = document.getElementById("shopName");

      function updateFieldsByRole(role) {
        if (role === "repairshop") {
          fullNameGroup.style.display = "none"; fullNameInput.required = false; fullNameInput.value = "";
          shopNameGroup.style.display = "block"; shopNameInput.required = true;
        } else {
          fullNameGroup.style.display = "block"; fullNameInput.required = true;
          shopNameGroup.style.display = "none"; shopNameInput.required = false; shopNameInput.value = "";
        }
      }
      radios.forEach(r => r.addEventListener("change", function() { updateFieldsByRole(this.value); }));

      // ── Success dialog (customer) ─────────────────────────────
      const successOverlay = document.getElementById("successDialog");
      let redirectTimer, countdownInterval;
      function showSuccessDialog(name) {
        document.getElementById("dialogName").textContent = name || "Welcome!";
        successOverlay.classList.add("visible");
        let s = 3; document.getElementById("countdown").textContent = s;
        countdownInterval = setInterval(() => { s--; document.getElementById("countdown").textContent = s; if (s <= 0) clearInterval(countdownInterval); }, 1000);
        redirectTimer = setTimeout(() => { window.location.href = "login.php"; }, 3500);
      }
      document.getElementById("dialogProceedBtn").addEventListener("click", () => {
        clearTimeout(redirectTimer); clearInterval(countdownInterval); window.location.href = "login.php";
      });

      // ── Pending dialog (repairshop) ───────────────────────────
      const pendingOverlay = document.getElementById("pendingDialog");
      function showPendingDialog(name) {
        document.getElementById("pendingShopName").textContent = name;
        pendingOverlay.classList.add("visible");
      }
      document.getElementById("pendingOkBtn").addEventListener("click", () => { window.location.href = "login.php"; });

      // ── Error dialog ──────────────────────────────────────────
      const errorOverlay   = document.getElementById("errorDialog");
      const errorDialogBox = document.getElementById("errorDialogBox");

      function showErrorDialog(title, message, type) {
        const header   = document.getElementById("errorDialogHeader");
        const hintRow  = document.getElementById("errorHintRow");
        const hintText = document.getElementById("errorMessage");
        const hintIcon = document.getElementById("errorHintIcon");
        const tips     = document.getElementById("errorTips");
        const btn      = document.getElementById("errorCloseBtn");
        const pwBars   = document.getElementById("pwBars");
        const iconSvg  = document.getElementById("iconSvg");

        document.getElementById("errorTitle").textContent = title;
        hintText.textContent = message;

        if (type === "mismatch") {
          header.className = "dialog-header-mismatch"; hintRow.className = "dialog-hint-row orange";
          hintText.className = "dialog-hint-text orange"; tips.className = "dialog-tips orange";
          btn.className = "dialog-btn-orange"; hintIcon.textContent = "🔐"; pwBars.style.display = "flex";
          iconSvg.innerHTML = `<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>`;
          tips.innerHTML = `<li>Both password fields must be exactly the same</li><li>Check for accidental spaces at the end</li><li>Make sure Caps Lock is off</li>`;
        } else {
          header.className = "dialog-header-error"; hintRow.className = "dialog-hint-row red";
          hintText.className = "dialog-hint-text red"; tips.className = "dialog-tips red";
          btn.className = "dialog-btn-red"; hintIcon.textContent = "⚠️"; pwBars.style.display = "none";
          iconSvg.innerHTML = `<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>`;
          tips.innerHTML = `<li>Please check all fields and try again</li>`;
        }

        const clone = iconSvg.cloneNode(true); iconSvg.parentNode.replaceChild(clone, iconSvg);
        errorOverlay.classList.add("visible");
        setTimeout(() => { errorDialogBox.classList.add("shake"); errorDialogBox.addEventListener("animationend", () => errorDialogBox.classList.remove("shake"), { once: true }); }, 420);
        document.getElementById("password").classList.add("input-error");
        document.getElementById("confirmPassword").classList.add("input-error");
      }

      function hideErrorDialog() {
        errorOverlay.classList.remove("visible");
        document.getElementById("password").classList.remove("input-error");
        document.getElementById("confirmPassword").classList.remove("input-error");
        document.getElementById("confirmPassword").value = "";
        document.getElementById("confirmPassword").focus();
      }

      document.getElementById("errorCloseBtn").addEventListener("click", hideErrorDialog);
      errorOverlay.addEventListener("click", e => { if (e.target === errorOverlay) hideErrorDialog(); });

      document.getElementById("signupForm").addEventListener("submit", function () {
        const btn = this.querySelector(".sign-in-btn");
        btn.textContent = "Creating account…"; btn.disabled = true;
      });
    </script>
  </body>
</html>