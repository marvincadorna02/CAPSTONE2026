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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$host   = "localhost";
$dbname = "fixitdavao";
$dbuser = "root";
$dbpass = "";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]);
    exit();
}

// ── Safe column adder (works on MySQL 5.x and MariaDB) ────────
function addColumnIfMissing($conn, $table, $column, $definition) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

addColumnIfMissing($conn, 'users', 'approval_status',  "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
addColumnIfMissing($conn, 'users', 'rejection_reason', "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'approved_at',      "DATETIME DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'rejected_at',      "DATETIME DEFAULT NULL");

// ── Input ─────────────────────────────────────────────────────
$data   = json_decode(file_get_contents("php://input"), true);

// ── CSRF validation ─────────────────────────────────────────
if (!isset($data['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'])) {
    echo json_encode(['error' => 'Invalid request.']);
    exit();
}

$id     = intval($data['id']     ?? 0);
$action = $data['action']        ?? '';
$reason = trim($data['reason']   ?? '');

if (!$id || !in_array($action, ['approve', 'reject', 'reconsider'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

// ── Execute ───────────────────────────────────────────────────
if ($action === 'approve') {
    $stmt = $conn->prepare("UPDATE users SET approval_status = 'approved', approved_at = NOW(), rejection_reason = NULL, rejected_at = NULL WHERE id = ? AND role = 'repairshop'");
    $stmt->bind_param("i", $id);

} elseif ($action === 'reconsider') {
    $stmt = $conn->prepare("UPDATE users SET approval_status = 'pending', rejection_reason = NULL, rejected_at = NULL, approved_at = NULL WHERE id = ? AND role = 'repairshop'");
    $stmt->bind_param("i", $id);

} else { // reject
    if (empty($reason)) {
        echo json_encode(['error' => 'Rejection reason is required']);
        exit();
    }
    $stmt = $conn->prepare("UPDATE users SET approval_status = 'rejected', rejected_at = NOW(), rejection_reason = ?, approved_at = NULL WHERE id = ? AND role = 'repairshop'");
    $stmt->bind_param("si", $reason, $id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to update: ' . $stmt->error]);
}

$stmt->close();
$conn->close();