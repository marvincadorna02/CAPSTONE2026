<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/config/env.php';

// ── CSRF Token generation ─────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Already logged in? Redirect ──────────────────────────────
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/admin-dashboard.php");
    } elseif ($_SESSION['role'] === 'repairshop') {
        header("Location: shop-owner/shop-information.php");
    } else {
        header("Location: shop-owner/dashboard.php");
    }
    exit();
}

// ── DB Connection ─────────────────────────────────────────────
$host   = "localhost";
$dbname = "fixitdavao";
$dbuser = "root";
$dbpass = "";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ── Brute Force Protection ────────────────────────────────────
define('MAX_ATTEMPTS',    5);
define('LOCKOUT_MINUTES', 15);

// ── Admin credentials ───────────────────────────────────────────
// Pulled from .env instead of being hardcoded in source. See
// config/env.php for the loader. Never commit real values to .env —
// it's already in .gitignore.
define('ADMIN_ACCESS_CODE',   $_ENV['ADMIN_ACCESS_CODE']   ?? '');
define('ADMIN_USERNAME',      $_ENV['ADMIN_USERNAME']      ?? '');
define('ADMIN_PASSWORD_HASH', $_ENV['ADMIN_PASSWORD_HASH'] ?? '');

function getLoginAttempts($conn, $email, $ip, $loginType) {
    $window = date('Y-m-d H:i:s', strtotime('-' . LOCKOUT_MINUTES . ' minutes'));
    $stmt   = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM login_attempts
         WHERE email = ? AND login_type = ? AND attempted_at > ?"
    );
    $stmt->bind_param("sss", $email, $loginType, $window);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'];
}

