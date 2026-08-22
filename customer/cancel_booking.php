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

// ── CSRF validation ─────────────────────────────────────────
if (!isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['error' => 'Invalid request.']); exit();
}

$userId    = (int)$_SESSION['user_id'];
$bookingId = (int)($_POST['booking_id'] ?? 0);

if (!$bookingId) {
    echo json_encode(['error' => 'Invalid booking ID']); exit();
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB error']); exit();
}

// Only allow cancelling own pending/confirmed bookings
$stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND customer_id = ? AND status IN ('pending','confirmed')");
$stmt->bind_param("ii", $bookingId, $userId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Booking not found or cannot be cancelled.']);
}

$stmt->close();
$conn->close();