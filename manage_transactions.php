<?php
session_start();
require_once __DIR__ . "/config.php";

/* Admin access protection */
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit();
}

/* Escape output */
function escape_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

/* Redirect after processing */
function redirect_transactions($message)
{
    $search = trim($_POST["search"] ?? "");

    $url = "manage_transactions.php?message=" . urlencode($message);

    if ($search !== "") {
        $url .= "&search=" . urlencode($search);
    }

    header("Location: " . $url);
    exit();
}

/* CSRF token */
if (empty($_SESSION["transactions_csrf"])) {
    $_SESSION["transactions_csrf"] = bin2hex(random_bytes(32));
}

/* Process transaction status actions */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";
    $transactionId = filter_var(
        $_POST["transaction_id"] ?? null,
        FILTER_VALIDATE_INT
    );
    $newStatus = $_POST["new_status"] ?? "";

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["transactions_csrf"], $csrfToken)
    ) {
        redirect_transactions("invalid_token");
    }

    if (
        !$transactionId ||
        !in_array($newStatus, ["paid", "failed"], true)
    ) {
        redirect_transactions("invalid_action");
    }

    /*
     * Only pending transactions can be updated.
     * Confirm payment externally before choosing "paid".
     */
    $stmt = $conn->prepare(
        "UPDATE transactions
         SET payment_status = ?
         WHERE transaction_id = ?
           AND payment_status = 'pending'"
    );

    if (!$stmt) {
        redirect_transactions("update_error");
    }

    $stmt->bind_param("si", $newStatus, $transactionId);

    if (!$stmt->execute()) {
        $stmt->close();
        redirect_transactions("update_error");
    }

    $updatedRows = $stmt->affected_rows;
    $stmt->close();

    if ($updatedRows === 1) {
        redirect_transactions(
            $newStatus === "paid" ? "marked_paid" : "marked_failed"
        );
    }

    redirect_transactions("not_updated");
}

/* Page data */
$full_name = $_SESSION["full_name"] ?? "Administrator";
$initial = strtoupper(substr($full_name, 0, 1));
$search = trim($_GET["search"] ?? "");
$message = $_GET["message"] ?? "";

/* Allowed notification messages */
$messages = [
    "marked_paid" => [
        "type" => "success",
        "text" => "Transaction marked as paid."
    ],
    "marked_failed" => [
        "type" => "warning",
        "text" => "Transaction marked as failed."
    ],
    "not_updated" => [
        "type" => "warning",
        "text" => "No changes made. The transaction may no longer be pending."
    ],
    "invalid_action" => [
        "type" => "error",
        "text" => "Invalid transaction action."
    ],
    "invalid_token" => [
        "type" => "error",
        "text" => "Your session validation failed. Please try again."
    ],
    "update_error" => [
        "type" => "error",
        "text" => "Unable to update the transaction. Please try again."
    ]
];

$notice = $messages[$message] ?? null;

/* Read transaction records */
$result = $conn->query(
    "SELECT * FROM transactions ORDER BY transaction_date DESC, transaction_id DESC"
);

if (!$result) {
    error_log("Manage transactions query failed: " . $conn->error);
    http_response_code(500);
    exit("Unable to load transactions. Please try again later.");
}

$fields = $result->fetch_fields();
$transactions = [];

while ($transaction = $result->fetch_assoc()) {
    $matches = ($search === "");

    if (!$matches) {
        foreach ($transaction as $value) {
            if (
                $value !== null &&
                stripos((string) $value, $search) !== false
            ) {
                $matches = true;
                break;
            }
        }
    }

    if ($matches) {
        $transactions[] = $transaction;
    }
}

$result->free();

$totalTransactions = count($transactions);
$totalColumns = count($fields) + 1;

/* Count statuses among the displayed records */
$statusCounts = [
    "pending" => 0,
    "paid" => 0,
    "failed" => 0,
    "refunded" => 0
];

