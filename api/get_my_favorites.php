<?php
session_start();
require_once __DIR__ . '/../includes/guard.php';

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

// Auto-create tables just in case
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
        u.id            AS shop_id,
        COALESCE(u.shop_name, u.name) AS shop_name,
        u.logo_url      AS shop_logo,
        u.shop_location AS shop_location,
        u.contact_number AS shop_contact,
        u.email         AS shop_email,
        f.created_at    AS favorited_at,
        -- avg rating across all reviews for this shop
        (SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.shop_id = u.id) AS avg_rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.shop_id = u.id) AS review_count,
        -- how many completed bookings this customer has with this shop
        (SELECT COUNT(*) FROM bookings b WHERE b.shop_id = u.id AND b.customer_id = ? AND b.status = 'completed') AS completed_count
    FROM favorites f
    JOIN users u ON u.id = f.shop_id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$shops = [];
while ($row = $result->fetch_assoc()) $shops[] = $row;

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'shops' => $shops]);
