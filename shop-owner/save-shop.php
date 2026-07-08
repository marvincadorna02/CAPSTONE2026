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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'repairshop') {
    http_response_code(403);
    exit();
}

$host   = "localhost";
$dbname = "fixitdavao";
$dbuser = "root";
$dbpass = "";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

// ── Auto-create any missing columns ─────────────────────────
function addColumnIfMissing($conn, $table, $column, $definition) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
addColumnIfMissing($conn, 'users', 'shop_name',      "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'shop_location',  "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'contact_number', "VARCHAR(50) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'logo_url',       "VARCHAR(255) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'latitude',  "DECIMAL(10,8) DEFAULT NULL");
addColumnIfMissing($conn, 'users', 'longitude', "DECIMAL(11,8) DEFAULT NULL");

$userId  = (int) $_SESSION['user_id'];
$logoUrl = null;

// ── Handle logo upload ───────────────────────────────────────
if (!empty($_FILES['shop_logo']['name'])) {
    $file    = $_FILES['shop_logo'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if ($file['size'] > $maxSize) {
        die("Logo too large. Max 5MB.");
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowed)) {
        die("Invalid file type. Only JPG, PNG, GIF, WEBP allowed.");
    }

    $uploadDir = __DIR__ . '/../uploads/shop-logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Delete old logo if one exists
    $oldResult = $conn->query("SELECT logo_url FROM users WHERE id = $userId");
    if ($oldResult) {
        $oldRow = $oldResult->fetch_assoc();
        if (!empty($oldRow['logo_url'])) {
            $oldPath = __DIR__ . '/' . $oldRow['logo_url'];
            if (file_exists($oldPath)) unlink($oldPath);
        }
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'shop_' . $userId . '_' . time() . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        die("Failed to save logo.");
    }

    $logoUrl = '../uploads/shop-logos/' . $filename;
}

// ── Save basic shop info ─────────────────────────────────────
$shopName = $conn->real_escape_string(trim($_POST['shop_name']      ?? ''));
$shopLoc  = $conn->real_escape_string(trim($_POST['shop_location']  ?? ''));
$contact  = $conn->real_escape_string(trim($_POST['contact_number'] ?? ''));
$email    = $conn->real_escape_string(trim($_POST['email']          ?? ''));

if (!$shopName || !$shopLoc || !$contact || !$email) {
    die("Please fill in all required fields.");
}

$lat = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
$lng = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
$latSql = $lat !== null ? ", latitude=$lat" : '';
$lngSql = $lng !== null ? ", longitude=$lng" : '';

if ($logoUrl) {
    $safe = $conn->real_escape_string($logoUrl);
    $conn->query("UPDATE users SET name='$shopName', shop_name='$shopName', shop_location='$shopLoc', contact_number='$contact', email='$email', logo_url='$safe'$latSql$lngSql WHERE id=$userId");
} else {
    $conn->query("UPDATE users SET name='$shopName', shop_name='$shopName', shop_location='$shopLoc', contact_number='$contact', email='$email'$latSql$lngSql WHERE id=$userId");
}

// ── Save services ────────────────────────────────────────────
$serviceNames     = $_POST['service_name']     ?? [];
$serviceFees      = $_POST['service_fee']      ?? [];
$serviceDurations = $_POST['service_duration'] ?? [];

// Create services table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    service_fee DECIMAL(10,2) DEFAULT 0,
    service_duration VARCHAR(100) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("DELETE FROM services WHERE user_id = $userId");

foreach ($serviceNames as $i => $svcName) {
    $svcName = trim($svcName);
    if (!$svcName) continue;
    $n = $conn->real_escape_string($svcName);
    $f = (float)($serviceFees[$i] ?? 0);
    $d = $conn->real_escape_string(trim($serviceDurations[$i] ?? ''));
    $conn->query("INSERT INTO services (user_id, service_name, service_fee, service_duration) VALUES ($userId, '$n', $f, '$d')");
}

// ── Save operating hours ─────────────────────────────────────
// Create operating_hours table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS operating_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    day VARCHAR(20) NOT NULL,
    open_time TIME NOT NULL,
    close_time TIME NOT NULL
)");

$days    = $_POST['days'] ?? [];
$allDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

$conn->query("DELETE FROM operating_hours WHERE user_id = $userId");

foreach ($allDays as $day) {
    if (in_array($day, $days)) {
        $o = $conn->real_escape_string($_POST['open_'  . $day] ?? '09:00');
        $c = $conn->real_escape_string($_POST['close_' . $day] ?? '18:00');
        $conn->query("INSERT INTO operating_hours (user_id, day, open_time, close_time) VALUES ($userId, '$day', '$o', '$c')");
    }
}

$conn->close();
header("Location: shop-information.php?saved=1");
exit();