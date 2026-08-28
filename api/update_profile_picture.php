<?php
session_start();
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_OFF); // return errors as JSON, never as an HTML fatal
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'Not logged in']); exit(); }

$input  = json_decode(file_get_contents('php://input'), true);
$base64 = $input['image'] ?? '';

if (!$base64) { echo json_encode(['success'=>false,'error'=>'No image provided']); exit(); }

if (!preg_match('/^data:image\/(jpeg|png|webp);base64,(.+)$/', $base64, $matches)) {
    echo json_encode(['success'=>false,'error'=>'Invalid image format']); exit();
}

if (strlen($base64) > 2800000) {
    echo json_encode(['success'=>false,'error'=>'Image too large. Max 2MB.']); exit();
}

// ── Decode and verify the bytes are actually a real image ──
// (the regex above only checks the label a client SENT — it doesn't prove
// the payload after the comma is really an image, so we decode it and
// ask PHP/GD to parse it as one)
$decoded = base64_decode($matches[2], true);
if ($decoded === false) {
    echo json_encode(['success'=>false,'error'=>'Corrupted image data']); exit();
}

$imageInfo = @getimagesizefromstring($decoded);
if ($imageInfo === false) {
    echo json_encode(['success'=>false,'error'=>'File is not a valid image']); exit();
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($imageInfo['mime'], $allowedMimes, true)) {
    echo json_encode(['success'=>false,'error'=>'Unsupported image type']); exit();
}

// ── Same connection as your other files ──    
$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['success'=>false,'error'=>'DB connection failed']); exit();
}

// ── Reject payloads bigger than the MySQL packet limit ──
$limitRow  = $conn->query("SELECT @@max_allowed_packet AS p");
$maxPacket = $limitRow ? (int)$limitRow->fetch_assoc()['p'] : 1048576;
if (strlen($base64) > $maxPacket - 8192) {
    $conn->close();
    echo json_encode(['success'=>false,'error'=>'Image too large for the server. Please pick a smaller photo.']);
    exit();
}

$stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
$stmt->bind_param("si", $base64, $_SESSION['user_id']);
if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close(); $conn->close();
    echo json_encode(['success'=>false,'error'=>'Could not save image: ' . $err]);
    exit();
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true]);