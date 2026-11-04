
<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit;
}

function escape_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$search = trim($_GET["search"] ?? "");
$role = trim($_GET["role"] ?? "");

$allowed_roles = ["admin", "business_owner", "customer"];

if (!in_array($role, $allowed_roles, true)) {
    $role = "";
}

$admin_name = $_SESSION["full_name"] ?? "Administrator";
$initial = strtoupper(substr($admin_name, 0, 1));

$sql = "SELECT user_id, full_name, email, role, created_at
        FROM users
        WHERE 1=1";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " AND (full_name LIKE ? OR email LIKE ?)";
    $keyword = "%" . $search . "%";

    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "ss";
}

if ($role !== "") {
    $sql .= " AND role = ?";
    $params[] = $role;
    $types .= "s";
}

$sql .= " ORDER BY user_id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Manage users query preparation failed: " . $conn->error);
    die("Unable to load users.");
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    error_log("Manage users query execution failed: " . $stmt->error);
    die("Unable to load users.");
}

$result = $stmt->get_result();
$total_users = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Users | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/manage_users.css">
</head>

<body class="admin-dashboard manage-users-page">

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
            <a class="active" href="manage_users.php">
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
                <h2>Manage Users</h2>
                <p>Manage and monitor registered UniServe accounts.</p>
            </div>

            <div class="admin-info">
                <div class="admin-avatar">
                    <?= escape_html($initial) ?>
                </div>

                <div>
                    <strong><?= escape_html($admin_name) ?></strong>
                    <p>Administrator</p>
                </div>
            </div>
        </header>

        <section class="admin-content">

            <!-- PAGE INTRODUCTION -->
            <div class="users-welcome">
                <div>
                    <span class="users-eyebrow">USER MANAGEMENT</span>
                    <h1>Registered Users</h1>
                    <p>
                        View, search, and manage all registered accounts
                        across the UniServe application.
                    </p>
                </div>

                <a href="add_user.php" class="users-primary-button">
                    <span>+</span>
                    Add New User
                </a>
            </div>


            <!-- USERS TABLE PANEL -->
            <section class="users-panel">

                <div class="users-panel-heading">
                    <div>
                        <h2>User Directory</h2>
                        <p>
                            Search accounts and filter users by their
                            assigned role.
                        </p>
                    </div>

                    <span class="users-count">
                        <?= number_format($total_users) ?>
                        <?= $total_users === 1 ? "user" : "users" ?>
                    </span>
                </div>

                <!-- SEARCH AND FILTER -->
                <form
                    method="GET"
                    action="manage_users.php"
                    class="users-filter-form"
                >

                    
<div class="users-search-field">
    <label for="search">Search users</label>

    <div class="users-search-controls">
        <div class="users-input-wrap"><br>
            <input
                type="search"
                id="search"
                name="search"
                placeholder="Search by name or email..."
                value="<?= escape_html($search) ?>"
            >
        </div>

        <button type="submit" class="users-filter-button">
            Search
        </button>
    </div>


                    </div>

                    <div class="users-role-field">
                        <label for="role">Filter by role</label>

                        <select id="role" name="role">
                            <option value="">All Roles</option>

                            <option value="admin"
                                <?= $role === "admin" ? "selected" : "" ?>>
                                Admin
                            </option>

                            <option value="business_owner"
                                <?= $role === "business_owner" ? "selected" : "" ?>>
                                Business Owner
                            </option>

                            <option value="customer"
                                <?= $role === "customer" ? "selected" : "" ?>>
                                Customer
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="users-filter-button">
                        Apply Filters
                    </button>

                    <a href="manage_users.php" class="users-reset-button">
                        Reset
                    </a>

                </form>

                <!-- TABLE -->
                <div class="users-table-wrapper">
                    <table class="users-table">

                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>User Information</th>
                                <th>Email Address</th>
                                <th>Role</th>
                                <th>Date Registered</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if ($total_users > 0): ?>

                            <?php while ($user = $result->fetch_assoc()): ?>

                                <?php
                                $user_name = trim($user["full_name"] ?? "");
                                $user_initial = strtoupper(
                                    substr($user_name !== "" ? $user_name : "U", 0, 1)
                                );

                                $user_role = $user["role"] ?? "";
                                $role_class = in_array(
                                    $user_role,
                                    $allowed_roles,
                                    true
                                ) ? $user_role : "unknown";
                                ?>

                                <tr>
                                    <td>
                                        <span class="user-id">
                                            #<?= escape_html($user["user_id"]) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="user-name-cell">
                                            <div class="user-initial">
                                                <?= escape_html($user_initial) ?>
                                            </div>

                                            <strong>
                                                <?= escape_html($user_name ?: "Unnamed User") ?>
                                            </strong>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="user-email">
                                            <?= escape_html($user["email"]) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="user-role role-<?= escape_html($role_class) ?>">
                                            <?= escape_html(
                                                ucwords(str_replace("_", " ", $user_role))
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="user-date">
                                            <?php
                                            if (!empty($user["created_at"])) {
                                                $timestamp = strtotime($user["created_at"]);

                                                echo $timestamp !== false
                                                    ? escape_html(date("M d, Y", $timestamp))
                                                    : "—";
                                            } else {
                                                echo "—";
                                            }
                                            ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="users-empty-state">
                                    <div class="empty-state-icon">⌕</div>
                                    <strong>No users found</strong>
                                    <p>
                                        No accounts match your search or
                                        selected role. Try adjusting your filters.
                                    </p>
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>
                    </table>
                </div>

                <div class="users-panel-footer">
                    Showing
                    <strong><?= number_format($total_users) ?></strong>
                    matching <?= $total_users === 1 ? "account" : "accounts" ?>.
                </div>

            </section>

            <footer class="admin-footer">
                <span>
                    UniServe &copy; <?= date("Y") ?>
                    — Unified Business and Service Management Application
                </span>
                <span>User Management</span>
            </footer>

        </section>

    </main>

</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
