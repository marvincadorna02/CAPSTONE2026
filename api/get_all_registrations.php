<?php
session_start();
require_once __DIR__ . '/../includes/guard.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

$result = $conn->query("
    SELECT id, name, email, role, status, approval_status, created_at
    FROM users
    WHERE role IN ('customer', 'repairshop')
    ORDER BY created_at DESC
");

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$totalCustomers     = 0;
$totalShops         = 0;
$thisMonthCustomers = 0;
$thisMonthShops     = 0;
$thisMonthKey       = date('Y-m');

foreach ($users as $u) {
    $isThisMonth = substr($u['created_at'], 0, 7) === $thisMonthKey;
    if ($u['role'] === 'customer') {
        $totalCustomers++;
        if ($isThisMonth) $thisMonthCustomers++;
    } else {
        $totalShops++;
        if ($isThisMonth) $thisMonthShops++;
    }
}

echo json_encode([
    'success'             => true,
    'users'               => $users,
    'totalCustomers'      => $totalCustomers,
    'totalShops'          => $totalShops,
    'thisMonthCustomers'  => $thisMonthCustomers,
    'thisMonthShops'      => $thisMonthShops,
]);

$conn->close();
