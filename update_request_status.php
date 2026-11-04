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
    header("Location: manage_requests.php");
    exit();
}

$request_id = filter_input(INPUT_POST, "request_id", FILTER_VALIDATE_INT);
$status = trim($_POST["status"] ?? "");

/*
 * Use only status values confirmed by your database.
 * Add other values only after checking the complete ENUM definition.
 */
$allowed_statuses = [
    "pending",
    "accepted",
    "in_progress",
    "completed",
    "cancelled",
    "rejected"
];

if (
    !$request_id ||
    $request_id < 1 ||
    !in_array($status, $allowed_statuses, true)
) {
    header("Location: manage_requests.php");
    exit();
}

$stmt = $conn->prepare(
    "UPDATE service_requests
     SET status = ?
     WHERE request_id = ?"
);

if (!$stmt) {
    $conn->close();
    exit("Unable to prepare the request status update.");
}

$stmt->bind_param("si", $status, $request_id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();

    header("Location: manage_requests.php");
    exit();
}

$stmt->close();
$conn->close();

exit("Unable to update the request status.");
?>