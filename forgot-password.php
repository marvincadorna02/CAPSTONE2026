<?php
session_start();
require_once __DIR__ . '/config/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) die("DB error: " . $conn->connect_error);

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $email = trim($_POST['email'] ?? '');

    if ($email) {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt2 = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt2->bind_param("ssi", $token, $expires, $user['id']);
            $stmt2->execute();
            $stmt2->close();

            $resetLink = "http://localhost/CAPSTONE2026/reset-password.php?token=" . $token;

            require_once 'PHPMailer/src/Exception.php';
            require_once 'PHPMailer/src/PHPMailer.php';
            require_once 'PHPMailer/src/SMTP.php';

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['MAIL_USERNAME'];
                $mail->Password   = $_ENV['MAIL_PASSWORD'];
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->setFrom($_ENV['MAIL_USERNAME'], $_ENV['MAIL_FROM_NAME']);
                $mail->addAddress($email, $user['name']);
                $mail->isHTML(true);
                $mail->Subject = 'Reset Your Password - Fix It Davao';
                $mail->Body    = "
                <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;'>
                    <div style='background:linear-gradient(135deg,#f59e0b,#d97706);padding:24px;text-align:center;border-radius:12px 12px 0 0;'>
                        <h2 style='color:white;margin:0;'>🔧 Fix It Davao</h2>
                    </div>
                    <div style='padding:24px;background:#fff;border:1px solid #e2e8f0;border-radius:0 0 12px 12px;'>
                        <h3 style='color:#0f172a;'>Password Reset Request</h3>
                        <p style='color:#64748b;'>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                        <p style='color:#64748b;'>Click the button below to set a new password. This link expires in 1 hour.</p>
                        <div style='text-align:center;margin:24px 0;'>
                            <a href='{$resetLink}' style='background:linear-gradient(135deg,#f59e0b,#d97706);color:white;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:700;display:inline-block;'>Reset Password</a>
                        </div>
                        <p style='color:#94a3b8;font-size:12px;'>If you didn't request this, ignore this email.</p>
                    </div>
                </div>";
                $mail->send();
                $msg = "Reset link sent! Check your email.";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = "Failed to send email. Please try again.";
                $msgType = 'error';
            }
        } else {
            $msg = "If that email exists, a reset link has been sent.";
            $msgType = 'success';
        }
    }
}
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Forgot Password</title>
  <link rel="icon" type="image/png" href="assets/images/logo.png" />
  <link rel="apple-touch-icon" href="assets/images/logo.png" />
  <link rel="shortcut icon" href="assets/images/logo.png" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    body{
      font-family:'Outfit',sans-serif;
      background:#0f172a;
      background-image:radial-gradient(circle at 20% 20%, rgba(245,158,11,0.10), transparent 45%),
                        radial-gradient(circle at 80% 80%, rgba(217,119,6,0.10), transparent 45%);
      padding:32px 28px;
      height:100vh;
      overflow-y:auto;
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .card{
      width:100%;
      max-width:380px;
      background:#111e36;
      border:1px solid rgba(245,158,11,0.15);
      border-radius:18px;
      padding:32px 28px;
      box-shadow:0 20px 40px -12px rgba(0,0,0,0.5);
    }
    .logo-row{display:flex;align-items:center;gap:8px;margin-bottom:24px;}
    .logo-row img{width:32px;height:32px;border-radius:7px;}
    .logo-row span{font-size:15px;font-weight:800;color:#f1f5f9;}
    .logo-row span b{color:#f59e0b;}
    h2{font-size:1.25rem;font-weight:800;color:#f1f5f9;margin-bottom:6px;}
    p.sub{color:#94a3b8;font-size:.83rem;margin-bottom:22px;line-height:1.5;}
    label{font-size:.78rem;font-weight:700;color:#cbd5e1;display:block;margin-bottom:6px;}
    input{
      width:100%;
      padding:11px 14px;
      background:#0b1526;
      border:1.5px solid #1e293b;
      border-radius:10px;
      font-size:.88rem;
      font-family:'Outfit',sans-serif;
      color:#f1f5f9;
      margin-bottom:16px;
      outline:none;
      transition:border-color .2s;
    }
    input::placeholder{color:#475569;}
    input:focus{border-color:#f59e0b;}
    button{
      width:100%;
      padding:12px;
      background:linear-gradient(135deg,#f59e0b,#d97706);
      color:white;
      border:none;
      border-radius:10px;
      font-weight:700;
      font-size:.88rem;
      cursor:pointer;
      font-family:'Outfit',sans-serif;
      transition:transform .2s;
      box-shadow:0 8px 20px -6px rgba(245,158,11,0.45);
    }
    button:hover{transform:translateY(-1px);}
    .alert{padding:11px 14px;border-radius:10px;font-size:.8rem;font-weight:600;margin-bottom:16px;}
    .alert-success{background:rgba(16,185,129,0.12);color:#34d399;border:1px solid rgba(16,185,129,0.25);}
    .alert-error{background:rgba(239,68,68,0.12);color:#f87171;border:1px solid rgba(239,68,68,0.25);}
          .back-link{
        display:block;
        text-align:center;
        margin-top:18px;
        font-size:.82rem;
        color:#94a3b8;
        text-decoration:none;
        cursor:pointer;
        background:none;
        border:none;
        box-shadow:none;
        outline:none;
        appearance:none;
        -webkit-appearance:none;
        padding:8px 0;
        font-family:'Outfit',sans-serif;
        width:100%;
      }
.back-link:hover{color:#f59e0b;}
.back-link:focus{outline:none;}
    .back-link:hover{color:#f59e0b;}
  </style>
</head>
<body>
  <div class="card">
    <div class="logo-row">
      <img src="assets/images/logo.png" alt="Fix It Davao" />
      <span>Fix It <b>Davao</b></span>
    </div>
    <h2>Forgot Password?</h2>
    <p class="sub">Enter your email and we'll send you a link to reset your password.</p>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
      <label>Email Address</label>
      <input type="email" name="email" placeholder="you@example.com" required />
      <button type="submit">Send Reset Link</button>
    </form>

    <button class="back-link" onclick="handleBackToLogin()">← Back to Login</button>
  </div>
  <script>
function handleBackToLogin() {
  if (window.parent !== window) {
    parent.postMessage('switch-to-login', '*');
  } else {
    window.location.href = 'home.php';
  }
}
</script>
</body>
</html>