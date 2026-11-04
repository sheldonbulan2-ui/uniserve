<?php
/**
 * UniServe Enterprise Admin — Financial Ledger & Settlement Governance
 */
$pageTitle = "Financials & Transactions";
$pageSubtitle = "Audit customer payments, settlement statuses, and platform transaction ledgers";
$activeNav = "transactions";

require_once __DIR__ . '/includes/admin_header.php';

/* CSRF Protection */
if (empty($_SESSION["transactions_csrf"])) {
    $_SESSION["transactions_csrf"] = bin2hex(random_bytes(32));
}

$alertMessage = "";
$alertType = "info";

/* Process status transitions */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";
    $transactionId = filter_var($_POST["transaction_id"] ?? null, FILTER_VALIDATE_INT);
    $newStatus = $_POST["new_status"] ?? "";

    if (!is_string($csrfToken) || !hash_equals($_SESSION["transactions_csrf"], $csrfToken)) {
        $alertMessage = "Security token mismatch. Please try again.";
        $alertType = "error";
    } elseif (!$transactionId || !in_array($newStatus, ["paid", "failed", "refunded"], true)) {
        $alertMessage = "Invalid transaction ID or target status specified.";
        $alertType = "error";
    } else {
        $stmt = $conn->prepare("UPDATE transactions SET payment_status = ? WHERE transaction_id = ? AND payment_status = 'pending'");
        if ($stmt) {
            $stmt->bind_param("si", $newStatus, $transactionId);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $alertMessage = "Transaction #TXN-{$transactionId} marked as " . strtoupper($newStatus) . " successfully.";
                $alertType = "success";
            } else {
                $alertMessage = "Transaction could not be updated. Only pending transactions can be changed.";
                $alertType = "error";
            }
            $stmt->close();
        }
    }
}

$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");

$whereClauses = [];
$params = [];
$types = "";

if ($search !== "") {
    $whereClauses[] = "(
        CAST(t.transaction_id AS TEXT) LIKE ?
        OR CAST(t.request_id AS TEXT) LIKE ?
        OR u.full_name LIKE ?
        OR s.service_name LIKE ?
        OR t.payment_method LIKE ?
    )";
    $kw = "%" . $search . "%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $kw;
        $types .= "s";
    }
}

if ($statusFilter !== "" && in_array($statusFilter, ['pending', 'paid', 'failed', 'refunded'], true)) {
    $whereClauses[] = "t.payment_status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql = "
    SELECT
        t.transaction_id,
        t.request_id,
        t.amount,
        t.payment_method,
        t.payment_status,
        t.transaction_date,
        u.full_name AS customer_name,
        s.service_name
    FROM transactions t
    LEFT JOIN service_requests r ON t.request_id = r.request_id
    LEFT JOIN users u ON r.customer_id = u.user_id
    LEFT JOIN services s ON r.service_id = s.service_id
";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " ORDER BY (t.payment_status = 'pending') DESC, t.transaction_id DESC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
}

// Financial KPI summaries
$kpis = [
    'volume' => 0.00,
    'paid' => 0.00,
    'pending' => 0.00,
    'failed' => 0.00,
    'count_pending' => 0
];

