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

$userId = (int)$_SESSION['user_id'];
$shopId = (int)($_POST['shop_id'] ?? 0);

if (!$shopId) { echo json_encode(['error' => 'Invalid shop ID']); exit(); }

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) { echo json_encode(['error' => 'DB error']); exit(); }

// Auto-create favorites table
$conn->query("CREATE TABLE IF NOT EXISTS favorites (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    shop_id     INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favorite (user_id, shop_id)
)");

// Check if already favorited
$check = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND shop_id = ?");
$check->bind_param("ii", $userId, $shopId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if ($exists) {
    // Remove favorite
    $del = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND shop_id = ?");
    $del->bind_param("ii", $userId, $shopId);
    $del->execute();
    $del->close();
    echo json_encode(['success' => true, 'favorited' => false]);
} else {
    // Add favorite
    $ins = $conn->prepare("INSERT INTO favorites (user_id, shop_id) VALUES (?, ?)");
    $ins->bind_param("ii", $userId, $shopId);
    $ins->execute();
    $ins->close();
    echo json_encode(['success' => true, 'favorited' => true]);
}

$conn->close();