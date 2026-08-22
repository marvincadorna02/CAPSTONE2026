<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) die("DB error: " . $conn->connect_error);

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$msg = '';
$msgType = '';
$validToken = false;

if ($token) {
    $stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && strtotime($user['reset_expires']) > time()) {
        $validToken = true;
    } else {
        $msg = "This reset link is invalid or has expired.";
        $msgType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $newPass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 8 || strlen($newPass) > 16) {
        $msg = "Password must be 8 to 16 characters long.";
        $msgType = 'error';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{8,16}$/', $newPass)) {
        $msg = "Password must include an uppercase letter, lowercase letter, number, and special character.";
        $msgType = 'error';
    } elseif ($newPass !== $confirm) {
        $msg = "Passwords do not match.";
        $msgType = 'error';
    } else {
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt2 = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt2->bind_param("si", $hashed, $user['id']);
        $stmt2->execute();
        $stmt2->close();
        $msg = "Password reset successful! You can now log in.";
        $msgType = 'success';
        $validToken = false; // hide form after success
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
    <title>Fix It Davao - Reset Password</title>
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
        padding:56px 20px 20px !important;
        align-items:flex-start !important;
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

      .form-group{margin-bottom:14px;}
      .form-group label{
        display:block;font-size:11px;font-weight:700;letter-spacing:1px;
        color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:8px;
      }
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

      .sign-in-btn{
        width:100%;padding:14px;
        background:linear-gradient(135deg,var(--accent),var(--accent-dark));
        color:#fff;border:none;border-radius:12px;
        font-size:14.5px;font-weight:700;font-family:'Outfit',sans-serif;
        cursor:pointer;transition:all 0.25s;
        box-shadow:0 8px 24px rgba(245,158,11,0.3);
        margin-top:6px;
      }
      .sign-in-btn:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(245,158,11,0.4);}
      .sign-in-btn:disabled{opacity:0.7;cursor:not-allowed;transform:none;}

      .signup-link{text-align:center;margin-top:16px;font-size:13px;color:rgba(255,255,255,0.4);}
      .signup-link a{color:var(--accent-light);font-weight:600;text-decoration:none;}
      .signup-link a:hover{text-decoration:underline;}

      .alert{
        border-radius:12px;padding:13px 15px;margin-bottom:18px;
        font-family:'Outfit',sans-serif;font-size:13px;line-height:1.6;font-weight:500;
      }
      .alert-success{background:rgba(16,185,129,0.12);border:1.5px solid rgba(16,185,129,0.3);color:#6ee7b7;}
      .alert-error{background:rgba(239,68,68,0.1);border:1.5px solid rgba(239,68,68,0.3);color:#fca5a5;}

      @media(max-width:480px){
        .auth-card{padding:28px 22px;}
        .auth-card h1{font-size:22px;}
      }
    </style>
  </head>
  <body>
    <div class="bg-grid"></div>
    <div class="bg-glow"></div>
    <div class="bg-glow2"></div>

    <div class="auth-wrap">
      <div style="width:100%;max-width:440px;">

        <div class="auth-card">
          <div>
            <h1>Reset Your Password</h1>
            <p class="subtext">Enter a new password to secure your account.</p>
          </div>

          <?php if ($msg): ?>
          <div class="alert alert-<?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
          <?php endif; ?>

          <?php if ($validToken): ?>
          <form method="POST" id="resetForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />

            <div class="form-group">
              <label>New Password</label>
              <div class="password-wrapper">
                <input type="password" id="password" name="password" placeholder="8-16 chars, upper/lower/number/symbol"
                  required minlength="8" maxlength="16"
                  pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{8,16}"
                  title="8-16 characters, with uppercase, lowercase, number, and special character" />
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
              <label>Confirm Password</label>
              <div class="password-wrapper">
                <input type="password" id="confirmPassword" name="confirm_password" placeholder="Re-enter password"
                  required minlength="8" maxlength="16" />
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

            <button type="submit" class="sign-in-btn">Reset Password</button>
          </form>
          <?php endif; ?>

          <div class="signup-link"><a href="login.php">← Back to Login</a></div>
        </div>

      </div>
    </div>

    <script>
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

      const resetForm = document.getElementById("resetForm");
      if (resetForm) {
        resetForm.addEventListener("submit", function () {
          const btn = this.querySelector(".sign-in-btn");
          btn.textContent = "Resetting..."; btn.disabled = true;
        });
      }
    </script>
  </body>
</html>