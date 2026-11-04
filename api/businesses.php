<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config.php";

$method = $_SERVER["REQUEST_METHOD"];

function respond($success, $message, $data = null, $status = 200) {
    http_response_code($status);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);

    exit;
}

if ($method === "GET") {
    $sql = "SELECT
                b.business_id,
                b.owner_id,
                b.business_name,
                b.description,
                b.address,
                b.contact_number,
                b.status,
                b.created_at,
                u.full_name AS owner_name
            FROM businesses b
            LEFT JOIN users u ON b.owner_id = u.user_id
            ORDER BY b.business_id DESC";

    $result = $conn->query($sql);

    if (!$result) {
        respond(false, "Failed to retrieve businesses.");
    }

    $businesses = [];

    while ($row = $result->fetch_assoc()) {
        $businesses[] = $row;
    }

    respond(true, "Businesses retrieved successfully.", $businesses);
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
    $ownerId = filter_var(
        $input["owner_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $businessName = trim($input["business_name"] ?? "");
    $description = trim($input["description"] ?? "");
    $address = trim($input["address"] ?? "");
    $contactNumber = trim($input["contact_number"] ?? "");

    if (!$ownerId || $businessName === "") {
        respond(false, "Owner ID and business name are required.", null, 400);
    }

    $check = $conn->prepare(
        "SELECT user_id FROM users WHERE user_id = ?"
    );
    $check->bind_param("i", $ownerId);
    $check->execute();
    $owner = $check->get_result();

    if ($owner->num_rows === 0) {
        respond(false, "Owner account not found.", null, 404);
    }

    $stmt = $conn->prepare(
        "INSERT INTO businesses
        (owner_id, business_name, description, address, contact_number)
        VALUES (?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "issss",
        $ownerId,
        $businessName,
        $description,
        $address,
        $contactNumber
    );

    if (!$stmt->execute()) {
        respond(false, "Failed to create business.", null, 500);
    }

    respond(true, "Business created successfully.", [
        "business_id" => $conn->insert_id
    ], 201);
}

if ($action === "update") {
    $businessId = filter_var(
        $input["business_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $businessName = trim($input["business_name"] ?? "");
    $description = trim($input["description"] ?? "");
    $address = trim($input["address"] ?? "");
    $contactNumber = trim($input["contact_number"] ?? "");

    if (!$businessId || $businessName === "") {
        respond(false, "Business ID and business name are required.", null, 400);
    }

    $stmt = $conn->prepare(
        "UPDATE businesses
         SET business_name = ?, description = ?,
             address = ?, contact_number = ?
         WHERE business_id = ?"
    );

    $stmt->bind_param(
        "ssssi",
        $businessName,
        $description,
        $address,
        $contactNumber,
        $businessId
    );

    if (!$stmt->execute()) {
        respond(false, "Failed to update business.", null, 500);
    }

    respond(true, "Business updated successfully.");
}

if ($action === "delete") {
    $businessId = filter_var(
        $input["business_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$businessId) {
        respond(false, "Valid business ID is required.", null, 400);
    }

    $stmt = $conn->prepare(
        "DELETE FROM businesses WHERE business_id = ?"
    );

    $stmt->bind_param("i", $businessId);

    if (!$stmt->execute()) {
        respond(false, "Failed to delete business.", null, 500);
    }

    if ($stmt->affected_rows === 0) {
        respond(false, "Business not found.", null, 404);
    }

    respond(true, "Business deleted successfully.");
}

respond(false, "Invalid action. Use create, update, or delete.", null, 400);
?>