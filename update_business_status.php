<?php
session_start();
require_once "config.php";

// Allow admin only
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

// Accept POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage_businesses.php");
    exit();
}

// Get and validate submitted values
$business_id = filter_input(
    INPUT_POST,
    "business_id",
    FILTER_VALIDATE_INT
);

$status = $_POST["status"] ?? "";

$allowed_statuses = [
    "pending",
    "approved",
    "rejected"
];

if (
    !$business_id ||
    $business_id < 1 ||
    !in_array($status, $allowed_statuses, true)
) {
    header("Location: manage_businesses.php?error=invalid");
    exit();
}

// Update business status
$stmt = $conn->prepare(
    "UPDATE businesses
     SET status = ?
     WHERE business_id = ?"
);

if (!$stmt) {
    header("Location: manage_businesses.php?error=update_failed");
    exit();
}

$stmt->bind_param("si", $status, $business_id);

if ($stmt->execute()) {
    $updated = $stmt->affected_rows;
    $stmt->close();

    header(
        "Location: manage_businesses.php?" .
        ($updated >= 0 ? "updated=1" : "error=update_failed")
    );
    exit();
}

$stmt->close();

header("Location: manage_businesses.php?error=update_failed");
exit();
?>