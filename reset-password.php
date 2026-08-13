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

    if (strlen($newPass) < 6) {
        $msg = "Password must be at least 6 characters.";
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reset Password - Fix It Davao</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Outfit',sans-serif;background:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
    .box{background:white;border-radius:20px;padding:36px 32px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);}
    h2{font-size:1.3rem;font-weight:800;color:#0f172a;margin-bottom:8px;}
    p.sub{color:#64748b;font-size:.85rem;margin-bottom:24px;}
    label{font-size:.8rem;font-weight:700;color:#374151;display:block;margin-bottom:6px;}
    input{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-family:'Outfit',sans-serif;margin-bottom:16px;outline:none;}
    input:focus{border-color:#f59e0b;}
    button{width:100%;padding:12px;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border:none;border-radius:10px;font-weight:700;font-size:.9rem;cursor:pointer;font-family:'Outfit',sans-serif;}
    .alert{padding:12px 14px;border-radius:10px;font-size:.82rem;font-weight:600;margin-bottom:16px;}
    .alert-success{background:#d1fae5;color:#065f46;}
    .alert-error{background:#fee2e2;color:#991b1b;}
    .back-link{display:block;text-align:center;margin-top:16px;font-size:.82rem;color:#64748b;text-decoration:none;}
    .back-link:hover{color:#f59e0b;}
  </style>
</head>
<body>
  <div class="box">
    <h2>Reset Your Password</h2>
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if ($validToken): ?>
    <p class="sub">Enter your new password below.</p>
    <form method="POST">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
      <label>New Password</label>
      <input type="password" name="password" placeholder="At least 6 characters" required minlength="6" />
      <label>Confirm Password</label>
      <input type="password" name="confirm_password" placeholder="Re-enter password" required minlength="6" />
      <button type="submit">Reset Password</button>
    </form>
    <?php endif; ?>

    <a href="login.php" class="back-link">← Back to Login</a>
  </div>
</body>
</html>