<?php
/**
 * UniServe Enterprise Admin — User Directory & Role Governance
 */
$pageTitle = "User Directory & Access Control";
$pageSubtitle = "Audit registered accounts, role entitlements, and security profiles";
$activeNav = "users";

require_once __DIR__ . '/includes/admin_header.php';

$search = trim($_GET["search"] ?? "");
$roleFilter = trim($_GET["role"] ?? "");

$whereClauses = [];
$params = [];
$types = "";

if ($search !== "") {
    $whereClauses[] = "(full_name LIKE ? OR email LIKE ? OR CAST(user_id AS TEXT) LIKE ?)";
    $kw = "%" . $search . "%";
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $types .= "sss";
}

if ($roleFilter !== "" && in_array($roleFilter, ['admin', 'business_owner', 'customer'], true)) {
    $whereClauses[] = "role = ?";
    $params[] = $roleFilter;
    $types .= "s";
}

$sql = "SELECT user_id, full_name, email, role, created_at FROM users";
if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}
$sql .= " ORDER BY user_id DESC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
}

// Role counts for filter tabs
$roleCounts = ['all' => 0, 'customer' => 0, 'business_owner' => 0, 'admin' => 0];
try {
    $cRes = $conn->query("SELECT role, COUNT(*) as c FROM users GROUP BY role");
    if ($cRes) {
        while ($row = $cRes->fetch_assoc()) {
            $r = strtolower($row['role']);
            $roleCounts[$r] = (int)$row['c'];
            $roleCounts['all'] += (int)$row['c'];
        }
        $cRes->free();
    }
} catch (Throwable $e) {}
?>

<!-- TOOLBAR: SEARCH & ROLE PILLS -->
<div class="admin-toolbar">
    <div class="filter-pills-group">
        <a href="manage_users.php<?= $search ? '?search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $roleFilter === '' ? 'active' : '' ?>">
            All Accounts (<?= $roleCounts['all'] ?>)
        </a>
        <a href="manage_users.php?role=customer<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $roleFilter === 'customer' ? 'active' : '' ?>">
            Customers (<?= $roleCounts['customer'] ?>)
        </a>
        <a href="manage_users.php?role=business_owner<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $roleFilter === 'business_owner' ? 'active' : '' ?>">
            Merchants (<?= $roleCounts['business_owner'] ?>)
        </a>
        <a href="manage_users.php?role=admin<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $roleFilter === 'admin' ? 'active' : '' ?>">
            Administrators (<?= $roleCounts['admin'] ?>)
        </a>
    </div>

    <div style="display: flex; align-items: center; gap: 12px;">
        <form method="GET" action="manage_users.php" class="toolbar-search-form">
            <?php if ($roleFilter): ?>
                <input type="hidden" name="role" value="<?= htmlspecialchars($roleFilter, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <div class="admin-input-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" name="search" class="admin-input admin-input-with-icon" 
                       placeholder="Search name, email, or user ID..." 
                       value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <button type="submit" class="btn-primary" style="padding: 10px 16px;">Search</button>
            <?php if ($search !== '' || $roleFilter !== ''): ?>
                <a href="manage_users.php" class="btn-secondary" style="padding: 10px 14px;" title="Reset filters">✕</a>
            <?php endif; ?>
        </form>

        <a href="add_user.php" class="btn-primary" style="white-space: nowrap; padding: 10px 18px;">
            <span>+ Provision User</span>
        </a>
    </div>
</div>

<!-- DATA TABLE PANEL -->
<div class="admin-panel">
    <div class="admin-panel-header">
        <div>
            <h3>User Accounts Ledger</h3>
            <p>Direct view of platform principals across mobile clients and administrative consoles.</p>
        </div>
    </div>

    <?php if (!$result || $result->num_rows === 0): ?>
        <div class="admin-empty-state">
            <div class="empty-icon-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                </svg>
            </div>
            <h4>No accounts match your criteria</h4>
            <p><?= $search ? 'Try clearing your search query or switching role filters.' : 'No users currently registered in the database.' ?></p>
            <?php if ($search || $roleFilter): ?>
                <a href="manage_users.php" class="btn-secondary">Clear Filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">User ID</th>
                        <th>User Profile</th>
                        <th>Email Address</th>
                        <th>System Role</th>
                        <th>Account Tier</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($u = $result->fetch_assoc()): ?>
                        <?php 
                        $uInitial = strtoupper(substr($u["full_name"], 0, 1));
                        $isCurrentAdmin = (int)$u["user_id"] === (int)$_SESSION["user_id"];
                        ?>
                        <tr>
                            <td><code style="color: var(--accent); font-weight: 700;">#UID-<?= (int)$u["user_id"] ?></code></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 34px; height: 34px; border-radius: 50%; background: rgba(57, 132, 246, 0.15); color: var(--accent); display: grid; place-items: center; font-weight: 700; font-size: 13px; border: 1px solid var(--border-subtle); flex-shrink: 0;">
                                        <?= $uInitial ?>
                                    </div>
                                    <div>
                                        <strong style="color: #ffffff; font-size: 13.5px;">
                                            <?= htmlspecialchars($u["full_name"], ENT_QUOTES, 'UTF-8') ?>
                                        </strong>
                                        <?php if ($isCurrentAdmin): ?>
                                            <span style="font-size: 10px; color: #38bdf8; font-weight: 700; margin-left: 6px;">(You)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 12.5px; color: var(--text-muted);">
                                    <?= htmlspecialchars($u["email"], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span class="admin-badge badge-<?= strtolower($u["role"]) ?>">
                                    <?= $u["role"] === "business_owner" ? "Merchant" : ucfirst($u["role"]) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u["role"] === "admin"): ?>
                                    <span style="font-size: 11.5px; color: #c084fc; font-weight: 600;">Executive Console</span>
                                <?php elseif ($u["role"] === "business_owner"): ?>
                                    <span style="font-size: 11.5px; color: #34d399; font-weight: 600;">Mobile Storefront</span>
                                <?php else: ?>
                                    <span style="font-size: 11.5px; color: #22d3ee; font-weight: 600;">Mobile Customer</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-muted);"><?= date("M d, Y", strtotime($u["created_at"])) ?></span>
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
