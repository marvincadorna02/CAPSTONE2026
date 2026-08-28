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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$userId = (int)$_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) { echo json_encode(['error' => 'DB error']); exit(); }

// Auto-create tables
$conn->query("CREATE TABLE IF NOT EXISTS notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    booking_id INT NOT NULL,
    status_seen VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_notif (user_id, booking_id, status_seen)
)");
$conn->query("CREATE TABLE IF NOT EXISTS review_reply_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    review_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reply_read (user_id, review_id)
)");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancelled_by ENUM('customer','shop') DEFAULT NULL");

// ── Handle mark as read FIRST (before fetching) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['mark_read'])) {
        // Mark all booking notifs as read
        $conn->query("INSERT IGNORE INTO notification_reads (user_id, booking_id, status_seen)
            SELECT $userId, b.id, b.status
            FROM bookings b
            WHERE b.customer_id = $userId
              AND b.status IN ('confirmed','completed','cancelled','no_show','paid','claimed')
              AND (b.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   OR b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))");

        // Mark all review reply notifs as read
        $reviewCheck = $conn->query("SHOW TABLES LIKE 'reviews'");
        if ($reviewCheck && $reviewCheck->num_rows > 0) {
            $conn->query("INSERT IGNORE INTO review_reply_reads (user_id, review_id)
                SELECT $userId, r.id
                FROM reviews r
                WHERE r.customer_id = $userId
                  AND r.reply IS NOT NULL AND r.reply != ''
                  AND r.replied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        }

        echo json_encode(['success' => true]);
        $conn->close(); exit();
    }
}

// ── Fetch booking status notifications ────────────────────────
$stmt = $conn->prepare("
    SELECT
        b.id AS booking_id,
        b.status,
        b.booking_date,
        b.booking_time,
        b.updated_at,
        b.created_at,
        u.name AS shop_name,
        u.logo_url AS shop_logo,
        b.reply,
        (SELECT COUNT(*) FROM notification_reads nr
         WHERE nr.user_id = ? AND nr.booking_id = b.id AND nr.status_seen = b.status) AS is_read
    FROM bookings b
    LEFT JOIN users u ON u.id = b.shop_id
    WHERE b.customer_id = ?
      AND ( b.status IN ('confirmed','completed','no_show','paid','claimed')
            OR (b.status = 'cancelled' AND b.cancelled_by = 'shop') )
      AND (b.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           OR b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
    ORDER BY COALESCE(b.updated_at, b.created_at) DESC
    LIMIT 15
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'booking_id'   => $row['booking_id'],
        'review_id'    => null,
        'status'       => $row['status'],
        'shop_name'    => $row['shop_name'],
        'shop_logo'    => $row['shop_logo'],
        'booking_date' => $row['booking_date'],
        'reply'        => $row['reply'] ?? null,
        'is_read'      => (bool)$row['is_read'],
        'time'         => $row['updated_at'] ?? $row['created_at'],
    ];
}
$stmt->close();

// ── Fetch review reply notifications ──────────────────────────
$reviewTableCheck = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($reviewTableCheck && $reviewTableCheck->num_rows > 0) {
    $stmt2 = $conn->prepare("
        SELECT
            r.id AS review_id,
            r.reply,
            r.replied_at,
            u.name AS shop_name,
            u.logo_url AS shop_logo,
            (SELECT COUNT(*) FROM review_reply_reads rr
             WHERE rr.user_id = ? AND rr.review_id = r.id) AS is_read
        FROM reviews r
        LEFT JOIN users u ON u.id = r.shop_id
        WHERE r.customer_id = ?
          AND r.reply IS NOT NULL AND r.reply != ''
          AND r.replied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY r.replied_at DESC
        LIMIT 10
    ");
    $stmt2->bind_param("ii", $userId, $userId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $notifications[] = [
            'booking_id'   => null,
            'review_id'    => $row['review_id'],
            'status'       => 'review_reply',
            'shop_name'    => $row['shop_name'],
            'shop_logo'    => $row['shop_logo'],
            'booking_date' => null,
            'reply'        => $row['reply'],
            'is_read'      => (bool)$row['is_read'],
            'time'         => $row['replied_at'],
        ];
    }
    $stmt2->close();
}

// ── Fetch unread direct messages from shops ───────────────────
$msgTableCheck = $conn->query("SHOW TABLES LIKE 'messages'");
if ($msgTableCheck && $msgTableCheck->num_rows > 0) {
    $stmt3 = $conn->prepare("
        SELECT m.shop_id AS other_id,
               COALESCE(u.shop_name, u.name) AS shop_name,
               u.logo_url        AS shop_logo,
               COUNT(*)          AS unread_msgs,
               MAX(m.created_at) AS last_time
        FROM messages m
        LEFT JOIN users u ON u.id = m.shop_id
        WHERE m.customer_id = ?
          AND m.sender_role = 'shop'
          AND m.is_read = 0
        GROUP BY m.shop_id, shop_name, shop_logo
        ORDER BY last_time DESC
        LIMIT 10
    ");
    $stmt3->bind_param("i", $userId);
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    while ($row = $result3->fetch_assoc()) {
        $notifications[] = [
            'booking_id'   => null,
            'review_id'    => null,
            'other_id'     => (int)$row['other_id'],
            'status'       => 'message',
            'shop_name'    => $row['shop_name'],
            'shop_logo'    => $row['shop_logo'],
            'booking_date' => null,
            'reply'        => null,
            'unread'       => (int)$row['unread_msgs'],
            'is_read'      => false,
            'time'         => $row['last_time'],
        ];
    }
    $stmt3->close();
}

// ── Sort by time DESC, limit 20 ───────────────────────────────
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