<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required.']);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? 'customer');
$businessName = trim($_POST['business_name'] ?? '');
$deviceInfo = trim($_POST['device_info'] ?? 'Android App');

if (!in_array($role, ['customer', 'business_owner'], true)) {
    $role = 'customer';
}

if ($fullName === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Full name, email, and password are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

try {
    // Check if email exists
    $checkStmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This email address is already registered.']);
        exit;
    }
    $checkStmt->close();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $conn->begin_transaction();

    $stmt = $conn->prepare('INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $fullName, $email, $hashedPassword, $role);
    $stmt->execute();
    $userId = (int) $conn->insert_id;
    $stmt->close();

    $businessId = null;
    if ($role === 'business_owner') {
        $bName = $businessName !== '' ? $businessName : ($fullName . "'s Services");
        $bStmt = $conn->prepare('INSERT INTO businesses (owner_id, business_name, status) VALUES (?, ?, ?)');
        $pending = 'pending';
        $bStmt->bind_param('iss', $userId, $bName, $pending);
        $bStmt->execute();
        $businessId = (int) $conn->insert_id;
        $bStmt->close();
    }

    // Generate Token
    $token = bin2hex(random_bytes(32));
    $tokenStmt = $conn->prepare('INSERT INTO api_tokens (user_id, token, device_info) VALUES (?, ?, ?)');
    $tokenStmt->bind_param('iss', $userId, $token, $deviceInfo);
    $tokenStmt->execute();
    $tokenStmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful.',
        'data' => [
            'token' => $token,
            'user' => [
                'user_id' => $userId,
                'full_name' => $fullName,
                'email' => $email,
                'role' => $role,
                'business_id' => $businessId,
                'business_name' => $businessName
            ]
        ]
    ]);
} catch (Exception $e) {
    if (isset($conn)) { $conn->rollback(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed. ' . $e->getMessage()]);
}
