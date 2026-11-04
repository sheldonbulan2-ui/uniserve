<?php
session_start();
require_once "config.php";

// Strict Admin Route Guard
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

$counts = [
    "users" => 0,
    "businesses" => 0,
    "pending_businesses" => 0,
    "services" => 0,
    "requests" => 0,
    "transactions" => 0,
    "categories" => 0
];

// Fetch Counts
$tables = [
    "users" => "users",
    "businesses" => "businesses",
    "services" => "services",
    "requests" => "service_requests",
    "transactions" => "transactions",
    "categories" => "categories"
];

foreach ($tables as $key => $table) {
    $res = $conn->query("SELECT COUNT(*) AS total FROM $table");
    if ($res) {
        $row = $res->fetch_assoc();
        $counts[$key] = (int)($row["total"] ?? 0);
        $res->free();
    }
}

// Fetch Pending Businesses Count
$pendingRes = $conn->query("SELECT COUNT(*) AS total FROM businesses WHERE status = 'pending'");
if ($pendingRes) {
    $row = $pendingRes->fetch_assoc();
    $counts["pending_businesses"] = (int)($row["total"] ?? 0);
    $pendingRes->free();
}

// Fetch 5 Recent Businesses awaiting moderation
$recent_businesses = [];
$bQuery = "SELECT b.business_id, b.business_name, b.status, b.created_at, u.full_name as owner_name, c.category_name 
           FROM businesses b 
           JOIN users u ON b.owner_id = u.user_id 
           LEFT JOIN categories c ON b.category_id = c.category_id 
           ORDER BY b.created_at DESC LIMIT 5";
