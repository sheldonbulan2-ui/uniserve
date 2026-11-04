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

$sql = "SELECT category_id, category_name, description, created_at
        FROM categories";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " WHERE category_name LIKE ? OR description LIKE ?";
    $keyword = "%" . $search . "%";
    $params = [$keyword, $keyword];
    $types = "ss";
}

$sql .= " ORDER BY category_id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Categories query failed: " . $conn->error);
    die("Unable to load categories.");
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    error_log("Categories execution failed: " . $stmt->error);
    die("Unable to load categories.");
}

$result = $stmt->get_result();
$total_categories = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/manage_categories.css">
</head>

<body class="admin-dashboard manage-categories-page">

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

            <a class="active" href="manage_categories.php">
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
                <h2>Manage Categories</h2>
                <p>Organize and monitor service categories.</p>
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

            <section class="categories-welcome">
                <div>
                    <span class="categories-eyebrow">CATEGORY MANAGEMENT</span>
                    <h2>Manage Categories</h2>
                    <p>
                        Organize the categories used to classify
                        businesses and services in UniServe.
                    </p>
                </div>

                <a href="add_category.php" class="category-add-button">
                    <span>+</span> Add Category
                </a>
            </section>


            <section class="categories-panel">

                <div class="categories-panel-heading">
                    <div>
                        <h2>Category Directory</h2>
                        <p>Search and review registered categories.</p>
                    </div>

                    <span class="categories-count">
                        <?= number_format($total_categories) ?>
                        <?= $total_categories === 1 ? "category" : "categories" ?>
                    </span>
                </div>

                <form method="GET"
                      action="manage_categories.php"
                      class="categories-search-form">

                    <div class="categories-search-field">
                        <label for="search">Search categories</label>

                        <input
                            type="search"
                            id="search"
                            name="search"
                            placeholder="Search by category name or description..."
                            value="<?= escape($search) ?>"
                        >
                    </div>

                    <button type="submit" class="category-search-button">
                        Search
                    </button>

                    <a href="manage_categories.php"
                       class="category-reset-button">
                        Reset
                    </a>
                </form>

                <div class="categories-table-wrapper">
                    <table class="categories-table">
                        <thead>
                            <tr>
                                <th>Category ID</th>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($total_categories > 0): ?>

                            <?php while ($category = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="category-id">
                                            #<?= escape($category["category_id"]) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="category-name-cell">
                                            <span class="category-icon">▦</span>
                                            <strong>
                                                <?= escape($category["category_name"]) ?>
                                            </strong>
                                        </div>
                                    </td>

                                    <td class="category-description">
                                        <?= escape(
                                            $category["description"] ?: "No description provided"
                                        ) ?>
                                    </td>

                                    <td class="category-date">
                                        <?php
                                        if (!empty($category["created_at"])) {
                                            $timestamp = strtotime($category["created_at"]);

                                            echo $timestamp !== false
                                                ? escape(date("M d, Y", $timestamp))
                                                : "—";
                                        } else {
                                            echo "—";
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="categories-empty">
                                    <div class="category-empty-icon">⌕</div>
                                    <strong>No categories found</strong>
                                    <p>
                                        Try another search or add a new category.
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="categories-panel-footer">
                    Showing
                    <strong><?= number_format($total_categories) ?></strong>
                    matching <?= $total_categories === 1 ? "category" : "categories" ?>.
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
