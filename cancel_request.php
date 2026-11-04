<?php
session_start();
require_once "config.php";

// Allow customers only
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "customer"
) {
    header("Location: login.php");
    exit();
}

// Accept POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my_requests.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

$request_id = filter_input(
    INPUT_POST,
    "request_id",
    FILTER_VALIDATE_INT
);

// Validate request ID
if (!$request_id || $request_id < 1) {
    header("Location: my_requests.php?message=invalid");
    exit();
}

// Cancel only the customer's own pending request
$sql = "UPDATE service_requests
        SET status = 'cancelled'
        WHERE request_id = ?
          AND customer_id = ?
          AND status = 'pending'";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $conn->close();
    header("Location: my_requests.php?message=cancel_error");
    exit();
}

$stmt->bind_param("ii", $request_id, $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();

    header("Location: my_requests.php?message=cancel_error");
    exit();
}

if ($stmt->affected_rows > 0) {
    $message = "cancelled";
} else {
    // Request may not exist, may belong to another customer,
    // or may no longer be pending.
    $message = "cancel_error";
}

$stmt->close();
$conn->close();

header("Location: my_requests.php?message=" . $message);
exit();
?>