$bRes = $conn->query($bQuery);
if ($bRes) {
    while ($row = $bRes->fetch_assoc()) {
        $recent_businesses[] = $row;
    }
    $bRes->free();
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$admin_name = $_SESSION["full_name"] ?? "System Admin";
$admin_initial = strtoupper(substr($admin_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Dashboard — UniServe Control Center</title>
    <link rel="stylesheet" href="assets/css/admin_theme.css">
</head>

<body class="admin-body">
<div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">
        <a class="admin-brand" href="admin_dashboard.php">
            <div class="brand-badge">U</div>
            <div class="brand-text">
                <strong>UniServe</strong>
                <small>ADMIN CONTROL CENTER</small>
            </div>
        </a>

        <div class="nav-heading">Platform Overview</div>
        <nav class="admin-nav" aria-label="Admin Navigation">
            <a class="admin-nav-link active" href="admin_dashboard.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                </svg>
                <span>Dashboard</span>
            </a>

            <div class="nav-heading">Management & Moderation</div>
            <a class="admin-nav-link" href="manage_businesses.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
                <span>Storefront Approvals</span>
                <?php if ($counts["pending_businesses"] > 0): ?>
                    <span style="margin-left:auto; background:rgba(245,158,11,0.2); color:#fbbf24; padding:2px 7px; border-radius:999px; font-size:11px; font-weight:800;">
                        <?= $counts["pending_businesses"] ?>
                    </span>
                <?php endif; ?>
            </a>

            <a class="admin-nav-link" href="manage_categories.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                <span>Categories</span>
            </a>

            <a class="admin-nav-link" href="manage_services.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                    <polyline points="2 17 12 22 22 17"></polyline>
                    <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
                <span>Services Audit</span>
            </a>

            <a class="admin-nav-link" href="manage_requests.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                <span>Order Requests</span>
            </a>

            <a class="admin-nav-link" href="manage_transactions.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                <span>Transactions Ledger</span>
            </a>

            <a class="admin-nav-link" href="manage_users.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Users & Roles</span>
            </a>
        </nav>

        <!-- Sidebar Footer -->
        <div class="admin-sidebar-footer">
            <div class="admin-profile-snippet">
                <div class="admin-avatar"><?= e($admin_initial) ?></div>
                <div class="admin-meta">
                    <strong><?= e($admin_name) ?></strong>
                    <span>Administrator</span>
                </div>
            </div>
            <a class="admin-logout-btn" href="logout.php" title="Sign Out">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </div>
    </aside>

    <!-- MAIN BODY -->
    <main class="admin-main">

        <!-- Topbar -->
        <header class="admin-topbar">
            <div class="admin-topbar-title">
                <h1>Platform Governance Center</h1>
                <p>Real-time oversight of verified merchants, listings, and financial records</p>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="background:var(--bg-card); border:1px solid var(--border-subtle); padding:6px 14px; border-radius:8px; font-size:12px; color:var(--text-muted); font-weight:600;">
                    <?= date("l, F j, Y") ?>
                </span>
                <a class="btn-primary" href="manage_businesses.php">
                    <span>Moderate Merchants</span>
                </a>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="admin-content-body">

            <!-- KPI Metric Cards Grid -->
            <section class="admin-stat-grid">
                <!-- Registered Businesses -->
                <article class="admin-kpi-card">
                    <div class="admin-kpi-header">
                        <span>Total Merchants</span>
                        <div class="admin-kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="admin-kpi-number"><?= number_format($counts["businesses"]) ?></div>
                    <div class="admin-kpi-desc"><?= $counts["pending_businesses"] ?> pending verification</div>
                </article>

                <!-- Users -->
                <article class="admin-kpi-card">
                    <div class="admin-kpi-header">
                        <span>Platform Users</span>
                        <div class="admin-kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg>
                        </div>
                    </div>
                    <div class="admin-kpi-number"><?= number_format($counts["users"]) ?></div>
                    <div class="admin-kpi-desc">Customers, merchants, and admins</div>
                </article>

                <!-- Listed Services -->
                <article class="admin-kpi-card">
                    <div class="admin-kpi-header">
                        <span>Active Services</span>
                        <div class="admin-kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                <polyline points="2 17 12 22 22 17"></polyline>
                            </svg>
                        </div>
                    </div>
                    <div class="admin-kpi-number"><?= number_format($counts["services"]) ?></div>
                    <div class="admin-kpi-desc">Across <?= $counts["categories"] ?> categories</div>
                </article>

                <!-- Service Requests -->
                <article class="admin-kpi-card">
                    <div class="admin-kpi-header">
                        <span>Total Bookings</span>
                        <div class="admin-kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                        </div>
                    </div>
                    <div class="admin-kpi-number"><?= number_format($counts["requests"]) ?></div>
                    <div class="admin-kpi-desc">Customer order transactions</div>
                </article>

                <!-- Transactions -->
                <article class="admin-kpi-card">
                    <div class="admin-kpi-header">
                        <span>Transactions Audit</span>
                        <div class="admin-kpi-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="admin-kpi-number"><?= number_format($counts["transactions"]) ?></div>
                    <div class="admin-kpi-desc">Financial audit trail records</div>
                </article>
            </section>

            <!-- Recent Business Registrations Table -->
            <section class="admin-panel">
                <div class="admin-panel-header">
                    <div>
                        <h3>Recent Merchant Submissions</h3>
                        <p>Latest business storefronts awaiting administrative status verification</p>
                    </div>
                    <a class="btn-primary" href="manage_businesses.php" style="font-size:11px;">
                        <span>View All Merchants →</span>
                    </a>
                </div>

                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Business Name</th>
                                <th>Owner</th>
                                <th>Category</th>
                                <th>Date Submitted</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_businesses)): ?>
                                <?php foreach ($recent_businesses as $b): ?>
                                    <tr>
                                        <td><strong><?= e($b["business_name"]) ?></strong></td>
                                        <td><?= e($b["owner_name"]) ?></td>
                                        <td><span style="color:var(--text-muted);"><?= e($b["category_name"] ?? "General") ?></span></td>
                                        <td><?= date("M j, Y", strtotime($b["created_at"])) ?></td>
                                        <td>
                                            <span class="admin-badge badge-<?= strtolower($b["status"]) ?>">
                                                <?= ucfirst($b["status"]) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($b["status"] === "pending"): ?>
                                                <form method="POST" action="update_business_status.php" style="display:inline-flex; gap:6px;">
                                                    <input type="hidden" name="business_id" value="<?= (int)$b["business_id"] ?>">
                                                    <button type="submit" name="status" value="approved" class="btn-sm-action btn-approve">Approve</button>
                                                    <button type="submit" name="status" value="rejected" class="btn-sm-action btn-reject">Reject</button>
                                                </form>
                                            <?php else: ?>
                                                <a href="manage_businesses.php" class="btn-sm-action" style="background:rgba(57,132,246,0.1); color:#7fb1ff; border:1px solid rgba(57,132,246,0.2);">Manage</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">
                                        No merchant registrations found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>

        <footer class="admin-footer">
            UniServe &copy; <?= date("Y") ?> &bull; Executive Administrative Control Console &bull; PostgreSQL Powered
        </footer>

    </main>
</div>

<?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>
