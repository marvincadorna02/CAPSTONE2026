<?php
require_once __DIR__ . '/../config/env.php';

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function generateAndSendOTP($conn, $userId, $userEmail, $userName) {
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Invalidate old unused OTPs for this user
    $stmt = $conn->prepare("UPDATE login_otp SET is_used = 1 WHERE user_id = ? AND is_used = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    // Insert new OTP
    $stmt = $conn->prepare("INSERT INTO login_otp (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $otp, $expiresAt);
    $stmt->execute();

    // Send email
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug  = 0; // set to 2 lang if need mo-debug via error_log
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($_ENV['MAIL_USERNAME'], $_ENV['MAIL_FROM_NAME']);
        $mail->addAddress($userEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = 'Your Fix It Davao Login Code';
        $mail->Body    = "
            <p>Hi {$userName},</p>
            <p>Your login verification code is:</p>
            <h2 style='letter-spacing:4px;'>{$otp}</h2>
            <p>This code expires in 5 minutes. If you didn't request this, ignore this email.</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP mail error: " . $mail->ErrorInfo);
        return false;
    }
}

function verifyOTP($conn, $userId, $inputCode) {
    $stmt = $conn->prepare(
        "SELECT id, otp_code, expires_at, attempts FROM login_otp
         WHERE user_id = ? AND is_used = 0 ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) return ['status' => 'not_found'];
    if ($row['attempts'] >= 5) return ['status' => 'locked'];
    if (strtotime($row['expires_at']) < time()) return ['status' => 'expired'];

    if (hash_equals($row['otp_code'], $inputCode)) {
        $stmt = $conn->prepare("UPDATE login_otp SET is_used = 1 WHERE id = ?");
        $stmt->bind_param("i", $row['id']);
        $stmt->execute();
        return ['status' => 'valid'];
    } else {
        $stmt = $conn->prepare("UPDATE login_otp SET attempts = attempts + 1 WHERE id = ?");
        $stmt->bind_param("i", $row['id']);
        $stmt->execute();
        return ['status' => 'invalid'];
    }
}

// ── Trusted Device ("Remember this device") ───────────────────
// Uses selector/validator pattern: selector = lookup key (safe to store
// plain), validator = secret proven via hash comparison. This way even if
// someone dumps the DB, they can't reconstruct usable cookies.

const TRUSTED_DEVICE_COOKIE = 'fid_device_token';
const TRUSTED_DEVICE_DAYS   = 30;

function issueTrustedDeviceToken($conn, $userId) {
    $selector  = bin2hex(random_bytes(9));   // 18 chars
    $validator = bin2hex(random_bytes(32));  // 64 chars
    $hash      = hash('sha256', $validator);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . TRUSTED_DEVICE_DAYS . ' days'));

    $stmt = $conn->prepare(
        "INSERT INTO trusted_devices (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isss", $userId, $selector, $hash, $expiresAt);
    $stmt->execute();

    $cookieValue = $selector . ':' . $validator;
    setcookie(
        TRUSTED_DEVICE_COOKIE,
        $cookieValue,
        [
            'expires'  => strtotime('+' . TRUSTED_DEVICE_DAYS . ' days'),
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

function isTrustedDevice($conn, $userId) {
    if (empty($_COOKIE[TRUSTED_DEVICE_COOKIE])) return false;

    $parts = explode(':', $_COOKIE[TRUSTED_DEVICE_COOKIE]);
    if (count($parts) !== 2) return false;
    [$selector, $validator] = $parts;

    $stmt = $conn->prepare(
        "SELECT id, validator_hash, expires_at FROM trusted_devices
         WHERE selector = ? AND user_id = ? LIMIT 1"
    );
    $stmt->bind_param("si", $selector, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) return false;
    if (strtotime($row['expires_at']) < time()) return false;

    return hash_equals($row['validator_hash'], hash('sha256', $validator));
}

function forgetTrustedDevice($conn) {
    if (!empty($_COOKIE[TRUSTED_DEVICE_COOKIE])) {
        $parts = explode(':', $_COOKIE[TRUSTED_DEVICE_COOKIE]);
        if (count($parts) === 2) {
            $stmt = $conn->prepare("DELETE FROM trusted_devices WHERE selector = ?");
            $stmt->bind_param("s", $parts[0]);
            $stmt->execute();
        }
    }
    setcookie(TRUSTED_DEVICE_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
    ]);
}