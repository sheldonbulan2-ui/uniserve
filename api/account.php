<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config.php";

function respond($success, $message, $data = null, $status = 200) {
    http_response_code($status);
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
    $userId = filter_var(
        $_GET["user_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$userId) {
        respond(false, "Valid user_id is required.", null, 400);
    }

    $stmt = $conn->prepare(
        "SELECT user_id, full_name, email, role
         FROM users
         WHERE user_id = ?"
    );

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        respond(false, "Account not found.", null, 404);
    }

    respond(
        true,
        "Account retrieved successfully.",
        $result->fetch_assoc()
    );
}

if ($method !== "POST") {
    header("Allow: GET, POST");
    respond(false, "Method not allowed.", null, 405);
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    $input = $_POST;
}

$action = $input["action"] ?? "";

if ($action === "update") {
    $userId = filter_var(
        $input["user_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $fullName = trim($input["full_name"] ?? "");
    $email = trim($input["email"] ?? "");

    if (
        !$userId ||
        $fullName === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        respond(false, "Valid user ID, full name, and email are required.", null, 400);
    }

    // Check whether another user already uses this email.
    $check = $conn->prepare(
        "SELECT user_id FROM users
         WHERE email = ? AND user_id != ?"
    );

    $check->bind_param("si", $email, $userId);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        respond(false, "Email is already in use.", null, 409);
    }

    $stmt = $conn->prepare(
        "UPDATE users
         SET full_name = ?, email = ?
         WHERE user_id = ?"
    );

    $stmt->bind_param("ssi", $fullName, $email, $userId);

    if (!$stmt->execute()) {
        respond(false, "Failed to update account.", null, 500);
    }

    if ($stmt->affected_rows === 0) {
        $checkUser = $conn->prepare(
            "SELECT user_id FROM users WHERE user_id = ?"
        );
        $checkUser->bind_param("i", $userId);
        $checkUser->execute();

        if ($checkUser->get_result()->num_rows === 0) {
            respond(false, "Account not found.", null, 404);
        }
    }

    respond(true, "Account updated successfully.");
}

respond(false, "Invalid action. Use update.", null, 400);
?>