<?php
session_start();
define('FIXIT_GUARD_JSON', true);
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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'repairshop') {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

// ── CSRF validation ─────────────────────────────────────────
if (!isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['error' => 'Invalid request.']); exit();
}

$shopId   = (int)$_SESSION['user_id'];
$reviewId = (int)($_POST['review_id'] ?? 0);
$reply    = trim($_POST['reply']     ?? '');

if (!$reviewId || !$reply) {
    echo json_encode(['error' => 'Invalid data']); exit();
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) { echo json_encode(['error' => 'DB error']); exit(); }

// Only allow replying to own shop's reviews
$stmt = $conn->prepare("UPDATE reviews SET reply = ?, replied_at = NOW() WHERE id = ? AND shop_id = ?");
$stmt->bind_param("sii", $reply, $reviewId, $shopId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $stmt->close(); $conn->close();
    echo json_encode(['success' => true]);
} else {
    $stmt->close(); $conn->close();
    echo json_encode(['error' => 'Review not found or already replied.']);
}
