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

$counts = [
    "total" => 0,
    "pending" => 0,
    "accepted" => 0,
    "in_progress" => 0,
    "completed" => 0,
    "cancelled" => 0
];

$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
        COALESCE(SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END), 0) AS accepted,
        COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) AS in_progress,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
        COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled
     FROM service_requests
     WHERE customer_id = ?"
);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to load your dashboard. Please try again later.");
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    http_response_code(500);
    exit("Unable to load your dashboard. Please try again later.");
}

$result = $stmt->get_result();
$db_counts = $result->fetch_assoc();

if ($db_counts) {
    foreach ($counts as $key => $value) {
        $counts[$key] = (int) ($db_counts[$key] ?? 0);
    }
}

$stmt->close();

function escape_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$customer_name = $_SESSION["full_name"] ?? "Customer";
$first_letter = strtoupper(substr($customer_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Dashboard | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="admin-dashboard customer-dashboard">

<div class="layout admin-layout">

    
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
            <a class="active" href="customer_dashboard.php">
                <span class="nav-icon">▦</span>
                Dashboard
            </a>

            <a href="browse_services.php">
                <span class="nav-icon">⌕</span>
                Browse Services
            </a>

            <a href="my_requests.php">
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

    <!-- MAIN CONTENT -->
    <main class="main-content admin-main">

        <!-- TOPBAR -->
        <header class="admin-topbar">

            <div>
                <h2>Customer Dashboard</h2>
                <p>Manage and monitor your service requests</p>
            </div>

            <div class="admin-info">

                <span class="admin-avatar">
                    <?= escape_html($first_letter) ?>
                </span>

                <div>
                    <strong><?= escape_html($customer_name) ?></strong>
                    <p>Customer Account</p>
                </div>

            </div>

        </header>

        <div class="admin-content">

            <!-- WELCOME SECTION -->
            <section class="welcome-section">

                <div class="welcome-copy">
                    <span class="welcome-label">
                        UNISERVE CUSTOMER PORTAL
                    </span>

                    <h2>
                        Welcome back,
                        <?= escape_html($customer_name) ?>!
                    </h2>

                    <p>
                        Explore available services, submit service requests,
                        and monitor the progress of your requests from
                        one convenient dashboard.
                    </p>
                </div>

                <div class="welcome-date">
                    <span>Today's Date</span>
                    <strong><?= date("F j, Y") ?></strong>
                </div>

            </section>

            <!-- REQUEST STATISTICS -->
            <section class="admin-overview">

                <div class="admin-section-heading">
                    <div>
                        <h2>My Request Statistics</h2>
                        <p>
                            Overview of your current service requests.
                        </p>
                    </div>

                    <span class="overview-label">
                        REQUEST OVERVIEW
                    </span>
                </div>

                <div class="cards admin-stats">

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span>Total Requests</span>
                            <span class="stat-icon">▤</span>
                        </div>

                        <p class="stat-number">
                            <?= number_format($counts["total"]) ?>
                        </p>

                        <p class="stat-description">
                            All your service requests
                        </p>
                    </article>

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span>Pending</span>
                            <span class="stat-icon">◷</span>
                        </div>

                        <p class="stat-number">
                            <?= number_format($counts["pending"]) ?>
                        </p>

                        <p class="stat-description">
                            Waiting for a response
                        </p>
                    </article>

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span>Accepted</span>
                            <span class="stat-icon">✓</span>
                        </div>

                        <p class="stat-number">
                            <?= number_format($counts["accepted"]) ?>
                        </p>

                        <p class="stat-description">
                            Requests accepted
                        </p>
                    </article>

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span>In Progress</span>
                            <span class="stat-icon">⟳</span>
                        </div>

                        <p class="stat-number">
                            <?= number_format($counts["in_progress"]) ?>
                        </p>

                        <p class="stat-description">
                            Services being processed
                        </p>
                    </article>

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span>Completed</span>
                            <span class="stat-icon">✓</span>
                        </div>

                        <p class="stat-number">
                            <?= number_format($counts["completed"]) ?>
                        </p>

                        <p class="stat-description">
                            Successfully completed
                        </p>
                    </article>

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span>Cancelled</span>
                            <span class="stat-icon">×</span>
                        </div>

                        <p class="stat-number">
                            <?= number_format($counts["cancelled"]) ?>
                        </p>

                        <p class="stat-description">
                            Cancelled requests
                        </p>
                    </article>

                </div>

            </section>

            <!-- QUICK ACTIONS -->
            <section class="admin-quick-links">

                <div class="admin-section-heading">
                    <div>
                        <h2>Quick Actions</h2>
                        <p>
                            Quickly access your customer services.
                        </p>
                    </div>
                </div>

                <div class="action-grid admin-actions">

                    <a class="action-item admin-action"
                       href="browse_services.php">

                        <span class="action-icon">⌕</span>

                        <span>
                            <strong>Browse Services</strong>
                            <small>
                                Explore available services
                            </small>
                        </span>

                        <span class="action-arrow">→</span>
                    </a>

                    <a class="action-item admin-action"
                       href="my_requests.php">

                        <span class="action-icon">☷</span>

                        <span>
                            <strong>My Requests</strong>
                            <small>
                                Track your submitted requests
                            </small>
                        </span>

                        <span class="action-arrow">→</span>
                    </a>

                    <a class="action-item admin-action"
                       href="profile.php">

                        <span class="action-icon">♙</span>

                        <span>
                            <strong>My Profile</strong>
                            <small>
                                View and manage your account
                            </small>
                        </span>

                        <span class="action-arrow">→</span>
                    </a>

                </div>

            </section>

            <!-- FOOTER -->
            <footer class="admin-footer">
                UniServe &copy; <?= date("Y") ?>
                — Unified Business and Service Management Application
            </footer>

        </div>

    </main>

</div>

<?php $conn->close(); ?>

</body>
</html>