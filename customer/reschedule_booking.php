<?php
session_start();
require_once __DIR__ . '/../config/env.php';

// ── Session timeout (30 mins) ──
$timeout = 1800;
if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    header("../login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']); exit();
}

$bookingId  = (int)($_POST['booking_id'] ?? 0);
$newDate    = trim($_POST['new_date']    ?? '');
$newTime    = trim($_POST['new_time']    ?? '');
$customerId = (int)$_SESSION['user_id'];

if (!$bookingId || !$newDate || !$newTime) {
    echo json_encode(['error' => 'Missing required fields.']); exit();
}

if (strtotime($newDate) < strtotime(date('Y-m-d'))) {
    echo json_encode(['error' => 'Please select a future date.']); exit();
}

// Fetch booking + shop info — verify ownership
$stmt = $conn->prepare("
    SELECT b.id, b.customer_name, b.device_type, b.device_brand,
           b.service_name, b.booking_date, b.booking_time, b.status,
           b.shop_id,
           u.email AS shop_email, u.shop_name, u.name AS shop_owner_name
    FROM bookings b
    JOIN users u ON u.id = b.shop_id
    WHERE b.id = ? AND b.customer_id = ?
");
$stmt->bind_param("ii", $bookingId, $customerId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo json_encode(['error' => 'Booking not found.']); exit();
}

if (!in_array($booking['status'], ['pending', 'confirmed'])) {
    echo json_encode(['error' => 'This booking cannot be rescheduled.']); exit();
}

// Save old date/time before update
$oldDate = $booking['booking_date'];
$oldTime = $booking['booking_time'];

// Update booking date/time
$update = $conn->prepare("
    UPDATE bookings
    SET booking_date = ?, booking_time = ?, status = 'pending',
        notes = CONCAT(IFNULL(notes,''), ' [Rescheduled by customer]')
    WHERE id = ? AND customer_id = ?
");
$update->bind_param("ssii", $newDate, $newTime, $bookingId, $customerId);

if (!$update->execute()) {
    echo json_encode(['error' => 'Failed to reschedule: ' . $update->error]); exit();
}
$update->close();

// ── Insert reschedule notification for shop ───────────────────
$conn->query("CREATE TABLE IF NOT EXISTS reschedule_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    booking_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(255) NOT NULL,
    service_name VARCHAR(255) DEFAULT NULL,
    old_date DATE NOT NULL,
    old_time TIME NOT NULL,
    new_date DATE NOT NULL,
    new_time TIME NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$notifStmt = $conn->prepare("
    INSERT INTO reschedule_notifications
        (shop_id, booking_id, customer_id, customer_name, service_name, old_date, old_time, new_date, new_time)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$notifStmt->bind_param(
    "iiissssss",
    $booking['shop_id'],
    $bookingId,
    $customerId,
    $booking['customer_name'],
    $booking['service_name'],
    $oldDate,
    $oldTime,
    $newDate,
    $newTime
);
$notifStmt->execute();
$notifStmt->close();

// ── Send email to repair shop via PHPMailer ───────────────────
$emailSent  = false;
$emailError = '';
$shopEmail  = trim($booking['shop_email'] ?? '');

// FIX 1: Log what email we're trying to send to
error_log("[Reschedule] Attempting email to: '{$shopEmail}' for booking #{$bookingId}");

if (empty($shopEmail)) {
    $emailError = 'Shop email is empty in database.';
    error_log("[Reschedule] SKIPPED: shop email is empty.");
} else {
    $mailerPath = __DIR__ . '/PHPMailer/src/';

    // FIX 2: Log whether PHPMailer files exist
    if (!file_exists($mailerPath . 'PHPMailer.php')) {
        $emailError = 'PHPMailer not found at: ' . $mailerPath;
        error_log("[Reschedule] SKIPPED: PHPMailer.php not found at {$mailerPath}");
    } else {
        require_once $mailerPath . 'PHPMailer.php';
        require_once $mailerPath . 'SMTP.php';
        require_once $mailerPath . 'Exception.php';

        $mail = new PHPMailer(true);
        try {
            // FIX 3: Enable SMTP debug logging to error_log (silent to user)
            $mail->SMTPDebug  = 2; // 0=off, 1=client, 2=client+server
            $mail->Debugoutput = function($str, $level) {
                error_log("[PHPMailer Debug] $str");
            };

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'];
            $mail->Password   = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($_ENV['MAIL_USERNAME'], $_ENV['MAIL_FROM_NAME']);
            $mail->addAddress($shopEmail, $booking['shop_owner_name'] ?? '');

            $oldDateFmt = date('F j, Y', strtotime($oldDate));
            $oldTimeFmt = date('g:i A',  strtotime($oldTime));
            $newDateFmt = date('F j, Y', strtotime($newDate));
            $newTimeFmt = date('g:i A',  strtotime($newTime));

            // FIX 5: Also set AltBody so it doesn't end up in spam
            $mail->isHTML(true);
            $mail->Subject = "Booking Rescheduled - {$booking['customer_name']} | Fix It Davao";
            $mail->AltBody = "Hello {$booking['shop_owner_name']},\n\n"
                . "A customer has rescheduled their booking.\n\n"
                . "Customer: {$booking['customer_name']}\n"
                . "Service: " . ($booking['service_name'] ?: '—') . "\n"
                . "Old Schedule: {$oldDateFmt} at {$oldTimeFmt}\n"
                . "New Schedule: {$newDateFmt} at {$newTimeFmt}\n\n"
                . "Please log in to your Fix It Davao dashboard to review this booking.\n\n"
                . "© 2026 Fix It Davao";

            $mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:20px;'>
                    <div style='background:linear-gradient(135deg,#f59e0b,#d97706);padding:24px;border-radius:12px 12px 0 0;text-align:center;'>
                        <h2 style='color:white;margin:0;font-size:20px;'>&#128197; Booking Rescheduled</h2>
                    </div>
                    <div style='background:#fff;border:1.5px solid #e2e8f0;border-top:none;padding:24px;border-radius:0 0 12px 12px;'>
                        <p style='color:#374151;'>Hello <strong>" . htmlspecialchars($booking['shop_owner_name']) . "</strong>,</p>
                        <p style='color:#374151;'>A customer has rescheduled their booking. Here are the updated details:</p>
                        <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
                            <tr style='background:#f8fafc;'>
                                <td style='padding:10px 12px;font-size:13px;color:#64748b;font-weight:600;width:40%;'>Customer</td>
                                <td style='padding:10px 12px;font-size:13px;color:#0f172a;font-weight:700;'>" . htmlspecialchars($booking['customer_name']) . "</td>
                            </tr>
                            <tr>
                                <td style='padding:10px 12px;font-size:13px;color:#64748b;font-weight:600;'>Service</td>
                                <td style='padding:10px 12px;font-size:13px;color:#0f172a;'>" . htmlspecialchars($booking['service_name'] ?: '—') . "</td>
                            </tr>
                            <tr style='background:#f8fafc;'>
                                <td style='padding:10px 12px;font-size:13px;color:#64748b;font-weight:600;'>Device</td>
                                <td style='padding:10px 12px;font-size:13px;color:#0f172a;'>" . htmlspecialchars($booking['device_type']) . ($booking['device_brand'] ? ' · ' . htmlspecialchars($booking['device_brand']) : '') . "</td>
                            </tr>
                            <tr style='background:#fff7ed;'>
                                <td style='padding:10px 12px;font-size:13px;color:#92400e;font-weight:700;'>Old Schedule</td>
                                <td style='padding:10px 12px;font-size:13px;color:#92400e;text-decoration:line-through;'>{$oldDateFmt} at {$oldTimeFmt}</td>
                            </tr>
                            <tr style='background:#d1fae5;'>
                                <td style='padding:10px 12px;font-size:13px;color:#065f46;font-weight:700;'>New Schedule</td>
                                <td style='padding:10px 12px;font-size:13px;color:#065f46;font-weight:700;'>{$newDateFmt} at {$newTimeFmt}</td>
                            </tr>
                        </table>
                        <p style='color:#64748b;font-size:13px;margin-top:16px;'>Please check your shop dashboard to confirm or update this booking.</p>
                        <div style='text-align:center;margin-top:20px;'>
                            <a href='http://localhost/FIXITDAVAO/shop-bookings.php'
                               style='background:linear-gradient(135deg,#f59e0b,#d97706);color:white;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;display:inline-block;'>
                                View Bookings
                            </a>
                        </div>
                    </div>
                    <p style='text-align:center;color:#94a3b8;font-size:12px;margin-top:16px;'>&#169; 2026 Fix It Davao</p>
                </div>
            ";

            $mail->send();
            $emailSent = true;
            error_log("[Reschedule] Email sent successfully to {$shopEmail}");

        } catch (Exception $e) {
            // FIX 6: Capture the actual PHPMailer error message, not just ErrorInfo
            $emailError = $mail->ErrorInfo;
            error_log("[Reschedule] Email FAILED: " . $mail->ErrorInfo);
        }
    }
}

$conn->close();

echo json_encode([
    'success'     => true,
    'email_sent'  => $emailSent,
    'email_error' => $emailError, // FIX 7: Return error to frontend so you can see it in browser DevTools
    'message'     => 'Booking rescheduled successfully!'
]);
?>