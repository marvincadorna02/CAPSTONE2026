<?php
session_start();

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

function getLoginAttempts($conn, $email, $ip) {
    $window = date('Y-m-d H:i:s', strtotime('-' . LOCKOUT_MINUTES . ' minutes'));
    $stmt   = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM login_attempts
         WHERE (email = ? OR ip_address = ?) AND attempted_at > ?"
    );
    $stmt->bind_param("sss", $email, $ip, $window);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'];
}

function recordLoginAttempt($conn, $email, $ip) {
    $stmt = $conn->prepare(
        "INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)"
    );
    $stmt->bind_param("ss", $email, $ip);
    $stmt->execute();
}

function clearLoginAttempts($conn, $email, $ip) {
    $stmt = $conn->prepare(
        "DELETE FROM login_attempts WHERE email = ? OR ip_address = ?"
    );
    $stmt->bind_param("ss", $email, $ip);
    $stmt->execute();
}

$userIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$userIp = trim(explode(',', $userIp)[0]);

// ── Handle POST ───────────────────────────────────────────────
$error      = "";
$errorTitle = "";
$errorType  = "general";

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

    // ── Brute force check ─────────────────────────────────────
    $checkEmail = !empty($email) ? $email : $username . '@admin';
    $attempts   = getLoginAttempts($conn, $checkEmail, $userIp);
    if ($attempts >= MAX_ATTEMPTS) {
        $errorTitle = "Too Many Attempts!";
        $error      = "Too many failed login attempts. Please wait <strong>" . LOCKOUT_MINUTES . " minutes</strong> before trying again.";
        $errorType  = "suspended";
    }

    if (empty($error)) {
        // ── Admin login ───────────────────────────────────────
        if ($role === 'admin') {
            if ($username === 'admin' && $password === 'admin123') {
                session_regenerate_id(true);
                clearLoginAttempts($conn, 'admin@admin', $userIp);
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
                recordLoginAttempt($conn, 'admin@admin', $userIp);
            }

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

                    // ── All clear — create session ─────────────
                    if (empty($error)) {
                        session_regenerate_id(true);
                        clearLoginAttempts($conn, $email, $userIp);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['name']    = $user['name'];
                        $_SESSION['email']   = $user['email'];
                        $_SESSION['role']    = $user['role'];
                        header("Location: " . ($user['role'] === 'repairshop' ? 'shop-owner/shop-information.php' : 'shop-owner/dashboard.php'));
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
                            recordLoginAttempt($conn, $email, $userIp);
                        }
                    } else {
                        $errorTitle = "Invalid Credentials!";
                        $error      = "The email or password you entered is incorrect.";
                        $errorType  = "general";
                        recordLoginAttempt($conn, $email, $userIp);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fix It Davao - Login</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png" />
    <link rel="apple-touch-icon" href="assets/images/logo.png" />
    <link rel="shortcut icon" href="assets/images/logo.png" />
    <link rel="stylesheet" href="assets/css/login.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <style>
      .password-wrapper { position: relative; width: 100%; }
      .password-wrapper input { width: 100%; padding-right: 45px; }
      .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; color: #666; transition: color 0.3s ease; }
      .toggle-password:hover { color: #333; }
      .toggle-password svg { width: 20px; height: 20px; }
      .input-error { border-color: #ef4444 !important; background: #fff5f5 !important; box-shadow: 0 0 0 3px rgba(239,68,68,0.1) !important; }

      /* ── DIALOG ── */
      .dialog-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.65); backdrop-filter: blur(5px); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; padding: 20px; }
      .dialog-overlay.visible { opacity: 1; pointer-events: all; }
      .dialog-box { background: white; border-radius: 24px; max-width: 400px; width: 100%; box-shadow: 0 40px 100px rgba(0,0,0,0.28); transform: scale(0.88) translateY(28px); transition: transform 0.38s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s ease; opacity: 0; overflow: hidden; }
      .dialog-overlay.visible .dialog-box { transform: scale(1) translateY(0); opacity: 1; }

      /* header */
      .dlg-header { padding: 30px 26px 22px; text-align: center; position: relative; }
      .dlg-header::after { content:""; position:absolute; bottom:-1px; left:0; right:0; height:22px; background:#fff; border-radius:22px 22px 0 0; }
      .dlg-header.red    { background: linear-gradient(135deg,#ef4444,#dc2626); }
      .dlg-header.amber  { background: linear-gradient(135deg,#f59e0b,#d97706); }
      .dlg-header.slate  { background: linear-gradient(135deg,#64748b,#475569); }

      .dlg-icon { width:58px; height:58px; background:rgba(255,255,255,0.2); border:3px solid rgba(255,255,255,0.4); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; animation:popIn 0.45s cubic-bezier(0.34,1.56,0.64,1) 0.12s both; }
      @keyframes popIn { from{transform:scale(0) rotate(-20deg);opacity:0}to{transform:scale(1) rotate(0);opacity:1} }
      .dlg-icon svg { width:26px; height:26px; stroke:white; fill:none; stroke-width:2.3; stroke-linecap:round; stroke-linejoin:round; }
      .dlg-title { font-family:"Montserrat",sans-serif; font-size:19px; font-weight:800; color:white; }

      /* body */
      .dlg-body { padding: 18px 26px 26px; }
      .dlg-info { border-radius:12px; padding:13px 15px; margin-bottom:18px; font-family:"Poppins",sans-serif; font-size:13px; line-height:1.7; }
      .dlg-info.red   { background:#fff5f5; border:1.5px solid #fecaca; color:#7f1d1d; }
      .dlg-info.amber { background:#fffbeb; border:1.5px solid #fde68a; color:#78350f; }
      .dlg-info.slate { background:#f8fafc; border:1.5px solid #cbd5e1; color:#1e293b; }

      .dlg-btn { width:100%; padding:12px; border:none; border-radius:12px; font-size:14px; font-weight:700; font-family:"Poppins",sans-serif; cursor:pointer; transition:all 0.25s ease; color:white; }
      .dlg-btn:hover { transform:translateY(-2px); }
      .dlg-btn.red   { background:linear-gradient(135deg,#ef4444,#dc2626); box-shadow:0 4px 14px rgba(239,68,68,0.35); }
      .dlg-btn.amber { background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 4px 14px rgba(245,158,11,0.35); }
      .dlg-btn.slate { background:linear-gradient(135deg,#64748b,#475569); box-shadow:0 4px 14px rgba(100,116,139,0.35); }
    </style>
  </head>
  <body>
    <div class="login-container">
      <div class="logo-section">
        <div class="car-icon"><img src="assets/images/logo.png" alt="Logo" /></div>
        <h1 class="company-name">FIX IT DAVAO</h1>
        <div class="company-subtitle">Find, Fix, Done.</div>
      </div>

      <form id="loginForm" method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />

        <div class="user-type-wrapper">
          <label class="section-label">Login As:</label>
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

        <div class="forgot-password"><a href="#">Forgot Password?</a></div>
        <button type="submit" class="sign-in-btn">Sign in</button>
        <div class="signup-link">Don't have an account? <a href="signup.php">Sign Up</a></div>
      </form>

      <div class="footer">© 2026 All Rights Reserved. | FIX IT DAVAO</div>
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
          <?php echo json_encode($errorType); ?>
        );
      });
      <?php endif; ?>

      // ── Dialog config per type ─────────────────────────────────
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

      let currentReset = false;

      function showDialog(title, message, type) {
        const cfg = DIALOG_CFG[type] || DIALOG_CFG.general;
        currentReset = cfg.reset;

        dlgHeader.className   = `dlg-header ${cfg.color}`;
        dlgTitle.textContent  = title;
        dlgIconSvg.innerHTML  = cfg.icon;
        dlgInfo.className     = `dlg-info ${cfg.color}`;
        dlgInfo.innerHTML     = message;
        dlgBtn.className      = `dlg-btn ${cfg.color}`;
        dlgBtn.textContent    = cfg.btnText;

        // Re-trigger icon animation
        const icon  = dlgHeader.querySelector(".dlg-icon");
        const clone = icon.cloneNode(true);
        icon.parentNode.replaceChild(clone, icon);
        // Re-attach svg into the clone
        clone.querySelector("svg").innerHTML = cfg.icon;

        dialogOverlay.classList.add("visible");
      }

      function hideDialog() {
        dialogOverlay.classList.remove("visible");
        if (currentReset) {
          document.getElementById("emailInput").classList.remove("input-error");
          document.getElementById("usernameInput").classList.remove("input-error");
          document.getElementById("password").classList.remove("input-error");
          document.getElementById("password").value = "";
          document.getElementById("password").focus();
        }
      }

      dlgBtn.addEventListener("click", hideDialog);
      dialogOverlay.addEventListener("click", e => { if (e.target === dialogOverlay) hideDialog(); });

      // ── Toggle password visibility ─────────────────────────────
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

      // ── Role switching ─────────────────────────────────────────
      const radios        = document.querySelectorAll('input[name="userType"]');
      const emailGroup    = document.getElementById("emailGroup");
      const usernameGroup = document.getElementById("usernameGroup");
      const emailInput    = document.getElementById("emailInput");
      const usernameInput = document.getElementById("usernameInput");

      function updateFieldsByRole(role) {
        if (role === "admin") {
          emailGroup.style.display = "none"; usernameGroup.style.display = "block";
          emailInput.removeAttribute("required"); usernameInput.setAttribute("required", "");
        } else {
          emailGroup.style.display = "block"; usernameGroup.style.display = "none";
          emailInput.setAttribute("required", ""); usernameInput.removeAttribute("required");
        }
        emailInput.classList.remove("input-error");
        usernameInput.classList.remove("input-error");
        document.getElementById("password").classList.remove("input-error");
      }

      radios.forEach(r => r.addEventListener("change", function () { updateFieldsByRole(this.value); }));
      const checkedRole = document.querySelector('input[name="userType"]:checked');
      if (checkedRole) updateFieldsByRole(checkedRole.value);

      // ── Loading state on submit ────────────────────────────────
      document.getElementById("loginForm").addEventListener("submit", function () {
        const btn = this.querySelector(".sign-in-btn");
        btn.textContent = "Logging in..."; btn.disabled = true;
        setTimeout(() => { btn.textContent = "Sign in"; btn.disabled = false; }, 3000);
      });
    </script>
  </body>
</html>