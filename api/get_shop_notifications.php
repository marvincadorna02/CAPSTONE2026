<?php
session_start();

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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'repairshop') {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$userId = (int)$_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) { echo json_encode(['error' => 'DB error']); exit(); }

// Auto-create tables
$conn->query("CREATE TABLE IF NOT EXISTS shop_notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    booking_id INT NOT NULL,
    status_seen VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_notif (shop_id, booking_id, status_seen)
)");
$conn->query("CREATE TABLE IF NOT EXISTS shop_review_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    review_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_review_notif (shop_id, review_id)
)");
$conn->query("CREATE TABLE IF NOT EXISTS reschedule_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    booking_id INT NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    service_name VARCHAR(255) DEFAULT NULL,
    old_date DATE NOT NULL,
    old_time TIME NOT NULL,
    new_date DATE NOT NULL,
    new_time TIME NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS subscription_notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    subscription_id INT NOT NULL,
    status_seen VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sub_notif (shop_id, subscription_id, status_seen)
)");

// Handle mark all read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['mark_read'])) {
        $conn->query("INSERT IGNORE INTO shop_notification_reads (shop_id, booking_id, status_seen)
            SELECT $userId, b.id, b.status
            FROM bookings b
            WHERE b.shop_id = $userId
              AND b.status IN ('pending','confirmed','completed','cancelled')
              AND (b.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   OR b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))");
        $conn->query("INSERT IGNORE INTO shop_review_reads (shop_id, review_id)
            SELECT $userId, r.id FROM reviews r
            WHERE r.shop_id = $userId
              AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $conn->query("UPDATE reschedule_notifications SET is_read = 1
            WHERE shop_id = $userId AND is_read = 0");
            $conn->query("INSERT IGNORE INTO subscription_notification_reads (shop_id, subscription_id, status_seen)
            SELECT $userId, ss.id, ss.status
            FROM shop_subscriptions ss
            WHERE ss.shop_id = $userId
              AND ss.status IN ('active','rejected')
              AND ss.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        echo json_encode(['success' => true]);
        $conn->close(); exit();
    }
}

$notifications = [];

// ── 1. Fetch reschedule notifications FIRST ───────────────────
// Also collect rescheduled booking IDs to exclude from booking notifs
$rescheduledBookingIds = [];

$stmt3 = $conn->prepare("
    SELECT id, booking_id, customer_name, service_name,
           old_date, old_time, new_date, new_time, is_read, created_at
    FROM reschedule_notifications
    WHERE shop_id = ?
      AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt3->bind_param("i", $userId);
$stmt3->execute();
$result3 = $stmt3->get_result();
while ($row = $result3->fetch_assoc()) {
    $rescheduledBookingIds[] = $row['booking_id'];
    $notifications[] = [
        'type'          => 'reschedule',
        'booking_id'    => $row['booking_id'],
        'status'        => 'rescheduled',
        'customer_name' => $row['customer_name'],
        'service_name'  => $row['service_name'],
        'old_date'      => $row['old_date'],
        'old_time'      => $row['old_time'],
        'new_date'      => $row['new_date'],
        'new_time'      => $row['new_time'],
        'is_read'       => (bool)$row['is_read'],
        'time'          => $row['created_at'],
    ];
}
$stmt3->close();

// ── 2. Fetch booking notifications (exclude rescheduled ones) ─
// Build exclusion list
$excludeIds = !empty($rescheduledBookingIds)
    ? implode(',', array_unique($rescheduledBookingIds))
    : '0';

$stmt = $conn->prepare("
    SELECT
        b.id AS booking_id,
        b.status,
        b.booking_date,
        b.booking_time,
        b.updated_at,
        b.created_at,
        b.customer_name,
        b.service_name,
        (SELECT COUNT(*) FROM shop_notification_reads nr
         WHERE nr.shop_id = ? AND nr.booking_id = b.id AND nr.status_seen = b.status) AS is_read
    FROM bookings b
    WHERE b.shop_id = ?
      AND b.status IN ('pending','confirmed','completed','cancelled')
      AND b.id NOT IN ($excludeIds)
      AND (b.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           OR b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
    ORDER BY COALESCE(b.updated_at, b.created_at) DESC
    LIMIT 15
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'type'          => 'booking',
        'booking_id'    => $row['booking_id'],
        'status'        => $row['status'],
        'customer_name' => $row['customer_name'],
        'service_name'  => $row['service_name'],
        'booking_date'  => $row['booking_date'],
        'is_read'       => (bool)$row['is_read'],
        'time'          => $row['updated_at'] ?? $row['created_at'],
    ];
}
$stmt->close();

// ── 3. Fetch review notifications ─────────────────────────────
$reviewTableCheck = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($reviewTableCheck && $reviewTableCheck->num_rows > 0) {
    $stmt2 = $conn->prepare("
        SELECT
            r.id AS review_id,
            r.rating,
            r.comment,
            r.created_at,
            u.name AS customer_name,
            (SELECT COUNT(*) FROM shop_review_reads rr
             WHERE rr.shop_id = ? AND rr.review_id = r.id) AS is_read
        FROM reviews r
        LEFT JOIN users u ON u.id = r.customer_id
        WHERE r.shop_id = ?
          AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt2->bind_param("ii", $userId, $userId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $notifications[] = [
            'type'          => 'review',
            'review_id'     => $row['review_id'],
            'status'        => 'review',
            'rating'        => (int)$row['rating'],
            'comment'       => $row['comment'],
            'customer_name' => $row['customer_name'] ?? 'A customer',
            'service_name'  => null,
            'is_read'       => (bool)$row['is_read'],
            'time'          => $row['created_at'],
        ];
    }
    $stmt2->close();
}

// ── 4. Fetch subscription approval/rejection notifications ────
$subTableCheck = $conn->query("SHOW TABLES LIKE 'shop_subscriptions'");
if ($subTableCheck && $subTableCheck->num_rows > 0) {
    $stmt4 = $conn->prepare("
        SELECT
            ss.id AS subscription_id,
            ss.status,
            ss.updated_at,
            sp.name AS plan_name,
            (SELECT COUNT(*) FROM subscription_notification_reads snr
             WHERE snr.shop_id = ? AND snr.subscription_id = ss.id AND snr.status_seen = ss.status) AS is_read
        FROM shop_subscriptions ss
        LEFT JOIN subscription_plans sp ON sp.id = ss.plan_id
        WHERE ss.shop_id = ?
          AND ss.status IN ('active','rejected')
          AND ss.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY ss.updated_at DESC
        LIMIT 5
    ");
    $stmt4->bind_param("ii", $userId, $userId);
    $stmt4->execute();
    $result4 = $stmt4->get_result();
    while ($row = $result4->fetch_assoc()) {
        $notifications[] = [
            'type'          => 'subscription',
            'subscription_id' => $row['subscription_id'],
            'status'        => $row['status'], // 'active' (approved) or 'rejected'
            'plan_name'     => $row['plan_name'],
            'customer_name' => null,
            'service_name'  => null,
            'is_read'       => (bool)$row['is_read'],
            'time'          => $row['updated_at'],
        ];
    }
    $stmt4->close();
}

// ── Sort all by time DESC (newest first) ──────────────────────
usort($notifications, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
$notifications = array_slice($notifications, 0, 20);

$unread_count = count(array_filter($notifications, fn($n) => !$n['is_read']));

$conn->close();
echo json_encode([
    'success'       => true,
    'notifications' => $notifications,
    'unread_count'  => $unread_count,
]);
?>