function recordLoginAttempt($conn, $email, $ip, $loginType) {
    $stmt = $conn->prepare(
        "INSERT INTO login_attempts (email, ip_address, login_type) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("sss", $email, $ip, $loginType);
    $stmt->execute();
}

function clearLoginAttempts($conn, $email, $ip, $loginType) {
    $stmt = $conn->prepare(
        "DELETE FROM login_attempts WHERE email = ? AND login_type = ?"
    );
    $stmt->bind_param("ss", $email, $loginType);
    $stmt->execute();
}

function getLockoutSecondsLeft($conn, $email, $ip, $loginType) {
    $stmt = $conn->prepare(
        "SELECT attempted_at FROM login_attempts
         WHERE email = ? AND login_type = ?
         ORDER BY attempted_at ASC LIMIT " . MAX_ATTEMPTS
    );
    $stmt->bind_param("ss", $email, $loginType);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    if (count($rows) < MAX_ATTEMPTS) return 0;

    $oldest = strtotime($rows[0]['attempted_at']);
    $unlockAt = $oldest + (LOCKOUT_MINUTES * 60);
    $secondsLeft = $unlockAt - time();

    return max(0, $secondsLeft);
}
$userIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$userIp = trim(explode(',', $userIp)[0]);

// ── Handle POST ───────────────────────────────────────────────
$error              = "";
$errorTitle         = "";
$errorType          = "general";
$lockoutSecondsLeft = 0;

// Session timeout message
if (isset($_GET['timeout'])) {
    $errorTitle = "Session Expired";
    $error      = "You have been inactive for too long. Please login again.";
    $errorType  = "general";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CSRF validation ───────────────────────────────────────
    if (!isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }
    // Regenerate token after each use
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $role     = $_POST['userType']  ?? '';
    $email    = trim($_POST['email']    ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']  ?? '';

    $loginType = in_array($role, ['admin', 'customer', 'repairshop'], true) ? $role : 'customer';

    // ── Brute force check ─────────────────────────────────────
    $checkEmail = !empty($email) ? $email : $username . '@admin';
    $attempts   = getLoginAttempts($conn, $checkEmail, $userIp, $loginType);
    if ($attempts >= MAX_ATTEMPTS) {
        $lockoutSecondsLeft = getLockoutSecondsLeft($conn, $checkEmail, $userIp, $loginType);
        $errorTitle = "Too Many Attempts!";
        $error      = "Too many failed login attempts. Please wait before trying again.";
        $errorType  = "suspended";
    }

    if (empty($error)) {
        // ── Admin login ───────────────────────────────────────
        if ($role === 'admin') {
            // Refuse to even attempt admin login if the .env secrets aren't
            // configured — otherwise an empty ADMIN_ACCESS_CODE/USERNAME/HASH
            // could match an empty submitted value via hash_equals('','').
            if (ADMIN_ACCESS_CODE === '' || ADMIN_USERNAME === '' || ADMIN_PASSWORD_HASH === '') {
                $errorTitle = "Admin Login Unavailable";
                $error      = "Admin credentials are not configured on this server.";
                $errorType  = "general";
            } else {
            $accessCode = trim($_POST['accessCode'] ?? '');

            if (!hash_equals(ADMIN_ACCESS_CODE, $accessCode)) {
                $errorTitle = "Access Denied!";
                $error      = "Invalid admin access code.";
                $errorType  = "general";
                recordLoginAttempt($conn, 'admin@admin', $userIp, $loginType);
            } elseif (hash_equals(ADMIN_USERNAME, $username) && password_verify($password, ADMIN_PASSWORD_HASH)) {
                session_regenerate_id(true);
                clearLoginAttempts($conn, 'admin@admin', $userIp, $loginType);
                $_SESSION['user_id'] = 0;
                $_SESSION['name']    = 'Admin User';
                $_SESSION['email']   = 'admin@fixitdavao.com';
                $_SESSION['role']    = 'admin';
                header("Location: admin/admin-dashboard.php");
                exit();
            } else {
                $errorTitle = "Invalid Credentials!";
                $error      = "The username or password you entered is incorrect.";
                $errorType  = "general";
                recordLoginAttempt($conn, 'admin@admin', $userIp, $loginType);
            }
            } // end .env-configured check

        // ── Customer / Repair Shop login ──────────────────────
        } else {
            if (empty($email) || empty($password)) {
                $errorTitle = "Fields Required!";
                $error      = "Please fill in all fields before signing in.";
                $errorType  = "general";
            } else {
                $stmt = $conn->prepare(
                    "SELECT id, name, email, password, role, status, suspend_reason, approval_status, rejection_reason
                     FROM users WHERE email = ? AND role = ? LIMIT 1"
                );
                $stmt->bind_param("ss", $email, $role);
                $stmt->execute();
                $result = $stmt->get_result();
                $user   = $result->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password, $user['password'])) {

                    // ── Repair shop: check approval status ────
                    if ($user['role'] === 'repairshop') {
                        if ($user['approval_status'] === 'pending') {
                            $errorTitle = "Pending Approval";
                            $error      = "Your repair shop is currently <strong>under review</strong> by our admin team.<br><br>You will be able to log in once your account is approved. This usually takes <strong>1–2 business days</strong>.";
                            $errorType  = "pending";
                        } elseif ($user['approval_status'] === 'rejected') {
                            $reason     = htmlspecialchars($user['rejection_reason'] ?? 'No reason provided.');
                            $errorTitle = "Registration Rejected";
                            $error      = "Your repair shop registration was <strong>not approved</strong>.<br><br><strong>Reason:</strong> " . $reason . "<br><br>Please contact support if you believe this is a mistake.";
                            $errorType  = "rejected";
                        }
                    }

                    // ── Check suspended ────────────────────────
                    if (empty($error) && isset($user['status']) && $user['status'] === 'suspended') {
                        $reason     = htmlspecialchars($user['suspend_reason'] ?? 'No reason provided.');
                        $errorTitle = "Account Suspended";
                        $error      = "Your account has been <strong>suspended</strong>.<br><br><strong>Reason:</strong> " . $reason . "<br><br>Please contact support for assistance.";
                        $errorType  = "suspended";
                    }

                    // ── All clear — trusted device? skip OTP. Else send OTP ───
                    if (empty($error)) {
                        require_once 'includes/otp-functions.php';
                        clearLoginAttempts($conn, $email, $userIp, $loginType);

                        if (isTrustedDevice($conn, $user['id'])) {
                            // Known device — log in directly, no OTP needed
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['name']    = $user['name'];
                            $_SESSION['email']   = $user['email'];
                            $_SESSION['role']    = $user['role'];
                            header("Location: " . ($user['role'] === 'repairshop' ? 'shop-owner/shop-information.php' : 'shop-owner/dashboard.php'));
                            exit();
                        }

                        session_regenerate_id(true);
                        $_SESSION['pending_user_id'] = $user['id'];
                        $_SESSION['pending_role']    = $user['role'];
                        $_SESSION['pending_name']    = $user['name'];
                        $_SESSION['pending_email']   = $user['email'];

                        $sent = generateAndSendOTP($conn, $user['id'], $user['email'], $user['name']);

                        if ($sent) {
                            header("Location: verify-otp.php");
                        } else {
                            $errorTitle = "Email Error";
                            $error      = "Failed to send verification code. Please try again.";
                            $errorType  = "general";
                        }
                        exit();
                    }

                } else {
                    // ── Wrong role check ───────────────────────
                    if (!$user) {
                        $stmtCheck = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
                        $stmtCheck->bind_param("s", $email);
                        $stmtCheck->execute();
                        $resCheck  = $stmtCheck->get_result();
                        $userCheck = $resCheck->fetch_assoc();
                        $stmtCheck->close();

                        if ($userCheck) {
                            $roleLabel  = $userCheck['role'] === 'repairshop' ? 'Repair Shop' : ucfirst($userCheck['role']);
                            $errorTitle = "Wrong Account Type!";
                            $error      = "This email is registered as a <strong>{$roleLabel}</strong> account.<br><br>Please select <strong>{$roleLabel}</strong> in the Login As section.";
                            $errorType  = "general";
                        } else {
                            $errorTitle = "Invalid Credentials!";
                            $error      = "The email or password you entered is incorrect.";
                            $errorType  = "general";
                            recordLoginAttempt($conn, $email, $userIp, $loginType);
                        }
                    } else {
                        $errorTitle = "Invalid Credentials!";
                        $error      = "The email or password you entered is incorrect.";
                        $errorType  = "general";
                        recordLoginAttempt($conn, $email, $userIp, $loginType);
                    }
                }
            }
        }
    }
}

