<?php
// ── Shared account endpoint: change password + edit profile ──
// Session-authed (any logged-in user), CSRF-checked, JSON in/out.
session_start();
require_once __DIR__ . '/../includes/guard.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']); exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

// ── CSRF ──
if (!isset($input['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request. Please refresh the page.']); exit();
}

$userId = (int) $_SESSION['user_id'];
$action = $input['action'] ?? '';

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']); exit();
}
$conn->set_charset("utf8mb4");

// ─────────────────────────────────────────────────────────────
if ($action === 'change_password') {
    $current = (string) ($input['current_password'] ?? '');
    $new     = (string) ($input['new_password'] ?? '');
    $confirm = (string) ($input['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        echo json_encode(['success' => false, 'error' => 'All password fields are required.']); exit();
    }

    // Load current hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($current, $row['password'])) {
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']); exit();
    }
    if (strlen($new) < 8 || strlen($new) > 16) {
        echo json_encode(['success' => false, 'error' => 'Password must be 8 to 16 characters long.']); exit();
    }
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{8,16}$/', $new)) {
        echo json_encode(['success' => false, 'error' => 'Password must include an uppercase letter, lowercase letter, number, and special character.']); exit();
    }
    if ($new !== $confirm) {
        echo json_encode(['success' => false, 'error' => 'New passwords do not match.']); exit();
    }
    if (password_verify($new, $row['password'])) {
        echo json_encode(['success' => false, 'error' => 'New password must be different from your current password.']); exit();
    }

    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $userId);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'message' => 'Password changed successfully.']); exit();
}

// ─────────────────────────────────────────────────────────────
if ($action === 'update_profile') {
    $name    = trim((string) ($input['name'] ?? ''));
    $contact = trim((string) ($input['contact_number'] ?? ''));
    $email   = trim((string) ($input['email'] ?? ''));

    if ($name === '') {
        echo json_encode(['success' => false, 'error' => 'Name cannot be empty.']); exit();
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']); exit();
    }

    // Email uniqueness (exclude self)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'That email is already used by another account.']); exit();
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, contact_number = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $email, $contact, $userId);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    // Reflect in session so UI updates without re-login
    $_SESSION['name']  = $name;
    $_SESSION['email'] = $email;

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.', 'name' => $name, 'email' => $email]); exit();
}

$conn->close();
echo json_encode(['success' => false, 'error' => 'Unknown action.']);
