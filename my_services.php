<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "business_owner"
) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$error = "";
$message = $_GET["message"] ?? "";

function escape_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function redirect_services($message) {
    header("Location: my_services.php?message=" . urlencode($message));
    exit();
}

function show_database_error() {
    http_response_code(500);
    exit("A database error occurred. Please try again later.");
}

$success_messages = [
    "added" => "Service added successfully.",
    "updated" => "Service availability updated.",
    "unchanged" => "The service already has that availability status."
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add") {
        $business_id = filter_input(
            INPUT_POST,
            "business_id",
            FILTER_VALIDATE_INT
        );

        $service_name = trim($_POST["service_name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $price_input = trim($_POST["price"] ?? "");

        $price_valid = (
            $price_input !== "" &&
            preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $price_input) &&
            (float) $price_input <= 99999999.99
        );

        if (
            !$business_id ||
            $business_id < 1 ||
            $service_name === "" ||
            strlen($service_name) > 150 ||
            strlen($description) > 2000 ||
            !$price_valid
        ) {
            $error = "Please enter valid service details and select an approved business.";
        } else {
            $check_sql = "SELECT business_id
                          FROM businesses
                          WHERE business_id = ?
                            AND owner_id = ?
                            AND status = 'approved'
                          LIMIT 1";

            $check_stmt = $conn->prepare($check_sql);

            if (!$check_stmt) {
                show_database_error();
            }

            $check_stmt->bind_param("ii", $business_id, $user_id);

            if (!$check_stmt->execute()) {
                $check_stmt->close();
                show_database_error();
            }

            $owned_business = $check_stmt
                ->get_result()
                ->fetch_assoc();

            $check_stmt->close();

            if (!$owned_business) {
                $error = "You can add services only to your own approved businesses.";
            } else {
                $price = (float) $price_input;
                $availability = "available";

                $insert_sql = "INSERT INTO services
                               (business_id, service_name, description, price, availability)
                               VALUES (?, ?, ?, ?, ?)";

                $insert_stmt = $conn->prepare($insert_sql);

                if (!$insert_stmt) {
                    show_database_error();
                }

                $insert_stmt->bind_param(
                    "issds",
                    $business_id,
                    $service_name,
                    $description,
                    $price,
                    $availability
                );

                if (!$insert_stmt->execute()) {
                    $insert_stmt->close();
                    $error = "Unable to add the service. Please try again.";
                } else {
                    $insert_stmt->close();
                    $conn->close();
                    redirect_services("added");
                }

                $insert_stmt->close();
            }
        }
    } elseif ($action === "toggle") {
        $service_id = filter_input(
            INPUT_POST,
            "service_id",
            FILTER_VALIDATE_INT
        );

        $new_availability = $_POST["availability"] ?? "";

        if (
            !$service_id ||
            $service_id < 1 ||
            !in_array(
                $new_availability,
                ["available", "unavailable"],
                true
            )
        ) {
            $error = "Invalid service or availability status.";
        } else {
            $check_sql = "SELECT s.availability
                          FROM services s
                          INNER JOIN businesses b
                              ON s.business_id = b.business_id
                          WHERE s.service_id = ?
                            AND b.owner_id = ?
                            AND b.status = 'approved'
                          LIMIT 1";

            $check_stmt = $conn->prepare($check_sql);

            if (!$check_stmt) {
                show_database_error();
            }

            $check_stmt->bind_param("ii", $service_id, $user_id);

            if (!$check_stmt->execute()) {
                $check_stmt->close();
                show_database_error();
            }

            $service = $check_stmt
                ->get_result()
                ->fetch_assoc();

            $check_stmt->close();

            if (!$service) {
                $error = "Service not found, or its business is not approved.";
            } elseif ($service["availability"] === $new_availability) {
                $conn->close();
                redirect_services("unchanged");
            } else {
                $update_sql = "UPDATE services s
                               INNER JOIN businesses b
                                   ON s.business_id = b.business_id
                               SET s.availability = ?
                               WHERE s.service_id = ?
                                 AND b.owner_id = ?
                                 AND b.status = 'approved'";

                $update_stmt = $conn->prepare($update_sql);

                if (!$update_stmt) {
                    show_database_error();
                }

                $update_stmt->bind_param(
                    "sii",
                    $new_availability,
                    $service_id,
                    $user_id
                );

                if (!$update_stmt->execute()) {
                    $update_stmt->close();
                    show_database_error();
                }

                if ($update_stmt->affected_rows > 0) {
                    $update_stmt->close();
                    $conn->close();
                    redirect_services("updated");
                }

                $update_stmt->close();
                $error = "The service could not be updated. Please refresh and try again.";
            }
        }
    } else {
        $error = "Invalid action.";
    }
}

