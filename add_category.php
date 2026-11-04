<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

function escape_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$message = "";
$category_name = "";
$description = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $category_name = trim($_POST["category_name"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($category_name === "") {
        $message = "Please enter a category name.";
    } elseif (strlen($category_name) > 100) {
        $message = "Category name must not exceed 100 characters.";
    } else {
        $check = $conn->prepare(
            "SELECT category_id
             FROM categories
             WHERE category_name = ?
             LIMIT 1"
        );

        if (!$check) {
            error_log("Add category check prepare failed: " . $conn->error);
            $message = "Unable to validate the category. Please try again.";
        } else {
            $check->bind_param("s", $category_name);

            if (!$check->execute()) {
                error_log("Add category check failed: " . $check->error);
                $message = "Unable to validate the category. Please try again.";
                $check->close();
            } else {
                $check->store_result();

                if ($check->num_rows > 0) {
                    $message = "This category already exists.";
                    $check->close();
                } else {
                    $check->close();

                    $stmt = $conn->prepare(
                        "INSERT INTO categories (category_name, description)
                         VALUES (?, ?)"
                    );

                    if (!$stmt) {
                        error_log("Add category insert prepare failed: " . $conn->error);
                        $message = "Unable to add the category. Please try again.";
                    } else {
                        $description_value = ($description === "")
                            ? null
                            : $description;

                        $stmt->bind_param(
                            "ss",
                            $category_name,
                            $description_value
                        );

                        if ($stmt->execute()) {
                            $stmt->close();
                            $conn->close();

                            header("Location: manage_categories.php");
                            exit();
                        }

                        error_log("Add category insert failed: " . $stmt->error);
                        $message = "Unable to add the category. Please try again.";
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$admin_name = $_SESSION["full_name"] ?? "Administrator";
$admin_initial = strtoupper(substr($admin_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Category | UniServe</title>

    <link rel="stylesheet" href="assets/css/add_category.css">
</head>

<body class="add-category-page">

<div class="add-category-layout">

    <aside class="add-category-sidebar">

        <a href="admin_dashboard.php" class="add-category-brand">
            <div class="add-category-brand-icon">U</div>

            <div class="add-category-brand-text">
                <h1>UniServe</h1>
                <p>Business &amp; Service Management</p>
            </div>
        </a>

        <div class="add-category-menu-label">ADMINISTRATION</div>

        <nav class="add-category-nav" aria-label="Admin navigation">

            <a href="admin_dashboard.php" class="add-category-nav-link">
                <span class="add-category-nav-icon">▦</span>
                <span>Dashboard</span>
            </a>

            <a href="manage_users.php" class="add-category-nav-link">
                <span class="add-category-nav-icon">♙</span>
                <span>Manage Users</span>
            </a>

            <a href="manage_categories.php" class="add-category-nav-link active">
                <span class="add-category-nav-icon">▤</span>
                <span>Manage Categories</span>
            </a>

        </nav>

        <div class="add-category-sidebar-bottom">

            <div class="add-category-menu-label">ACCOUNT</div>

            <nav class="add-category-nav" aria-label="Account navigation">
                <a href="logout.php" class="add-category-nav-link add-category-logout">
                    <span class="add-category-nav-icon">↪</span>
                    <span>Logout</span>
                </a>
            </nav>

            <div class="add-category-sidebar-footer">
                <span class="add-category-status-dot"></span>
                Administrator Access
            </div>

        </div>

    </aside>

    <div class="add-category-main">

        <header class="add-category-topbar">

            <div class="add-category-topbar-title">
                <span class="add-category-topbar-eyebrow">ADMINISTRATION</span>
                <h2>Add Category</h2>
                <p>Create and organize service categories</p>
            </div>

            <div class="add-category-admin-info">

                <div class="add-category-avatar">
                    <?= escape_html($admin_initial) ?>
                </div>

                <div class="add-category-admin-details">
                    <strong><?= escape_html($admin_name) ?></strong>
                    <span>Administrator</span>
                </div>

            </div>

        </header>

        <main class="add-category-content">

            <section class="add-category-welcome">

                <div class="add-category-welcome-text">
                    <span class="add-category-eyebrow">CATEGORY MANAGEMENT</span>
                    <h2>Create New Category</h2>

                    <p>
                        Add a category to help organize the services
                        available within the UniServe application.
                    </p>
                </div>

            </section>

            <section class="add-category-panel">

                <div class="add-category-panel-heading">

                    <div class="add-category-heading-icon">＋</div>

                    <div>
                        <h3>Category Information</h3>
                        <p>Enter the category details in the fields below.</p>
                    </div>

                </div>

                <div class="add-category-divider"></div>

                <?php if ($message !== ""): ?>
                    <div class="add-category-alert error" role="alert">
                        <span class="add-category-alert-icon">!</span>
                        <p><?= escape_html($message) ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="add_category.php">

                    <div class="add-category-form-grid">

                        <div class="add-category-form-group">
                            <label for="category_name">
                                Category Name <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="category_name"
                                name="category_name"
                                maxlength="100"
                                value="<?= escape_html($category_name) ?>"
                                placeholder="Enter the category name"
                                required
                            >

                            <span class="add-category-helper">
                                Use a clear name that describes the category.
                            </span>
                        </div>

                        <div class="add-category-form-group">
                            <label for="description">
                                Description
                                <span class="optional-label">(Optional)</span>
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                rows="6"
                                placeholder="Describe the purpose of this category..."
                            ><?= escape_html($description) ?></textarea>

                            <span class="add-category-helper">
                                Provide additional details to help identify the category.
                            </span>
                        </div>

                    </div>

                    <div class="add-category-form-actions">

                        <a
                            href="manage_categories.php"
                            class="add-category-btn secondary"
                        >
                            Cancel
                        </a>

                        <button type="submit" class="add-category-btn primary">
                            <span>＋</span>
                            Add Category
                        </button>

                    </div>

                </form>

            </section>

            <footer class="add-category-footer">
                <span>UniServe</span>
                <span class="add-category-footer-separator">•</span>
                &copy; <?= date("Y") ?>
                Unified Business and Service Management Application
            </footer>

        </main>

    </div>

</div>

</body>
</html>

<?php
$conn->close();
?>
