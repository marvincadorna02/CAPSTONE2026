<?php
session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'Not logged in']); exit(); }

$input  = json_decode(file_get_contents('php://input'), true);
$base64 = $input['image'] ?? '';

if (!$base64) { echo json_encode(['success'=>false,'error'=>'No image provided']); exit(); }

if (!preg_match('/^data:image\/(jpeg|png|webp);base64,/', $base64)) {
    echo json_encode(['success'=>false,'error'=>'Invalid image format']); exit();
}

if (strlen($base64) > 2800000) {
    echo json_encode(['success'=>false,'error'=>'Image too large. Max 2MB.']); exit();
}

// ── Same connection as your other files ──
$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['success'=>false,'error'=>'DB connection failed']); exit();
}

$stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
$stmt->bind_param("si", $base64, $_SESSION['user_id']);
$stmt->execute();
$conn->close();

echo json_encode(['success' => true]);