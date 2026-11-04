<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$deviceInfo = trim($_POST['device_info'] ?? 'Android App');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

try {
    $stmt = $conn->prepare(
        'SELECT user_id, full_name, email, password, role FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Generate persistent API Token
    $token = bin2hex(random_bytes(32));
    $tokenStmt = $conn->prepare(
        'INSERT INTO api_tokens (user_id, token, device_info) VALUES (?, ?, ?)'
    );
    $tokenStmt->bind_param('iss', $user['user_id'], $token, $deviceInfo);
    $tokenStmt->execute();
    $tokenStmt->close();

    // If business owner, also fetch their business_id
    $businessId = null;
    $businessName = null;
    if ($user['role'] === 'business_owner') {
        $bStmt = $conn->prepare('SELECT business_id, business_name FROM businesses WHERE owner_id = ? LIMIT 1');
        $bStmt->bind_param('i', $user['user_id']);
        $bStmt->execute();
        $bRes = $bStmt->get_result();
        if ($bRow = $bRes->fetch_assoc()) {
            $businessId = (int)$bRow['business_id'];
            $businessName = $bRow['business_name'];
        }
        $bStmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'data' => [
            'token' => $token,
            'user' => [
                'user_id' => (int) $user['user_id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'business_id' => $businessId,
                'business_name' => $businessName
            ]
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Authentication failed. ' . $e->getMessage()]);
}
