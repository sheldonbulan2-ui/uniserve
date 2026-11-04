<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config.php";

function respond($success, $message, $data = null, $status = 200) {
    http_response_code($status);
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
    $customerId = filter_input(INPUT_GET, "customer_id", FILTER_VALIDATE_INT);
    $businessId = filter_input(INPUT_GET, "business_id", FILTER_VALIDATE_INT);

    $sql = "
        SELECT
            r.request_id,
            r.customer_id,
            u.full_name AS customer_name,
            u.email AS customer_email,
            r.service_id,
            s.service_name,
            s.price AS unit_price,
            b.business_id,
            b.business_name,
            r.request_date,
            r.preferred_date,
            r.quantity,
            r.total_amount,
            r.status,
            r.notes,
            r.created_at
        FROM service_requests r
        LEFT JOIN users u ON r.customer_id = u.user_id
        LEFT JOIN services s ON r.service_id = s.service_id
        LEFT JOIN businesses b ON s.business_id = b.business_id
    ";

    $where = [];
    $params = [];
    $types = "";

    if ($customerId) {
        $where[] = "r.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }

    if ($businessId) {
        $where[] = "s.business_id = ?";
        $params[] = $businessId;
        $types .= "i";
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY r.request_id DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        $stmt->close();
        respond(true, "Service requests retrieved successfully.", $requests);
    } else {
        respond(false, "Failed to retrieve service requests.", null, 500);
    }
}

if ($method !== "POST") {
    header("Allow: GET, POST");
    respond(false, "Method not allowed.", null, 405);
}

// Support dual JSON and URL-encoded form POST
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input["action"] ?? "create";

if ($action === "create") {
    $customerId = filter_var($input["customer_id"] ?? null, FILTER_VALIDATE_INT);
    $serviceId = filter_var($input["service_id"] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($input["quantity"] ?? 1, FILTER_VALIDATE_INT);
    $preferredDate = trim($input["preferred_date"] ?? "");
    $notes = trim($input["notes"] ?? "");

    if (!$customerId || !$serviceId || !$quantity || $quantity < 1) {
        respond(false, "Valid customer ID, service ID, and quantity are required.", null, 400);
    }

    // Verify Customer Existence
    $checkCustomer = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
    $checkCustomer->bind_param("i", $customerId);
    $checkCustomer->execute();
    if ($checkCustomer->get_result()->num_rows === 0) {
        $checkCustomer->close();
        respond(false, "Customer account not found.", null, 404);
    }
    $checkCustomer->close();

    // Verify Service & Rate (Fixed column: availability)
    $checkService = $conn->prepare("SELECT service_id, price FROM services WHERE service_id = ? AND availability = 'available' LIMIT 1");
    $checkService->bind_param("i", $serviceId);
    $checkService->execute();
    $sRes = $checkService->get_result();
    if ($sRes->num_rows === 0) {
        $checkService->close();
        respond(false, "Service is currently unavailable or not found.", null, 404);
    }
    $service = $sRes->fetch_assoc();
    $checkService->close();

    $totalAmount = (float)$service["price"] * $quantity;

    if ($preferredDate !== "") {
        $date = DateTime::createFromFormat("!Y-m-d", $preferredDate);
        if (!$date) {
            $preferredDate = null;
        }
    } else {
        $preferredDate = null;
    }

    $stmt = $conn->prepare(
        "INSERT INTO service_requests (customer_id, service_id, preferred_date, quantity, total_amount, status, notes)
         VALUES (?, ?, ?, ?, ?, 'pending', ?)"
    );
    $stmt->bind_param("iisids", $customerId, $serviceId, $preferredDate, $quantity, $totalAmount, $notes);

    if (!$stmt->execute()) {
        respond(false, "Failed to create service booking: " . $stmt->error, null, 500);
    }

    $requestId = (int)($stmt->insert_id ?: $conn->insert_id);
    $stmt->close();

    // Create associated pending financial transaction
    try {
        $txnStmt = $conn->prepare(
            "INSERT INTO transactions (request_id, amount, payment_method, payment_status) VALUES (?, ?, 'Cash on Delivery', 'pending')"
        );
        $txnStmt->bind_param("id", $requestId, $totalAmount);
        $txnStmt->execute();
        $txnStmt->close();
    } catch (Throwable $e) {}

    respond(true, "Service booking submitted successfully.", [
        "request_id" => $requestId,
        "total_amount" => $totalAmount,
        "status" => "pending"
    ], 201);
}

if ($action === "update") {
    $requestId = filter_var($input["request_id"] ?? null, FILTER_VALIDATE_INT);
    $status = trim($input["status"] ?? "");

    $allowedStatuses = ["pending", "accepted", "in_progress", "completed", "cancelled", "rejected"];

    if (!$requestId || !in_array($status, $allowedStatuses, true)) {
        respond(false, "Invalid order ID or lifecycle status specified.", null, 400);
    }

    $stmt = $conn->prepare("UPDATE service_requests SET status = ? WHERE request_id = ?");
    $stmt->bind_param("si", $status, $requestId);

    if (!$stmt->execute()) {
        respond(false, "Failed to update order status.", null, 500);
    }
    $stmt->close();

    // If order was marked completed, mark cash transaction paid
    if ($status === "completed") {
        try {
            $updTxn = $conn->prepare("UPDATE transactions SET payment_status = 'paid' WHERE request_id = ? AND payment_status = 'pending'");
            $updTxn->bind_param("i", $requestId);
            $updTxn->execute();
            $updTxn->close();
        } catch (Throwable $e) {}
    }

    respond(true, "Order status updated to " . ucfirst($status) . " successfully.", [
        "request_id" => $requestId,
        "status" => $status
    ]);
}

respond(false, "Unrecognized action.", null, 400);
