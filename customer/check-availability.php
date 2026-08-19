<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']); exit();
}

$shopId       = (int)($_GET['shop_id']      ?? 0);
$bookingDate  = trim($_GET['booking_date']  ?? '');
$bookingTime  = trim($_GET['booking_time']  ?? '');

if (!$shopId || !$bookingDate || !$bookingTime) {
    echo json_encode(['error' => 'Missing parameters']); exit();
}

// ── Check for an existing booking at the same shop, date, and time ──
// Only pending/confirmed bookings count as "taken" — cancelled bookings free up the slot.
$stmt = $conn->prepare("
    SELECT id FROM bookings
    WHERE shop_id = ?
      AND booking_date = ?
      AND booking_time = ?
      AND status IN ('pending','confirmed')
    LIMIT 1
");
$stmt->bind_param("iss", $shopId, $bookingDate, $bookingTime);
$stmt->execute();
$taken = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'success'   => true,
    'available' => $taken ? false : true
]);