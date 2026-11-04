
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

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$full_name = $_SESSION["full_name"] ?? "Administrator";
$initial = strtoupper(substr($full_name, 0, 1));
$search = trim($_GET["search"] ?? "");

$sql = "
    SELECT
        b.business_id,
        b.owner_id,
        u.full_name AS owner_name,
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

if ($search !== "") {
    $sql .= "
        WHERE
            CAST(b.business_id AS CHAR) LIKE ?
            OR CAST(b.owner_id AS CHAR) LIKE ?
            OR u.full_name LIKE ?
            OR CAST(b.category_id AS CHAR) LIKE ?
            OR c.category_name LIKE ?
            OR b.business_name LIKE ?
            OR b.description LIKE ?
            OR b.address LIKE ?
            OR b.contact_number LIKE ?
            OR b.status LIKE ?
    ";
}

$sql .= " ORDER BY b.business_id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Manage businesses query failed: " . $conn->error);
    die("Unable to load business records.");
}

if ($search !== "") {
    $keyword = "%" . $search . "%";
    $types = str_repeat("s", 10);
    $params = array_fill(0, 10, $keyword);

    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    error_log("Manage businesses execution failed: " . $stmt->error);
    die("Unable to load business records.");
}

$result = $stmt->get_result();
$total_businesses = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Businesses | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/manage_businesses.css">
</head>

<body class="admin-dashboard manage-businesses-page">

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

            <a class="active" href="manage_businesses.php" aria-current="page">
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
                <h2>Manage Businesses</h2>
                <p>Review and manage registered businesses.</p>
            </div>

            <div class="admin-info">
                <span class="admin-avatar">
                    <?= escape($initial) ?>
                </span>

                <div>
                    <strong><?= escape($full_name) ?></strong>
                    <p>System Administrator</p>
                </div>
            </div>
        </header>

        <div class="admin-content">

            <section class="businesses-welcome">
                <div>
                    <span class="businesses-eyebrow">
                        BUSINESS MANAGEMENT
                    </span>

                    <h2>Manage Businesses</h2>

                    <p>
                        View registered businesses, review their details,
                        and manage registration approval status.
                    </p>
                </div>
            </section>

            <section class="businesses-records">

                <div class="businesses-section-heading">
                    <div>
                        <h2>Registered Businesses</h2>
                        <p>
                            Search and review business records and update
                            registration status.
                        </p>
                    </div>

                    <span class="businesses-count">
                        <?= number_format($total_businesses) ?>
                        <?= $total_businesses === 1 ? "record" : "records" ?>
                    </span>
                </div>
<form method="GET"
      action="manage_businesses.php"
      class="businesses-search-form">

    <div class="businesses-search-field">
        <label for="business-search">Search businesses</label>
        <input
            type="search"
            id="business-search"
            name="search"
            placeholder="Search by business, owner, category, address..."
            value="<?= escape($search) ?>"
        >
    </div>

    <button type="submit" class="business-search-button">
        Search
    </button>

    <a href="manage_businesses.php"
       class="business-reset-button">
        Reset
    </a>

</form>
                <div class="businesses-table-container">
                    <table class="businesses-table">
                        <thead>
                            <tr>
                                <th>Business ID</th>
                                <th>Owner ID</th>
                                <th>Owner Name</th>
                                <th>Category ID</th>
                                <th>Category</th>
                                <th>Business Name</th>
                                <th>Description</th>
                                <th>Address</th>
                                <th>Contact Number</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Update Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($total_businesses > 0): ?>

                            <?php while ($business = $result->fetch_assoc()): ?>
                                <?php
                                $status = strtolower(
                                    trim((string) $business["status"])
                                );

                                $status_class = in_array(
                                    $status,
                                    ["pending", "approved", "rejected"],
                                    true
                                ) ? $status : "unknown";
                                ?>

                                <tr>
                                    <td class="business-id">
                                        #<?= (int) $business["business_id"] ?>
                                    </td>

                                    <td>
                                        <?= escape($business["owner_id"] ?? "N/A") ?>
                                    </td>

                                    <td>
                                        <?= escape($business["owner_name"] ?? "Unknown") ?>
                                    </td>

                                    <td>
                                        <?= escape($business["category_id"] ?? "N/A") ?>
                                    </td>

                                    <td>
                                        <?= escape($business["category_name"] ?? "Uncategorized") ?>
                                    </td>

                                    <td class="business-name">
                                        <?= escape($business["business_name"]) ?>
                                    </td>

                                    <td class="business-description">
                                        <?= escape($business["description"] ?? "") ?>
                                    </td>

                                    <td class="business-address">
                                        <?= escape($business["address"] ?? "") ?>
                                    </td>

                                    <td>
                                        <?= escape($business["contact_number"] ?? "") ?>
                                    </td>

                                    <td>
                                        <span class="business-status status-<?= escape($status_class) ?>">
                                            <?= escape(ucfirst($status ?: "Unknown")) ?>
                                        </span>
                                    </td>

                                    <td class="business-created">
                                        <?php
                                        if (!empty($business["created_at"])) {
                                            $timestamp = strtotime($business["created_at"]);

                                            echo $timestamp !== false
                                                ? escape(date("M d, Y", $timestamp))
                                                : "—";
                                        } else {
                                            echo "—";
                                        }
                                        ?>
                                    </td>

                                    <td>
                                        <form
                                            class="business-status-form"
                                            method="POST"
                                            action="update_business_status.php"
                                        >
                                            <input
                                                type="hidden"
                                                name="business_id"
                                                value="<?= (int) $business["business_id"] ?>"
                                            >

                                            <select
                                                name="status"
                                                aria-label="Update business status"
                                                required
                                            >
                                                <option value="pending"
                                                    <?= $status === "pending" ? "selected" : "" ?>>
                                                    Pending
                                                </option>

                                                <option value="approved"
                                                    <?= $status === "approved" ? "selected" : "" ?>>
                                                    Approved
                                                </option>

                                                <option value="rejected"
                                                    <?= $status === "rejected" ? "selected" : "" ?>>
                                                    Rejected
                                                </option>
                                            </select>

                                            <button type="submit">Save</button>
                                        </form>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="12" class="businesses-empty">
                                    <div class="businesses-empty-icon">⌕</div>
                                    <h3>No businesses found</h3>
                                    <p>
                                        Try another search or check your
                                        search keywords.
                                    </p>
                                </td>
                            </tr>

                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="businesses-panel-footer">
                    Showing
                    <strong><?= number_format($total_businesses) ?></strong>
                    matching
                    <?= $total_businesses === 1 ? "business record." : "business records." ?>
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
$stmt->close();
$conn->close();
?>
