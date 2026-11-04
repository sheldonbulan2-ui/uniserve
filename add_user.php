
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
$full_name = "";
$email = "";
$role = "customer";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $role = trim($_POST["role"] ?? "customer");

    $allowed_roles = ["admin", "business_owner", "customer"];

    if ($full_name === "" || $email === "" || $password === "") {
        $message = "Please complete all required fields.";
    } elseif (strlen($full_name) > 100) {
        $message = "Full name must not exceed 100 characters.";
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 100
    ) {
        $message = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (!in_array($role, $allowed_roles, true)) {
        $message = "Please select a valid role.";
    } else {
        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ? LIMIT 1"
        );

        if (!$check) {
            error_log("Add user email check prepare failed: " . $conn->error);
            $message = "Unable to validate the email address. Please try again.";
        } else {
            $check->bind_param("s", $email);

            if (!$check->execute()) {
                error_log("Add user email check failed: " . $check->error);
                $message = "Unable to validate the email address. Please try again.";
                $check->close();
            } else {
                $check->store_result();

                if ($check->num_rows > 0) {
                    $message = "This email address is already registered.";
                    $check->close();
                } else {
                    $check->close();

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare(
                        "INSERT INTO users (full_name, email, password, role)
                         VALUES (?, ?, ?, ?)"
                    );

                    if (!$stmt) {
                        error_log("Add user insert prepare failed: " . $conn->error);
                        $message = "Unable to add the user. Please try again.";
                    } else {
                        $stmt->bind_param(
                            "ssss",
                            $full_name,
                            $email,
                            $hashed_password,
                            $role
                        );

                        if ($stmt->execute()) {
                            $stmt->close();
                            $conn->close();

                            header("Location: manage_users.php");
                            exit();
                        }

                        error_log("Add user insert failed: " . $stmt->error);
                        $message = "Unable to add the user. Please try again.";
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

    <title>Add User | UniServe</title>


    <!-- Add User page stylesheet -->
    <link rel="stylesheet" href="assets/css/add_user.css">
</head>

<body class="add-user-page">

<div class="add-user-layout">

    <aside class="add-user-sidebar">

        <a href="admin_dashboard.php" class="add-user-brand">
            <div class="add-user-brand-icon">U</div>

            <div class="add-user-brand-text">
                <h1>UniServe</h1>
                <p>Business &amp; Service Management</p>
            </div>
        </a>

        <div class="add-user-menu-label">ADMINISTRATION</div>

        <nav class="add-user-nav" aria-label="Admin navigation">

            <a href="admin_dashboard.php" class="add-user-nav-link">
                <span class="add-user-nav-icon">▦</span>
                <span>Dashboard</span>
            </a>

            <a href="manage_users.php" class="add-user-nav-link active">
                <span class="add-user-nav-icon">♙</span>
                <span>Manage Users</span>
            </a>

        </nav>

        <div class="add-user-sidebar-bottom">

            <div class="add-user-menu-label">ACCOUNT</div>

            <nav class="add-user-nav" aria-label="Account navigation">
                <a href="logout.php" class="add-user-nav-link add-user-logout">
                    <span class="add-user-nav-icon">↪</span>
                    <span>Logout</span>
                </a>
            </nav>

            <div class="add-user-sidebar-footer">
                <span class="add-user-status-dot"></span>
                Administrator Access
            </div>

        </div>

    </aside>

    <div class="add-user-main">

        <header class="add-user-topbar">

            <div class="add-user-topbar-title">
                <span class="add-user-topbar-eyebrow">ADMINISTRATION</span>
                <h2>Add User</h2>
                <p>Create a new UniServe account</p>
            </div>

            <div class="add-user-admin-info">

                <div class="add-user-avatar">
                    <?= escape_html($admin_initial) ?>
                </div>

                <div class="add-user-admin-details">
                    <strong><?= escape_html($admin_name) ?></strong>
                    <span>Administrator</span>
                </div>

            </div>

        </header>

        <main class="add-user-content">

            <section class="add-user-welcome">

                <div class="add-user-welcome-text">
                    <span class="add-user-eyebrow">USER MANAGEMENT</span>
                    <h2>Create New User</h2>

                    <p>
                        Register a new account and assign the appropriate
                        role within the UniServe application.
                    </p>
                </div>

            </section>

            <section class="add-user-panel">

                <div class="add-user-panel-heading">

                    <div class="add-user-heading-icon">＋</div>

                    <div>
                        <h3>User Information</h3>
                        <p>Enter the account details in the fields below.</p>
                    </div>

                </div>

                <div class="add-user-divider"></div>

                <?php if ($message !== ""): ?>
                    <div class="add-user-alert error" role="alert">
                        <span class="add-user-alert-icon">!</span>
                        <p><?= escape_html($message) ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="add_user.php">

                    <div class="add-user-form-grid">

                        <div class="add-user-form-group full-width">
                            <label for="full_name">Full Name <span>*</span></label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                maxlength="100"
                                value="<?= escape_html($full_name) ?>"
                                placeholder="Enter the user's full name"
                                autocomplete="name"
                                required
                            >
                        </div>

                        <div class="add-user-form-group full-width">
                            <label for="email">Email Address <span>*</span></label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="100"
                                value="<?= escape_html($email) ?>"
                                placeholder="Enter the user's email address"
                                autocomplete="email"
                                required
                            >
                        </div>

                        <div class="add-user-form-group">
                            <label for="password">Password <span>*</span></label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                minlength="8"
                                placeholder="Enter a password"
                                autocomplete="new-password"
                                required
                            >

                            <span class="add-user-helper">
                                Minimum of 8 characters.
                            </span>
                        </div>

                        <div class="add-user-form-group">
                            <label for="confirm_password">
                                Confirm Password <span>*</span>
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                minlength="8"
                                placeholder="Re-enter the password"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <div class="add-user-form-group full-width">
                            <label for="role">Account Role <span>*</span></label>

                            <select id="role" name="role" required>
                                <option
                                    value="customer"
                                    <?= $role === "customer" ? "selected" : "" ?>
                                >
                                    Customer
                                </option>

                                <option
                                    value="business_owner"
                                    <?= $role === "business_owner" ? "selected" : "" ?>
                                >
                                    Business Owner
                                </option>

                                <option
                                    value="admin"
                                    <?= $role === "admin" ? "selected" : "" ?>
                                >
                                    Admin
                                </option>
                            </select>

                            <span class="add-user-helper">
                                Choose the access level for this account.
                            </span>
                        </div>

                    </div>

                    <div class="add-user-form-actions">

                        <a href="manage_users.php" class="add-user-btn secondary">
                            Cancel
                        </a>

                        <button type="submit" class="add-user-btn primary">
                            <span>＋</span>
                            Add User
                        </button>

                    </div>

                </form>

            </section>

            <footer class="add-user-footer">
                <span>UniServe</span>
                <span class="add-user-footer-separator">•</span>
                &copy; <?= date("Y") ?> Unified Business and Service Management Application
            </footer>

        </main>

    </div>

</div>

</body>
</html>

<?php
$conn->close();
?>
