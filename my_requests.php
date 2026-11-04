
<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "customer"
) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$message = $_GET["message"] ?? "";

$messages = [
    "submitted" => "Your service request was submitted successfully.",
    "cancelled" => "Your service request was cancelled.",
    "cancel_error" => "The request could not be cancelled. It may no longer be pending.",
    "invalid" => "Invalid request ID.",
    "not_found" => "The requested record was not found."
];

$sql = "SELECT
            sr.request_id,
            sr.request_date,
            sr.preferred_date,
            sr.quantity,
            sr.total_amount,
            sr.status,
            s.service_name,
            b.business_name
        FROM service_requests sr
        INNER JOIN services s
            ON sr.service_id = s.service_id
        INNER JOIN businesses b
            ON s.business_id = b.business_id
        WHERE sr.customer_id = ?
        ORDER BY sr.created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $conn->close();
    http_response_code(500);
    exit("Unable to load your requests. Please try again later.");
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    http_response_code(500);
    exit("Unable to load your requests. Please try again later.");
}

$requests = $stmt->get_result();

function escape_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function status_label($status) {
    return ucwords(str_replace("_", " ", (string) $status));
}

function status_class($status) {
    $allowed = [
        "pending",
        "accepted",
        "in_progress",
        "completed",
        "cancelled",
        "rejected"
    ];

    return in_array($status, $allowed, true) ? $status : "unknown";
}

$full_name = $_SESSION["full_name"] ?? "Customer";
$total_requests = $requests->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Requests | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/my_requests.css">
</head>

<body class="my-requests-page">

<div class="layout">

    <aside class="sidebar admin-sidebar">

        <div class="brand">
            <div class="brand-icon">U</div>

            <div>
                <h1>UniServe</h1>
                <p>Business &amp; Service Management</p>
            </div>
        </div>

        <h3>Customer Menu</h3>

        <nav aria-label="Customer navigation">
            <a href="customer_dashboard.php">
                <span class="nav-icon">▦</span>
                Dashboard
            </a>

            <a href="browse_services.php">
                <span class="nav-icon">⌕</span>
                Browse Services
            </a>

            <a class="active" href="my_requests.php">
                <span class="nav-icon">☷</span>
                My Requests
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

    <div class="main-area">

        <header class="topbar">
            <div class="my-requests-topbar-heading">
                <h2>My Requests</h2>
                <p>View your service requests and track their progress.</p>
            </div>

            <div class="user-info">
                <div class="avatar">
                    <?= escape_html(strtoupper(substr($full_name, 0, 1))) ?>
                </div>

                <div>
                    <strong><?= escape_html($full_name) ?></strong>
                    <p>Customer Account</p>
                </div>
            </div>
        </header>

        <main class="content">

            <div class="requests-breadcrumb">
                <a href="customer_dashboard.php">Dashboard</a>
                <span>/</span>
                <strong>My Requests</strong>
            </div>

            <section class="requests-hero">
                <div class="requests-hero-copy">
                    <span class="requests-eyebrow">CUSTOMER PORTAL</span>

                    <h1>My Service Requests</h1>

                    <p>
                        Monitor your submitted requests, review service details,
                        and keep track of their latest status.
                    </p>
                </div>

                <a href="browse_services.php" class="requests-primary-button">
                    <span>+</span> Browse Services
                </a>
            </section>

            <?php if (isset($messages[$message])): ?>
                <div class="requests-message" role="status">
                    <span class="requests-message-icon">✓</span>
                    <span><?= escape_html($messages[$message]) ?></span>
                </div>
            <?php endif; ?>

            <section class="requests-directory">

                <div class="requests-section-header">
                    <div>
                        <h2>Your Requests</h2>
                        <p>
                            Review your requests and access their full details.
                        </p>
                    </div>

                    <span class="requests-count">
                        <?= number_format($total_requests) ?>
                        <?= $total_requests === 1 ? "request" : "requests" ?>
                    </span>
                </div>

                <?php if ($total_requests > 0): ?>

                    <div class="requests-table-wrap">
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>REQUEST ID</th>
                                    <th>BUSINESS</th>
                                    <th>SERVICE</th>
                                    <th>PREFERRED DATE</th>
                                    <th>QTY.</th>
                                    <th>TOTAL AMOUNT</th>
                                    <th>STATUS</th>
                                    <th>DETAILS</th>
                                    <th>ACTION</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php while ($request = $requests->fetch_assoc()): ?>
                                    <tr>
                                        <td class="request-id">
                                            #<?= (int) $request["request_id"] ?>
                                        </td>

                                        <td class="request-business">
                                            <?= escape_html($request["business_name"]) ?>
                                        </td>

                                        <td class="request-service">
                                            <?= escape_html($request["service_name"]) ?>
                                        </td>

                                        <td>
                                            <?= escape_html(
                                                $request["preferred_date"] ?: "Not specified"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= (int) $request["quantity"] ?>
                                        </td>

                                        <td class="request-amount">
                                            ₱<?= number_format(
                                                (float) $request["total_amount"],
                                                2
                                            ) ?>
                                        </td>

                                        <td>
                                            <span class="request-status <?= escape_html(
                                                status_class($request["status"])
                                            ) ?>">
                                                <span class="status-dot"></span>
                                                <?= escape_html(
                                                    status_label($request["status"])
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <a
                                                class="request-view-button"
                                                href="request_details.php?id=<?= (int) $request["request_id"] ?>"
                                            >
                                                View
                                            </a>
                                        </td>

                                        <td>
                                            <?php if ($request["status"] === "pending"): ?>
                                                <form
                                                    class="request-cancel-form"
                                                    method="POST"
                                                    action="cancel_request.php"
                                                    onsubmit="return confirm('Are you sure you want to cancel this request?');"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="request_id"
                                                        value="<?= (int) $request["request_id"] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="request-cancel-button"
                                                    >
                                                        Cancel
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="request-action-unavailable">
                                                    —
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="requests-table-footer">
                        Showing <?= number_format($total_requests) ?>
                        <?= $total_requests === 1 ? "request" : "requests" ?>
                        in your account.
                    </div>

                <?php else: ?>

                    <div class="requests-empty-state">
                        <div class="requests-empty-icon">▧</div>

                        <h3>No Service Requests Yet</h3>

                        <p>
                            You haven't submitted any service requests yet.
                            Browse available services to get started.
                        </p>

                        <a
                            href="browse_services.php"
                            class="requests-primary-button"
                        >
                            <span>+</span> Find a Service
                        </a>
                    </div>

                <?php endif; ?>

            </section>

            <footer class="requests-footer">
                <p>
                    &copy; <?= date("Y") ?>
                    UniServe — Unified Business and Service Management Application
                </p>
            </footer>

        </main>
    </div>
</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
