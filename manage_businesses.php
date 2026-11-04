<?php
/**
 * UniServe Enterprise Admin — Merchant & Storefront Governance
 */
$pageTitle = "Merchant Management";
$pageSubtitle = "Review, verify, and moderate digital storefronts";
$activeNav = "businesses";

require_once __DIR__ . '/includes/admin_header.php';

$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");

$whereClauses = [];
$params = [];
$types = "";

if ($search !== "") {
    $whereClauses[] = "(
        CAST(b.business_id AS TEXT) LIKE ?
        OR CAST(b.owner_id AS TEXT) LIKE ?
        OR u.full_name LIKE ?
        OR c.category_name LIKE ?
        OR b.business_name LIKE ?
        OR b.description LIKE ?
        OR b.address LIKE ?
        OR b.contact_number LIKE ?
    )";
    $kw = "%" . $search . "%";
    for ($i = 0; $i < 8; $i++) {
        $params[] = $kw;
        $types .= "s";
    }
}

if ($statusFilter !== "" && in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $whereClauses[] = "b.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql = "
    SELECT
        b.business_id,
        b.owner_id,
        u.full_name AS owner_name,
        u.email AS owner_email,
        b.category_id,
        c.category_name,
        b.business_name,
        b.description,
        b.address,
        b.contact_number,
        b.status,
        b.created_at
    FROM businesses b
    LEFT JOIN users u ON b.owner_id = u.user_id
    LEFT JOIN categories c ON b.category_id = c.category_id
";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " ORDER BY (b.status = 'pending') DESC, b.business_id DESC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
}

// Counts for filter pills
$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
try {
    $cRes = $conn->query("SELECT status, COUNT(*) AS c FROM businesses GROUP BY status");
    if ($cRes) {
        while ($row = $cRes->fetch_assoc()) {
            $s = strtolower($row['status']);
            $counts[$s] = (int)$row['c'];
            $counts['all'] += (int)$row['c'];
        }
        $cRes->free();
    }
} catch (Throwable $e) {}
?>

<!-- FLASH NOTIFICATION IF REDIRECTED -->
<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'approved'): ?>
        <div class="admin-alert alert-success">
            <span>✓ Storefront has been approved successfully and is now active on the mobile marketplace.</span>
        </div>
    <?php elseif ($_GET['msg'] === 'rejected'): ?>
        <div class="admin-alert alert-error">
            <span>Storefront listing has been rejected.</span>
        </div>
    <?php elseif ($_GET['msg'] === 'updated'): ?>
        <div class="admin-alert alert-info">
            <span>Store status updated successfully.</span>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- TOOLBAR: SEARCH & STATUS FILTER PILLS -->
<div class="admin-toolbar">
    <div class="filter-pills-group">
        <a href="manage_businesses.php<?= $search ? '?search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === '' ? 'active' : '' ?>">
            All (<?= $counts['all'] ?>)
        </a>
        <a href="manage_businesses.php?status=pending<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>"
           style="<?= $counts['pending'] > 0 ? 'color: #f59e0b; border-color: rgba(245, 158, 11, 0.4);' : '' ?>">
            Pending Review (<?= $counts['pending'] ?>)
        </a>
        <a href="manage_businesses.php?status=approved<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === 'approved' ? 'active' : '' ?>">
            Approved (<?= $counts['approved'] ?>)
        </a>
        <a href="manage_businesses.php?status=rejected<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === 'rejected' ? 'active' : '' ?>">
            Rejected (<?= $counts['rejected'] ?>)
        </a>
    </div>

    <form method="GET" action="manage_businesses.php" class="toolbar-search-form">
        <?php if ($statusFilter): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div class="admin-input-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="search" class="admin-input admin-input-with-icon" 
                   placeholder="Search store, owner, address, or ID..." 
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn-primary" style="padding: 10px 16px;">Search</button>
        <?php if ($search !== '' || $statusFilter !== ''): ?>
            <a href="manage_businesses.php" class="btn-secondary" style="padding: 10px 14px;" title="Reset filters">✕</a>
        <?php endif; ?>
    </form>
