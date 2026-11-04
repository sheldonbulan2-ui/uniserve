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
    $sql = "SELECT
                request_id,
                customer_id,
                service_id,
                request_date,
                preferred_date,
                quantity,
                total_amount,
                status,
                notes,
                created_at
            FROM service_requests
            ORDER BY request_id DESC";

    $result = $conn->query($sql);

    if (!$result) {
        respond(false, "Failed to retrieve service requests.", null, 500);
    }

    $requests = [];

    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    respond(true, "Service requests retrieved successfully.", $requests);
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

if ($action === "create") {
    $customerId = filter_var(
        $input["customer_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $serviceId = filter_var(
        $input["service_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $quantity = filter_var(
        $input["quantity"] ?? null,
        FILTER_VALIDATE_INT
    );

    $preferredDate = trim($input["preferred_date"] ?? "");
    $notes = trim($input["notes"] ?? "");

    if (!$customerId || !$serviceId || !$quantity || $quantity < 1) {
        respond(
            false,
            "Valid customer ID, service ID, and quantity are required.",
            null,
            400
        );
    }

    // Verify that the customer exists.
    $checkCustomer = $conn->prepare(
        "SELECT user_id FROM users WHERE user_id = ?"
    );
    $checkCustomer->bind_param("i", $customerId);
    $checkCustomer->execute();

    if ($checkCustomer->get_result()->num_rows === 0) {
        respond(false, "Customer not found.", null, 404);
    }

    // Get the price of the selected service.
    $checkService = $conn->prepare(
        "SELECT price FROM services
         WHERE service_id = ? AND status = 'available'"
    );
    $checkService->bind_param("i", $serviceId);
    $checkService->execute();

    $serviceResult = $checkService->get_result();

    if ($serviceResult->num_rows === 0) {
        respond(false, "Service not found or unavailable.", null, 404);
    }

    $service = $serviceResult->fetch_assoc();
    $totalAmount = (float)$service["price"] * $quantity;

    if ($preferredDate !== "") {
        $date = DateTime::createFromFormat("!Y-m-d", $preferredDate);

        if (!$date || $date->format("Y-m-d") !== $preferredDate) {
            respond(false, "Preferred date must use YYYY-MM-DD format.", null, 400);
        }

        $stmt = $conn->prepare(
            "INSERT INTO service_requests
             (customer_id, service_id, preferred_date, quantity,
              total_amount, status, notes)
             VALUES (?, ?, ?, ?, ?, 'pending', ?)"
        );

        $stmt->bind_param(
            "issids",
            $customerId,
            $serviceId,
            $preferredDate,
            $quantity,
            $totalAmount,
            $notes
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO service_requests
             (customer_id, service_id, quantity,
              total_amount, status, notes)
             VALUES (?, ?, ?, ?, 'pending', ?)"
        );

        $stmt->bind_param(
            "iiids",
            $customerId,
            $serviceId,
            $quantity,
            $totalAmount,
            $notes
        );
    }

    if (!$stmt->execute()) {
        respond(false, "Failed to create service request.", null, 500);
    }

    respond(true, "Service request created successfully.", [
        "request_id" => $conn->insert_id,
        "total_amount" => $totalAmount,
        "status" => "pending"
    ], 201);
}

if ($action === "update") {
    $requestId = filter_var(
        $input["request_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $status = $input["status"] ?? "";

    $allowedStatuses = [
        "pending",
        "accepted",
        "rejected",
        "in_progress",
        "completed",
        "cancelled"
    ];

    if (
        !$requestId ||
        !in_array($status, $allowedStatuses, true)
    ) {
        respond(false, "Invalid request ID or status.", null, 400);
    }

    $stmt = $conn->prepare(
        "UPDATE service_requests
         SET status = ?
         WHERE request_id = ?"
    );

    $stmt->bind_param("si", $status, $requestId);

    if (!$stmt->execute()) {
        respond(false, "Failed to update request status.", null, 500);
    }

    if ($stmt->affected_rows === 0) {
        $check = $conn->prepare(
            "SELECT request_id FROM service_requests
             WHERE request_id = ?"
        );
        $check->bind_param("i", $requestId);
        $check->execute();

        if ($check->get_result()->num_rows === 0) {
            respond(false, "Service request not found.", null, 404);
        }
    }

    respond(true, "Service request status updated successfully.");
}

respond(false, "Invalid action. Use create or update.", null, 400);
?>