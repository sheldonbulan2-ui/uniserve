<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit();
}

function escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$full_name = $_SESSION["full_name"] ?? "Administrator";
$initial = strtoupper(substr($full_name, 0, 1));

$counts = [
    "users" => 0,
    "businesses" => 0,
    "services" => 0,
    "requests" => 0,
    "transactions" => 0,
    "categories" => 0
];

$tables = [
    "users" => "users",
    "businesses" => "businesses",
    "services" => "services",
    "requests" => "service_requests",
    "transactions" => "transactions",
    "categories" => "categories"
];

foreach ($tables as $key => $table) {
    $result = $conn->query(
        "SELECT COUNT(*) AS total FROM $table"
    );

    if ($result) {
        $row = $result->fetch_assoc();
        $counts[$key] = (int)$row["total"];
        $result->free();
    } else {
        error_log("Dashboard query failed for {$table}: " . $conn->error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | UniServe</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="admin-dashboard">

<div class="layout admin-layout">

    <aside class="sidebar admin-sidebar">

        <div class="brand">
            <div class="brand-icon">U</div>
            <div>
                <h1>UniServe</h1>
                <p>Business &amp; Service Management</p>
            </div>
        </div>

        <h3>Main Menu</h3>

        <nav aria-label="Main navigation">
            <a class="active" href="admin_dashboard.php">
                <span class="nav-icon">▦</span> Dashboard
            </a>
            <a href="manage_users.php">
                <span class="nav-icon">♙</span> Manage Users
            </a>
            <a href="manage_businesses.php">
                <span class="nav-icon">▤</span> Manage Businesses
            </a>
            <a href="manage_categories.php">
                <span class="nav-icon">▦</span> Manage Categories
            </a>
            <a href="manage_services.php">
                <span class="nav-icon">◇</span> Manage Services
            </a>
            <a href="manage_requests.php">
                <span class="nav-icon">☷</span> Manage Requests
            </a>
            <a href="manage_transactions.php">
                <span class="nav-icon">▣</span> Manage Transactions
            </a>
        </nav>

        <div class="sidebar-bottom">
            <h3>Account</h3>
            <nav aria-label="Account navigation">
                <a href="profile.php">
                    <span class="nav-icon">♙</span> My Profile
                </a>
                <a class="logout-link" href="logout.php">
                    <span class="nav-icon">↪</span> Logout
                </a>
            </nav>
        </div>

    </aside>

    <main class="main-content admin-main">

        <header class="admin-topbar">
            <div>
                <h2>Admin Dashboard</h2>
                <p>Manage and monitor your UniServe platform</p>
            </div>

            <div class="admin-info">
                <span class="admin-avatar">
                    <?php echo escape($initial); ?>
                </span>
                <div>
                    <strong><?php echo escape($full_name); ?></strong>
                    <p>System Administrator</p>
                </div>
            </div>
        </header>

        <div class="admin-content">

            <section class="welcome-section">
                <div class="welcome-copy">
                    <span class="welcome-label">UNISERVE ADMINISTRATION</span>
                    <h2>Welcome back, <?php echo escape($full_name); ?>!</h2>
                    <p>
                        Manage users, businesses, services, and transactions
                        from one centralized dashboard.
                    </p>
                </div>

                <div class="welcome-date">
                    <span>Today's Date</span>
                    <strong><?php echo date("F j, Y"); ?></strong>
                </div>
            </section>

            <section class="admin-overview">
                <div class="admin-section-heading">
                    <div>
                        <h2>System Overview</h2>
                        <p>Summary of your current database records.</p>
                    </div>
                    <span class="overview-label">DATABASE OVERVIEW</span>
                </div>

                <div class="cards admin-stats">

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span class="stat-icon">♙</span>
                            <span>Total Users</span>
                        </div>
                        <p class="stat-number"><?php echo number_format($counts["users"]); ?></p>
                        <p class="stat-description">Registered system users</p>
                    </article>

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span class="stat-icon">▤</span>
                            <span>Total Businesses</span>
                        </div>
                        <p class="stat-number"><?php echo number_format($counts["businesses"]); ?></p>
                        <p class="stat-description">Registered businesses</p>
                    </article>

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span class="stat-icon">◇</span>
                            <span>Total Services</span>
                        </div>
                        <p class="stat-number"><?php echo number_format($counts["services"]); ?></p>
                        <p class="stat-description">Services recorded</p>
                    </article>

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span class="stat-icon">☷</span>
                            <span>Service Requests</span>
                        </div>
                        <p class="stat-number"><?php echo number_format($counts["requests"]); ?></p>
                        <p class="stat-description">Recorded service requests</p>
                    </article>

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span class="stat-icon">▣</span>
                            <span>Transactions</span>
                        </div>
                        <p class="stat-number"><?php echo number_format($counts["transactions"]); ?></p>
                        <p class="stat-description">Recorded transactions</p>
                    </article>

                    <article class="card admin-stat-card">
                        <div class="stat-heading">
                            <span class="stat-icon">▦</span>
                            <span>Categories</span>
                        </div>
                        <p class="stat-number"><?php echo number_format($counts["categories"]); ?></p>
                        <p class="stat-description">Service categories</p>
                    </article>

                </div>
            </section>

            <section class="quick-links admin-quick-links">
                <div class="admin-section-heading">
                    <div>
                        <h2>Quick Actions</h2>
                        <p>Navigate directly to management pages.</p>
                    </div>
                </div>

                <div class="action-grid admin-actions">

                    <a class="action-item admin-action" href="manage_users.php">
                        <span class="action-icon">♙</span>
                        <span>
                            <strong>Manage Users</strong>
                            <small>View and manage accounts</small>
                        </span>
                        <span class="action-arrow">→</span>
                    </a>

                    <a class="action-item admin-action" href="manage_businesses.php">
                        <span class="action-icon">▤</span>
                        <span>
                            <strong>Manage Businesses</strong>
                            <small>Review registered businesses</small>
                        </span>
                        <span class="action-arrow">→</span>
                    </a>

                    <a class="action-item admin-action" href="manage_categories.php">
                        <span class="action-icon">▦</span>
                        <span>
                            <strong>Manage Categories</strong>
                            <small>Organize service categories</small>
                        </span>
                        <span class="action-arrow">→</span>
                    </a>

                    <a class="action-item admin-action" href="manage_services.php">
                        <span class="action-icon">◇</span>
                        <span>
                            <strong>Manage Services</strong>
                            <small>Review listed services</small>
                        </span>
                        <span class="action-arrow">→</span>
                    </a>

                    <a class="action-item admin-action" href="manage_requests.php">
                        <span class="action-icon">☷</span>
                        <span>
                            <strong>Service Requests</strong>
                            <small>Monitor customer requests</small>
                        </span>
                        <span class="action-arrow">→</span>
                    </a>

                    <a class="action-item admin-action" href="manage_transactions.php">
                        <span class="action-icon">▣</span>
                        <span>
                            <strong>Transactions</strong>
                            <small>View transaction records</small>
                        </span>
                        <span class="action-arrow">→</span>
                    </a>

                </div>
            </section>

            <footer class="admin-footer">
                UniServe &copy; <?php echo date("Y"); ?>
                — Unified Business and Service Management Application
            </footer>

        </div>

    </main>

</div>

</body>
</html>