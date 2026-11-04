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

// 1. Fetch Request Statistics
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

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $db_counts = $result->fetch_assoc();
        if ($db_counts) {
            foreach ($counts as $key => $value) {
                $counts[$key] = (int) ($db_counts[$key] ?? 0);
            }
        }
    }
    $stmt->close();
}

// 2. Fetch Recent Requests (Latest 5)
$recent_requests = [];
$recent_stmt = $conn->prepare(
    "SELECT sr.request_id, sr.request_date, sr.preferred_date, sr.quantity, sr.total_amount, sr.status,
            s.service_name, b.business_name
     FROM service_requests sr
     JOIN services s ON sr.service_id = s.service_id
     JOIN businesses b ON s.business_id = b.business_id
     WHERE sr.customer_id = ?
     ORDER BY sr.created_at DESC
     LIMIT 5"
);

if ($recent_stmt) {
    $recent_stmt->bind_param("i", $user_id);
    if ($recent_stmt->execute()) {
        $res = $recent_stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $recent_requests[] = $row;
        }
    }
    $recent_stmt->close();
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$customer_name = $_SESSION["full_name"] ?? "Valued Customer";
$first_letter = strtoupper(substr($customer_name, 0, 1));
$customer_email = $_SESSION["email"] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal — UniServe</title>
    <link rel="stylesheet" href="assets/css/customer_dashboard.css">
</head>