foreach ($transactions as $transaction) {
    $status = strtolower((string) ($transaction["payment_status"] ?? ""));

    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Transactions | UniServe</title>
   <link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/manage_transactions.css?v=2">
</head>

<body class="admin-dashboard manage-transactions-page">

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
            <a href="admin_dashboard.php">
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

            <a class="active" href="manage_transactions.php">
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
                <h2>Manage Transactions</h2>
                <p>View and monitor transaction records in UniServe</p>
            </div>

            <div class="admin-info">
                <span class="admin-avatar">
                    <?= escape_html($initial) ?>
                </span>

                <div>
                    <strong><?= escape_html($full_name) ?></strong>
                    <p>System Administrator</p>
                </div>
            </div>
        </header>

        <div class="admin-content">

            <section class="welcome-section">
                <div class="welcome-copy">
                    <span class="welcome-label">UNISERVE ADMINISTRATION</span>

                    <h2>Manage Transactions</h2>

                    <p>
                        Review payment records, check transaction statuses,
                        and manage pending payment records.
                    </p>
                </div>
            </section>

            <?php if ($notice !== null): ?>
                <div
                    class="transaction-notice notice-<?= escape_html($notice["type"]) ?>"
                    role="status"
                >
                    <?= escape_html($notice["text"]) ?>
                </div>
            <?php endif; ?>

            <!-- Transaction overview -->
            <section class="transaction-summary-grid">

                <article class="transaction-summary-card">
                    <span class="summary-card-label">Total Records</span>
                    <strong><?= number_format($totalTransactions) ?></strong>
                    <small>
                        <?= $search !== "" ? "Matching your search" : "All transactions" ?>
                    </small>
                </article>

                <article class="transaction-summary-card summary-pending">
                    <span class="summary-card-label">Pending</span>
                    <strong><?= number_format($statusCounts["pending"]) ?></strong>
                    <small>Awaiting verification</small>
                </article>

                <article class="transaction-summary-card summary-paid">
                    <span class="summary-card-label">Paid</span>
                    <strong><?= number_format($statusCounts["paid"]) ?></strong>
                    <small>Marked as paid</small>
                </article>

                <article class="transaction-summary-card summary-failed">
                    <span class="summary-card-label">Failed</span>
                    <strong><?= number_format($statusCounts["failed"]) ?></strong>
                    <small>Failed transactions</small>
                </article>

            </section>

            <section class="transaction-panel">

                <div class="admin-section-heading">
                    <div>
                        <h2>Transactions List</h2>
                        <p>
                            Search records and review payment status.
                        </p>
                    </div>

                    <span class="overview-label">TRANSACTION RECORDS</span>
                </div>

                <form
                    method="GET"
                    action="manage_transactions.php"
                    class="transaction-search"
                >
                    <div class="search-input-wrap">
                        <input
                            type="search"
                            name="search"
                            placeholder="Search transaction records..."
                            value="<?= escape_html($search) ?>"
                            aria-label="Search transaction records"
                        >
                    </div>

                    <button type="submit" class="search-button">
                        Search
                    </button>

                    <?php if ($search !== ""): ?>
                        <a
                            href="manage_transactions.php"
                            class="clear-search"
                        >
                            Clear
                        </a>
                    <?php endif; ?>
                </form>

                <div class="transaction-table-wrap">
                    <table class="transaction-table">

                        <thead>
                            <tr>
                                <?php foreach ($fields as $field): ?>
                                    <th>
                                        <?= escape_html(
                                            ucwords(
                                                str_replace(
                                                    "_",
                                                    " ",
                                                    $field->name
                                                )
                                            )
                                        ) ?>
                                    </th>
                                <?php endforeach; ?>

                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!empty($transactions)): ?>

                                <?php foreach ($transactions as $transaction): ?>
                                    <?php
                                    $status = strtolower(
                                        (string) ($transaction["payment_status"] ?? "")
                                    );

                                    $statusClass = in_array(
                                        $status,
                                        ["pending", "paid", "failed", "refunded"],
                                        true
                                    ) ? $status : "unknown";
                                    ?>

                                    <tr>
                                        <?php foreach ($transaction as $column => $value): ?>
                                            <td>
                                                <?php if ($column === "payment_status"): ?>
                                                    <span class="payment-status status-<?= escape_html($statusClass) ?>">
                                                        <?= escape_html(ucfirst($status !== "" ? $status : "Unknown")) ?>
                                                    </span>
                                                <?php elseif ($column === "amount"): ?>
                                                    <span class="transaction-amount">
                                                        ₱<?= escape_html(number_format((float) $value, 2)) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <?= escape_html($value ?? "—") ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>

                                        <td class="transaction-actions-cell">
                                            <?php if ($status === "pending"): ?>

                                                <div class="transaction-actions">

                                                    <form
                                                        method="POST"
                                                        action="manage_transactions.php"
                                                        onsubmit="return confirm('Confirm that payment has been received and verified before marking this transaction as paid?');"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= escape_html($_SESSION["transactions_csrf"]) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="transaction_id"
                                                            value="<?= (int) $transaction["transaction_id"] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="new_status"
                                                            value="paid"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="search"
                                                            value="<?= escape_html($search) ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="transaction-action-btn action-paid"
                                                        >
                                                            Mark as Paid
                                                        </button>
                                                    </form>

                                                    <form
                                                        method="POST"
                                                        action="manage_transactions.php"
                                                        onsubmit="return confirm('Mark this pending transaction as failed?');"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= escape_html($_SESSION["transactions_csrf"]) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="transaction_id"
                                                            value="<?= (int) $transaction["transaction_id"] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="new_status"
                                                            value="failed"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="search"
                                                            value="<?= escape_html($search) ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="transaction-action-btn action-failed"
                                                        >
                                                            Mark as Failed
                                                        </button>
                                                    </form>

                                                </div>

                                            <?php else: ?>
                                                <span class="action-completed">
                                                    No actions available
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>
                                <tr>
                                    <td
                                        colspan="<?= $totalColumns ?>"
                                        class="empty-state"
                                    >
                                        <div class="empty-state-icon">⌕</div>

                                        <?php if ($search !== ""): ?>
                                            <strong>No matching transactions</strong>
                                            <p>Try a different keyword.</p>
                                        <?php else: ?>
                                            <strong>No transactions found</strong>
                                            <p>
                                                Transaction records will appear
                                                here when available.
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                    </table>
                </div>

                <div class="transaction-panel-footer">
                    <span>
                        Showing <?= number_format($totalTransactions) ?>
                        transaction(s)
                    </span>

                    <a
                        href="admin_dashboard.php"
                        class="back-dashboard"
                    >
                        ← Back to Dashboard
                    </a>
                </div>

            </section>

            <footer class="admin-footer">
                UniServe &copy; <?= date("Y") ?>
                — Unified Business and Service Management Application
            </footer>

        </div>
    </main>

</div>

</body>
</html>
<?php
$conn->close();
?>
