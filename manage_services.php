<?php
/**
 * UniServe Enterprise Admin — Service Catalog Governance
 */
$pageTitle = "Service Catalog Management";
$pageSubtitle = "Audit all marketplace offerings, rates, and operational availability";
$activeNav = "services";

require_once __DIR__ . '/includes/admin_header.php';

$search = trim($_GET["search"] ?? "");
$businessId = filter_var($_GET["business_id"] ?? null, FILTER_VALIDATE_INT);
$availabilityFilter = trim($_GET["availability"] ?? "");

$whereClauses = [];
$params = [];
$types = "";

if ($search !== "") {
    $whereClauses[] = "(
        s.service_name LIKE ?
        OR s.description LIKE ?
        OR b.business_name LIKE ?
        OR CAST(s.service_id AS TEXT) LIKE ?
        OR CAST(s.business_id AS TEXT) LIKE ?
    )";
    $kw = "%" . $search . "%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $kw;
        $types .= "s";
    }
}

if ($businessId) {
    $whereClauses[] = "s.business_id = ?";
    $params[] = $businessId;
    $types .= "i";
}

if ($availabilityFilter !== "" && in_array($availabilityFilter, ['available', 'unavailable'], true)) {
    $whereClauses[] = "s.availability = ?";
    $params[] = $availabilityFilter;
    $types .= "s";
}

$sql = "
    SELECT
        s.service_id,
        s.business_id,
        s.service_name,
        s.description,
        s.price,
        s.availability,
        s.created_at,
        b.business_name,
        b.status AS business_status
    FROM services s
    LEFT JOIN businesses b ON s.business_id = b.business_id
";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " ORDER BY s.service_id DESC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
}

// Counts for filter pills
$availCounts = ['all' => 0, 'available' => 0, 'unavailable' => 0];
try {
    $cRes = $conn->query("SELECT availability, COUNT(*) as c FROM services GROUP BY availability");
    if ($cRes) {
        while ($row = $cRes->fetch_assoc()) {
            $a = strtolower($row['availability']);
            $availCounts[$a] = (int)$row['c'];
            $availCounts['all'] += (int)$row['c'];
        }
        $cRes->free();
    }
} catch (Throwable $e) {}
?>

<!-- TOOLBAR: SEARCH & STATUS PILLS -->
<div class="admin-toolbar">
    <div class="filter-pills-group">
        <a href="manage_services.php<?= $businessId ? '?business_id=' . $businessId : '' ?>" 
           class="filter-pill <?= $availabilityFilter === '' ? 'active' : '' ?>">
            All Services (<?= $availCounts['all'] ?>)
        </a>
        <a href="manage_services.php?availability=available<?= $businessId ? '&business_id=' . $businessId : '' ?>" 
           class="filter-pill <?= $availabilityFilter === 'available' ? 'active' : '' ?>">
            Available (<?= $availCounts['available'] ?>)
        </a>
        <a href="manage_services.php?availability=unavailable<?= $businessId ? '&business_id=' . $businessId : '' ?>" 
           class="filter-pill <?= $availabilityFilter === 'unavailable' ? 'active' : '' ?>">
            Unavailable (<?= $availCounts['unavailable'] ?>)
        </a>
    </div>

    <form method="GET" action="manage_services.php" class="toolbar-search-form">
        <?php if ($businessId): ?>
            <input type="hidden" name="business_id" value="<?= (int)$businessId ?>">
        <?php endif; ?>
        <?php if ($availabilityFilter): ?>
            <input type="hidden" name="availability" value="<?= htmlspecialchars($availabilityFilter, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div class="admin-input-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="search" class="admin-input admin-input-with-icon" 
                   placeholder="Search service name, merchant, or ID..." 
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn-primary" style="padding: 10px 16px;">Search</button>
        <?php if ($search !== '' || $availabilityFilter !== '' || $businessId): ?>
            <a href="manage_services.php" class="btn-secondary" style="padding: 10px 14px;" title="Reset filters">✕</a>
        <?php endif; ?>
    </form>
</div>

<!-- SERVICES TABLE -->
<div class="admin-panel">
    <div class="admin-panel-header">
        <div>
            <h3>Active Service Offerings</h3>
            <p>Direct view of service catalog entries offered by verified merchants on the mobile app.</p>
        </div>
        <?php if ($businessId): ?>
            <span class="admin-badge badge-admin">Filtered by Business #<?= (int)$businessId ?></span>
        <?php endif; ?>
    </div>

    <?php if (!$result || $result->num_rows === 0): ?>
        <div class="admin-empty-state">
            <div class="empty-icon-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                    <polyline points="2 17 12 22 22 17"></polyline>
                    <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
            </div>
            <h4>No services found</h4>
            <p><?= $search ? 'Try clearing your search query or switching availability filters.' : 'No merchant services have been listed yet.' ?></p>
            <?php if ($search || $availabilityFilter || $businessId): ?>
                <a href="manage_services.php" class="btn-secondary">Clear Filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Service Title & Description</th>
                        <th>Merchant Storefront</th>
                        <th>Rate / Price</th>
                        <th>Availability</th>
                        <th>Date Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($s = $result->fetch_assoc()): ?>
                        <tr>
                            <td><code style="color: var(--accent); font-weight: 700;">#SVC-<?= (int)$s["service_id"] ?></code></td>
                            <td>
                                <strong style="color: #ffffff; font-size: 14px;"><?= htmlspecialchars($s["service_name"], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if (!empty($s["description"])): ?>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 3px; max-width: 380px;">
                                        <?= htmlspecialchars($s["description"], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="manage_businesses.php?search=<?= urlencode($s['business_name'] ?? '') ?>" 
                                   style="color: var(--text-main); font-weight: 600; text-decoration: none;">
                                    <?= htmlspecialchars($s["business_name"] ?? 'Unknown Store', ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <?php if (isset($s['business_status'])): ?>
                                    <div style="margin-top: 2px;">
                                        <span class="admin-badge badge-<?= strtolower($s['business_status']) ?>" style="font-size: 9px; padding: 1px 6px;">
                                            <?= ucfirst($s['business_status']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="font-size: 14px; color: #38bdf8;">
                                    ₱<?= number_format((float)$s["price"], 2) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="admin-badge badge-<?= strtolower($s["availability"]) ?>">
                                    <?= ucfirst($s["availability"]) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-muted);"><?= date("M d, Y", strtotime($s["created_at"])) ?></span>
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
