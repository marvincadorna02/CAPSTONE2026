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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'repairshop') {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$shopId = (int)$_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) { echo json_encode(['error' => 'DB error']); exit(); }

// Auto-create tables
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT NOT NULL UNIQUE,
    shop_id     INT NOT NULL,
    customer_id INT NOT NULL,
    rating      TINYINT NOT NULL,
    comment     TEXT DEFAULT NULL,
    reply       TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Add reply column if missing
$r = $conn->query("SHOW COLUMNS FROM `reviews` LIKE 'reply'");
if ($r && $r->num_rows === 0) {
    $conn->query("ALTER TABLE `reviews` ADD COLUMN `reply` TEXT DEFAULT NULL");
}
$r2 = $conn->query("SHOW COLUMNS FROM `reviews` LIKE 'replied_at'");
if ($r2 && $r2->num_rows === 0) {
    $conn->query("ALTER TABLE `reviews` ADD COLUMN `replied_at` DATETIME DEFAULT NULL");
}

$stmt = $conn->prepare("
    SELECT
        rv.id, rv.rating, rv.comment, rv.reply, rv.replied_at, rv.created_at,
        u.name      AS customer_name,
        b.service_name
    FROM reviews rv
    LEFT JOIN users u ON u.id = rv.customer_id
    LEFT JOIN bookings b ON b.id = rv.booking_id
    WHERE rv.shop_id = ?
    ORDER BY rv.created_at DESC
");
$stmt->bind_param("i", $shopId);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
$counts  = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
$total   = 0;
$sum     = 0;

while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
    $counts[(int)$row['rating']]++;
    $total++;
    $sum += (int)$row['rating'];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success'    => true,
    'reviews'    => $reviews,
    'total'      => $total,
    'avg_rating' => $total > 0 ? round($sum / $total, 1) : 0,
    'counts'     => $counts
]);
