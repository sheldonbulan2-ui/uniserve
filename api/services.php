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
                s.service_id,
                s.business_id,
                b.business_name,
                s.service_name,
                s.description,
                s.price,
                s.availability,
                s.created_at
            FROM services s
            INNER JOIN businesses b
                ON s.business_id = b.business_id
            ORDER BY s.service_id DESC";

    $result = $conn->query($sql);

    if (!$result) {
        respond(false, "Failed to retrieve services.", null, 500);
    }

    $services = [];

    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }

    respond(true, "Services retrieved successfully.", $services);
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
    $businessId = filter_var(
        $input["business_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $serviceName = trim($input["service_name"] ?? "");
    $description = trim($input["description"] ?? "");
    $price = $input["price"] ?? null;

    if (
        !$businessId ||
        $serviceName === "" ||
        !is_numeric($price) ||
        (float)$price < 0
    ) {
        respond(false, "Valid business ID, service name, and price are required.", null, 400);
    }

    $check = $conn->prepare(
        "SELECT business_id FROM businesses WHERE business_id = ?"
    );
    $check->bind_param("i", $businessId);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {
        respond(false, "Business not found.", null, 404);
    }

    $price = (float)$price;

    $stmt = $conn->prepare(
        "INSERT INTO services
         (business_id, service_name, description, price)
         VALUES (?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "issd",
        $businessId,
        $serviceName,
        $description,
        $price
    );

    if (!$stmt->execute()) {
        respond(false, "Failed to create service.", null, 500);
    }

    respond(true, "Service created successfully.", [
        "service_id" => $conn->insert_id
    ], 201);
}

if ($action === "update") {
    $serviceId = filter_var(
        $input["service_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $serviceName = trim($input["service_name"] ?? "");
    $description = trim($input["description"] ?? "");
    $price = $input["price"] ?? null;
    $status = $input["status"] ?? "available";

    if (
        !$serviceId ||
        $serviceName === "" ||
        !is_numeric($price) ||
        (float)$price < 0 ||
        !in_array($status, ["available", "unavailable"], true)
    ) {
        respond(false, "Invalid service details.", null, 400);
    }

    $price = (float)$price;

    $stmt = $conn->prepare(
        "UPDATE services
         SET service_name = ?, description = ?, price = ?, status = ?
         WHERE service_id = ?"
    );

    $stmt->bind_param(
        "ssdsi",
        $serviceName,
        $description,
        $price,
        $status,
        $serviceId
    );

    if (!$stmt->execute()) {
        respond(false, "Failed to update service.", null, 500);
    }

    if ($stmt->affected_rows === 0) {
        $check = $conn->prepare(
            "SELECT service_id FROM services WHERE service_id = ?"
        );
        $check->bind_param("i", $serviceId);
        $check->execute();

        if ($check->get_result()->num_rows === 0) {
            respond(false, "Service not found.", null, 404);
        }
    }

    respond(true, "Service updated successfully.");
}

if ($action === "delete") {
    $serviceId = filter_var(
        $input["service_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$serviceId) {
        respond(false, "Valid service ID is required.", null, 400);
    }

    $stmt = $conn->prepare(
        "DELETE FROM services WHERE service_id = ?"
    );

    $stmt->bind_param("i", $serviceId);

    if (!$stmt->execute()) {
        respond(false, "Failed to delete service.", null, 500);
    }

    if ($stmt->affected_rows === 0) {
        respond(false, "Service not found.", null, 404);
    }

    respond(true, "Service deleted successfully.");
}

respond(false, "Invalid action. Use create, update, or delete.", null, 400);
?>