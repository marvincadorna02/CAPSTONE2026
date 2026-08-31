<?php
session_start();
define('FIXIT_GUARD_JSON', true);
require_once __DIR__ . '/../includes/guard.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'repairshop') {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

// ── CSRF validation ─────────────────────────────────────────
if (!isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['error' => 'Invalid request.']); exit();
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$newStatus = trim($_POST['status']     ?? '');
$shopId    = (int)$_SESSION['user_id'];

$allowed = ['confirmed', 'completed', 'cancelled', 'no_show', 'paid', 'claimed', 'unclaimed'];
if (!$bookingId || !in_array($newStatus, $allowed)) {
    echo json_encode(['error' => 'Invalid request']); exit();
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]); exit();
}

// Ensure the enum supports the full booking lifecycle
$conn->query("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','confirmed','completed','cancelled','no_show','paid','claimed','unclaimed') NOT NULL DEFAULT 'pending'");

// Fetch current status to validate the transition (shop must own the booking)
$cur = $conn->prepare("SELECT status FROM bookings WHERE id = ? AND shop_id = ?");
$cur->bind_param("ii", $bookingId, $shopId);
$cur->execute();
$curRow = $cur->get_result()->fetch_assoc();
$cur->close();

if (!$curRow) {
    $conn->close();
    echo json_encode(['error' => 'Booking not found.']); exit();
}

// Allowed forward transitions — enforces pending→confirmed→completed→paid→claimed
$transitions = [
    'pending'   => ['confirmed', 'cancelled'],
    'confirmed' => ['completed', 'cancelled', 'no_show'],
    'completed' => ['paid'],
    'paid'      => ['claimed', 'unclaimed'],
];
$current = $curRow['status'];
if (!isset($transitions[$current]) || !in_array($newStatus, $transitions[$current], true)) {
    $conn->close();
    echo json_encode(['error' => "Invalid status change from '{$current}' to '{$newStatus}'."]); exit();
}

$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancelled_by ENUM('customer','shop') DEFAULT NULL");

$stmt = $conn->prepare("UPDATE bookings SET status = ?, cancelled_by = IF(? = 'cancelled','shop',cancelled_by) WHERE id = ? AND shop_id = ?");
$stmt->bind_param("ssii", $newStatus, $newStatus, $bookingId, $shopId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $stmt->close(); $conn->close();
    echo json_encode(['success' => true]);
} else {
    $err = $conn->error;
    $stmt->close(); $conn->close();
    echo json_encode(['error' => 'Booking not found or already updated. ' . $err]);
}
