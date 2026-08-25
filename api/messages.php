<?php
// api/messages.php
// Customer <-> Shop Owner direct messaging.
// Actions (all POST, JSON body):
//   { action: 'list' }                          -> list of conversation threads for the logged-in user
//   { action: 'thread', other_id: <id> }         -> full message history with one counterpart, marks incoming as read
//   { action: 'send',   other_id: <id>, message: '...' } -> send a message

session_start();

// ── Session timeout (30 mins) ──
$timeout = 1800;
if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit();
}
$_SESSION['last_activity'] = time();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['customer', 'repairshop'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$myId   = (int) $_SESSION['user_id'];
$myRole = $_SESSION['role']; // 'customer' or 'repairshop'

$conn = new mysqli("localhost", "root", "", "fixitdavao");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit();
}
$conn->set_charset("utf8mb4");

// Auto-create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS messages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    shop_id      INT NOT NULL,
    customer_id  INT NOT NULL,
    sender_role  ENUM('customer','shop') NOT NULL,
    message      TEXT NOT NULL,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread (shop_id, customer_id, created_at)
)");

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';

// ── LIST: conversation threads for the logged-in user ──
if ($action === 'list') {
    if ($myRole === 'customer') {
        $stmt = $conn->prepare(
            "SELECT m.shop_id AS other_id,
                    COALESCE(u.shop_name, u.name) AS other_name,
                    u.logo_url AS other_avatar,
                    (SELECT message FROM messages m2 WHERE m2.shop_id = m.shop_id AND m2.customer_id = ?
                     ORDER BY m2.id DESC LIMIT 1) AS last_message,
                    (SELECT created_at FROM messages m2 WHERE m2.shop_id = m.shop_id AND m2.customer_id = ?
                     ORDER BY m2.id DESC LIMIT 1) AS last_time,
                    (SELECT COUNT(*) FROM messages m3 WHERE m3.shop_id = m.shop_id AND m3.customer_id = ?
                     AND m3.sender_role = 'shop' AND m3.is_read = 0) AS unread_count
             FROM messages m
             JOIN users u ON u.id = m.shop_id
             WHERE m.customer_id = ?
             GROUP BY m.shop_id
             ORDER BY last_time DESC"
        );
        $stmt->bind_param("iiii", $myId, $myId, $myId, $myId);
    } else {
        $stmt = $conn->prepare(
            "SELECT m.customer_id AS other_id,
                    u.name AS other_name,
                    u.profile_picture AS other_avatar,
                    (SELECT message FROM messages m2 WHERE m2.shop_id = ? AND m2.customer_id = m.customer_id
                     ORDER BY m2.id DESC LIMIT 1) AS last_message,
                    (SELECT created_at FROM messages m2 WHERE m2.shop_id = ? AND m2.customer_id = m.customer_id
                     ORDER BY m2.id DESC LIMIT 1) AS last_time,
                    (SELECT COUNT(*) FROM messages m3 WHERE m3.shop_id = ? AND m3.customer_id = m.customer_id
                     AND m3.sender_role = 'customer' AND m3.is_read = 0) AS unread_count
             FROM messages m
             JOIN users u ON u.id = m.customer_id
             WHERE m.shop_id = ?
             GROUP BY m.customer_id
             ORDER BY last_time DESC"
        );
        $stmt->bind_param("iiii", $myId, $myId, $myId, $myId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $threads = [];
    while ($row = $res->fetch_assoc()) {
        $row['other_id']      = (int)$row['other_id'];
        $row['unread_count']  = (int)$row['unread_count'];
        $threads[] = $row;
    }
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'threads' => $threads]);
    exit();
}

// ── THREAD: full history with one counterpart ──
if ($action === 'thread') {
    $otherId = (int)($input['other_id'] ?? 0);
    if ($otherId <= 0) {
        echo json_encode(['success' => false, 'message' => 'other_id is required.']);
        exit();
    }

    if ($myRole === 'customer') {
        $shopId = $otherId; $customerId = $myId;
    } else {
        $shopId = $myId; $customerId = $otherId;
    }

    // Verify counterpart exists and has the expected role
    $checkStmt = $conn->prepare("SELECT role, COALESCE(shop_name, name) AS display_name, logo_url, profile_picture FROM users WHERE id = ?");
    $checkStmt->bind_param("i", $otherId);
    $checkStmt->execute();
    $other = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    if (!$other) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit();
    }

    // Logged-in user's own avatar (shop logo for shop owner, profile picture for customer)
    $meStmt = $conn->prepare("SELECT logo_url, profile_picture FROM users WHERE id = ?");
    $meStmt->bind_param("i", $myId);
    $meStmt->execute();
    $me = $meStmt->get_result()->fetch_assoc();
    $meStmt->close();

    $stmt = $conn->prepare(
        "SELECT id, sender_role, message, created_at, is_read
         FROM messages WHERE shop_id = ? AND customer_id = ? ORDER BY id ASC"
    );
    $stmt->bind_param("ii", $shopId, $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    $msgs = [];
    while ($row = $res->fetch_assoc()) {
        $msgs[] = $row;
    }
    $stmt->close();

    // Mark incoming messages (from the other party) as read
    $incomingRole = $myRole === 'customer' ? 'shop' : 'customer';
    $markStmt = $conn->prepare(
        "UPDATE messages SET is_read = 1
         WHERE shop_id = ? AND customer_id = ? AND sender_role = ? AND is_read = 0"
    );
    $markStmt->bind_param("iis", $shopId, $customerId, $incomingRole);
    $markStmt->execute();
    $markStmt->close();

    $conn->close();
    echo json_encode([
        'success'    => true,
        'messages'   => $msgs,
        'other_name' => $other['display_name'],
        'other_avatar' => $other['logo_url'] ?: $other['profile_picture'],
        'my_avatar'    => ($me['logo_url'] ?? '') ?: ($me['profile_picture'] ?? ''),
    ]);
    exit();
}

// ── SEND: post a new message ──
if ($action === 'send') {
    $otherId = (int)($input['other_id'] ?? 0);
    $text    = trim($input['message'] ?? '');

    if ($otherId <= 0 || $text === '') {
        echo json_encode(['success' => false, 'message' => 'other_id and message are required.']);
        exit();
    }
    if (mb_strlen($text) > 1000) {
        echo json_encode(['success' => false, 'message' => 'Message is too long.']);
        exit();
    }

    if ($myRole === 'customer') {
        $shopId = $otherId; $customerId = $myId; $senderRole = 'customer';
    } else {
        $shopId = $myId; $customerId = $otherId; $senderRole = 'shop';
    }

    $stmt = $conn->prepare(
        "INSERT INTO messages (shop_id, customer_id, sender_role, message) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("iiss", $shopId, $customerId, $senderRole, $text);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'id' => $newId]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);