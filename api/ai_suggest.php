    <?php
    // ai_suggest.php
    // Place this file in: C:\XAMPP\htdocs\FIXITDAVAO\
    // Groq API endpoint for auto-suggesting repair services

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
    header('Content-Type: application/json');

    // --- DB CONNECTION ---
    $pdo = new PDO("mysql:host=localhost;dbname=fixitdavao", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- CONFIG ---
    define('GROQ_API_KEY', getenv('GROQ_API_KEY'));
    define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
    define('GROQ_MODEL', 'llama-3.3-70b-versatile'); // fast & free

    // --- INPUT VALIDATION ---
    $input = json_decode(file_get_contents('php://input'), true);

    $problem_description = trim($input['problem_description'] ?? '');
    $device_type         = trim($input['device_type'] ?? '');
    $customer_id         = intval($input['customer_id'] ?? 0); // 0 if not logged in or admin use

    if (empty($problem_description)) {
        echo json_encode(['success' => false, 'message' => 'Problem description is required.']);
        exit;
    }

    // --- FETCH AVAILABLE SERVICES FROM DB ---
    // Expected columns: id, service_name, description, price
    // Adjust column names if different in your DB
    // --- FETCH AVAILABLE SERVICES FROM DB ---
    $shop_id = intval($input['shop_id'] ?? 0);
    $services_list = [];
    try {
        if ($shop_id > 0) {
            $stmt = $pdo->prepare("SELECT id, service_name, service_fee FROM services WHERE user_id = ? ORDER BY service_name ASC");
            $stmt->execute([$shop_id]);
        } else {
            $stmt = $pdo->query("SELECT id, service_name, service_fee FROM services ORDER BY service_name ASC");
        }
        $services_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Could not fetch services: ' . $e->getMessage()]);
        exit;
    }

    // --- FETCH PAST REPAIR HISTORY (if customer is logged in) ---
    $past_repairs = [];
    if ($customer_id > 0) {
        try {
            // Adjust column names to match your bookings table
            $stmt = $pdo->prepare("
                SELECT b.service_id, s.service_name, b.issue_description, b.status
                FROM bookings b
                LEFT JOIN services s ON b.service_id = s.id
                WHERE b.customer_id = ?
                ORDER BY b.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$customer_id]);
            $past_repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Non-critical, just skip history
            $past_repairs = [];
        }
    }

    // --- BUILD PROMPT ---
    $services_text = '';
    foreach ($services_list as $svc) {
        $price_text = !empty($svc['service_fee']) ? " (PHP {$svc['service_fee']})" : '';
        $desc_text  = !empty($svc['description']) ? " - {$svc['description']}" : '';
        $services_text .= "- [ID:{$svc['id']}] {$svc['service_name']}{$price_text}{$desc_text}\n";
    }

    $history_text = '';
    if (!empty($past_repairs)) {
        $history_text = "\nCustomer's past repair history:\n";
        foreach ($past_repairs as $repair) {
            $history_text .= "- {$repair['service_name']}: {$repair['issue_description']} (Status: {$repair['status']})\n";
        }
    }

    $prompt = <<<PROMPT
    You are a helpful repair shop assistant for Fix It Davao, a repair booking platform in Davao City, Philippines.

    A customer (or staff) has described a device issue. Based on the problem description, device type, and available services, suggest the most relevant repair services.

    Device Type: {$device_type}
    Problem Description: {$problem_description}
    {$history_text}

    Available Services:
    {$services_text}

    Instructions:
    - Return ONLY a valid JSON array, no explanation, no markdown.
    - Suggest 2 to 4 services maximum that best match the issue.
    - Format: [{"id": 1, "service_name": "Screen Replacement", "reason": "short reason why this fits"}]
    - If no services match, return an empty array: []
    PROMPT;

    // --- CALL GROQ API ---
    $payload = json_encode([
        'model'       => GROQ_MODEL,
        'messages'    => [
            ['role' => 'system', 'content' => 'You are a repair shop assistant. Always respond with valid JSON only.'],
            ['role' => 'user',   'content' => $prompt]
        ],
        'temperature' => 0.3,
        'max_tokens'  => 500,
    ]);

    $ch = curl_init(GROQ_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response    = curl_exec($ch);
    $curl_error  = curl_error($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode(['success' => false, 'message' => 'cURL error: ' . $curl_error]);
        exit;
    }

    if ($http_status !== 200) {
    $groq_error = json_decode($response, true);
    $error_msg = $groq_error['error']['message'] ?? 'Unknown error';
    echo json_encode(['success' => false, 'message' => 'Groq error: ' . $error_msg, 'raw' => $response]);
    exit;
}

    $groq_data = json_decode($response, true);
    $raw_content = $groq_data['choices'][0]['message']['content'] ?? '[]';

    // Strip markdown fences if Groq wraps the JSON anyway
    $clean_content = preg_replace('/```json|```/', '', $raw_content);
    $clean_content = trim($clean_content);

    $suggestions = json_decode($clean_content, true);

    if (!is_array($suggestions)) {
        echo json_encode(['success' => false, 'message' => 'Could not parse AI response.', 'raw' => $raw_content]);
        exit;
    }

    echo json_encode(['success' => true, 'suggestions' => $suggestions]);