try {
    $sumRes = $conn->query("
        SELECT 
            payment_status, 
            COUNT(*) AS c, 
            COALESCE(SUM(amount), 0) AS s 
        FROM transactions 
        GROUP BY payment_status
    ");
    if ($sumRes) {
        while ($row = $sumRes->fetch_assoc()) {
            $s = strtolower($row['payment_status']);
            $amt = (float)$row['s'];
            $kpis['volume'] += $amt;
            if ($s === 'paid') $kpis['paid'] += $amt;
            if ($s === 'pending') {
                $kpis['pending'] += $amt;
                $kpis['count_pending'] = (int)$row['c'];
            }
            if ($s === 'failed') $kpis['failed'] += $amt;
        }
        $sumRes->free();
    }
} catch (Throwable $e) {}
?>

<!-- FLASH NOTIFICATION -->
<?php if ($alertMessage): ?>
    <div class="admin-alert alert-<?= $alertType ?>">
        <span><?= htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<!-- FINANCIAL METRICS -->
<div class="admin-stat-grid" style="margin-bottom: 24px;">
    <div class="admin-kpi-card">
        <div class="admin-kpi-header">
            <span>SETTLED REVENUE</span>
            <div class="admin-kpi-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.14);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-number" style="color: #34d399;">₱<?= number_format($kpis['paid'], 2) ?></div>
        <div class="admin-kpi-desc">Confirmed & Succeeded Payments</div>
    </div>

    <div class="admin-kpi-card">
        <div class="admin-kpi-header">
            <span>PENDING SETTLEMENTS</span>
            <div class="admin-kpi-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.14);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-number" style="color: #fbbf24;">₱<?= number_format($kpis['pending'], 2) ?></div>
        <div class="admin-kpi-desc"><?= $kpis['count_pending'] ?> Pending Collection(s)</div>
    </div>

    <div class="admin-kpi-card">
        <div class="admin-kpi-header">
            <span>TOTAL GROSS VOLUME</span>
            <div class="admin-kpi-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-number">₱<?= number_format($kpis['volume'], 2) ?></div>
        <div class="admin-kpi-desc">All Transaction Records Combined</div>
    </div>
</div>

<!-- TOOLBAR: SEARCH & STATUS PILLS -->
<div class="admin-toolbar">
    <div class="filter-pills-group">
        <a href="manage_transactions.php<?= $search ? '?search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === '' ? 'active' : '' ?>">
            All Transactions
        </a>
        <a href="manage_transactions.php?status=pending<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>"
           style="<?= $kpis['count_pending'] > 0 ? 'color: #f59e0b; border-color: rgba(245, 158, 11, 0.4);' : '' ?>">
            Pending (<?= $kpis['count_pending'] ?>)
        </a>
        <a href="manage_transactions.php?status=paid<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === 'paid' ? 'active' : '' ?>">
            Paid
        </a>
        <a href="manage_transactions.php?status=failed<?= $search ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-pill <?= $statusFilter === 'failed' ? 'active' : '' ?>">
            Failed
        </a>
    </div>

    <form method="GET" action="manage_transactions.php" class="toolbar-search-form">
        <?php if ($statusFilter): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div class="admin-input-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="search" class="admin-input admin-input-with-icon" 
                   placeholder="Search transaction ID, order, method..." 
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn-primary" style="padding: 10px 16px;">Search</button>
        <?php if ($search !== '' || $statusFilter !== ''): ?>
            <a href="manage_transactions.php" class="btn-secondary" style="padding: 10px 14px;" title="Reset filters">✕</a>
        <?php endif; ?>
    </form>
</div>

<!-- TRANSACTIONS TABLE PANEL -->
<div class="admin-panel">
    <div class="admin-panel-header">
        <div>
            <h3>Platform Financial Audit Ledger</h3>
            <p>Immutable record of payment transactions generated from mobile bookings.</p>
        </div>
    </div>

    <?php if (!$result || $result->num_rows === 0): ?>
        <div class="admin-empty-state">
            <div class="empty-icon-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
            </div>
            <h4>No transactions match your criteria</h4>
            <p><?= $search ? 'Try clearing your search query or switching status filters.' : 'No financial transactions recorded yet.' ?></p>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Txn ID</th>
                        <th>Associated Order</th>
                        <th>Customer & Service</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Timestamp</th>
                        <th>Settlement Status</th>
                        <th style="text-align: right; width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($t = $result->fetch_assoc()): ?>
                        <tr>
                            <td><code style="color: var(--accent); font-weight: 700;">#TXN-<?= (int)$t["transaction_id"] ?></code></td>
                            <td>
                                <a href="manage_requests.php?search=<?= (int)$t['request_id'] ?>" 
                                   style="color: #60a5fa; text-decoration: none; font-weight: 600;">
                                    #ORD-<?= (int)$t["request_id"] ?>
                                </a>
                            </td>
                            <td>
                                <strong style="color: #ffffff; font-size: 13.5px;">
                                    <?= htmlspecialchars($t["customer_name"] ?? 'Anonymous Customer', ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                                <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($t["service_name"] ?? 'Service Item', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <strong style="color: #38bdf8; font-size: 14px;">
                                    ₱<?= number_format((float)$t["amount"], 2) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="admin-badge" style="background: rgba(255, 255, 255, 0.04); color: var(--text-main); border: 1px solid var(--border-subtle);">
                                    <?= htmlspecialchars($t["payment_method"] ?? 'Cash', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-muted);"><?= date("M d, Y h:i A", strtotime($t["transaction_date"])) ?></span>
                            </td>
                            <td>
                                <span class="admin-badge badge-<?= strtolower($t["payment_status"]) ?>">
                                    <?= ucfirst($t["payment_status"]) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($t["payment_status"] === "pending"): ?>
                                    <div class="action-btn-group">
                                        <form method="POST" action="manage_transactions.php" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['transactions_csrf'] ?>">
                                            <input type="hidden" name="transaction_id" value="<?= (int)$t['transaction_id'] ?>">
                                            <input type="hidden" name="new_status" value="paid">
                                            <button type="submit" class="btn-sm-action btn-approve" title="Mark as Paid">
                                                ✓ Settle
                                            </button>
                                        </form>

                                        <form method="POST" action="manage_transactions.php" style="display: inline;" 
                                              onsubmit="return confirm('Flag transaction #TXN-<?= (int)$t['transaction_id'] ?> as Failed?');">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['transactions_csrf'] ?>">
                                            <input type="hidden" name="transaction_id" value="<?= (int)$t['transaction_id'] ?>">
                                            <input type="hidden" name="new_status" value="failed">
                                            <button type="submit" class="btn-sm-action btn-reject" title="Mark as Failed">
                                                ✕
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 11px; color: var(--text-dim); font-style: italic;">Settled</span>
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
