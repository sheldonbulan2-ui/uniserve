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
                t.transaction_id,
                t.request_id,
                r.customer_id,
                r.service_id,
                t.amount,
                t.payment_method,
                t.payment_status,
                t.transaction_date
            FROM transactions t
            INNER JOIN service_requests r
                ON t.request_id = r.request_id
            ORDER BY t.transaction_id DESC";

    $result = $conn->query($sql);

    if (!$result) {
        respond(false, "Failed to retrieve transactions.", null, 500);
    }

    $transactions = [];

    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    respond(true, "Transactions retrieved successfully.", $transactions);
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
    $requestId = filter_var(
        $input["request_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $paymentMethod = trim($input["payment_method"] ?? "");
    $amount = $input["amount"] ?? null;

    $allowedMethods = [
        "cash",
        "bank_transfer",
        "card",
        "e_wallet"
    ];

    if (
        !$requestId ||
        !is_numeric($amount) ||
        (float)$amount < 0 ||
        !in_array($paymentMethod, $allowedMethods, true)
    ) {
        respond(false, "Invalid request ID, amount, or payment method.", null, 400);
    }

    $check = $conn->prepare(
        "SELECT request_id, total_amount
         FROM service_requests
         WHERE request_id = ?"
    );
    $check->bind_param("i", $requestId);
    $check->execute();

    $requestResult = $check->get_result();

    if ($requestResult->num_rows === 0) {
        respond(false, "Service request not found.", null, 404);
    }

    $request = $requestResult->fetch_assoc();

    // Use the saved request total instead of trusting the submitted amount.
    $amount = (float)$request["total_amount"];

    $paymentStatus = "pending";

    $stmt = $conn->prepare(
        "INSERT INTO transactions
         (request_id, amount, payment_method, payment_status)
         VALUES (?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "idss",
        $requestId,
        $amount,
        $paymentMethod,
        $paymentStatus
    );

    if (!$stmt->execute()) {
        respond(false, "Failed to create transaction.", null, 500);
    }

    respond(true, "Transaction created successfully.", [
        "transaction_id" => $conn->insert_id,
        "request_id" => $requestId,
        "amount" => $amount,
        "payment_status" => $paymentStatus
    ], 201);
}

if ($action === "update_status") {
    $transactionId = filter_var(
        $input["transaction_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $paymentStatus = $input["payment_status"] ?? "";

    $allowedStatuses = [
        "pending",
        "paid",
        "failed",
        "refunded"
    ];

    if (
        !$transactionId ||
        !in_array($paymentStatus, $allowedStatuses, true)
    ) {
        respond(false, "Invalid transaction ID or payment status.", null, 400);
    }

    $stmt = $conn->prepare(
        "UPDATE transactions
         SET payment_status = ?
         WHERE transaction_id = ?"
    );

    $stmt->bind_param("si", $paymentStatus, $transactionId);

    if (!$stmt->execute()) {
        respond(false, "Failed to update payment status.", null, 500);
    }

    if ($stmt->affected_rows === 0) {
        $check = $conn->prepare(
            "SELECT transaction_id FROM transactions
             WHERE transaction_id = ?"
        );
        $check->bind_param("i", $transactionId);
        $check->execute();

        if ($check->get_result()->num_rows === 0) {
            respond(false, "Transaction not found.", null, 404);
        }
    }

    respond(true, "Payment status updated successfully.");
}

respond(
    false,
    "Invalid action. Use create or update_status.",
    null,
    400
);
?>