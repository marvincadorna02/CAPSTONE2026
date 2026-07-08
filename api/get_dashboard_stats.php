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

header('Content-Type: application/json');

$host   = "localhost";
$dbname = "fixitdavao";
$dbuser = "root";
$dbpass = "";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// ── Counts ────────────────────────────────────────────────────
$totalShops    = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='repairshop'")->fetch_assoc()['c'];
$pendingShops  = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='repairshop' AND approval_status='pending'")->fetch_assoc()['c'];
$approvedShops = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='repairshop' AND approval_status='approved'")->fetch_assoc()['c'];
$rejectedShops = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='repairshop' AND approval_status='rejected'")->fetch_assoc()['c'];
$totalUsers    = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='customer'")->fetch_assoc()['c'];

// ── This month registrations ──────────────────────────────────
$thisMonth     = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='repairshop' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_assoc()['c'];

// ── Approval rate ─────────────────────────────────────────────
$approvalRate  = $totalShops > 0 ? round(($approvedShops / $totalShops) * 100) : 0;

// ── Recent activity (last 8 events) ──────────────────────────
$recentRes = $conn->query("
    SELECT name, role, approval_status, status, created_at, approved_at, rejected_at
    FROM users
    WHERE role IN ('repairshop','customer')
    ORDER BY GREATEST(
        COALESCE(created_at,  '1970-01-01'),
        COALESCE(approved_at, '1970-01-01'),
        COALESCE(rejected_at, '1970-01-01')
    ) DESC
    LIMIT 8
");
$recentActivity = [];
while ($row = $recentRes->fetch_assoc()) {
    // Most recent event for this row
    $timestamps = array_filter([
        'approved' => $row['approved_at'],
        'rejected' => $row['rejected_at'],
        'created'  => $row['created_at'],
    ]);
    arsort($timestamps);
    $latestType = array_key_first($timestamps);
    $latestTime = $timestamps[$latestType];

    if ($latestType === 'approved') {
        $event = ['type'=>'approved','label'=>'Shop Approved','name'=>$row['name'],'time'=>$latestTime];
    } elseif ($latestType === 'rejected') {
        $event = ['type'=>'rejected','label'=>'Shop Rejected','name'=>$row['name'],'time'=>$latestTime];
    } else {
        if ($row['role'] === 'repairshop') {
            $event = ['type'=>'pending','label'=>'New Shop Registered','name'=>$row['name'],'time'=>$latestTime];
        } else {
            $event = ['type'=>'user','label'=>'New User Registered','name'=>$row['name'],'time'=>$latestTime];
        }
    }
    $recentActivity[] = $event;
}

// ── Monthly trend (last 6 months) ────────────────────────────
$trendRes = $conn->query("
    SELECT DATE_FORMAT(created_at,'%b') AS month,
           COUNT(*) AS total,
           SUM(approval_status='approved') AS approved
    FROM users
    WHERE role='repairshop'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
");
$trend = [];
while ($row = $trendRes->fetch_assoc()) {
    $trend[] = ['month'=>$row['month'],'total'=>(int)$row['total'],'approved'=>(int)$row['approved']];
}


// ── Daily registrations last 30 days ─────────────────
$dailyResult = $conn->query("
    SELECT 
        DATE(created_at) as reg_date,
        role,
        COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND role IN ('customer', 'repairshop')
    GROUP BY DATE(created_at), role
    ORDER BY reg_date ASC
");
$dailyRegistrations = [];
while ($row = $dailyResult->fetch_assoc()) {
    $dailyRegistrations[] = $row;
}
$conn->close();

echo json_encode([
    'totalShops'    => (int)$totalShops,
    'pendingShops'  => (int)$pendingShops,
    'approvedShops' => (int)$approvedShops,
    'rejectedShops' => (int)$rejectedShops,
    'totalUsers'    => (int)$totalUsers,
    'thisMonth'     => (int)$thisMonth,
    'approvalRate'  => (int)$approvalRate,
    'recentActivity'=> $recentActivity,
    'trend'         => $trend,
    'dailyRegistrations' => $dailyRegistrations,
]);