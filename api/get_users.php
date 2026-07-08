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
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

function addColumnIfMissing($conn, $table, $column, $definition) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
addColumnIfMissing($conn, 'users', 'status',          "ENUM('active','suspended') NOT NULL DEFAULT 'active'");
addColumnIfMissing($conn, 'users', 'suspend_reason',  "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'approval_status', "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
addColumnIfMissing($conn, 'users', 'logo_url',        "VARCHAR(255) DEFAULT NULL"); // ← ADDED

// Customers always show. Repairshops only show if approved.
$result = $conn->query("
    SELECT id, name, email, role, status, suspend_reason, logo_url, created_at
    FROM users
    WHERE role = 'customer'
       OR (role = 'repairshop' AND approval_status = 'approved')
    ORDER BY created_at DESC
");

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

$users = [];
while ($row = $result->fetch_assoc()) {
    // Make logo_url absolute (only repairshops have one)
    if (!empty($row['logo_url'])) {
        $row['logo_url'] = $baseUrl . $row['logo_url'];
    }
    $users[] = $row;
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($users);