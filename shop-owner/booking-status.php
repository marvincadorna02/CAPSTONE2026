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

$bookingId = (int)($_POST['booking_id'] ?? 0);
$newStatus = trim($_POST['status'] ?? '');
$shopId    = (int)$_SESSION['user_id'];

$allowed = ['confirmed', 'completed', 'cancelled'];
if (!$bookingId || !in_array($newStatus, $allowed)) {
    echo json_encode(['error' => 'Invalid request']); exit();
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) { echo json_encode(['error' => 'DB error']); exit(); }

// Only allow the shop that owns the booking to update it
$stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ? AND shop_id = ?");
$stmt->bind_param("sii", $newStatus, $bookingId, $shopId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $stmt->close(); $conn->close();
    echo json_encode(['success' => true]);
} else {
    $stmt->close(); $conn->close();
    echo json_encode(['error' => 'Booking not found or already updated.']);
}