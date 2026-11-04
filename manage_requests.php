<?php
/**
 * UniServe Enterprise Admin — Booking & Service Request Oversight
 */
$pageTitle = "Service Bookings & Orders";
$pageSubtitle = "Monitor fulfillment pipeline, customer schedules, and delivery progress";
$activeNav = "requests";

require_once __DIR__ . '/includes/admin_header.php';

$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");

$whereClauses = [];
$params = [];
$types = "";

if ($search !== "") {
    $whereClauses[] = "(
        u.full_name LIKE ?
        OR u.email LIKE ?
        OR s.service_name LIKE ?
        OR b.business_name LIKE ?
        OR r.notes LIKE ?
        OR CAST(r.request_id AS TEXT) LIKE ?
    )";
    $kw = "%" . $search . "%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $kw;
        $types .= "s";
    }
}

if ($statusFilter !== "" && in_array($statusFilter, ['pending', 'accepted', 'in_progress', 'completed', 'cancelled', 'rejected'], true)) {
    $whereClauses[] = "r.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql = "
    SELECT
        r.request_id,
        r.customer_id,
        u.full_name AS customer_name,
        u.email AS customer_email,
        r.service_id,
        s.service_name,
        s.price AS unit_price,
        b.business_name,
        r.request_date,
        r.preferred_date,
        r.quantity,
        r.total_amount,
        r.status,
        r.notes,
        r.created_at
    FROM service_requests r
    LEFT JOIN users u ON r.customer_id = u.user_id
    LEFT JOIN services s ON r.service_id = s.service_id
    LEFT JOIN businesses b ON s.business_id = b.business_id
";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " ORDER BY (r.status = 'pending') DESC, r.request_id DESC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
}

// Counts for filter pills
$statusCounts = ['all' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
try {
    $cRes = $conn->query("SELECT status, COUNT(*) as c FROM service_requests GROUP BY status");
    if ($cRes) {
        while ($row = $cRes->fetch_assoc()) {
            $s = strtolower($row['status']);
            if (isset($statusCounts[$s])) {
                $statusCounts[$s] = (int)$row['c'];
            }
            $statusCounts['all'] += (int)$row['c'];
        }
        $cRes->free();
    }
} catch (Throwable $e) {}
?>

<!-- TOOLBAR: SEARCH & STATUS PILLS -->
<div class="admin-toolbar">
    <div class="filter-pills-group">
        <a href="manage_requests.php<?= $search ? '?search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === '' ? 'active' : '' ?>">
            All Orders (<?= $statusCounts['all'] ?>)
        </a>
        <a href="manage_requests.php?status=pending<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>"
           style="<?= $statusCounts['pending'] > 0 ? 'color: #f59e0b; border-color: rgba(245, 158, 11, 0.4);' : '' ?>">
            Pending (<?= $statusCounts['pending'] ?>)
        </a>
        <a href="manage_requests.php?status=in_progress<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === 'in_progress' ? 'active' : '' ?>">
            In Progress (<?= $statusCounts['in_progress'] ?>)
        </a>
        <a href="manage_requests.php?status=completed<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === 'completed' ? 'active' : '' ?>">
            Completed (<?= $statusCounts['completed'] ?>)
        </a>
    </div>

    <form method="GET" action="manage_requests.php" class="toolbar-search-form">
        <?php if ($statusFilter): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div class="admin-input-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="search" class="admin-input admin-input-with-icon" 
                   placeholder="Search customer, service, merchant, notes..." 
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn-primary" style="padding: 10px 16px;">Search</button>
        <?php if ($search !== '' || $statusFilter !== ''): ?>
            <a href="manage_requests.php" class="btn-secondary" style="padding: 10px 14px;" title="Reset filters">✕</a>
        <?php endif; ?>
    </form>
</div>

<!-- ORDERS TABLE PANEL -->
<div class="admin-panel">
    <div class="admin-panel-header">
        <div>
            <h3>Customer Booking Requests</h3>
            <p>Direct view of service orders placed across the Android ecosystem.</p>
        </div>
    </div>

    <?php if (!$result || $result->num_rows === 0): ?>
        <div class="admin-empty-state">
            <div class="empty-icon-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
            </div>
            <h4>No orders match your criteria</h4>
            <p><?= $search ? 'Try clearing your search query or switching status filters.' : 'No customer orders have been recorded yet.' ?></p>
            <?php if ($search || $statusFilter): ?>
                <a href="manage_requests.php" class="btn-secondary">Clear Filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Order ID</th>
                        <th>Customer</th>
                        <th>Service & Merchant</th>
                        <th>Schedule</th>
                        <th>Qty & Total</th>
                        <th>Notes / Special Instructions</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td><code style="color: var(--accent); font-weight: 700;">#ORD-<?= (int)$r["request_id"] ?></code></td>
                            <td>
                                <strong style="color: #ffffff; font-size: 13.5px;">
                                    <?= htmlspecialchars($r["customer_name"] ?? 'Anonymous Customer', ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($r["customer_email"] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <strong style="font-size: 13.5px; color: var(--text-main);">
                                    <?= htmlspecialchars($r["service_name"] ?? 'Service Item', ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <div style="font-size: 11px; color: var(--accent);">
                                    Store: <?= htmlspecialchars($r["business_name"] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 12.5px; color: var(--text-main);">
                                    <?= !empty($r["preferred_date"]) ? date("M d, Y", strtotime($r["preferred_date"])) : 'Anytime' ?>
                                </div>
                                <div style="font-size: 11px; color: var(--text-muted);">
                                    Booked <?= date("M d, Y", strtotime($r["created_at"])) ?>
                                </div>
                            </td>
                            <td>
                                <strong style="color: #38bdf8; font-size: 14px;">
                                    ₱<?= number_format((float)$r["total_amount"], 2) ?>
                                </strong>
                                <div style="font-size: 11px; color: var(--text-dim);">Qty: <?= (int)$r["quantity"] ?></div>
                            </td>
                            <td>
                                <?php if (!empty($r["notes"])): ?>
                                    <span style="font-size: 12px; color: var(--text-muted); max-width: 240px; display: inline-block;">
                                        <?= htmlspecialchars($r["notes"], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 11px; color: var(--text-dim); font-style: italic;">No notes provided</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="admin-badge badge-<?= strtolower(str_replace(' ', '_', $r["status"])) ?>">
                                    <?= ucfirst($r["status"]) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php 
if (isset($stmt)) { $stmt->close(); }
require_once __DIR__ . '/includes/admin_footer.php'; 
?>
