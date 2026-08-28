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
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']); exit();
}

// Auto-create bookings table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS bookings (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    shop_id             INT NOT NULL,
    customer_id         INT NOT NULL,
    service_id          INT DEFAULT NULL,
    service_name        VARCHAR(255) DEFAULT '',
    customer_name       VARCHAR(255) NOT NULL,
    customer_contact    VARCHAR(50)  NOT NULL,
    device_type         VARCHAR(100) DEFAULT '',
    device_brand        VARCHAR(150) DEFAULT '',
    problem_description TEXT,
    booking_date        DATE NOT NULL,
    booking_time        TIME NOT NULL,
    status              ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes               TEXT DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $conn->prepare("
    SELECT
        b.*,
        COALESCE(u.shop_name, u.name) AS shop_name,
        u.logo_url    AS shop_logo,
        u.shop_location AS shop_location,
        u.contact_number AS shop_contact
    FROM bookings b
    LEFT JOIN users u ON u.id = b.shop_id
    WHERE b.customer_id = ?
    ORDER BY
        FIELD(b.status, 'pending','confirmed','completed','paid','claimed','cancelled','no_show'),
        b.created_at DESC,
        b.booking_date DESC,
        b.booking_time DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'bookings' => $bookings]);