$conn->close();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <script>
  if (window.top !== window.self) {
    document.documentElement.classList.add('in-modal');
  }
</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fix It Davao - Login</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png" />
    <link rel="apple-touch-icon" href="assets/images/logo.png" />
    <link rel="shortcut icon" href="assets/images/logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
            html.in-modal, html.in-modal body{
        background:transparent !important;
        overflow:visible !important;
      }
      html.in-modal .bg-grid,
      html.in-modal .bg-glow,
      html.in-modal .bg-glow2{
        display:none !important;
      }
          html.in-modal .auth-wrap{
      height:100% !important;
      padding:20px !important;
    }
      html.in-modal .auth-card{
        animation:none !important;
      }
      *{margin:0;padding:0;box-sizing:border-box;}
      :root{
        --primary:#0f172a;
        --primary-dark:#020617;
        --accent:#f59e0b;
        --accent-light:#fbbf24;
        --accent-dark:#d97706;
        --text-secondary:#64748b;
        --border:#e2e8f0;
      }
      html,body{
        font-family:'Outfit',-apple-system,sans-serif;
        background:var(--primary-dark);
        height:100%;
        overflow:hidden;
        position:relative;
      }

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

      .auth-wrap{
        position:relative;z-index:2;
        height:100vh;
        display:flex;align-items:center;justify-content:center;
        padding:10px 30px;
      }

      .auth-card{
        width:100%;
        background:rgba(30,41,59,0.85);
        border:1px solid rgba(245,158,11,0.2);
        border-radius:20px;padding:28px 30px;
        backdrop-filter:blur(14px);
        box-shadow:0 30px 80px rgba(0,0,0,0.4);
        animation:fadeUp 0.6s ease both;
      }
      @keyframes fadeUp{from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);}}

      .auth-card h1{
        font-size:24px;font-weight:900;color:#fff;letter-spacing:-0.5px;margin-bottom:5px;
      }
      .auth-card .subtext{
        font-size:13px;color:rgba(255,255,255,0.45);margin-bottom:22px;
      }

      .section-label{
        display:block;font-size:11px;font-weight:700;letter-spacing:1px;
        color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:10px;
      }
      .user-type-wrapper{margin-bottom:16px;}
      .user-type-selection{display:flex;gap:8px;background:rgba(255,255,255,0.05);padding:5px;border-radius:12px;}
      .user-type{flex:1;position:relative;}
      .user-type input{position:absolute;opacity:0;width:0;height:0;}
      .user-type span{
  display:flex;align-items:center;justify-content:center;
  padding:9px 4px;border-radius:9px;
  font-size:11.5px;font-weight:600;color:rgba(255,255,255,0.5);
  cursor:pointer;transition:all 0.2s;text-align:center;
  white-space:nowrap;
}
      .user-type input:checked + span{
        background:linear-gradient(135deg,var(--accent),var(--accent-dark));
        color:#fff;box-shadow:0 4px 14px rgba(245,158,11,0.3);
      }

      .form-group{margin-bottom:12px;}
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
      .toggle-password svg{width:19px;height:19px;}

      .forgot-password{text-align:right;margin-bottom:16px;margin-top:-4px;}
      .forgot-password a{color:var(--accent-light);font-size:12.5px;text-decoration:none;font-weight:500;}
      .forgot-password a:hover{text-decoration:underline;}

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

      .dialog-overlay { position: fixed; inset: 0; background: rgba(2,6,23,0.8); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; padding: 20px; }
      .dialog-overlay.visible { opacity: 1; pointer-events: all; }
      .dialog-box { background: #0f172a; border: 1px solid rgba(245,158,11,0.2); border-radius: 24px; max-width: 400px; width: 100%; box-shadow: 0 40px 100px rgba(0,0,0,0.5); transform: scale(0.88) translateY(28px); transition: transform 0.38s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s ease; opacity: 0; overflow: hidden; }
      .dialog-overlay.visible .dialog-box { transform: scale(1) translateY(0); opacity: 1; }

      .dlg-header { padding: 30px 26px 22px; text-align: center; position: relative; }
      .dlg-header::after { content:""; position:absolute; bottom:-1px; left:0; right:0; height:22px; background:#0f172a; border-radius:22px 22px 0 0; }
      .dlg-header.red    { background: linear-gradient(135deg,#ef4444,#dc2626); }
      .dlg-header.amber  { background: linear-gradient(135deg,#f59e0b,#d97706); }
      .dlg-header.slate  { background: linear-gradient(135deg,#64748b,#475569); }

      .dlg-icon { width:58px; height:58px; background:rgba(255,255,255,0.2); border:3px solid rgba(255,255,255,0.4); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; animation:popIn 0.45s cubic-bezier(0.34,1.56,0.64,1) 0.12s both; }
      @keyframes popIn { from{transform:scale(0) rotate(-20deg);opacity:0}to{transform:scale(1) rotate(0);opacity:1} }
      .dlg-icon svg { width:26px; height:26px; stroke:white; fill:none; stroke-width:2.3; stroke-linecap:round; stroke-linejoin:round; }
      .dlg-title { font-family:'Outfit',sans-serif; font-size:19px; font-weight:800; color:white; }

      .dlg-body { padding: 18px 26px 26px; }
      .dlg-info { border-radius:12px; padding:13px 15px; margin-bottom:18px; font-family:'Outfit',sans-serif; font-size:13px; line-height:1.7; }
      .dlg-info.red   { background:rgba(239,68,68,0.1); border:1.5px solid rgba(239,68,68,0.3); color:#fca5a5; }
      .dlg-info.amber { background:rgba(245,158,11,0.1); border:1.5px solid rgba(245,158,11,0.3); color:#fcd34d; }
      .dlg-info.slate { background:rgba(148,163,184,0.1); border:1.5px solid rgba(148,163,184,0.3); color:#cbd5e1; }

      .dlg-btn { width:100%; padding:12px; border:none; border-radius:12px; font-size:14px; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; transition:all 0.25s ease; color:white; }
      .dlg-btn:hover { transform:translateY(-2px); }
      .dlg-btn:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
      .dlg-btn.red   { background:linear-gradient(135deg,#ef4444,#dc2626); box-shadow:0 4px 14px rgba(239,68,68,0.35); }
      .dlg-btn.amber { background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 4px 14px rgba(245,158,11,0.35); }
      .dlg-btn.slate { background:linear-gradient(135deg,#64748b,#475569); box-shadow:0 4px 14px rgba(100,116,139,0.35); }

      @media(max-width:480px){
    .auth-card{padding:28px 22px;}
    .auth-card h1{font-size:22px;}
    .user-type span{font-size:10.5px;padding:9px 2px;}
  }
    </style>
  </head>
  <body>
    <div class="bg-grid"></div>
    <div class="bg-glow"></div>
    <div class="bg-glow2"></div>

    <!-- AUTH CARD -->
    <div class="auth-wrap">
  <div style="width:100%;max-width:440px;">

        <div class="auth-card">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
  <div>
              <h1>Welcome Back</h1>
              <p class="subtext" id="loginSubtext">Sign in to book, manage, or track your repairs.</p>
            </div>
                  <button type="button" onclick="parent.postMessage('close-modal','*')"
        onmouseover="this.style.background='rgba(245,158,11,0.15)';this.style.color='#f59e0b'"
        onmouseout="this.style.background='rgba(255,255,255,0.08)';this.style.color='rgba(255,255,255,0.6)'"
        style="flex-shrink:0;width:30px;height:30px;border-radius:8px;border:none;
        background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.6);font-size:16px;
        line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;
        transition:all 0.2s;">&times;</button>
          </div>

          <form id="loginForm" method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />

            <div class="user-type-wrapper">
              <label class="section-label">Login As</label>
              <div class="user-type-selection">
                <label class="user-type">
                  <input type="radio" name="userType" value="customer"
                    <?php echo (!isset($_POST['userType']) || $_POST['userType'] === 'customer') ? 'checked' : ''; ?> />
                  <span>Customer</span>
                </label>
                <label class="user-type">
                  <input type="radio" name="userType" value="repairshop"
                    <?php echo (isset($_POST['userType']) && $_POST['userType'] === 'repairshop') ? 'checked' : ''; ?> />
                  <span>Repair Shop</span>
                </label>
                <label class="user-type">
                  <input type="radio" name="userType" value="admin"
                    <?php echo (isset($_POST['userType']) && $_POST['userType'] === 'admin') ? 'checked' : ''; ?> />
                  <span>Admin</span>
                </label>
              </div>
            </div>

            <div class="form-group" id="emailGroup">
              <input type="email" id="emailInput" name="email" placeholder="Email address"
                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                <?php echo (!isset($_POST['userType']) || $_POST['userType'] !== 'admin') ? 'required' : ''; ?>
                <?php echo (!empty($error) && $errorType === 'general') ? 'class="input-error"' : ''; ?> />
            </div>

                        <div class="form-group" id="usernameGroup" style="display:none;">
              <input type="text" id="usernameInput" name="username" placeholder="Username" autocomplete="off"
                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                <?php echo (!empty($error) && $errorType === 'general') ? 'class="input-error"' : ''; ?> />
            </div>

                    <div class="form-group" id="accessCodeGroup" style="display:none;">
          <div class="password-wrapper">
            <input type="password" id="accessCodeInput" name="accessCode" placeholder="Admin Access Code" autocomplete="off"
              <?php echo (!empty($error) && $errorType === 'general') ? 'class="input-error"' : ''; ?> />
            <span class="toggle-password" onclick="togglePasswordVisibility('accessCodeInput')">
              <svg id="eyeIcon-accessCodeInput" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg id="eyeSlashIcon-accessCodeInput" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
            </span>
          </div>
        </div>

            <div class="form-group">
              <div class="password-wrapper">
                <input type="password" id="password" name="password" placeholder="Password" required
                  <?php echo (!empty($error) && $errorType === 'general') ? 'class="input-error"' : ''; ?> />
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

            <div id="forgotPasswordWrap" style="text-align:right;margin-top:-8px;margin-bottom:16px;">
  <a href="forgot-password.php" onclick="return goToForgotPassword(event);" style="font-size:.8rem;color:#f59e0b;text-decoration:none;font-weight:600;">Forgot Password?</a>
</div>
            <button type="submit" class="sign-in-btn">Sign in</button>
            <div class="signup-link">Don't have an account? <a href="signup.php">Sign Up</a></div>
          </form>
        </div>

        <div class="footer">© 2026 FIX IT DAVAO — All Rights Reserved</div>
      </div>
    </div>

    <!-- ── DIALOG ── -->
    <div class="dialog-overlay" id="statusDialog">
      <div class="dialog-box">
        <div class="dlg-header" id="dlgHeader">
          <div class="dlg-icon">
            <svg id="dlgIconSvg" viewBox="0 0 24 24"></svg>
          </div>
          <div class="dlg-title" id="dlgTitle"></div>
        </div>
        <div class="dlg-body">
          <div class="dlg-info" id="dlgInfo"></div>
          <button class="dlg-btn" id="dlgBtn"></button>
        </div>
      </div>
    </div>

    <script>
      // ── Show dialog if PHP set an error ───────────────────────
      <?php if (!empty($error)): ?>
      window.addEventListener("DOMContentLoaded", function () {
        showDialog(
          <?php echo json_encode($errorTitle); ?>,
          <?php echo json_encode($error); ?>,
          <?php echo json_encode($errorType); ?>,
          <?php echo json_encode($lockoutSecondsLeft); ?>
        );
      });
      <?php endif; ?>

      const DIALOG_CFG = {
        general: {
          color:   "red",
          icon:    `<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>`,
          btnText: "Try Again",
          reset:   true
        },
        pending: {
          color:   "amber",
          icon:    `<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/>`,
          btnText: "OK, I'll Wait",
          reset:   false
        },
        rejected: {
          color:   "red",
          icon:    `<circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>`,
          btnText: "Close",
          reset:   false
        },
        suspended: {
          color:   "slate",
          icon:    `<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>`,
          btnText: "Close",
          reset:   false
        }
      };

      const dialogOverlay = document.getElementById("statusDialog");
      const dlgHeader     = document.getElementById("dlgHeader");
      const dlgTitle      = document.getElementById("dlgTitle");
      const dlgInfo       = document.getElementById("dlgInfo");
      const dlgBtn        = document.getElementById("dlgBtn");
      const dlgIconSvg    = document.getElementById("dlgIconSvg");

      let currentReset      = false;
      let countdownInterval = null;

      function showDialog(title, message, type, secondsLeft = 0) {
        const cfg = DIALOG_CFG[type] || DIALOG_CFG.general;
        currentReset = cfg.reset;

        // Clear any previous countdown running
        if (countdownInterval) {
          clearInterval(countdownInterval);
          countdownInterval = null;
        }

        dlgHeader.className   = `dlg-header ${cfg.color}`;
        dlgTitle.textContent  = title;
        dlgIconSvg.innerHTML  = cfg.icon;
        dlgInfo.className     = `dlg-info ${cfg.color}`;
        dlgInfo.innerHTML     = message;
        dlgBtn.className      = `dlg-btn ${cfg.color}`;

        const icon  = dlgHeader.querySelector(".dlg-icon");
        const clone = icon.cloneNode(true);
        icon.parentNode.replaceChild(clone, icon);
        clone.querySelector("svg").innerHTML = cfg.icon;

        if (secondsLeft > 0) {
          dlgBtn.disabled = true;
          let remaining = secondsLeft;

          const updateBtn = () => {
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            const timeStr = mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
            dlgBtn.textContent = `Wait ${timeStr}...`;

            if (remaining <= 0) {
              clearInterval(countdownInterval);
              countdownInterval = null;
              dlgBtn.disabled = false;
              dlgBtn.textContent = cfg.btnText;
              return;
            }
            remaining--;
          };

          updateBtn();
          countdownInterval = setInterval(updateBtn, 1000);
        } else {
          dlgBtn.disabled = false;
          dlgBtn.textContent = cfg.btnText;
        }

        dialogOverlay.classList.add("visible");
      }

      function hideDialog() {
        dialogOverlay.classList.remove("visible");
        if (countdownInterval) {
          clearInterval(countdownInterval);
          countdownInterval = null;
        }
        if (currentReset) {
          document.getElementById("emailInput").classList.remove("input-error");
          document.getElementById("usernameInput").classList.remove("input-error");
          document.getElementById("password").classList.remove("input-error");
          document.getElementById("password").value = "";
          document.getElementById("password").focus();
        }
      }

      dlgBtn.addEventListener("click", function() {
        if (!dlgBtn.disabled) hideDialog();
      });
      dialogOverlay.addEventListener("click", e => {
        if (e.target === dialogOverlay && !dlgBtn.disabled) hideDialog();
      });

      // Works whether login.php is loaded standalone (PWA, direct visit)
      // or embedded inside the home.php auth iframe/modal.
      function goToForgotPassword(e) {
        e.preventDefault();
        if (window.top !== window.self) {
          // Inside the home.php modal — swap the iframe's src
          parent.postMessage('switch-to-forgot', '*');
        } else {
          // Standalone / PWA — just navigate directly
          window.location.href = 'forgot-password.php';
        }
        return false;
      }

      function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const eye   = document.getElementById("eyeIcon-" + fieldId);
        const slash = document.getElementById("eyeSlashIcon-" + fieldId);
        if (field.type === "password") {
          field.type = "text"; eye.style.display = "none"; slash.style.display = "block";
        } else {
          field.type = "password"; eye.style.display = "block"; slash.style.display = "none";
        }
      }

        const radios         = document.querySelectorAll('input[name="userType"]');
        const emailGroup     = document.getElementById("emailGroup");
        const usernameGroup  = document.getElementById("usernameGroup");
        const accessCodeGroup = document.getElementById("accessCodeGroup");
        const emailInput     = document.getElementById("emailInput");
        const usernameInput  = document.getElementById("usernameInput");
        const accessCodeInput = document.getElementById("accessCodeInput");

        const loginSubtext = document.getElementById("loginSubtext");
        const SUBTEXT_BY_ROLE = {
          customer:   "Sign in to book, manage, or track your repairs.",
          repairshop: "Sign in to manage bookings and grow your repair shop.",
          admin:      "Only authorize person can access here."
        };

        function updateFieldsByRole(role) {
          const forgotPasswordWrap = document.getElementById("forgotPasswordWrap");
          if (role === "admin") {
            emailGroup.style.display = "none"; usernameGroup.style.display = "block"; accessCodeGroup.style.display = "block";
            emailInput.removeAttribute("required"); usernameInput.setAttribute("required", ""); accessCodeInput.setAttribute("required", "");
            // Admin login isn't email-based (username + access code), so a
            // "forgot password" email-reset flow doesn't apply here.
            if (forgotPasswordWrap) forgotPasswordWrap.style.display = "none";
          } else {
            emailGroup.style.display = "block"; usernameGroup.style.display = "none"; accessCodeGroup.style.display = "none";
            emailInput.setAttribute("required", ""); usernameInput.removeAttribute("required"); accessCodeInput.removeAttribute("required");
            if (forgotPasswordWrap) forgotPasswordWrap.style.display = "block";
          }
          emailInput.classList.remove("input-error");
          usernameInput.classList.remove("input-error");
          accessCodeInput.classList.remove("input-error");
          document.getElementById("password").classList.remove("input-error");

          loginSubtext.textContent = SUBTEXT_BY_ROLE[role] || SUBTEXT_BY_ROLE.customer;
        }

      radios.forEach(r => r.addEventListener("change", function () { updateFieldsByRole(this.value); }));
      const checkedRole = document.querySelector('input[name="userType"]:checked');
      if (checkedRole) updateFieldsByRole(checkedRole.value);

      document.getElementById("loginForm").addEventListener("submit", function () {
        const btn = this.querySelector(".sign-in-btn");
        btn.textContent = "Logging in..."; btn.disabled = true;
        setTimeout(() => { btn.textContent = "Sign in"; btn.disabled = false; }, 3000);
      });
    </script>
  </body>
</html>