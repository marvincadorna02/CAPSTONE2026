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

// ── Auth: allow admins AND logged-in customers/repairshops ──
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$isAdmin        = $_SESSION['role'] === 'admin';
$isCustomerMode = isset($_GET['customer']) && $_GET['customer'] == '1';

if (!$isAdmin && !$isCustomerMode) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

// ── Auto-expire subscriptions past end_date (runs on every load) ──
$conn->query("UPDATE shop_subscriptions SET status='expired' WHERE status='active' AND end_date < CURDATE()");

function addColumnIfMissing($conn, $table, $column, $definition) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
addColumnIfMissing($conn, 'users', 'status',          "ENUM('active','suspended') NOT NULL DEFAULT 'active'");
addColumnIfMissing($conn, 'users', 'suspend_reason',  "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'approval_status', "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
addColumnIfMissing($conn, 'users', 'logo_url',        "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'shop_name',       "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'shop_location',   "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'contact_number',  "VARCHAR(50) DEFAULT NULL");

// ── Auto-create reviews table if not exists ──
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT NOT NULL UNIQUE,
    shop_id     INT NOT NULL,
    customer_id INT NOT NULL,
    rating      TINYINT NOT NULL,
    comment     TEXT DEFAULT NULL,
    reply       TEXT DEFAULT NULL,
    replied_at  DATETIME DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ── Query ──
if ($isCustomerMode) {
    $sql = "
        SELECT u.id, u.name, u.shop_name, u.email, u.status, u.suspend_reason,
               u.logo_url, u.shop_location, u.contact_number,
               u.latitude, u.longitude,
               u.created_at, u.approved_at
        FROM users u
        WHERE u.role = 'repairshop'
          AND u.approval_status = 'approved'
          AND u.status = 'active'
          AND EXISTS (
              SELECT 1 FROM shop_subscriptions ss
              WHERE ss.shop_id = u.id
              AND ss.status = 'active'
              AND ss.end_date >= CURDATE()
          )
        ORDER BY u.created_at DESC
    ";
} else {
    $sql = "
        SELECT id, name, shop_name, email, status, suspend_reason,
       logo_url, shop_location, contact_number,
       created_at, approved_at
        FROM users
        WHERE role = 'repairshop' AND approval_status = 'approved'
        ORDER BY created_at DESC
    ";
}

$result = $conn->query($sql);

// ── HTTPS detection (aware of reverse proxies like ngrok) ──
// Direct HTTPS check fails behind ngrok because the tunnel forwards plain
// HTTP to Apache — so we also check the X-Forwarded-Proto header ngrok sets.
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$baseUrl = ($isHttps ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

$shops = [];
while ($row = $result->fetch_assoc()) {
    $row['location'] = !empty($row['shop_location'])  ? $row['shop_location']  : '—';
    $row['contact']  = !empty($row['contact_number']) ? $row['contact_number'] : '—';

    $row['name'] = !empty($row['shop_name']) ? $row['shop_name'] : $row['name'];

    // Make logo_url absolute
    if (!empty($row['logo_url'])) {
        $row['logo_url'] = $baseUrl . $row['logo_url'];
    }

    $sid = (int)$row['id'];

    // ── Fetch services ──
    $svcStmt = $conn->prepare("SELECT service_name, service_fee FROM services WHERE user_id = ? ORDER BY id ASC");
    $svcStmt->bind_param("i", $sid);
    $svcStmt->execute();
    $svcResult = $svcStmt->get_result();
    $row['services'] = [];
    if ($svcResult) {
        while ($svc = $svcResult->fetch_assoc()) {
            $row['services'][] = $svc;
        }
    }
    $svcStmt->close();

    // ── Fetch operating hours ──
    $row['operating_hours'] = (object)[];
    $row['open_days']       = [];

    $hStmt = $conn->prepare(
        "SELECT day, open_time, close_time
         FROM operating_hours
         WHERE user_id = ?
         ORDER BY FIELD(day,'monday','tuesday','wednesday','thursday','friday','saturday','sunday')"
    );
    $hStmt->bind_param("i", $sid);
    $hStmt->execute();
    $hResult = $hStmt->get_result();

    if ($hResult && $hResult->num_rows > 0) {
        $hoursMap = [];
        $openDays = [];
        while ($h = $hResult->fetch_assoc()) {
            $day = strtolower($h['day']);
            $hoursMap[$day] = [
                'open'  => substr($h['open_time'],  0, 5),
                'close' => substr($h['close_time'], 0, 5),
            ];
            $openDays[] = $day;
        }
        $row['operating_hours'] = $hoursMap;
        $row['open_days']       = $openDays;
    }
    $hStmt->close();

    // ── Fetch avg rating & review count ──
    $row['avg_rating']     = 0;
    $row['review_count']   = 0;
    $row['recent_reviews'] = [];

    $ratingStmt = $conn->prepare(
        "SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS review_count
         FROM reviews WHERE shop_id = ?"
    );
    $ratingStmt->bind_param("i", $sid);
    $ratingStmt->execute();
    $ratingResult = $ratingStmt->get_result();
    if ($ratingResult && $ratingRow = $ratingResult->fetch_assoc()) {
        $row['avg_rating']   = $ratingRow['avg_rating']   ? (float)$ratingRow['avg_rating']   : 0;
        $row['review_count'] = $ratingRow['review_count'] ? (int)$ratingRow['review_count']   : 0;
    }
    $ratingStmt->close();

    // ── Fetch latest 5 reviews ──
    $rvStmt = $conn->prepare(
        "SELECT rv.id, rv.rating, rv.comment, rv.reply, rv.replied_at, rv.created_at,
                u.name AS customer_name
         FROM reviews rv
         LEFT JOIN users u ON u.id = rv.customer_id
         WHERE rv.shop_id = ?
         ORDER BY rv.created_at DESC
         LIMIT 5"
    );
    $rvStmt->bind_param("i", $sid);
    $rvStmt->execute();
    $rvResult = $rvStmt->get_result();
    if ($rvResult) {
        while ($rvRow = $rvResult->fetch_assoc()) {
            $row['recent_reviews'][] = $rvRow;
        }
    }
    $rvStmt->close();

    $shops[] = $row;
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($shops);