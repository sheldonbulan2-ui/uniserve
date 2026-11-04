<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);
    exit;
}

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
    ]);
    exit;
}

try {
    // Verify the administrator's credentials.
    $stmt = $conn->prepare(
        "SELECT user_id, full_name, email, password, role
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (
        !$user ||
        !password_verify($password, $user["password"]) ||
        $user["role"] !== "admin"
    ) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Administrator access denied."
        ]);
        exit;
    }

    // Count existing records without changing the database.
    $tables = [
        "users" => "total_users",
        "businesses" => "total_businesses",
        "services" => "total_services",
        "service_requests" => "total_requests",
        "transactions" => "total_transactions",
        "categories" => "total_categories"
    ];

    $stats = [];

    foreach ($tables as $table => $key) {
        // Table names come only from the fixed list above.
        $query = "SELECT COUNT(*) AS total FROM $table";
        $countResult = $conn->query($query);

        if (!$countResult) {
            throw new Exception("Unable to retrieve dashboard statistics.");
        }

        $row = $countResult->fetch_assoc();
        $stats[$key] = (int) $row["total"];
        $countResult->free();
    }

    echo json_encode([
        "success" => true,
        "message" => "Dashboard statistics loaded.",
        "user" => [
            "user_id" => (int) $user["user_id"],
            "full_name" => $user["full_name"],
            "role" => $user["role"]
        ],
        "stats" => $stats
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to load dashboard statistics."
    ]);
}
?>
