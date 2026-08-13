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

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['error' => 'Not logged in']);
  exit();
}

$userId = (int) $_SESSION['user_id'];

$conn = new mysqli('localhost', 'root', '', 'fixitdavao');
if ($conn->connect_error) {
  echo json_encode(['error' => 'DB error']);
  exit();
}

// Total bookings
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE customer_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalBookings = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// Total favorites
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM favorites WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalFavorites = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// Member since
$stmt = $conn->prepare("SELECT created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result();
$memberSince = '';
if ($row = $user->fetch_assoc()) {
  $memberSince = date('M Y', strtotime($row['created_at']));
}
$stmt->close();

$conn->close();

echo json_encode([
  'total_bookings'  => (int)$totalBookings,
  'total_favorites' => (int)$totalFavorites,
  'member_since'    => $memberSince
]);