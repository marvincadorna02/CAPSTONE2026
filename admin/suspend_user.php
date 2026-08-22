<?php
session_start();

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
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

// ── Auto-add columns if they don't exist yet ─────────────────
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','suspended') NOT NULL DEFAULT 'active'");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS suspend_reason VARCHAR(255) DEFAULT NULL");

$data   = json_decode(file_get_contents("php://input"), true);

// ── CSRF validation ─────────────────────────────────────────
if (!isset($data['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'])) {
    echo json_encode(['error' => 'Invalid request.']);
    exit();
}

$id     = intval($data['id']     ?? 0);
$reason = trim($data['reason']   ?? '');
$action = $data['action']        ?? 'suspend';

if (!$id) {
    echo json_encode(['error' => 'Invalid ID']);
    exit();
}

if ($action === 'reactivate') {
    $stmt = $conn->prepare("UPDATE users SET status = 'active', suspend_reason = NULL WHERE id = ?");
    $stmt->bind_param("i", $id);
} else {
    if (empty($reason)) {
        echo json_encode(['error' => 'Reason is required']);
        exit();
    }
    $stmt = $conn->prepare("UPDATE users SET status = 'suspended', suspend_reason = ? WHERE id = ?");
    $stmt->bind_param("si", $reason, $id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to update status']);
}

$stmt->close();
$conn->close();