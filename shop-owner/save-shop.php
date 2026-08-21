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

if (!isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("Your session has expired or this page was loaded before an update. Please refresh the page (Ctrl+F5) and try again.");
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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
    // Map MIME -> extension: never trust the client-sent filename/extension.
    // The extension we actually save with is derived from the detected type
    // below, so a mislabeled "photo.php" can't slip through as executable.
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if ($file['size'] > $maxSize) {
        die("Logo too large. Max 5MB.");
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mimeType]) || @getimagesize($file['tmp_name']) === false) {
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

    $ext      = $allowed[$mimeType];
    $filename = 'shop_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        die("Failed to save logo.");
    }

    $logoUrl = '../uploads/shop-logos/' . $filename;
}

// ── Save basic shop info ─────────────────────────────────────
$shopName = trim($_POST['shop_name']      ?? '');
$shopLoc  = trim($_POST['shop_location']  ?? '');
$contact  = trim($_POST['contact_number'] ?? '');
$email    = trim($_POST['email']          ?? '');

// ── Validate required fields ─────────────────────────────────
if (!$shopName || !$shopLoc || !$contact || !$email) {
    die("Please fill in all required fields.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Please enter a valid email address.");
}

// PH mobile/landline: digits, spaces, +, - only, 7-15 digits
if (!preg_match('/^[0-9+\-\s]{7,15}$/', $contact)) {
    die("Please enter a valid contact number.");
}

// latitude/longitude are optional -- only touch them if the user actually sent coords,
// so bind_param never has to deal with binding NULL into a "d" (double) slot
$hasCoords = !empty($_POST['latitude']) && !empty($_POST['longitude']);
$lat = $hasCoords ? (float)$_POST['latitude']  : 0;
$lng = $hasCoords ? (float)$_POST['longitude'] : 0;

$coordSql = $hasCoords ? ", latitude=?, longitude=?" : "";

if ($logoUrl) {
    $sql = "UPDATE users SET name=?, shop_name=?, shop_location=?, contact_number=?, email=?, logo_url=?$coordSql WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($hasCoords) {
        $stmt->bind_param("ssssssddi", $shopName, $shopName, $shopLoc, $contact, $email, $logoUrl, $lat, $lng, $userId);
    } else {
        $stmt->bind_param("ssssssi", $shopName, $shopName, $shopLoc, $contact, $email, $logoUrl, $userId);
    }
} else {
    $sql = "UPDATE users SET name=?, shop_name=?, shop_location=?, contact_number=?, email=?$coordSql WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($hasCoords) {
        $stmt->bind_param("sssssddi", $shopName, $shopName, $shopLoc, $contact, $email, $lat, $lng, $userId);
    } else {
        $stmt->bind_param("sssssi", $shopName, $shopName, $shopLoc, $contact, $email, $userId);
    }
}
$stmt->execute();
$stmt->close();

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