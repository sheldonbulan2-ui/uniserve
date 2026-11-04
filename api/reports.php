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

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    respond(false, "Method not allowed.", null, 405);
}

$queries = [
    "total_users" =>
        "SELECT COUNT(*) AS total FROM users",

    "total_businesses" =>
        "SELECT COUNT(*) AS total FROM businesses",

    "total_services" =>
        "SELECT COUNT(*) AS total FROM services",

    "total_service_requests" =>
        "SELECT COUNT(*) AS total FROM service_requests",

    "pending_requests" =>
        "SELECT COUNT(*) AS total
         FROM service_requests
         WHERE status = 'pending'",

    "total_transactions" =>
        "SELECT COUNT(*) AS total FROM transactions",

    "paid_transactions" =>
        "SELECT COUNT(*) AS total
         FROM transactions
         WHERE payment_status = 'paid'",

    "total_revenue" =>
        "SELECT COALESCE(SUM(amount), 0) AS total
         FROM transactions
         WHERE payment_status = 'paid'"
];

$report = [];

foreach ($queries as $key => $sql) {
    $result = $conn->query($sql);

    if (!$result) {
        respond(
            false,
            "Failed to generate report: " . $key,
            null,
            500
        );
    }

    $row = $result->fetch_assoc();
    $report[$key] = $row["total"];
}

respond(true, "Reports generated successfully.", $report);
?>