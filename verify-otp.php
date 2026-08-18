<?php
session_start();
require_once 'includes/otp-functions.php';

// ── CSRF token ───────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── No pending login? Kick back to login ───────────────────────
if (empty($_SESSION['pending_user_id'])) {
    header("Location: home.php");
    exit();
}

// ── DB Connection ────────────────────────────────────────────
$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$pendingId    = $_SESSION['pending_user_id'];
$pendingRole  = $_SESSION['pending_role'];
$pendingName  = $_SESSION['pending_name'];
$pendingEmail = $_SESSION['pending_email'];

$error       = "";
$resendMsg   = "";
$resendCooldown = 0;

// Mask email para dili full expose sa UI (e.g. m****y@gmail.com)
function maskEmail($email) {
    [$user, $domain] = explode('@', $email);
    $visible = substr($user, 0, 1);
    return $visible . str_repeat('*', max(strlen($user) - 1, 1)) . '@' . $domain;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // ── Resend code ─────────────────────────────────────────
    if (isset($_POST['resend'])) {
        $lastSent = $_SESSION['otp_last_sent'] ?? 0;
        if (time() - $lastSent < 30) {
            $resendCooldown = 30 - (time() - $lastSent);
            $error = "Please wait before requesting a new code.";
        } else {
            $sent = generateAndSendOTP($conn, $pendingId, $pendingEmail, $pendingName);
            if ($sent) {
                $_SESSION['otp_last_sent'] = time();
                $resendMsg = "A new code has been sent to your email.";
            } else {
                $error = "Failed to resend code. Please try again.";
            }
        }

    // ── Verify code ─────────────────────────────────────────
    } else {
        $inputCode = trim($_POST['otp_code'] ?? '');

        if (empty($inputCode) || !ctype_digit($inputCode) || strlen($inputCode) !== 6) {
            $error = "Please enter the 6-digit code.";
        } else {
            $result = verifyOTP($conn, $pendingId, $inputCode);

            switch ($result['status']) {
                case 'valid':
                    // ── Remember this device? ────────────────
                    if (!empty($_POST['remember_device'])) {
                        issueTrustedDeviceToken($conn, $pendingId);
                    }

                    // ── Finalize login ──────────────────────
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $pendingId;
                    $_SESSION['name']    = $pendingName;
                    $_SESSION['email']   = $pendingEmail;
                    $_SESSION['role']    = $pendingRole;

                    unset($_SESSION['pending_user_id'], $_SESSION['pending_role'],
                          $_SESSION['pending_name'], $_SESSION['pending_email'],
                          $_SESSION['otp_last_sent']);

                    header("Location: " . ($pendingRole === 'repairshop' ? 'shop-owner/shop-information.php' : 'shop-owner/dashboard.php'));
                    exit();

                case 'expired':
                    $error = "This code has expired. Please request a new one.";
                    break;
                case 'locked':
                    $error = "Too many failed attempts. Please request a new code.";
                    break;
                case 'not_found':
                    $error = "No active code found. Please request a new one.";
                    break;
                default: // invalid
                    $error = "Incorrect code. Please try again.";
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
    <title>Verify Code - Fix It Davao</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        :root{
            --primary-dark:#020617;
            --accent:#f59e0b;
            --accent-light:#fbbf24;
            --accent-dark:#d97706;
        }
        html,body{
            font-family:'Outfit',-apple-system,sans-serif;
            background:var(--primary-dark);
            min-height:100vh;
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
        .wrap{
            position:relative;z-index:2;
            min-height:100vh;
            display:flex;align-items:center;justify-content:center;
            padding:20px;
        }
        .card{
            width:100%;max-width:420px;
            background:rgba(30,41,59,0.85);
            border:1px solid rgba(245,158,11,0.2);
            border-radius:20px;padding:34px 30px;
            backdrop-filter:blur(14px);
            box-shadow:0 30px 80px rgba(0,0,0,0.4);
            text-align:center;
        }
        .icon-badge{
            width:56px;height:56px;border-radius:50%;
            background:linear-gradient(135deg,var(--accent),var(--accent-dark));
            display:flex;align-items:center;justify-content:center;
            margin:0 auto 18px;
            box-shadow:0 8px 24px rgba(245,158,11,0.35);
        }
        .icon-badge svg{width:26px;height:26px;stroke:#fff;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
        h1{font-size:22px;font-weight:900;color:#fff;margin-bottom:6px;}
        .subtext{font-size:13px;color:rgba(255,255,255,0.5);margin-bottom:26px;line-height:1.6;}
        .subtext strong{color:var(--accent-light);}

        .otp-inputs{display:flex;gap:8px;justify-content:center;margin-bottom:18px;}
        .otp-inputs input{
            width:44px;height:52px;text-align:center;
            font-size:20px;font-weight:700;color:#fff;
            background:rgba(255,255,255,0.06);
            border:1.5px solid rgba(255,255,255,0.12);
            border-radius:11px;font-family:'Outfit',sans-serif;
            transition:all 0.2s;
        }
        .otp-inputs input:focus{outline:none;border-color:var(--accent);background:rgba(245,158,11,0.08);}
        .otp-inputs input.err{border-color:#ef4444;background:rgba(239,68,68,0.08);}

        .alert{padding:11px 14px;border-radius:10px;font-size:12.5px;font-weight:600;margin-bottom:18px;text-align:left;line-height:1.5;}
        .alert-error{background:rgba(239,68,68,0.1);border:1.5px solid rgba(239,68,68,0.3);color:#fca5a5;}
        .alert-success{background:rgba(34,197,94,0.1);border:1.5px solid rgba(34,197,94,0.3);color:#86efac;}

        .remember-row{
            display:flex;align-items:center;gap:8px;
            justify-content:center;margin-bottom:18px;
            font-size:12.5px;color:rgba(255,255,255,0.55);
            cursor:pointer;user-select:none;
        }
        .remember-row input{
            width:15px;height:15px;accent-color:var(--accent);cursor:pointer;
        }

        .verify-btn{
            width:100%;padding:14px;
            background:linear-gradient(135deg,var(--accent),var(--accent-dark));
            color:#fff;border:none;border-radius:12px;
            font-size:14.5px;font-weight:700;font-family:'Outfit',sans-serif;
            cursor:pointer;transition:all 0.25s;
            box-shadow:0 8px 24px rgba(245,158,11,0.3);
            margin-bottom:16px;
        }
        .verify-btn:hover{transform:translateY(-2px);}
        .verify-btn:disabled{opacity:0.6;cursor:not-allowed;transform:none;}

        .resend-row{font-size:12.5px;color:rgba(255,255,255,0.45);}
        .resend-row button{
            background:none;border:none;color:var(--accent-light);
            font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;font-size:12.5px;
            padding:0;
        }
        .resend-row button:disabled{color:rgba(255,255,255,0.3);cursor:not-allowed;}

        .back-link{display:block;margin-top:18px;font-size:12.5px;color:rgba(255,255,255,0.35);text-decoration:none;}
        .back-link:hover{color:var(--accent-light);}
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="bg-glow"></div>

    <div class="wrap">
        <div class="card">
            <div class="icon-badge">
                <svg viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <h1>Check Your Email</h1>
            <p class="subtext">We sent a 6-digit code to<br><strong><?php echo htmlspecialchars(maskEmail($pendingEmail)); ?></strong></p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif (!empty($resendMsg)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($resendMsg); ?></div>
            <?php endif; ?>

            <form method="POST" id="otpForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />

                <div class="otp-inputs" id="otpInputs">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-box <?php echo !empty($error) ? 'err' : ''; ?>" autocomplete="off" />
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="otp_code" id="otpCode" />

                <label class="remember-row">
                    <input type="checkbox" name="remember_device" value="1" checked />
                    <span>Remember this device for 30 days</span>
                </label>

                <button type="submit" class="verify-btn" id="verifyBtn">Verify Code</button>
            </form>

            <form method="POST" id="resendForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
                <input type="hidden" name="resend" value="1" />
                <div class="resend-row">
                    Didn't get the code?
                    <button type="submit" id="resendBtn">Resend Code</button>
                </div>
            </form>

            <a href="home.php" class="back-link">← Back to Login</a>
        </div>
    </div>

    <script>
        const boxes = document.querySelectorAll('.otp-box');
        const hiddenInput = document.getElementById('otpCode');
        const otpForm = document.getElementById('otpForm');

        boxes.forEach((box, i) => {
            box.addEventListener('input', () => {
                box.value = box.value.replace(/[^0-9]/g, '');
                if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
                updateHidden();
            });
            box.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
            });
            box.addEventListener('paste', (e) => {
                e.preventDefault();
                const digits = (e.clipboardData.getData('text').match(/\d/g) || []).slice(0, 6);
                digits.forEach((d, idx) => { if (boxes[idx]) boxes[idx].value = d; });
                updateHidden();
                boxes[Math.min(digits.length, 5)].focus();
            });
        });

        function updateHidden() {
            hiddenInput.value = Array.from(boxes).map(b => b.value).join('');
        }

        otpForm.addEventListener('submit', function (e) {
            updateHidden();
            if (hiddenInput.value.length !== 6) {
                e.preventDefault();
                boxes.forEach(b => b.classList.add('err'));
                return;
            }
            document.getElementById('verifyBtn').textContent = 'Verifying...';
            document.getElementById('verifyBtn').disabled = true;
        });

        if (boxes.length) boxes[0].focus();

        // ── Resend cooldown (client-side UX only; server enforces real cooldown) ──
        <?php if ($resendCooldown > 0): ?>
        (function () {
            const btn = document.getElementById('resendBtn');
            let remaining = <?php echo (int)$resendCooldown; ?>;
            btn.disabled = true;
            const tick = () => {
                btn.textContent = `Resend in ${remaining}s`;
                if (remaining <= 0) {
                    clearInterval(iv);
                    btn.disabled = false;
                    btn.textContent = 'Resend Code';
                    return;
                }
                remaining--;
            };
            tick();
            const iv = setInterval(tick, 1000);
        })();
        <?php endif; ?>
    </script>
</body>
</html>