<body class="customer-portal">
<div class="portal-layout">

    <!-- SIDEBAR -->
    <aside class="portal-sidebar">
        <a class="portal-brand" href="customer_dashboard.php">
            <div class="brand-badge">U</div>
            <div class="brand-text">
                <strong>UniServe</strong>
                <small>CUSTOMER PORTAL</small>
            </div>
        </a>

        <div class="nav-section-title">Main Menu</div>
        <nav class="portal-nav" aria-label="Customer navigation">
            <a class="nav-link active" href="customer_dashboard.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                </svg>
                <span>Dashboard</span>
            </a>

            <a class="nav-link" href="browse_services.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <span>Browse Services</span>
            </a>

            <a class="nav-link" href="my_requests.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>My Requests</span>
                <?php if ($counts["pending"] > 0): ?>
                    <span class="nav-badge-pill"><?= $counts["pending"] ?></span>
                <?php endif; ?>
            </a>

            <div class="nav-section-title">Preferences</div>
            <a class="nav-link" href="profile.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>My Profile</span>
            </a>
            <a class="nav-link" href="change_password.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span>Security</span>
            </a>
        </nav>

        <!-- Sidebar User Footer -->
        <div class="sidebar-user-card">
            <div class="user-snippet">
                <div class="user-avatar-circle"><?= e($first_letter) ?></div>
                <div class="user-meta">
                    <strong><?= e($customer_name) ?></strong>
                    <span>Customer Account</span>
                </div>
            </div>
            <a class="logout-icon-btn" href="logout.php" title="Log Out" aria-label="Log out">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </div>
    </aside>

    <!-- MAIN BODY -->
    <main class="portal-main">

        <!-- TOPBAR -->
        <header class="portal-topbar">
            <div class="topbar-title">
                <h1>Overview & Activity</h1>
                <p>Track your bookings, requests, and marketplace activity</p>
            </div>
            <div class="topbar-actions">
                <div class="date-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span><?= date("l, F j, Y") ?></span>
                </div>
                <a class="cta-btn-primary" href="browse_services.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>New Booking</span>
                </a>
            </div>
        </header>

        <!-- DASHBOARD CONTAINER -->
        <div class="portal-content-body">

            <!-- HERO WELCOME BANNER -->
            <section class="hero-welcome-card">
                <div class="hero-text-side">
                    <div class="hero-eyebrow">UNISERVE CUSTOMER HUB</div>
                    <h2>Welcome back, <?= e($customer_name) ?>!</h2>
                    <p>
                        Discover verified local businesses, manage your scheduled bookings, and monitor service requests in real-time.
                    </p>
                </div>
                <div class="hero-action-side">
                    <a class="cta-btn-primary" href="browse_services.php">
                        <span>Explore Services Catalog</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>
            </section>

            <!-- KPI STATS CARDS -->
            <section class="stats-kpi-grid">
                <!-- Total -->
                <article class="kpi-card kpi-total">
                    <div class="kpi-header">
                        <span>Total Requests</span>
                        <div class="kpi-icon-bubble">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                        </div>
                    </div>
                    <div class="kpi-value"><?= number_format($counts["total"]) ?></div>
                    <div class="kpi-subtext">All lifetime bookings</div>
                </article>

                <!-- Pending -->
                <article class="kpi-card kpi-pending">
                    <div class="kpi-header">
                        <span>Pending</span>
                        <div class="kpi-icon-bubble">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                    </div>
                    <div class="kpi-value"><?= number_format($counts["pending"]) ?></div>
                    <div class="kpi-subtext">Awaiting merchant review</div>
                </article>

                <!-- Accepted -->
                <article class="kpi-card kpi-accepted">
                    <div class="kpi-header">
                        <span>Accepted</span>
                        <div class="kpi-icon-bubble">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                    </div>
                    <div class="kpi-value"><?= number_format($counts["accepted"]) ?></div>
                    <div class="kpi-subtext">Confirmed by business</div>
                </article>

                <!-- In Progress -->
                <article class="kpi-card kpi-progress">
                    <div class="kpi-header">
                        <span>In Progress</span>
                        <div class="kpi-icon-bubble">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="kpi-value"><?= number_format($counts["in_progress"]) ?></div>
                    <div class="kpi-subtext">Active fulfillment</div>
                </article>

                <!-- Completed -->
                <article class="kpi-card kpi-completed">
                    <div class="kpi-header">
                        <span>Completed</span>
                        <div class="kpi-icon-bubble">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="16 12 12 8 8 12"></polyline>
                                <line x1="12" y1="16" x2="12" y2="8"></line>
                            </svg>
                        </div>
                    </div>
                    <div class="kpi-value"><?= number_format($counts["completed"]) ?></div>
                    <div class="kpi-subtext">Successfully delivered</div>
                </article>
            </section>

            <!-- RECENT SERVICE REQUESTS TABLE -->
            <section class="section-panel-card">
                <div class="panel-header">
                    <div class="panel-title">
                        <h3>Recent Service Requests</h3>
                        <p>Latest updates on your booked and requested services</p>
                    </div>
                    <a class="view-all-link" href="my_requests.php">
                        <span>View All Requests</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </a>
                </div>

                <?php if (!empty($recent_requests)): ?>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Service & Business</th>
                                    <th>Preferred Date</th>
                                    <th>Quantity</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_requests as $req): ?>
                                    <?php
                                        $statusClass = "status-" . preg_replace('/[^a-z0-9_]/', '', strtolower($req["status"]));
                                        $prefDate = !empty($req["preferred_date"]) ? date("M j, Y", strtotime($req["preferred_date"])) : "Not set";
                                    ?>
                                    <tr>
                                        <td class="service-cell">
                                            <strong><?= e($req["service_name"]) ?></strong>
                                            <small><?= e($req["business_name"]) ?></small>
                                        </td>
                                        <td><?= e($prefDate) ?></td>
                                        <td><?= (int) $req["quantity"] ?></td>
                                        <td><strong>₱<?= number_format((float) $req["total_amount"], 2) ?></strong></td>
                                        <td>
                                            <span class="badge-status <?= $statusClass ?>">
                                                <?= ucfirst(str_replace('_', ' ', $req["status"])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a class="table-action-link" href="request_details.php?id=<?= (int) $req["request_id"] ?>">
                                                <span>Details</span>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <polyline points="9 18 15 12 9 6"></polyline>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-data-state">
                        <div class="empty-icon-box">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <h4>No Service Requests Yet</h4>
                        <p>You haven't requested any services yet. Explore our verified marketplace to find services that match your needs.</p>
                        <a class="cta-btn-primary" href="browse_services.php" style="margin-top: 10px;">
                            <span>Browse Available Services</span>
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- QUICK ACCESS TILES -->
            <section class="quick-tiles-grid">
                <a class="quick-tile" href="browse_services.php">
                    <div class="tile-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </div>
                    <div class="tile-text">
                        <strong>Browse Storefronts</strong>
                        <small>Search top-rated categories & services</small>
                    </div>
                    <span class="tile-arrow">→</span>
                </a>

                <a class="quick-tile" href="my_requests.php">
                    <div class="tile-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                    </div>
                    <div class="tile-text">
                        <strong>My Request Log</strong>
                        <small>Review progress, history, and receipts</small>
                    </div>
                    <span class="tile-arrow">→</span>
                </a>

                <a class="quick-tile" href="profile.php">
                    <div class="tile-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="tile-text">
                        <strong>Account Settings</strong>
                        <small>Update profile info & security</small>
                    </div>
                    <span class="tile-arrow">→</span>
                </a>
            </section>

        </div>

        <!-- FOOTER -->
        <footer class="portal-footer">
            &copy; <?= date("Y") ?> UniServe Platform. All rights reserved. &bull; Customer Business &amp; Service Management
        </footer>

    </main>
</div>

<?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>
