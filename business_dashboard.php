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

function escape($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

$owner_name = $_SESSION["full_name"] ?? "Business Owner";
$initial = strtoupper(substr($owner_name, 0, 1));

$counts = [
    "businesses" => 0,
    "services" => 0,
    "requests" => 0,
    "pending_requests" => 0
];

$stmt = $conn->prepare(
    "SELECT
        (SELECT COUNT(*)
         FROM businesses
         WHERE owner_id = ?) AS businesses,

        (SELECT COUNT(*)
         FROM services s
         INNER JOIN businesses b
            ON s.business_id = b.business_id
         WHERE b.owner_id = ?) AS services,

        (SELECT COUNT(*)
         FROM service_requests sr
         INNER JOIN services s
            ON sr.service_id = s.service_id
         INNER JOIN businesses b
            ON s.business_id = b.business_id
         WHERE b.owner_id = ?) AS requests,

        (SELECT COUNT(*)
         FROM service_requests sr
         INNER JOIN services s
            ON sr.service_id = s.service_id
         INNER JOIN businesses b
            ON s.business_id = b.business_id
         WHERE b.owner_id = ?
           AND sr.status = 'pending') AS pending_requests"
);

if ($stmt) {
    $stmt->bind_param(
        "iiii",
        $user_id,
        $user_id,
        $user_id,
        $user_id
    );

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            foreach ($counts as $key => $value) {
                $counts[$key] = (int) ($row[$key] ?? 0);
            }

            $result->free();
        }
    } else {
        error_log("Business dashboard query failed: " . $stmt->error);
    }

    $stmt->close();
} else {
    error_log("Business dashboard prepare failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Business Dashboard | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="admin-dashboard business-dashboard">

<div class="layout admin-layout">

    <!-- SIDEBAR -->
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

            <a class="active" href="business_dashboard.php">
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
    <main class="main-content admin-main">

        <!-- TOPBAR -->
        <header class="admin-topbar">

            <div>
                <h2>Business Dashboard</h2>
                <p>Manage and monitor your business activities</p>
            </div>

            <div class="admin-info">

                <span class="admin-avatar">
                    <?= escape($initial) ?>
                </span>

                <div>
                    <strong><?= escape($owner_name) ?></strong>
                    <p>Business Owner</p>
                </div>

            </div>

        </header>

        <div class="admin-content">

            <!-- WELCOME PANEL -->
            <section class="welcome-section">

                <div class="welcome-copy">

                    <span class="welcome-label">
                        UNISERVE BUSINESS MANAGEMENT
                    </span>

                    <h2>
                        Welcome back, <?= escape($owner_name) ?>!
                    </h2>

                    <p>
                        Manage your registered businesses, maintain your
                        services, and monitor customer requests from one
                        centralized dashboard.
                    </p>

                </div>

                <div class="welcome-date">
                    <span>Today's Date</span>
                    <strong><?= date("F j, Y") ?></strong>
                </div>

            </section>

            <!-- BUSINESS OVERVIEW -->
            <section class="admin-overview">

                <div class="admin-section-heading">

                    <div>
                        <h2>Business Overview</h2>
                        <p>Summary of your business records and activities.</p>
                    </div>

                    <span class="overview-label">
                        ACCOUNT OVERVIEW
                    </span>

                </div>

                <div class="cards admin-stats">

                    <!-- BUSINESSES -->
                    <article class="card admin-stat-card">

                        <div class="stat-heading">
                            <span>My Businesses</span>
                            <span class="stat-icon">▤</span>
                        </div>

                        <p class="stat-number">
                            <?= number_format($counts["businesses"]) ?>
                        </p>

                        <p class="stat-description">
                            Businesses registered to your account
                        </p>

                    </article>

                    <!-- SERVICES -->
                    <article class="card admin-stat-card">

                        <div class="stat-heading">
                            <span>My Services</span>
                            <span class="stat-icon">◇</span>
                        </div>

                        <p class="stat-number">
                            <?= number_format($counts["services"]) ?>
                        </p>

                        <p class="stat-description">
                            Services under your businesses
                        </p>

                    </article>

                    <!-- TOTAL REQUESTS -->
                    <article class="card admin-stat-card">

                        <div class="stat-heading">
                            <span>Total Requests</span>
                            <span class="stat-icon">☷</span>
                        </div>

                        <p class="stat-number">
                            <?= number_format($counts["requests"]) ?>
                        </p>

                        <p class="stat-description">
                            Customer requests for your services
                        </p>

                    </article>

                    <!-- PENDING REQUESTS -->
                    <article class="card admin-stat-card">

                        <div class="stat-heading">
                            <span>Pending Requests</span>
                            <span class="stat-icon">◷</span>
                        </div>

                        <p class="stat-number">
                            <?= number_format($counts["pending_requests"]) ?>
                        </p>

                        <p class="stat-description">
                            Requests awaiting your attention
                        </p>

                    </article>

                </div>

            </section>

            <!-- QUICK ACTIONS -->
            <section class="quick-links admin-quick-links">

                <div class="admin-section-heading">

                    <div>
                        <h2>Quick Actions</h2>
                        <p>Quickly access your business management features.</p>
                    </div>

                </div>

                <div class="action-grid admin-actions">

                    <a
                        class="action-item admin-action"
                        href="register_business.php"
                    >
                        <span class="action-icon">＋</span>

                        <span>
                            <strong>Register Business</strong>
                            <small>Add a new business to your account</small>
                        </span>

                        <span class="action-arrow">→</span>
                    </a>

                    <a
                        class="action-item admin-action"
                        href="my_businesses.php"
                    >
                        <span class="action-icon">▤</span>

                        <span>
                            <strong>My Businesses</strong>
                            <small>View and manage your businesses</small>
                        </span>

                        <span class="action-arrow">→</span>
                    </a>

                    <a
                        class="action-item admin-action"
                        href="my_services.php"
                    >
                        <span class="action-icon">◇</span>

                        <span>
                            <strong>Manage Services</strong>
                            <small>Maintain your listed services</small>
                        </span>

                        <span class="action-arrow">→</span>
                    </a>

                    <a
                        class="action-item admin-action"
                        href="business_requests.php"
                    >
                        <span class="action-icon">☷</span>

                        <span>
                            <strong>Business Requests</strong>
                            <small>Review customer service requests</small>
                        </span>

                        <span class="action-arrow">→</span>
                    </a>

                    <a
                        class="action-item admin-action"
                        href="profile.php"
                    >
                        <span class="action-icon">♙</span>

                        <span>
                            <strong>My Profile</strong>
                            <small>View your account information</small>
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

</body>
</html>

<?php
$conn->close();
?>