<?php
/**
 * UniServe Enterprise Admin Dashboard — Platform Overview & Moderation
 */
$pageTitle = "Platform Overview";
$pageSubtitle = "Real-time ecosystem metrics & live moderation queue";
$activeNav = "dashboard";

require_once __DIR__ . '/includes/admin_header.php';

// KPI Metrics from Database
$counts = [
    "users" => 0,
    "businesses" => 0,
    "pending_businesses" => 0,
    "services" => 0,
    "requests" => 0,
    "transactions" => 0,
    "categories" => 0,
    "total_revenue" => 0.00
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
    try {
        $res = $conn->query("SELECT COUNT(*) AS total FROM $table");
        if ($res) {
            $counts[$key] = (int)($res->fetch_assoc()["total"] ?? 0);
            $res->free();
        }
    } catch (Throwable $e) {}
}

try {
    $pendingRes = $conn->query("SELECT COUNT(*) AS total FROM businesses WHERE status = 'pending'");
    if ($pendingRes) {
        $counts["pending_businesses"] = (int)($pendingRes->fetch_assoc()["total"] ?? 0);
        $pendingRes->free();
    }
    $revRes = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM transactions WHERE payment_status = 'paid'");
    if ($revRes) {
        $counts["total_revenue"] = (float)($revRes->fetch_assoc()["total"] ?? 0.00);
        $revRes->free();
    }
} catch (Throwable $e) {}

// Fetch Recent Businesses Awaiting Moderation
$recent_businesses = [];
try {
    $bQuery = "SELECT b.business_id, b.business_name, b.status, b.created_at, u.full_name as owner_name, c.category_name 
               FROM businesses b 
               JOIN users u ON b.owner_id = u.user_id 
               LEFT JOIN categories c ON b.category_id = c.category_id 
               ORDER BY (b.status = 'pending') DESC, b.created_at DESC LIMIT 6";
    $bRes = $conn->query($bQuery);
    if ($bRes) {
        while ($row = $bRes->fetch_assoc()) {
            $recent_businesses[] = $row;
        }
        $bRes->free();
    }
} catch (Throwable $e) {}

// Recent Order Requests
$recent_requests = [];
try {
    $rQuery = "SELECT r.request_id, r.total_amount, r.status, r.created_at, u.full_name as customer_name, s.service_name 
               FROM service_requests r 
               LEFT JOIN users u ON r.customer_id = u.user_id 
               LEFT JOIN services s ON r.service_id = s.service_id 
               ORDER BY r.created_at DESC LIMIT 5";
    $rRes = $conn->query($rQuery);
    if ($rRes) {
        while ($row = $rRes->fetch_assoc()) {
            $recent_requests[] = $row;
        }
        $rRes->free();
    }
} catch (Throwable $e) {}
?>

<!-- KPI METRICS GRID -->
<div class="admin-stat-grid">
    <div class="admin-kpi-card">
        <div class="admin-kpi-header">
            <span>TOTAL USERS</span>
            <div class="admin-kpi-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-number"><?= number_format($counts["users"]) ?></div>
        <div class="admin-kpi-desc">Registered Mobile & Web Profiles</div>
    </div>

    <div class="admin-kpi-card">
        <div class="admin-kpi-header">
            <span>ACTIVE MERCHANTS</span>
            <div class="admin-kpi-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.14);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-number"><?= number_format($counts["businesses"]) ?></div>
        <div class="admin-kpi-desc">Storefronts Listed Platform-wide</div>
    </div>

    <div class="admin-kpi-card" style="<?= $counts['pending_businesses'] > 0 ? 'border-color: rgba(245, 158, 11, 0.4);' : '' ?>">
        <div class="admin-kpi-header">
            <span>PENDING APPROVALS</span>
            <div class="admin-kpi-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.14);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-number" style="<?= $counts['pending_businesses'] > 0 ? 'color: #f59e0b;' : '' ?>">
            <?= number_format($counts["pending_businesses"]) ?>
        </div>
        <div class="admin-kpi-desc">Storefronts Awaiting Review</div>
    </div>

    <div class="admin-kpi-card">
        <div class="admin-kpi-header">
            <span>TOTAL VOLUME</span>
            <div class="admin-kpi-icon" style="color: #38bdf8; background: rgba(56, 189, 248, 0.14);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-number">₱<?= number_format($counts["total_revenue"], 2) ?></div>
        <div class="admin-kpi-desc">Settled Transaction Volume</div>
    </div>
