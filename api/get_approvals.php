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

addColumnIfMissing($conn, 'users', 'status',           "ENUM('active','suspended') NOT NULL DEFAULT 'active'");
addColumnIfMissing($conn, 'users', 'suspend_reason',   "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'approval_status',  "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
addColumnIfMissing($conn, 'users', 'rejection_reason', "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'approved_at',      "DATETIME DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'rejected_at',      "DATETIME DEFAULT NULL");

// ── Query ─────────────────────────────────────────────────────
$result = $conn->query("
    SELECT id, name, email, approval_status, rejection_reason,
    approved_at, rejected_at, created_at, status, suspend_reason
    FROM users
    WHERE role = 'repairshop'
    ORDER BY created_at DESC
");

if (!$result) {
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    $conn->close();
    exit();
}

$pending  = [];
$approved = [];
$rejected = [];

while ($row = $result->fetch_assoc()) {
    $row['location'] = '—';
    $row['contact']  = '—';
    switch ($row['approval_status']) {
        case 'approved': $approved[] = $row; break;
        case 'rejected': $rejected[] = $row; break;
        default:         $pending[]  = $row; break;
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'pending'  => $pending,
    'approved' => $approved,
    'rejected' => $rejected,
]);
