<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"], $_SESSION["role"])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION["role"];
$user_id = (int) $_SESSION["user_id"];

if (!in_array($role, ["admin", "customer"], true)) {
    header("Location: index.php");
    exit();
}

$request_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$request_id || $request_id < 1) {
    header(
        "Location: " .
        ($role === "admin" ? "manage_requests.php" : "my_requests.php")
    );
    exit();
}

function escape_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function format_date($value)
{
    if (empty($value)) {
        return "—";
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date("M d, Y", $timestamp)
        : "—";
}

function format_datetime($value)
{
    if (empty($value)) {
        return "—";
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date("M d, Y · h:i A", $timestamp)
        : "—";
}

function status_class($status)
{
    switch (strtolower((string) $status)) {
        case "pending":
            return "status-pending";

        case "accepted":
            return "status-accepted";

        case "in_progress":
            return "status-progress";

        case "completed":
            return "status-completed";

        case "cancelled":
            return "status-cancelled";

        case "rejected":
            return "status-rejected";

        default:
            return "status-unknown";
    }
}

function status_label($status)
{
    return ucwords(str_replace("_", " ", (string) $status));
}

$sql = "SELECT
            sr.request_id,
            sr.customer_id,
            sr.request_date,
            sr.preferred_date,
            sr.quantity,
            sr.total_amount,
            sr.status,
            sr.notes,
            sr.created_at,
            u.full_name AS customer_name,
            u.email AS customer_email,
            s.service_name,
            s.description AS service_description,
            s.price,
            b.business_name,
            b.address AS business_address,
            b.contact_number
        FROM service_requests sr
        INNER JOIN users u ON sr.customer_id = u.user_id
        INNER JOIN services s ON sr.service_id = s.service_id
        INNER JOIN businesses b ON s.business_id = b.business_id
        WHERE sr.request_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to retrieve service request.");
}

$stmt->bind_param("i", $request_id);
$stmt->execute();

$result = $stmt->get_result();
$request = $result->fetch_assoc();

$stmt->close();

if (!$request) {
    http_response_code(404);
    exit("Service request not found.");
}

if (
    $role === "customer" &&
    (int) $request["customer_id"] !== $user_id
) {
    http_response_code(403);
    exit("You are not allowed to view this request.");
}

$full_name = $_SESSION["full_name"] ?? (
    $role === "admin" ? "Administrator" : "Customer"
);

$initial = strtoupper(substr($full_name, 0, 1));
$status = strtolower((string) $request["status"]);
$dashboard_url = $role === "admin"
    ? "admin_dashboard.php"
    : "customer_dashboard.php";

$back_url = $role === "admin"
    ? "manage_requests.php"
    : "my_requests.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Request Details | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/request_details.css">
</head>

<body>

<div class="layout details-layout">

    <aside class="sidebar details-sidebar">
        <div class="brand">
            <div class="brand-icon">U</div>
            <div>
                <h1>UniServe</h1>
                <p><?= $role === "admin" ? "Admin Portal" : "Customer Portal" ?></p>
            </div>
        </div>

        <div class="details-nav-label">MENU</div>

        <nav class="nav-menu">
            <a href="<?= escape_html($dashboard_url) ?>" class="nav-link">
                <span class="nav-icon">⌂</span>
                <span>Dashboard</span>
            </a>

            <?php if ($role === "admin"): ?>

                <a href="manage_requests.php" class="nav-link active">
                    <span class="nav-icon">▤</span>
                    <span>Manage Requests</span>
                </a>

                <a href="manage_services.php" class="nav-link">
                    <span class="nav-icon">▧</span>
                    <span>Manage Services</span>
                </a>

                <a href="manage_businesses.php" class="nav-link">
                    <span class="nav-icon">⌂</span>
                    <span>Manage Businesses</span>
                </a>

            <?php else: ?>

                <a href="browse_services.php" class="nav-link">
                    <span class="nav-icon">⌕</span>
                    <span>Browse Services</span>
                </a>

                <a href="my_requests.php" class="nav-link active">
                    <span class="nav-icon">▤</span>
                    <span>My Requests</span>
                </a>

                <a href="profile.php" class="nav-link">
                    <span class="nav-icon">◎</span>
                    <span>My Profile</span>
                </a>

            <?php endif; ?>
        </nav>

        <div class="sidebar-bottom">
            <a href="logout.php" class="nav-link logout-link">
                <span class="nav-icon">↪</span>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content details-main">

        <header class="topbar details-topbar">
            <div>
                <p class="details-eyebrow">
                    <?= $role === "admin" ? "ADMINISTRATION" : "CUSTOMER PORTAL" ?>
                </p>

                <h2>Request Details</h2>

                <p class="details-subtitle">
                    Review the information for this service request.
                </p>
            </div>

            <div class="details-user">
                <div class="details-avatar">
                    <?= escape_html($initial) ?>
                </div>

                <div>
                    <strong><?= escape_html($full_name) ?></strong>
                    <span><?= $role === "admin" ? "Administrator" : "Customer" ?></span>
                </div>
            </div>
        </header>

        <div class="details-content">

            <div class="details-breadcrumb">
                <a href="<?= escape_html($back_url) ?>">
                    <?= $role === "admin" ? "Manage Requests" : "My Requests" ?>
                </a>
                <span>/</span>
                <span>Request #<?= (int) $request["request_id"] ?></span>
            </div>

            <section class="details-hero">
                <div class="details-hero-info">
                    <span class="details-section-eyebrow">SERVICE REQUEST</span>

                    <h1>
                        <?= escape_html($request["service_name"]) ?>
                    </h1>

                    <p>
                        Request #<?= (int) $request["request_id"] ?>
                        <span class="hero-separator">·</span>
                        Submitted <?= escape_html(format_date($request["created_at"])) ?>
                    </p>
                </div>

                <div class="details-hero-status">
                    <span class="hero-status-label">Current Status</span>

                    <span class="request-status <?= escape_html(status_class($status)) ?>">
                        <?= escape_html(status_label($status)) ?>
                    </span>
                </div>
            </section>

            <section class="details-total-card">
                <div>
                    <span class="details-total-label">Total Amount</span>
                    <strong>
                        ₱<?= number_format((float) $request["total_amount"], 2) ?>
                    </strong>
                    <span class="details-total-note">
                        <?= (int) $request["quantity"] ?> item(s) requested
                    </span>
                </div>

                <div class="total-card-icon">₱</div>
            </section>

            <div class="details-grid">

                <section class="details-panel">
                    <div class="details-panel-heading">
                        <div class="panel-icon">▤</div>
                        <div>
                            <h2>Request Information</h2>
                            <p>Details and schedule of your request.</p>
                        </div>
                    </div>

                    <div class="details-fields">
                        <div class="details-field">
                            <span>Request ID</span>
                            <strong>#<?= (int) $request["request_id"] ?></strong>
                        </div>

                        <div class="details-field">
                            <span>Request Date</span>
                            <strong>
                                <?= escape_html(format_date($request["request_date"])) ?>
                            </strong>
                        </div>

                        <div class="details-field">
                            <span>Preferred Date</span>
                            <strong>
                                <?= escape_html(format_date($request["preferred_date"])) ?>
                            </strong>
                        </div>

                        <div class="details-field">
                            <span>Quantity</span>
                            <strong><?= (int) $request["quantity"] ?></strong>
                        </div>

                        <div class="details-field">
                            <span>Price per Unit</span>
                            <strong>
                                ₱<?= number_format((float) $request["price"], 2) ?>
                            </strong>
                        </div>

                        <div class="details-field">
                            <span>Total Amount</span>
                            <strong class="field-total">
                                ₱<?= number_format((float) $request["total_amount"], 2) ?>
                            </strong>
                        </div>

                        <div class="details-field full-field">
                            <span>Submitted At</span>
                            <strong>
                                <?= escape_html(format_datetime($request["created_at"])) ?>
                            </strong>
                        </div>
                    </div>
                </section>

                <section class="details-panel">
                    <div class="details-panel-heading">
                        <div class="panel-icon">▧</div>
                        <div>
                            <h2>Service Information</h2>
                            <p>Information about the requested service.</p>
                        </div>
                    </div>

                    <div class="details-fields">
                        <div class="details-field full-field">
                            <span>Service Name</span>
                            <strong>
                                <?= escape_html($request["service_name"]) ?>
                            </strong>
                        </div>

                        <div class="details-field full-field">
                            <span>Business Name</span>
                            <strong>
                                <?= escape_html($request["business_name"]) ?>
                            </strong>
                        </div>

                        <div class="details-field full-field">
                            <span>Business Address</span>
                            <strong>
                                <?= escape_html($request["business_address"] ?: "Not provided") ?>
                            </strong>
                        </div>

                        <div class="details-field full-field">
                            <span>Contact Number</span>
                            <strong>
                                <?= escape_html($request["contact_number"] ?: "Not provided") ?>
                            </strong>
                        </div>

                        <div class="details-field full-field">
                            <span>Service Description</span>
                            <p class="details-description">
                                <?= nl2br(escape_html($request["service_description"] ?: "No description provided.")) ?>
                            </p>
                        </div>
                    </div>
                </section>

                <section class="details-panel">
                    <div class="details-panel-heading">
                        <div class="panel-icon">◎</div>
                        <div>
                            <h2>Customer Information</h2>
                            <p>Contact information associated with this request.</p>
                        </div>
                    </div>

                    <div class="details-fields">
                        <div class="details-field full-field">
                            <span>Customer Name</span>
                            <strong>
                                <?= escape_html($request["customer_name"]) ?>
                            </strong>
                        </div>

                        <div class="details-field full-field">
                            <span>Email Address</span>
                            <strong class="breakable-value">
                                <?= escape_html($request["customer_email"]) ?>
                            </strong>
                        </div>
                    </div>
                </section>

                <section class="details-panel">
                    <div class="details-panel-heading">
                        <div class="panel-icon">☰</div>
                        <div>
                            <h2>Additional Notes</h2>
                            <p>Extra information submitted with the request.</p>
                        </div>
                    </div>

                    <div class="notes-content">
                        <?php if (trim((string) $request["notes"]) !== ""): ?>
                            <?= nl2br(escape_html($request["notes"])) ?>
                        <?php else: ?>
                            <span class="notes-empty">No additional notes provided.</span>
                        <?php endif; ?>
                    </div>
                </section>

            </div>

            <section class="details-actions">
                <a href="<?= escape_html($back_url) ?>" class="details-back-button">
                    <span aria-hidden="true">←</span>
                    <?= $role === "admin" ? "Back to Manage Requests" : "Back to My Requests" ?>
                </a>

                <?php if ($role === "admin"): ?>

                    <a href="admin_dashboard.php" class="details-dashboard-button">
                        Admin Dashboard
                    </a>

                <?php elseif ($status === "pending"): ?>

                    <form
                        action="cancel_request.php"
                        method="POST"
                        class="details-cancel-form"
                        onsubmit="return confirm('Are you sure you want to cancel this request?');"
                    >
                        <input
                            type="hidden"
                            name="request_id"
                            value="<?= (int) $request["request_id"] ?>"
                        >

                        <button type="submit" class="details-cancel-button">
                            Cancel Request
                        </button>
                    </form>

                <?php endif; ?>
            </section>

            <footer class="details-footer">
                <p>© <?= date("Y") ?> UniServe. All rights reserved.</p>
            </footer>

        </div>
    </main>
</div>

</body>
</html>