</div>

<!-- MERCHANT MODERATION QUEUE -->
<div class="admin-panel">
    <div class="admin-panel-header">
        <div>
            <h3>Merchant Verification & Moderation</h3>
            <p>Review incoming merchant applications, evaluate licenses, and authorize platform store listings.</p>
        </div>
        <a class="btn-primary" href="manage_businesses.php">
            <span>View All Merchants (<?= $counts['businesses'] ?>)</span>
            <span>&rarr;</span>
        </a>
    </div>

    <?php if (empty($recent_businesses)): ?>
        <div class="admin-empty-state">
            <div class="empty-icon-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
            <h4>No merchants registered yet</h4>
            <p>When merchants sign up through the mobile app, their applications will populate this review queue.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Store ID</th>
                        <th>Business Name</th>
                        <th>Owner / Account</th>
                        <th>Category</th>
                        <th>Application Date</th>
                        <th>Status</th>
                        <th style="text-align: right;">Moderation Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_businesses as $b): ?>
                        <tr>
                            <td><code style="color: var(--accent); font-weight: 700;">#<?= (int)$b["business_id"] ?></code></td>
                            <td><strong style="color: #ffffff;"><?= htmlspecialchars($b["business_name"] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars($b["owner_name"] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="admin-badge" style="background: rgba(255, 255, 255, 0.05); color: var(--text-muted); border: 1px solid var(--border-subtle);">
                                    <?= htmlspecialchars($b["category_name"] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><span style="font-size: 12px; color: var(--text-muted);"><?= date("M d, Y", strtotime($b["created_at"])) ?></span></td>
                            <td>
                                <span class="admin-badge badge-<?= strtolower($b["status"]) ?>">
                                    <?= ucfirst($b["status"]) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($b["status"] === "pending"): ?>
                                    <div class="action-btn-group">
                                        <a href="update_business_status.php?id=<?= $b['business_id'] ?>&status=approved" class="btn-sm-action btn-approve">
                                            ✓ Approve
                                        </a>
                                        <a href="update_business_status.php?id=<?= $b['business_id'] ?>&status=rejected" class="btn-sm-action btn-reject">
                                            ✕ Reject
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <a href="manage_businesses.php?search=<?= urlencode($b['business_name']) ?>" class="btn-secondary" style="padding: 4px 10px; font-size: 11px;">
                                        View Record
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- RECENT ACTIVITY FEED -->
<div class="admin-panel">
    <div class="admin-panel-header">
        <div>
            <h3>Recent Booking & Service Requests</h3>
            <p>Chronological feed of customer requests dispatched via the Android client.</p>
        </div>
        <a class="btn-secondary" href="manage_requests.php">
            <span>Manage All Bookings &rarr;</span>
        </a>
    </div>

    <?php if (empty($recent_requests)): ?>
        <div class="admin-empty-state">
            <h4>No customer orders yet</h4>
            <p>Orders submitted by customers via the UniServe Android app will appear here in real-time.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Requested Service</th>
                        <th>Total Amount</th>
                        <th>Timestamp</th>
                        <th>Lifecycle Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_requests as $r): ?>
                        <tr>
                            <td><code style="color: var(--accent); font-weight: 700;">#ORD-<?= (int)$r["request_id"] ?></code></td>
                            <td><strong style="color: #ffffff;"><?= htmlspecialchars($r["customer_name"] ?? 'Anonymous Customer', ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars($r["service_name"] ?? 'Service Item', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong>₱<?= number_format((float)($r["total_amount"] ?? 0), 2) ?></strong></td>
                            <td><span style="font-size: 12px; color: var(--text-muted);"><?= date("M d, Y h:i A", strtotime($r["created_at"])) ?></span></td>
                            <td>
                                <span class="admin-badge badge-<?= strtolower(str_replace(' ', '_', $r["status"])) ?>">
                                    <?= ucfirst($r["status"]) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
