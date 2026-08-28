<?php
// ── Shop-owner analytics stats (own bookings/revenue/ratings) ──
session_start();
require_once __DIR__ . '/../includes/guard.php';

$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    http_response_code(401);
    echo json_encode(['error' => 'Session expired']); exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'repairshop') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']); exit();
}

header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) { echo json_encode(['error' => 'Database connection failed']); exit(); }
$conn->set_charset("utf8mb4");

$shopId = (int) $_SESSION['user_id'];

// ── Period filter (quick range) ───────────────────────────────
$range = $_GET['range'] ?? 'all';
if (!in_array($range, ['month','3m','year','all'], true)) $range = 'all';

$periodWhere = function ($col) use ($range) {
    switch ($range) {
        case 'month': return " AND $col >= DATE_FORMAT(CURDATE(),'%Y-%m-01')";
        case '3m':    return " AND $col >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 2 MONTH)";
        case 'year':  return " AND $col >= DATE_FORMAT(CURDATE(),'%Y-01-01')";
        default:      return '';
    }
};
$w  = $periodWhere('booking_date');
$wb = $periodWhere('b.booking_date');

// ── Status counts ─────────────────────────────────────────────
$counts = ['pending'=>0,'confirmed'=>0,'completed'=>0,'paid'=>0,'claimed'=>0,'cancelled'=>0,'no_show'=>0];
$stmt = $conn->prepare("SELECT status, COUNT(*) AS c FROM bookings WHERE shop_id = ?{$w} GROUP BY status");
$stmt->bind_param("i", $shopId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) { if (isset($counts[$r['status']])) $counts[$r['status']] = (int) $r['c']; }
$stmt->close();

$totalBookings = array_sum($counts);
$finished      = $counts['completed'] + $counts['paid'] + $counts['claimed']; // work rendered

// ── Total revenue (completed bookings × service fee) ──────────
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(s.service_fee), 0) AS revenue
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.shop_id = ? AND b.status IN ('completed','paid','claimed'){$wb}
");
$stmt->bind_param("i", $shopId);
$stmt->execute();
$totalRevenue = (float) $stmt->get_result()->fetch_assoc()['revenue'];
$stmt->close();

// ── Average rating + review count (period-filtered) ───────────
$wr = $periodWhere('created_at');
$stmt = $conn->prepare("SELECT COALESCE(AVG(rating),0) AS avg_rating, COUNT(*) AS c FROM reviews WHERE shop_id = ?{$wr}");
$stmt->bind_param("i", $shopId);
$stmt->execute();
$rev = $stmt->get_result()->fetch_assoc();
$avgRating   = round((float) $rev['avg_rating'], 1);
$reviewCount = (int) $rev['c'];
$stmt->close();

// ── Subscription status ───────────────────────────────────────
$conn->query("UPDATE shop_subscriptions SET status='expired' WHERE shop_id={$shopId} AND status='active' AND end_date < CURDATE()");
$stmt = $conn->prepare("SELECT status, end_date FROM shop_subscriptions WHERE shop_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $shopId);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();
$subStatus  = $sub['status'] ?? 'none';
$subEndDate = $sub['end_date'] ?? null;

// ── Range-aware scaffold for trend charts ─────────────────────
$buckets = [];
if ($range === 'month') {
    $trendFmt = '%Y-%m-%d';
    $trendStart = date('Y-m-01');
    for ($ts = strtotime($trendStart); $ts <= time(); $ts = strtotime('+1 day', $ts)) {
        $buckets[date('Y-m-d', $ts)] = ['label' => date('j', $ts), 'bookings' => 0, 'revenue' => 0.0];
    }
    $trendLabel = 'This Month'; $trendGran = 'day';
} elseif ($range === '3m') {
    $trendFmt = '%Y-%m';
    $trendStart = date('Y-m-01', strtotime(date('Y-m-01') . ' -2 month'));
    for ($i = 2; $i >= 0; $i--) { $ts = strtotime(date('Y-m-01') . " -$i month"); $buckets[date('Y-m', $ts)] = ['label' => date('M', $ts), 'bookings' => 0, 'revenue' => 0.0]; }
    $trendLabel = 'Last 3 Months'; $trendGran = 'month';
} elseif ($range === 'year') {
    $trendFmt = '%Y-%m';
    $trendStart = date('Y-01-01');
    $cm = (int) date('n');
    for ($m = 1; $m <= $cm; $m++) { $ts = mktime(0, 0, 0, $m, 1, (int) date('Y')); $buckets[date('Y-m', $ts)] = ['label' => date('M', $ts), 'bookings' => 0, 'revenue' => 0.0]; }
    $trendLabel = 'This Year'; $trendGran = 'month';
} else {
    $trendFmt = '%Y-%m';
    $trendStart = date('Y-m-01', strtotime(date('Y-m-01') . ' -5 month'));
    for ($i = 5; $i >= 0; $i--) { $ts = strtotime(date('Y-m-01') . " -$i month"); $buckets[date('Y-m', $ts)] = ['label' => date('M', $ts), 'bookings' => 0, 'revenue' => 0.0]; }
    $trendLabel = 'Last 6 Months'; $trendGran = 'month';
}

// Bookings per bucket (all statuses)
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(booking_date, ?) AS grp, COUNT(*) AS c
    FROM bookings
    WHERE shop_id = ? AND booking_date >= ?
    GROUP BY grp
");
$stmt->bind_param("sis", $trendFmt, $shopId, $trendStart);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) { if (isset($buckets[$r['grp']])) $buckets[$r['grp']]['bookings'] = (int) $r['c']; }
$stmt->close();

// Revenue per bucket (finished only)
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(b.booking_date, ?) AS grp, COALESCE(SUM(s.service_fee),0) AS revenue
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.shop_id = ? AND b.status IN ('completed','paid','claimed') AND b.booking_date >= ?
    GROUP BY grp
");
$stmt->bind_param("sis", $trendFmt, $shopId, $trendStart);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) { if (isset($buckets[$r['grp']])) $buckets[$r['grp']]['revenue'] = (float) $r['revenue']; }
$stmt->close();

$conn->close();

$trend = array_values($buckets);

echo json_encode([
    'totalBookings' => $totalBookings,
    'completed'     => $finished,
    'pending'       => $counts['pending'],
    'confirmed'     => $counts['confirmed'],
    'paid'          => $counts['paid'],
    'claimed'       => $counts['claimed'],
    'cancelled'     => $counts['cancelled'],
    'noShow'        => $counts['no_show'],
    'totalRevenue'  => $totalRevenue,
    'avgRating'     => $avgRating,
    'reviewCount'   => $reviewCount,
    'subStatus'     => $subStatus,
    'subEndDate'    => $subEndDate,
    'statusBreakdown' => [
        'pending'   => $counts['pending'],
        'confirmed' => $counts['confirmed'],
        'completed' => $counts['completed'],
        'paid'      => $counts['paid'],
        'claimed'   => $counts['claimed'],
        'cancelled' => $counts['cancelled'],
        'no_show'   => $counts['no_show'],
    ],
    'trendLabels'   => array_column($trend, 'label'),
    'trendBookings' => array_column($trend, 'bookings'),
    'trendRevenue'  => array_map(fn($m) => round($m['revenue'], 2), $trend),
    'trendLabel'    => $trendLabel,
    'trendGran'     => $trendGran,
]);
