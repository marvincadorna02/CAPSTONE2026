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

$userId = $_SESSION['user_id'];

$conn = new mysqli('localhost', 'root', '', 'fixitdavao');
if ($conn->connect_error) {
  echo json_encode(['error' => 'DB error']);
  exit();
}

// Total bookings
$bookings = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE customer_id = $userId");
$totalBookings = $bookings->fetch_assoc()['cnt'] ?? 0;

// Total favorites
$favs = $conn->query("SELECT COUNT(*) as cnt FROM favorites WHERE user_id = $userId");
$totalFavorites = $favs->fetch_assoc()['cnt'] ?? 0;

// Member since
$user = $conn->query("SELECT created_at FROM users WHERE id = $userId");
$memberSince = '';
if ($row = $user->fetch_assoc()) {
  $memberSince = date('M Y', strtotime($row['created_at']));
}

$conn->close();

echo json_encode([
  'total_bookings'  => (int)$totalBookings,
  'total_favorites' => (int)$totalFavorites,
  'member_since'    => $memberSince
]);