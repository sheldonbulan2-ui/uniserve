
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
$message = "";
$message_type = "";

function escape_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function status_label($status) {
    return ucwords(str_replace("_", " ", $status));
}

function show_database_error() {
    http_response_code(500);
    exit("A database error occurred. Please try again later.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $request_id = filter_input(
        INPUT_POST,
        "request_id",
        FILTER_VALIDATE_INT
    );

    $new_status = $_POST["status"] ?? "";

    $allowed_statuses = [
        "accepted",
        "rejected",
        "in_progress",
        "completed"
    ];

    if (
        !$request_id ||
        $request_id < 1 ||
        !in_array($new_status, $allowed_statuses, true)
    ) {
        $message = "Invalid request or status.";
        $message_type = "error";
    } else {
        $update_sql = "UPDATE service_requests sr
                       INNER JOIN services s
                           ON sr.service_id = s.service_id
                       INNER JOIN businesses b
                           ON s.business_id = b.business_id
                       SET sr.status = ?
                       WHERE sr.request_id = ?
                         AND b.owner_id = ?
                         AND (
                             (sr.status = 'pending'
                              AND ? IN ('accepted', 'rejected'))
                             OR
                             (sr.status = 'accepted'
                              AND ? = 'in_progress')
                             OR
                             (sr.status = 'in_progress'
                              AND ? = 'completed')
                         )";

        $update_stmt = $conn->prepare($update_sql);

        if (!$update_stmt) {
            show_database_error();
        }

        $update_stmt->bind_param(
            "siisss",
            $new_status,
            $request_id,
            $user_id,
            $new_status,
            $new_status,
            $new_status
        );

        if (!$update_stmt->execute()) {
            $update_stmt->close();
            show_database_error();
        }

        if ($update_stmt->affected_rows > 0) {
            $message = "Request status updated successfully.";
            $message_type = "success";
        } else {
            $message = "The request could not be updated. It may already have been changed or may no longer be eligible for this action.";
            $message_type = "error";
        }

        $update_stmt->close();
    }
}

$sql = "SELECT
            sr.request_id,
            sr.request_date,
            sr.preferred_date,
            sr.quantity,
            sr.total_amount,
            sr.status,
            sr.notes,
            u.full_name AS customer_name,
            u.email AS customer_email,
            s.service_name,
            b.business_name
        FROM service_requests sr
        INNER JOIN users u
            ON sr.customer_id = u.user_id
        INNER JOIN services s
            ON sr.service_id = s.service_id
        INNER JOIN businesses b
            ON s.business_id = b.business_id
        WHERE b.owner_id = ?
        ORDER BY sr.created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    show_database_error();
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    show_database_error();
}

$requests = $stmt->get_result();

$total_requests = $requests->num_rows;
$pending_count = 0;
$accepted_count = 0;
$progress_count = 0;
$completed_count = 0;

/*
 * Count requests for the summary cards.
 * Store the fetched records so the table can display them afterward.
 */
$request_rows = [];

while ($row = $requests->fetch_assoc()) {
    $request_rows[] = $row;

    switch ($row["status"]) {
        case "pending":
            $pending_count++;
            break;

        case "accepted":
            $accepted_count++;
            break;

        case "in_progress":
            $progress_count++;
            break;

        case "completed":
            $completed_count++;
            break;
    }
}

