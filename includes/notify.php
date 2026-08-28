<?php
/**
 * In-app notifications + admin audit trail.
 *
 * The existing notification bells derive their items from bookings / reviews /
 * subscriptions plus a *_reads table. That works for events that map 1:1 onto a
 * row, but not for system events (auto-cancelled booking, subscription expiring,
 * admin warning, maintenance notice). Those get a real row in `notifications`.
 *
 * Both feeds coexist: the bells read the derived items AND this table.
 */

require_once __DIR__ . '/db.php';

/** Notification types that are important enough to also send an email. */
const FIXIT_EMAIL_TYPES = [
    'booking_auto_cancelled',
    'booking_shop_deactivated',
    'booking_expired_no_response',
    'subscription_expiring',
    'subscription_expired',
    'subscription_grace',
    'account_suspended',
    'account_warned',
    'booking_restricted',
    'maintenance',
];

/**
 * Writes an in-app notification and (optionally) emails it.
 *
 * @param mysqli $conn
 * @param int    $userId  recipient
 * @param string $role    'customer' | 'repairshop' | 'admin'
 * @param string $type    machine key, also used for dedup
 * @param string $title   short headline
 * @param string $body    one or two sentences
 * @param string|null $link  relative URL the bell item should open
 * @param bool   $email   send email too (only honoured for FIXIT_EMAIL_TYPES)
 * @param int|null $refId related row id (booking/subscription/report) for dedup
 * @return int|false new notification id
 */
function pushNotification($conn, $userId, $role, $type, $title, $body, $link = null, $email = false, $refId = null) {
    if (!$conn || !$userId) return false;

    $stmt = fixit_p(
        $conn,
        "INSERT INTO notifications (user_id, role, type, title, body, link, ref_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        error_log('pushNotification: notifications table missing?');
        return false;
    }
    $stmt->bind_param("isssssi", $userId, $role, $type, $title, $body, $link, $refId);
    if (!fixit_x($stmt)) { $stmt->close(); return false; }
    $id = $stmt->insert_id;
    $stmt->close();

    if ($email && in_array($type, FIXIT_EMAIL_TYPES, true)) {
        require_once __DIR__ . '/mailer.php';

        $u = fixit_p($conn, "SELECT name, email FROM users WHERE id = ? LIMIT 1");
        if ($u) {
            $u->bind_param("i", $userId);
            fixit_x($u);
            $row = $u->get_result()->fetch_assoc();
            $u->close();

            if ($row && !empty($row['email'])) {
                $html = '<p>' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</p>';
                if ($link) {
                    $base = fixit_base_url();
                    $html .= '<p style="margin-top:18px;">
                        <a href="' . htmlspecialchars($base . ltrim($link, '/'), ENT_QUOTES, 'UTF-8') . '"
                           style="background:#f59e0b;color:#fff;text-decoration:none;padding:10px 18px;
                                  border-radius:8px;font-weight:700;display:inline-block;">Open Fix It Davao</a>
                    </p>';
                }
                sendSystemEmail($row['email'], $row['name'], $title, $html);
            }
        }
    }

    return $id;
}

/**
 * True if this user already got this notification type for this ref today.
 * Keeps the throttled housekeeping runner from spamming the same reminder
 * every 5 minutes.
 */
function notificationSentToday($conn, $userId, $type, $refId = null) {
    if (!$conn) return false;

    $sql = "SELECT id FROM notifications
            WHERE user_id = ? AND type = ? AND DATE(created_at) = CURDATE()";
    $sql .= $refId === null ? " AND ref_id IS NULL" : " AND ref_id = ?";
    $sql .= " LIMIT 1";

    $stmt = fixit_p($conn, $sql);
    if (!$stmt) return false;

    if ($refId === null) $stmt->bind_param("is", $userId, $type);
    else                 $stmt->bind_param("isi", $userId, $type, $refId);

    if (!fixit_x($stmt)) { $stmt->close(); return false; }
    $hit = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $hit;
}

/**
 * Records an admin action. Called from every admin write so a disputed change
 * can be traced back to who made it.
 */
function logAdminAction($conn, $action, $targetType, $targetId, $details = '') {
    if (!$conn) return false;
    $adminId = (int)($_SESSION['user_id'] ?? 0);

    $stmt = fixit_p(
        $conn,
        "INSERT INTO admin_audit_log (admin_id, admin_name, action, target_type, target_id, details)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return false;

    $adminName = (string)($_SESSION['name'] ?? 'Admin');
    $stmt->bind_param("isssis", $adminId, $adminName, $action, $targetType, $targetId, $details);
    $ok = fixit_x($stmt);
    $stmt->close();
    return $ok;
}

/**
 * Absolute base URL of the app, proxy-aware (ngrok forwards plain HTTP).
 * Same detection the shop/booking pages already do inline.
 */
function fixit_base_url() {
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Walk up from the current script to the project root.
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    foreach (['/admin', '/customer', '/shop-owner', '/api', '/includes'] as $sub) {
        if (substr($dir, -strlen($sub)) === $sub) {
            $dir = substr($dir, 0, -strlen($sub));
            break;
        }
    }
    return ($isHttps ? 'https' : 'http') . '://' . $host . rtrim($dir, '/') . '/';
}
