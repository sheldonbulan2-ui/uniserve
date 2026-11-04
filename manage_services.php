
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

$adminName = $_SESSION["full_name"] ?? "Administrator";
$adminInitial = strtoupper(substr($adminName, 0, 1));
$search = trim($_GET["search"] ?? "");

$sql = "
    SELECT
        s.service_id,
        s.business_id,
        s.service_name,
        s.description,
        s.price,
        s.availability,
        s.created_at,
        b.business_name
    FROM services s
    LEFT JOIN businesses b
        ON s.business_id = b.business_id
";

if ($search !== "") {
    $sql .= "
        WHERE s.service_name LIKE ?
           OR s.description LIKE ?
           OR s.availability LIKE ?
           OR b.business_name LIKE ?
           OR CAST(s.business_id AS CHAR) LIKE ?
    ";
}

$sql .= " ORDER BY s.service_id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to load services. Please check the database structure.");
}

if ($search !== "") {
    $term = "%" . $search . "%";
    $stmt->bind_param(
        "sssss",
        $term,
        $term,
        $term,
        $term,
        $term
    );
}

if (!$stmt->execute()) {
    die("Unable to load services.");
}

$result = $stmt->get_result();
$totalServices = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Services | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/manage_services.css">
</head>

<body class="admin-dashboard manage-services-page">

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

            <a class="active" href="manage_services.php">
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
                <h2>Manage Services</h2>
                <p>View and monitor services registered on UniServe</p>
            </div>

            <div class="admin-info">
                <span class="admin-avatar">
                    <?= escape($adminInitial) ?>
                </span>

                <div>
                    <strong><?= escape($adminName) ?></strong>
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

                    <h2>Manage Your Services</h2>

                    <p>
                        Review service details, business information,
                        pricing, and availability from one place.
                    </p>
                </div>
            </section>

            <br>
            <section class="services-table-panel">

                <div class="services-table-heading">
                    <div>
                        <h2>Services List</h2>
                        <p>View and search registered business services.</p>
                    </div>

                    <span class="services-count">
                        <?= number_format($totalServices) ?>
                        <?= $totalServices === 1 ? "Service" : "Services" ?>
                    </span>
                </div>

                <form
                    action="manage_services.php"
                    method="GET"
                    class="services-search-form"
                >
                    <div class="services-search-input">
                        <input
                            type="search"
                            name="search"
                            value="<?= escape($search) ?>"
                            placeholder="Search service, business, or description..."
                            aria-label="Search services"
                        >
                    </div>

                    <button type="submit">Search</button>

                    <?php if ($search !== ""): ?>
                        <a class="services-clear" href="manage_services.php">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>

                <div class="services-table-wrap">
                    <table class="services-table">
                        <thead>
                            <tr>
                                <th>Service ID</th>
                                <th>Business</th>
                                <th>Service Name</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Availability</th>
                                <th>Created At</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($totalServices > 0): ?>

                            <?php while ($service = $result->fetch_assoc()): ?>
                                <?php
                                $businessName = $service["business_name"]
                                    ?? "Unknown Business";

                                $availability = trim(
                                    (string) ($service["availability"] ?? "")
                                );

                                $status = strtolower($availability);

                                if (in_array($status, ["available", "active", "yes", "1"], true)) {
                                    $statusClass = "available";
                                } elseif (in_array($status, ["unavailable", "inactive", "no", "0"], true)) {
                                    $statusClass = "unavailable";
                                } else {
                                    $statusClass = "other";
                                }

                                $description = trim(
                                    (string) ($service["description"] ?? "")
                                );
                                ?>

                                <tr>
                                    <td>
                                        <span class="service-id">
                                            #<?= (int) $service["service_id"] ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="business-cell">
                                            <span class="business-avatar">
                                                <?= escape(strtoupper(substr($businessName, 0, 1))) ?>
                                            </span>

                                            <div>
                                                <strong><?= escape($businessName) ?></strong>
                                                <small>
                                                    Business ID:
                                                    <?= (int) $service["business_id"] ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <strong class="service-name">
                                            <?= escape($service["service_name"]) ?>
                                        </strong>
                                    </td>

                                    <td class="description-cell">
                                        <?= $description !== ""
                                            ? escape($description)
                                            : "No description" ?>
                                    </td>

                                    <td>
                                        <strong class="service-price">
                                            ₱<?= number_format((float) $service["price"], 2) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <span class="service-status <?= $statusClass ?>">
                                            <?= escape(
                                                $availability !== ""
                                                    ? $availability
                                                    : "Not specified"
                                            ) ?>
                                        </span>
                                    </td>

                                    <td class="created-cell">
                                        <?= escape($service["created_at"] ?? "—") ?>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7">
                                    <div class="services-empty">
                                        <span>◇</span>

                                        <h3>
                                            <?= $search !== ""
                                                ? "No Matching Services Found"
                                                : "No Services Available" ?>
                                        </h3>

                                        <p>
                                            <?= $search !== ""
                                                ? "Try another keyword or clear your search."
                                                : "Registered services will appear here." ?>
                                        </p>

                                        <?php if ($search !== ""): ?>
                                            <a href="manage_services.php">Clear Search</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="services-table-footer">
                    <span>
                        Showing <?= number_format($totalServices) ?>
                        <?= $totalServices === 1 ? "record" : "records" ?>
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
$stmt->close();
$conn->close();
?>
