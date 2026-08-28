<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

// ── CSRF validation ─────────────────────────────────────────
if (!isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['error' => 'Invalid request.']); exit();
}

$userId    = (int)$_SESSION['user_id'];
$bookingId = (int)($_POST['booking_id'] ?? 0);
$shopId    = (int)($_POST['shop_id']    ?? 0);
$rating    = (int)($_POST['rating']     ?? 0);
$comment   = trim($_POST['comment']     ?? '');

if (!$bookingId || !$shopId || $rating < 1 || $rating > 5) {
    echo json_encode(['error' => 'Invalid data. Rating must be 1–5.']); exit();
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) { echo json_encode(['error' => 'DB error']); exit(); }

// Auto-create reviews table
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT NOT NULL UNIQUE,
    shop_id     INT NOT NULL,
    customer_id INT NOT NULL,
    rating      TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Verify booking belongs to this customer, is for this shop, and is completed
$verify = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND customer_id = ? AND shop_id = ? AND status IN ('completed','paid','claimed')");
$verify->bind_param("iii", $bookingId, $userId, $shopId);
$verify->execute();
if (!$verify->get_result()->fetch_assoc()) {
    $verify->close(); $conn->close();
    echo json_encode(['error' => 'Booking not found or not completed.']); exit();
}
$verify->close();

// Check if already reviewed
$dup = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
$dup->bind_param("i", $bookingId);
$dup->execute();
if ($dup->get_result()->fetch_assoc()) {
    $dup->close(); $conn->close();
    echo json_encode(['error' => 'You already reviewed this booking.']); exit();
}
$dup->close();

// Insert review
$ins = $conn->prepare("INSERT INTO reviews (booking_id, shop_id, customer_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
$ins->bind_param("iiiis", $bookingId, $shopId, $userId, $rating, $comment);

if ($ins->execute()) {
    $ins->close(); $conn->close();
    echo json_encode(['success' => true]);
} else {
    $err = $ins->error;
    $ins->close(); $conn->close();
    echo json_encode(['error' => 'Failed to save review: ' . $err]);
}