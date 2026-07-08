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
$conn->query("CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, shop_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favorite (user_id, shop_id)
)");
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL UNIQUE,
    shop_id INT NOT NULL, customer_id INT NOT NULL,
    rating TINYINT NOT NULL, comment TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $conn->prepare("
    SELECT
        b.id, b.shop_id, b.service_name, b.device_type, b.device_brand,
        b.problem_description, b.booking_date, b.booking_time,
        b.status, b.created_at, b.customer_name, b.customer_contact,
        COALESCE(u.shop_name, u.name) AS shop_name,
        u.logo_url    AS shop_logo,
        u.shop_location AS shop_location,
        u.contact_number AS shop_contact,
        -- is this shop favorited by this customer?
        (SELECT COUNT(*) FROM favorites f WHERE f.user_id = ? AND f.shop_id = b.shop_id) AS is_favorited,
        -- has this booking been reviewed?
        (SELECT COUNT(*) FROM reviews r WHERE r.booking_id = b.id) AS is_reviewed,
        -- existing review data if any
        (SELECT r2.rating  FROM reviews r2 WHERE r2.booking_id = b.id LIMIT 1) AS review_rating,
        (SELECT r2.comment FROM reviews r2 WHERE r2.booking_id = b.id LIMIT 1) AS review_comment
    FROM bookings b
    LEFT JOIN users u ON u.id = b.shop_id
    WHERE b.customer_id = ?
      AND b.status IN ('completed', 'cancelled')
    ORDER BY b.created_at DESC
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $row['is_favorited'] = (bool)$row['is_favorited'];
    $row['is_reviewed']  = (bool)$row['is_reviewed'];
    $bookings[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'bookings' => $bookings]);