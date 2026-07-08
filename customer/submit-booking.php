<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']); exit();
}

// ── Auto-create bookings table ───────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS bookings (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    shop_id          INT NOT NULL,
    customer_id      INT NOT NULL,
    service_id       INT DEFAULT NULL,
    service_name     VARCHAR(255) DEFAULT '',
    customer_name    VARCHAR(255) NOT NULL,
    customer_contact VARCHAR(50)  NOT NULL,
    device_type      VARCHAR(100) DEFAULT '',
    device_brand     VARCHAR(150) DEFAULT '',
    problem_description TEXT,
    booking_date     DATE NOT NULL,
    booking_time     TIME NOT NULL,
    status           ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes            TEXT DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ── Collect & sanitize inputs ────────────────────────────────
$shopId      = (int)($_POST['shop_id']             ?? 0);
$customerId  = (int)$_SESSION['user_id'];
$serviceId   = (int)($_POST['service_id']          ?? 0) ?: null;
$custName    = trim($_POST['customer_name']         ?? '');
$custContact = trim($_POST['customer_contact']      ?? '');
$deviceType  = trim($_POST['device_type']           ?? '');
$deviceBrand = trim($_POST['device_brand']          ?? '');
$problemDesc = trim($_POST['problem_description']   ?? '');
$bookingDate = trim($_POST['booking_date']          ?? '');
$bookingTime = trim($_POST['booking_time']          ?? '');

// ── Validate required fields ─────────────────────────────────
if (!$shopId || !$custName || !$custContact || !$deviceType || !$problemDesc || !$bookingDate || !$bookingTime) {
    echo json_encode(['error' => 'Please fill in all required fields.']); exit();
}

// Validate date is not in the past
if (strtotime($bookingDate) < strtotime(date('Y-m-d'))) {
    echo json_encode(['error' => 'Please select a future date.']); exit();
}

// ── Verify shop is still active ──────────────────────────────
$shopCheck = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'repairshop' AND status = 'active' AND approval_status = 'approved'");
$shopCheck->bind_param("i", $shopId);
$shopCheck->execute();
if (!$shopCheck->get_result()->fetch_assoc()) {
    $shopCheck->close(); $conn->close();
    echo json_encode(['error' => 'Shop not found or unavailable.']); exit();
}
$shopCheck->close();

// ── Look up service name if service_id provided ──────────────
$serviceName = '';
if ($serviceId) {
    $sr = $conn->prepare("SELECT service_name FROM services WHERE id = ? AND user_id = ?");
    $sr->bind_param("ii", $serviceId, $shopId);
    $sr->execute();
    $sRow = $sr->get_result()->fetch_assoc();
    $sr->close();
    $serviceName = $sRow['service_name'] ?? '';
}

// ── Insert booking ───────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO bookings
        (shop_id, customer_id, service_id, service_name,
         customer_name, customer_contact,
         device_type, device_brand, problem_description,
         booking_date, booking_time, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");

// i  i  i  s           s           s            s            s            s                s             s
$stmt->bind_param(
    "isissssssss",   // ← shop_id=i, customer_id=i, service_id=s (allows null), rest=s
    $shopId,
    $customerId,
    $serviceId,
    $serviceName,
    $custName,
    $custContact,
    $deviceType,
    $deviceBrand,
    $problemDesc,
    $bookingDate,
    $bookingTime
);

if ($stmt->execute()) {
    $bookingId = $conn->insert_id;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'booking_id' => $bookingId]);
} else {
    $err = $stmt->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['error' => 'Failed to save booking: ' . $err]);
}