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

$conn   = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['open_days' => []]); exit();
}

$shopId = (int)($_GET['shop_id'] ?? 0);

if (!$shopId) {
    echo json_encode(['open_days' => []]); exit();
}

// Table: operating_hours
// Columns: user_id, day, open_time, close_time
// A row existing = that day is open (no is_open flag)
$stmt = $conn->prepare("SELECT day FROM operating_hours WHERE user_id = ?");
$stmt->bind_param("i", $shopId);
$stmt->execute();
$result = $stmt->get_result();

$days = [];
while ($row = $result->fetch_assoc()) {
    $days[] = strtolower(trim($row['day']));
}

$stmt->close();
$conn->close();

echo json_encode(['open_days' => $days]);
?>
