<?php
/**
 * UniServe Enterprise Admin Sidebar Navigation
 */
if (!defined('UNISERVE_ADMIN_LAYOUT')) {
    exit('Direct access not permitted.');
}

// Fetch pending counts for live badge notification
$pendingBusinessesCount = 0;
$pendingRequestsCount = 0;

if (isset($conn) && !$conn->connect_error) {
    try {
        $pRes = $conn->query("SELECT COUNT(*) AS c FROM businesses WHERE status = 'pending'");
        if ($pRes) {
            $pendingBusinessesCount = (int)($pRes->fetch_assoc()['c'] ?? 0);
            $pRes->free();
        }
        $rRes = $conn->query("SELECT COUNT(*) AS c FROM service_requests WHERE status = 'pending'");
        if ($rRes) {
            $pendingRequestsCount = (int)($rRes->fetch_assoc()['c'] ?? 0);
            $rRes->free();
        }
    } catch (Throwable $e) {}
}

$currentAdminName = $_SESSION['full_name'] ?? 'Administrator';
$adminInitial = strtoupper(substr($currentAdminName, 0, 1));
$activeNav = $activeNav ?? 'dashboard';
?>
<aside class="admin-sidebar">
    <a class="admin-brand" href="admin_dashboard.php">
        <div class="brand-badge">U</div>
        <div class="brand-text">
            <strong>UniServe</strong>
            <small>ADMIN GOVERNANCE</small>
        </div>
    </a>

    <div class="nav-heading">Platform Oversight</div>

    <nav class="admin-nav" aria-label="Main Governance Navigation">
        <!-- 1. Dashboard -->
        <a class="admin-nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>" href="admin_dashboard.php">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
            </svg>
            <span>Overview</span>
        </a>

        <!-- 2. Users -->
        <a class="admin-nav-link <?= $activeNav === 'users' ? 'active' : '' ?>" href="manage_users.php">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span>Users & Accounts</span>
        </a>

        <!-- 3. Businesses -->
        <a class="admin-nav-link <?= $activeNav === 'businesses' ? 'active' : '' ?>" href="manage_businesses.php">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            <span style="flex: 1;">Merchants</span>
            <?php if ($pendingBusinessesCount > 0): ?>
                <span class="admin-badge badge-pending" style="font-size: 10px; padding: 2px 7px;"><?= $pendingBusinessesCount ?></span>
            <?php endif; ?>
        </a>

        <!-- 4. Categories -->
        <a class="admin-nav-link <?= $activeNav === 'categories' ? 'active' : '' ?>" href="manage_categories.php">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                <line x1="7" y1="7" x2="7.01" y2="7"></line>
            </svg>
            <span>Taxonomy</span>
        </a>

        <!-- 5. Services -->
        <a class="admin-nav-link <?= $activeNav === 'services' ? 'active' : '' ?>" href="manage_services.php">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                <polyline points="2 17 12 22 22 17"></polyline>
                <polyline points="2 12 12 17 22 12"></polyline>
            </svg>
            <span>Service Catalog</span>
        </a>

        <!-- 6. Requests -->
        <a class="admin-nav-link <?= $activeNav === 'requests' ? 'active' : '' ?>" href="manage_requests.php">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <span style="flex: 1;">Bookings & Orders</span>
            <?php if ($pendingRequestsCount > 0): ?>
                <span class="admin-badge badge-pending" style="font-size: 10px; padding: 2px 7px;"><?= $pendingRequestsCount ?></span>
            <?php endif; ?>
        </a>

        <!-- 7. Transactions -->
        <a class="admin-nav-link <?= $activeNav === 'transactions' ? 'active' : '' ?>" href="manage_transactions.php">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                <line x1="1" y1="10" x2="23" y2="10"></line>
            </svg>
            <span>Financials</span>
        </a>

        <!-- 8. Inventory Logs Audit -->
        <a class="admin-nav-link <?= $activeNav === 'inventory_logs' ? 'active' : '' ?>" href="inventory_logs.php">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
            <span>Inventory Logs</span>
        </a>

        <!-- 9. APK Release Manager -->
        <a class="admin-nav-link <?= $activeNav === 'apk_manager' ? 'active' : '' ?>" href="apk_manager.php">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                <line x1="12" y1="18" x2="12.01" y2="18"></line>
            </svg>
            <span>APK Release Mgr</span>
        </a>

        <div class="nav-heading" style="margin-top: 14px;">Distribution & App</div>

        <a class="admin-nav-link" href="download.php" target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span>Get Android APK</span>
        </a>

        <a class="admin-nav-link" href="index.php" target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            <span>Public Portal</span>
        </a>
    </nav>

    <!-- Sidebar Footer / Profile -->
    <div class="admin-sidebar-footer">
        <a class="admin-profile-snippet" href="profile.php" style="text-decoration: none;">
            <div class="admin-avatar"><?= $adminInitial ?></div>
            <div class="admin-meta">
                <strong><?= htmlspecialchars($currentAdminName, ENT_QUOTES, 'UTF-8') ?></strong>
                <span>Super Admin</span>
            </div>
        </a>

        <a class="admin-logout-btn" href="logout.php" title="Sign Out">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </a>
    </div>
</aside>
