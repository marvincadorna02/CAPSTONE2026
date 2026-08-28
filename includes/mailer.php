<?php
/**
 * One place for outgoing system email.
 *
 * The same ~15-line PHPMailer SMTP block was copy-pasted into
 * includes/otp-functions.php, admin/admin-subscriptions.php,
 * customer/reschedule_booking.php and forgot-password.php. New code uses
 * sendSystemEmail() so the SMTP config only ever changes here.
 *
 * Failures are logged, never fatal — a page must not 500 because Gmail
 * is slow or the .env is missing on a dev machine.
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * @param string $to       recipient address
 * @param string $name     recipient display name
 * @param string $subject  subject line
 * @param string $bodyHtml inner HTML — wrapped in the standard shell below
 * @return bool            true if handed to SMTP
 */
function sendSystemEmail($to, $name, $subject, $bodyHtml) {
    if (empty($to) || empty($_ENV['MAIL_USERNAME']) || empty($_ENV['MAIL_PASSWORD'])) {
        error_log("sendSystemEmail skipped (no recipient or SMTP creds): {$subject}");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug  = 0;
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 12;   // don't hang a page load on a dead SMTP host

        $mail->setFrom($_ENV['MAIL_USERNAME'], $_ENV['MAIL_FROM_NAME'] ?? 'Fix It Davao');
        $mail->addAddress($to, $name ?: $to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = fixit_email_shell($subject, $bodyHtml);
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $bodyHtml)));

        $mail->send();
        return true;
    } catch (MailException $e) {
        error_log("sendSystemEmail failed ({$subject}): " . $mail->ErrorInfo);
        return false;
    }
}

/** Wraps body HTML in the branded shell so every system email looks the same. */
function fixit_email_shell($heading, $bodyHtml) {
    $h = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    return "
    <div style=\"font-family:Arial,Helvetica,sans-serif;background:#f8fafc;padding:24px;\">
      <div style=\"max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;\">
        <div style=\"background:linear-gradient(135deg,#f59e0b,#d97706);padding:18px 24px;\">
          <div style=\"color:#ffffff;font-size:18px;font-weight:800;letter-spacing:.5px;\">FIX IT DAVAO</div>
        </div>
        <div style=\"padding:24px;color:#0f172a;font-size:14px;line-height:1.6;\">
          <h2 style=\"margin:0 0 14px;font-size:17px;color:#0f172a;\">{$h}</h2>
          {$bodyHtml}
        </div>
        <div style=\"padding:14px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:11px;\">
          This is an automated message from Fix It Davao. Please do not reply to this email.
        </div>
      </div>
    </div>";
}