$business_sql = "SELECT business_id, business_name
                 FROM businesses
                 WHERE owner_id = ?
                   AND status = 'approved'
                 ORDER BY business_name ASC";

$business_stmt = $conn->prepare($business_sql);

if (!$business_stmt) {
    show_database_error();
}

$business_stmt->bind_param("i", $user_id);

if (!$business_stmt->execute()) {
    $business_stmt->close();
    show_database_error();
}

$businesses = $business_stmt->get_result();

$service_sql = "SELECT
                    s.service_id,
                    s.service_name,
                    s.description,
                    s.price,
                    s.availability,
                    b.business_name
                FROM services s
                INNER JOIN businesses b
                    ON s.business_id = b.business_id
                WHERE b.owner_id = ?
                ORDER BY s.created_at DESC";

$service_stmt = $conn->prepare($service_sql);

if (!$service_stmt) {
    show_database_error();
}

$service_stmt->bind_param("i", $user_id);

if (!$service_stmt->execute()) {
    $service_stmt->close();
    show_database_error();
}

$services = $service_stmt->get_result();

$business_count = $businesses->num_rows;
$service_count = $services->num_rows;
$owner_name = $_SESSION["full_name"] ?? "Business Owner";
$owner_initial = strtoupper(substr($owner_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Services - UniServe</title>
    <link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/my_services.css">
</head>

<body class="my-services-page">
    
<div class="layout">

  <aside class="sidebar admin-sidebar">

        <div class="brand">
            <div class="brand-icon">U</div>

            <div>
                <h1>UniServe</h1>
                <p>Business &amp; Service Management</p>
            </div>
        </div>

        <h3>Business Management</h3>

        <nav aria-label="Business navigation">

            <a href="business_dashboard.php">
                <span class="nav-icon">▦</span>
                Dashboard
            </a>

            <a href="register_business.php">
                <span class="nav-icon">＋</span>
                Register Business
            </a>

            <a href="my_businesses.php">
                <span class="nav-icon">▤</span>
                My Businesses
            </a>

            <a class="active" href="my_services.php">
                <span class="nav-icon">◇</span>
                My Services
            </a>

            <a href="business_requests.php">
                <span class="nav-icon">☷</span>
                Business Requests
            </a>

        </nav>

        <div class="sidebar-bottom">

            <h3>Account</h3>

            <nav aria-label="Account navigation">

                <a href="profile.php">
                    <span class="nav-icon">♙</span>
                    My Profile
                </a>

                <a class="logout-link" href="logout.php">
                    <span class="nav-icon">↪</span>
                    Logout
                </a>

            </nav>

        </div>

    </aside>
    <div class="main-content">

        <header class="topbar">
            <div>
                <div class="topbar-title">My Services</div>
                <div class="topbar-subtitle">
                    Add services and manage their availability
                </div>
            </div>

            <div class="user-info">
                <div class="avatar">
                    <?= escape_html($owner_initial) ?>
                </div>
                <div>
                    <div class="user-name">
                        <?= escape_html($owner_name) ?>
                    </div>
                    <div class="user-role">Business Owner</div>
                </div>
            </div>
        </header>

        <main class="content">

            <section class="welcome-section">
                <div>
                    <h2>My Services</h2>
                    <p>
                        Manage your services, set prices, and control
                        whether customers can request them.
                    </p>
                </div>

            </section>

            <?php if (isset($success_messages[$message])): ?>
                <div class="alert success" role="status">
                    <span>&#10003;</span>
                    <div>
                        <?= escape_html($success_messages[$message]) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="alert error" role="alert">
                    <span>&#9888;</span>
                    <div><?= escape_html($error) ?></div>
                </div>
            <?php endif; ?>

            <section class="summary-row">
                <div class="summary-card">
                    <div class="summary-icon">&#128736;</div>
                    <div>
                        <div class="summary-label">Total Services</div>
                        <div class="summary-number">
                            <?= number_format($service_count) ?>
                        </div>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon">&#127970;</div>
                    <div>
                        <div class="summary-label">Approved Businesses</div>
                        <div class="summary-number">
                            <?= number_format($business_count) ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h3>Add a Service</h3>
                        <p>
                            Enter the service details for one of your
                            approved businesses.
                        </p>
                    </div>
                </div>

                <?php if ($business_count > 0): ?>
                    <form method="POST" action="my_services.php">
                        <input type="hidden" name="action" value="add">

                        <div class="form-grid">

                            <div class="form-group full-width">
                                <label for="business_id">
                                    Approved Business
                                </label>

                                <select
                                    id="business_id"
                                    name="business_id"
                                    required
                                >
                                    <option value="">Select a business</option>

                                    <?php while ($business = $businesses->fetch_assoc()): ?>
                                        <option value="<?= (int) $business["business_id"] ?>">
                                            <?= escape_html($business["business_name"]) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label for="service_name">Service Name</label>
                                <input
                                    type="text"
                                    id="service_name"
                                    name="service_name"
                                    maxlength="150"
                                    placeholder="Enter service name"
                                    required
                                >
                            </div>

                            <div class="form-group full-width">
                                <label for="description">Description</label>
                                <textarea
                                    id="description"
                                    name="description"
                                    maxlength="2000"
                                    placeholder="Describe your service"
                                ></textarea>
                            </div>

                            <div class="form-group">
                                <label for="price">Price per Unit</label>
                                <input
                                    type="number"
                                    id="price"
                                    name="price"
                                    min="0"
                                    max="99999999.99"
                                    step="0.01"
                                    placeholder="0.00"
                                    required
                                >
                                <div class="helper-text">
                                    Enter the price for one unit of service.
                                </div>
                            </div>

                        </div>

                        <div class="form-actions">
                            <button class="button" type="submit">
                                &#43; Add Service
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">&#127970;</div>
                        <h4>No Approved Business Yet</h4>
                        <p>
                            You need an approved business before you can
                            add services.
                        </p>
                        <a class="button" href="my_businesses.php">
                            View My Businesses
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h3>Services List</h3>
                        <p>
                            Review your services and update their availability.
                        </p>
                    </div>
                </div>

                <div class="table-container">
                    <?php if ($service_count > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Service ID</th>
                                    <th>Business</th>
                                    <th>Service Name</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Availability</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php while ($service = $services->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            #<?= (int) $service["service_id"] ?>
                                        </td>

                                        <td>
                                            <?= escape_html($service["business_name"]) ?>
                                        </td>

                                        <td class="service-name">
                                            <?= escape_html($service["service_name"]) ?>
                                        </td>

                                        <td class="description-cell">
                                            <?php
                                            $description = $service["description"] ?? "";
                                            echo $description !== ""
                                                ? nl2br(escape_html($description))
                                                : "No description provided.";
                                            ?>
                                        </td>

                                        <td class="price">
                                            <?= number_format((float) $service["price"], 2) ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= $service["availability"] === "available" ? "available" : "unavailable" ?>">
                                                <?= escape_html(ucfirst($service["availability"])) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($service["availability"] === "available"): ?>
                                                <form
                                                    class="inline"
                                                    method="POST"
                                                    action="my_services.php"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="toggle"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="service_id"
                                                        value="<?= (int) $service["service_id"] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="availability"
                                                        value="unavailable"
                                                    >

                                                    <button
                                                        class="button small danger"
                                                        type="submit"
                                                    >
                                                        Mark Unavailable
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form
                                                    class="inline"
                                                    method="POST"
                                                    action="my_services.php"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="toggle"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="service_id"
                                                        value="<?= (int) $service["service_id"] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="availability"
                                                        value="available"
                                                    >

                                                    <button
                                                        class="button small"
                                                        type="submit"
                                                    >
                                                        Mark Available
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">&#128736;</div>
                            <h4>No Services Added Yet</h4>
                            <p>
                                You have not added any services yet.
                                Use the form above to add your first service.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        </main>
    </div>
</div>
</body>
</html>

<?php
$business_stmt->close();
$service_stmt->close();
$conn->close();
?>