$owner_name = $_SESSION["full_name"] ?? "Business Owner";
$owner_initial = strtoupper(substr($owner_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Requests | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/business_requests.css">
</head>

<body class="business-requests-page">

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

            <a href="my_services.php">
                <span class="nav-icon">◇</span>
                My Services
            </a>

            <a class="active" href="business_requests.php">
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

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">

            <div class="topbar-heading">
                <div class="topbar-title">Customer Requests</div>

                <div class="topbar-subtitle">
                    Review and manage your customer service requests
                </div>
            </div>

            <div class="user-info">
                <div class="avatar">
                    <?= escape_html($owner_initial) ?>
                </div>

                <div class="user-details">
                    <div class="user-name">
                        <?= escape_html($owner_name) ?>
                    </div>

                    <div class="user-role">Business Owner</div>
                </div>
            </div>

        </header>

        <!-- PAGE CONTENT -->
        <main class="content">

            <!-- PAGE HEADING -->
            <section class="page-heading">

                <div class="page-heading-text">
                    <p class="eyebrow">BUSINESS OWNER PORTAL</p>

                    <h2>Customer Requests</h2>

                    <p>
                        View customer details, review service requests,
                        and track the progress of your services.
                    </p>
                </div>

                <a class="button secondary"
                   href="business_dashboard.php">
                    <span>&#8592;</span>
                    Back to Dashboard
                </a>

            </section>

            <!-- NOTIFICATION -->
            <?php if ($message !== ""): ?>
                <div class="alert <?= $message_type === "success" ? "alert-success" : "alert-error" ?>"
                     role="status">

                    <span class="alert-icon">
                        <?= $message_type === "success" ? "&#10003;" : "&#9888;" ?>
                    </span>

                    <span>
                        <?= escape_html($message) ?>
                    </span>

                </div>
            <?php endif; ?>

            <!-- SUMMARY CARDS -->
            <section class="summary-grid">

                <div class="summary-card">
                    <div class="summary-icon icon-blue">
                        &#128203;
                    </div>

                    <div class="summary-info">
                        <p>Total Requests</p>
                        <h3><?= number_format($total_requests) ?></h3>
                        <span>All customer requests</span>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon icon-yellow">
                        &#9203;
                    </div>

                    <div class="summary-info">
                        <p>Pending</p>
                        <h3><?= number_format($pending_count) ?></h3>
                        <span>Awaiting your decision</span>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon icon-purple">
                        &#128736;
                    </div>

                    <div class="summary-info">
                        <p>In Progress</p>
                        <h3><?= number_format($progress_count) ?></h3>
                        <span>Services being handled</span>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon icon-green">
                        &#10003;
                    </div>

                    <div class="summary-info">
                        <p>Completed</p>
                        <h3><?= number_format($completed_count) ?></h3>
                        <span>Finished service requests</span>
                    </div>
                </div>

            </section>

            <!-- REQUESTS TABLE -->
            <section class="requests-panel">

                <div class="panel-heading">

                    <div>
                        <h3>Service Request Records</h3>

                        <p>
                            Review request information and update
                            the status of each service.
                        </p>
                    </div>

                    <div class="record-count">
                        <?= number_format($total_requests) ?>
                        <?= $total_requests === 1 ? "Request" : "Requests" ?>
                    </div>

                </div>

                <div class="table-container">

                    <?php if ($total_requests > 0): ?>

                        <table>
                            <thead>
                                <tr>
                                    <th>Request</th>
                                    <th>Customer</th>
                                    <th>Business</th>
                                    <th>Service</th>
                                    <th>Request Date</th>
                                    <th>Preferred Date</th>
                                    <th>Quantity</th>
                                    <th>Total Amount</th>
                                    <th>Notes</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($request_rows as $request): ?>

                                    <tr>

                                        <td>
                                            <span class="request-id">
                                                #<?= (int) $request["request_id"] ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="customer-cell">
                                                <strong>
                                                    <?= escape_html($request["customer_name"]) ?>
                                                </strong>

                                                <span>
                                                    <?= escape_html($request["customer_email"]) ?>
                                                </span>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="business-name">
                                                <?= escape_html($request["business_name"]) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="service-name">
                                                <?= escape_html($request["service_name"]) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="date-value">
                                                <?= escape_html($request["request_date"]) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="date-value">
                                                <?= escape_html($request["preferred_date"] ?? "Not specified") ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="quantity-value">
                                                <?= (int) $request["quantity"] ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="amount-value">
                                                &#8369;<?= number_format((float) $request["total_amount"], 2) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="notes-cell">
                                                <?php
                                                $notes = trim((string) ($request["notes"] ?? ""));
                                                ?>

                                                <?php if ($notes !== ""): ?>
                                                    <?= nl2br(escape_html($notes)) ?>
                                                <?php else: ?>
                                                    <span class="no-notes">
                                                        No notes provided
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="status status-<?= escape_html($request["status"]) ?>">
                                                <?= escape_html(status_label($request["status"])) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="action-cell">

                                                <?php if ($request["status"] === "pending"): ?>

                                                    <form method="POST"
                                                          action="business_requests.php"
                                                          class="action-form">

                                                        <input type="hidden"
                                                               name="request_id"
                                                               value="<?= (int) $request["request_id"] ?>">

                                                        <select name="status"
                                                                aria-label="Choose request action"
                                                                required>
                                                            <option value="">Choose action</option>
                                                            <option value="accepted">Accept Request</option>
                                                            <option value="rejected">Reject Request</option>
                                                        </select>

                                                        <button type="submit"
                                                                class="action-button update-button">
                                                            Update Status
                                                        </button>

                                                    </form>

                                                <?php elseif ($request["status"] === "accepted"): ?>

                                                    <form method="POST"
                                                          action="business_requests.php"
                                                          class="action-form">

                                                        <input type="hidden"
                                                               name="request_id"
                                                               value="<?= (int) $request["request_id"] ?>">

                                                        <input type="hidden"
                                                               name="status"
                                                               value="in_progress">

                                                        <button type="submit"
                                                                class="action-button progress-button">
                                                            Start Service
                                                        </button>

                                                    </form>

                                                <?php elseif ($request["status"] === "in_progress"): ?>

                                                    <form method="POST"
                                                          action="business_requests.php"
                                                          class="action-form">

                                                        <input type="hidden"
                                                               name="request_id"
                                                               value="<?= (int) $request["request_id"] ?>">

                                                        <input type="hidden"
                                                               name="status"
                                                               value="completed">

                                                        <button type="submit"
                                                                class="action-button complete-button">
                                                            Mark Completed
                                                        </button>

                                                    </form>

                                                <?php elseif ($request["status"] === "rejected"): ?>

                                                    <span class="no-action">
                                                        Request Rejected
                                                    </span>

                                                <?php elseif ($request["status"] === "completed"): ?>

                                                    <span class="no-action">
                                                        Service Completed
                                                    </span>

                                                <?php else: ?>

                                                    <span class="no-action">
                                                        No action available
                                                    </span>

                                                <?php endif; ?>

                                            </div>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>
                        </table>

                    <?php else: ?>

                        <div class="empty-state">

                            <div class="empty-icon">
                                &#128203;
                            </div>

                            <h4>No Customer Requests Yet</h4>

                            <p>
                                Customer service requests for your businesses
                                will appear here once customers submit them.
                            </p>

                            <a class="button secondary"
                               href="my_services.php">
                                View My Services
                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <footer class="page-footer">
                &copy; <?= date("Y") ?> UniServe. All rights reserved.
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
