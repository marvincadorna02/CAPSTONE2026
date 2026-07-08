<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'repairshop') {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$newStatus = trim($_POST['status']     ?? '');
$shopId    = (int)$_SESSION['user_id'];

$allowed = ['confirmed', 'completed', 'cancelled', 'no_show'];
if (!$bookingId || !in_array($newStatus, $allowed)) {
    echo json_encode(['error' => 'Invalid request']); exit();
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]); exit();
}

// Only allow the shop that owns the booking to update it
$conn->query("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending'"); // <- ADD THIS
$stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ? AND shop_id = ?");
$stmt->bind_param("sii", $newStatus, $bookingId, $shopId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $stmt->close(); $conn->close();
    echo json_encode(['success' => true]);
} else {
    $err = $conn->error;
    $stmt->close(); $conn->close();
    echo json_encode(['error' => 'Booking not found or already updated. ' . $err]);
}