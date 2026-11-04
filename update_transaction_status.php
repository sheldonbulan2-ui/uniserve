<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage_transactions.php");
    exit();
}

$transaction_id = filter_input(
    INPUT_POST,
    "transaction_id",
    FILTER_VALIDATE_INT
);

$payment_status = trim($_POST["payment_status"] ?? "");

$allowed_statuses = [
    "pending",
    "paid",
    "failed",
    "refunded"
];

if (
    !$transaction_id ||
    $transaction_id < 1 ||
    !in_array($payment_status, $allowed_statuses, true)
) {
    header("Location: manage_transactions.php");
    exit();
}

$stmt = $conn->prepare(
    "UPDATE transactions
     SET payment_status = ?
     WHERE transaction_id = ?"
);

if (!$stmt) {
    $conn->close();
    exit("Unable to prepare the transaction status update.");
}

$stmt->bind_param("si", $payment_status, $transaction_id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();

    header("Location: manage_transactions.php");
    exit();
}

$stmt->close();
$conn->close();

exit("Unable to update the transaction status.");
?>