
<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: login.php");
    exit();
}

function escape_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$adminName = $_SESSION["full_name"] ?? "Administrator";
$adminInitial = strtoupper(substr($adminName, 0, 1));
$search = trim($_GET["search"] ?? "");

$sql = "SELECT * FROM service_requests ORDER BY request_id DESC";
$result = $conn->query($sql);

if (!$result) {
    die("Unable to load service requests. Please check the existing database table.");
}

$columns = [];
foreach ($result->fetch_fields() as $field) {
    $columns[] = $field->name;
}

$requests = [];

while ($row = $result->fetch_assoc()) {
    if ($search === "") {
        $requests[] = $row;
        continue;
    }

    foreach ($row as $value) {
        if (
            $value !== null &&
            stripos((string) $value, $search) !== false
        ) {
            $requests[] = $row;
            break;
        }
    }
}

$totalRequests = count($requests);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Requests | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/manage_requests.css">
</head>

<body class="admin-dashboard manage-requests-page">

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

            <a class="active" href="manage_requests.php">
                <span class="nav-icon">☷</span> Manage Requests
            </a>

            <a href="manage_transactions.php">
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
                <h2>Manage Requests</h2>
                <p>View and monitor customer service requests</p>
            </div>

            <div class="admin-info">
                <span class="admin-avatar">
                    <?= escape_html($adminInitial) ?>
                </span>

                <div>
                    <strong><?= escape_html($adminName) ?></strong>
                    <p>System Administrator</p>
                </div>
            </div>
        </header>

        <div class="admin-content">

            <section class="welcome-section">
                <div class="welcome-copy">
                    <span class="welcome-label">
                        UNISERVE ADMINISTRATION
                    </span>

                    <h2>Manage Service Requests</h2>

                    <p>
                        Review and monitor service request records
                        submitted through the UniServe platform.
                    </p>
                </div>
            </section>
            <br>

            <section class="requests-table-panel">

                <div class="requests-table-heading">
                    <div>
                        <h2>Service Requests List</h2>
                        <p>Search and review the available request records.</p>
                    </div>

                    <span class="requests-count">
                        <?= number_format($totalRequests) ?>
                        <?= $totalRequests === 1 ? "Request" : "Requests" ?>
                    </span>
                </div>

                <form
                    action="manage_requests.php"
                    method="GET"
                    class="requests-search-form"
                >
                    <div class="requests-search-input">
                        <input
                            type="search"
                            name="search"
                            value="<?= escape_html($search) ?>"
                            placeholder="Search request records..."
                            aria-label="Search service requests"
                        >
                    </div>

                    <button type="submit">Search</button>

                    <?php if ($search !== ""): ?>
                        <a class="requests-clear" href="manage_requests.php">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>

                <div class="requests-table-wrap">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <th>
                                        <?= escape_html(
                                            ucwords(str_replace("_", " ", $column))
                                        ) ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($totalRequests > 0): ?>

                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <?php
                                            $value = $request[$column] ?? "";
                                            $columnName = strtolower($column);

                                            $isStatus = (
                                                strpos($columnName, "status") !== false ||
                                                strpos($columnName, "availability") !== false
                                            );

                                            $statusClass = "other";

                                            if ($isStatus) {
                                                $statusValue = strtolower(trim((string) $value));

                                                if (in_array(
                                                    $statusValue,
                                                    ["approved", "completed", "accepted", "resolved", "active"],
                                                    true
                                                )) {
                                                    $statusClass = "success";
                                                } elseif (in_array(
                                                    $statusValue,
                                                    ["pending", "processing", "in progress"],
                                                    true
                                                )) {
                                                    $statusClass = "pending";
                                                } elseif (in_array(
                                                    $statusValue,
                                                    ["rejected", "cancelled", "canceled", "failed"],
                                                    true
                                                )) {
                                                    $statusClass = "danger";
                                                }
                                            }
                                            ?>

                                            <td>
                                                <?php if ($isStatus && $value !== ""): ?>
                                                    <span class="request-status <?= $statusClass ?>">
                                                        <?= escape_html($value) ?>
                                                    </span>
                                                <?php elseif ($columnName === "request_id"): ?>
                                                    <span class="request-id">
                                                        #<?= escape_html($value) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="request-cell-value">
                                                        <?= $value !== null && $value !== ""
                                                            ? escape_html($value)
                                                            : "—" ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="<?= max(count($columns), 1) ?>">
                                        <div class="requests-empty">
                                            <span class="requests-empty-icon">☷</span>

                                            <h3>
                                                <?= $search !== ""
                                                    ? "No Matching Requests Found"
                                                    : "No Service Requests Available" ?>
                                            </h3>

                                            <p>
                                                <?= $search !== ""
                                                    ? "Try another keyword or clear your search."
                                                    : "Service request records will appear here when available." ?>
                                            </p>

                                            <?php if ($search !== ""): ?>
                                                <a href="manage_requests.php">Clear Search</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="requests-table-footer">
                    <span>
                        Showing <?= number_format($totalRequests) ?>
                        <?= $totalRequests === 1 ? "record" : "records" ?>
                    </span>

                    <a href="admin_dashboard.php">← Back to Dashboard</a>
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
$result->free();
$conn->close();
?>
