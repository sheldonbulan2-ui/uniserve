<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__. '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'POST method required.'
    ]);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($fullName === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required.'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters.'
    ]);
    exit;
}

try {
    $check = $conn->prepare(
        'SELECT user_id FROM users WHERE email = ? LIMIT 1'
    );
    $check->bind_param('s', $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Email is already registered.'
        ]);
        $check->close();
        exit;
    }

    $check->close();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role = 'customer';

    $stmt = $conn->prepare(
        'INSERT INTO users (full_name, email, password, role)
         VALUES (?, ?, ?, ?)'
    );

    $stmt->bind_param(
        'ssss',
        $fullName,
        $email,
        $hashedPassword,
        $role
    );

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful. You can now log in.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed.'
        ]);
    }

    $stmt->close();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A server error occurred.'
    ]);
}
?>