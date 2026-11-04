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
    $businessId = filter_input(INPUT_GET, "business_id", FILTER_VALIDATE_INT);
    $categoryId = filter_input(INPUT_GET, "category_id", FILTER_VALIDATE_INT);
    $search = trim($_GET["search"] ?? "");

    $sql = "
        SELECT
            s.service_id,
            s.business_id,
            b.business_name,
            b.category_id,
            s.service_name,
            s.description,
            s.price,
            s.availability,
            s.created_at
        FROM services s
        INNER JOIN businesses b ON s.business_id = b.business_id
    ";

    $where = ["b.status = 'approved'"];
    $params = [];
    $types = "";

    // If business_id is explicitly requested by a merchant, allow all its services
    if ($businessId) {
        $where = ["s.business_id = ?"];
        $params[] = $businessId;
        $types .= "i";
    }

    if ($categoryId) {
        $where[] = "b.category_id = ?";
        $params[] = $categoryId;
        $types .= "i";
    }

    if ($search !== "") {
        $where[] = "(s.service_name LIKE ? OR s.description LIKE ? OR b.business_name LIKE ?)";
        $kw = "%" . $search . "%";
        $params[] = $kw;
        $params[] = $kw;
        $params[] = $kw;
        $types .= "sss";
    }

    $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY s.service_id DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $services = [];
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
        $stmt->close();
        respond(true, "Services retrieved successfully.", $services);
    } else {
        respond(false, "Failed to retrieve services.", null, 500);
    }
}

if ($method !== "POST") {
    header("Allow: GET, POST");
    respond(false, "Method not allowed.", null, 405);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input["action"] ?? "create";

if ($action === "create") {
    $businessId = filter_var($input["business_id"] ?? null, FILTER_VALIDATE_INT);
    $serviceName = trim($input["service_name"] ?? "");
    $description = trim($input["description"] ?? "");
    $price = $input["price"] ?? null;
    $availability = trim($input["availability"] ?? "available");

    if (!in_array($availability, ['available', 'unavailable'], true)) {
        $availability = 'available';
    }

    if (!$businessId || $serviceName === "" || !is_numeric($price) || (float)$price < 0) {
        respond(false, "Valid business ID, service name, and non-negative price are required.", null, 400);
    }

    $check = $conn->prepare("SELECT business_id FROM businesses WHERE business_id = ? LIMIT 1");
    $check->bind_param("i", $businessId);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $check->close();
        respond(false, "Merchant business not found.", null, 404);
    }
    $check->close();

    $price = (float)$price;

    $stmt = $conn->prepare(
        "INSERT INTO services (business_id, service_name, description, price, availability) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issds", $businessId, $serviceName, $description, $price, $availability);

    if (!$stmt->execute()) {
        respond(false, "Failed to create service: " . $stmt->error, null, 500);
    }

    $serviceId = (int)($stmt->insert_id ?: $conn->insert_id);
    $stmt->close();

    respond(true, "Service created successfully.", [
        "service_id" => $serviceId,
        "business_id" => $businessId,
        "service_name" => $serviceName,
        "price" => $price,
        "availability" => $availability
    ], 201);
}

respond(false, "Unrecognized action.", null, 400);