</div>

<!-- DATA TABLE PANEL -->
<div class="admin-panel">
    <div class="admin-panel-header">
        <div>
            <h3>Registered Merchant Storefronts</h3>
            <p>Showing verified and pending merchant establishments registered on UniServe.</p>
        </div>
    </div>

    <?php if (!$result || $result->num_rows === 0): ?>
        <div class="admin-empty-state">
            <div class="empty-icon-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <rect x="2" y="7" width="20" height="14" rx="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
            <h4>No merchants match your criteria</h4>
            <p><?= $search ? 'Try clearing your search query or switching status filters.' : 'No businesses have registered yet.' ?></p>
            <?php if ($search || $statusFilter): ?>
                <a href="manage_businesses.php" class="btn-secondary">Clear Filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Store Name & Details</th>
                        <th>Proprietor</th>
                        <th>Category</th>
                        <th>Contact & Address</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                        <th style="text-align: right; width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($b = $result->fetch_assoc()): ?>
                        <tr>
                            <td><code style="color: var(--accent); font-weight: 700;">#<?= (int)$b["business_id"] ?></code></td>
                            <td>
                                <strong style="color: #ffffff; font-size: 14px;"><?= htmlspecialchars($b["business_name"] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if (!empty($b["description"])): ?>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 3px; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($b["description"], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="font-size: 13px; color: var(--text-main);"><?= htmlspecialchars($b["owner_name"] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></strong>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($b["owner_email"] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <span class="admin-badge" style="background: rgba(255, 255, 255, 0.05); color: var(--text-muted); border: 1px solid var(--border-subtle);">
                                    <?= htmlspecialchars($b["category_name"] ?? 'General', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 12.5px;"><?= htmlspecialchars($b["contact_number"] ?? 'No phone', ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="font-size: 11px; color: var(--text-dim); max-width: 200px;"><?= htmlspecialchars($b["address"] ?? 'No address', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-muted);"><?= date("M d, Y", strtotime($b["created_at"])) ?></span>
                            </td>
                            <td>
                                <span class="admin-badge badge-<?= strtolower($b["status"]) ?>">
                                    <?= ucfirst($b["status"]) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($b["status"] === "pending"): ?>
                                    <div class="action-btn-group">
                                        <a href="update_business_status.php?id=<?= $b['business_id'] ?>&status=approved" 
                                           class="btn-sm-action btn-approve"
                                           onclick="return confirm('Approve storefront <?= htmlspecialchars(addslashes($b['business_name']), ENT_QUOTES, 'UTF-8') ?> for live customer access?');">
                                            ✓ Approve
                                        </a>
                                        <a href="update_business_status.php?id=<?= $b['business_id'] ?>&status=rejected" 
                                           class="btn-sm-action btn-reject"
                                           onclick="return confirm('Reject this merchant application?');">
                                            ✕ Reject
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="action-btn-group">
                                        <?php if ($b["status"] === "approved"): ?>
                                            <a href="update_business_status.php?id=<?= $b['business_id'] ?>&status=rejected" 
                                               class="btn-sm-action btn-reject"
                                               onclick="return confirm('Revoke approval for <?= htmlspecialchars(addslashes($b['business_name']), ENT_QUOTES, 'UTF-8') ?>?');">
                                                Suspend
                                            </a>
                                        <?php else: ?>
                                            <a href="update_business_status.php?id=<?= $b['business_id'] ?>&status=approved" 
                                               class="btn-sm-action btn-approve"
                                               onclick="return confirm('Re-approve <?= htmlspecialchars(addslashes($b['business_name']), ENT_QUOTES, 'UTF-8') ?>?');">
                                                Re-Approve
                                            </a>
                                        <?php endif; ?>
                                        <a href="manage_services.php?business_id=<?= $b['business_id'] ?>" 
                                           class="btn-secondary" style="padding: 4px 8px; font-size: 11px;" title="View Services">
                                            Services &rarr;
                                        </a>
                                    </div>
                                <?php endif; ?>
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
