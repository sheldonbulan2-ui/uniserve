<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["admin", "business_owner", "customer"], true)
) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"];

$stmt = $conn->prepare(
    "SELECT full_name, email, role, created_at
     FROM users
     WHERE user_id = ?"
);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to load your profile. Please try again later.");
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    http_response_code(500);
    exit("Unable to load your profile. Please try again later.");
}

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    $conn->close();
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

function escape_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function format_date($date) {
    if (empty($date)) {
        return "Not available";
    }

    $timestamp = strtotime($date);

    return $timestamp
        ? date("F d, Y", $timestamp)
        : "Not available";
}

$full_name = $user["full_name"] ?: "User";
$initial = strtoupper(substr(trim($full_name), 0, 1));

$dashboard = match ($role) {
    "admin" => "admin_dashboard.php",
    "business_owner" => "business_owner_dashboard.php",
    default => "customer_dashboard.php"
};

$role_label = ucwords(str_replace("_", " ", $user["role"]));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile - UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/profile.css">
</head>

<body class="profile-page">

<div class="profile-layout">

    
<aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">U</div>
            <div>
                <h1>UniServe</h1>
                <p>Business &amp; Service System</p>
            </div>
        </div>

        <div class="nav-heading">Customer Menu</div>

        <nav>
            <a class="nav-link" href="customer_dashboard.php">
                <span class="nav-icon">▦</span>
                Dashboard
            </a>

            <a class="nav-link" href="browse_services.php">
                <span class="nav-icon">⌕</span>
                Browse Services
            </a>

            <a class="nav-link" href="my_requests.php">
                <span class="nav-icon">▤</span>
                My Requests
            </a>

            <a class="nav-link active" href="profile.php">
                <span class="nav-icon">♙</span>
                My Profile
            </a>
        </nav>

        <div class="sidebar-bottom">
            <a class="nav-link" href="logout.php">
                <span class="nav-icon">↪</span>
                Logout
            </a>
        </div>
    </aside>
    <main class="profile-main">

        <header class="profile-topbar">
            <div>
                <h1>My Profile</h1>
                <p>Manage and view your account information.</p>
            </div>

            <div class="topbar-account">
                <div class="topbar-avatar">
                    <?= escape_html($initial) ?>
                </div>

                <div>
                    <strong><?= escape_html($full_name) ?></strong>
                    <span><?= escape_html($role_label) ?></span>
                </div>
            </div>
        </header>

        <section class="profile-content">

            <div class="profile-heading">
                <div>
                    <span class="eyebrow">ACCOUNT SETTINGS</span>
                    <h2>Profile Information</h2>
                    <p>Review your personal details and account information.</p>
                </div>
            </div>

            <section class="profile-overview">
                <div class="overview-cover"></div>

                <div class="overview-body">
                    <div class="overview-avatar">
                        <?= escape_html($initial) ?>
                    </div>

                    <div class="overview-info">
                        <h2><?= escape_html($full_name) ?></h2>
                        <p><?= escape_html($user["email"]) ?></p>
                        <span class="role-badge">
                            <?= escape_html($role_label) ?>
                        </span>
                    </div>

                    <a href="edit_profile.php" class="profile-edit-button">
                        Edit Profile
                    </a>
                </div>
            </section>

            <section class="profile-details-card">
                <div class="section-heading">
                    <div>
                        <h3>Personal Information</h3>
                        <p>Your registered UniServe account details.</p>
                    </div>
                </div>

                <div class="profile-information-grid">

                    <div class="information-item">
                        <span class="information-icon">♙</span>
                        <div>
                            <span class="information-label">Full Name</span>
                            <strong><?= escape_html($user["full_name"]) ?></strong>
                        </div>
                    </div>

                    <div class="information-item">
                        <span class="information-icon">@</span>
                        <div>
                            <span class="information-label">Email Address</span>
                            <strong><?= escape_html($user["email"]) ?></strong>
                        </div>
                    </div>

                    <div class="information-item">
                        <span class="information-icon">▤</span>
                        <div>
                            <span class="information-label">Account Role</span>
                            <strong><?= escape_html($role_label) ?></strong>
                        </div>
                    </div>

                    <div class="information-item">
                        <span class="information-icon">◷</span>
                        <div>
                            <span class="information-label">Date Registered</span>
                            <strong><?= escape_html(format_date($user["created_at"])) ?></strong>
                        </div>
                    </div>

                </div>
            </section>

            <section class="profile-details-card security-card">
                <div class="section-heading">
                    <div>
                        <h3>Account Security</h3>
                        <p>Keep your account information secure.</p>
                    </div>
                </div>

                <div class="security-row">
                    <div class="security-description">
                        <span class="security-icon">⌑</span>
                        <div>
                            <strong>Password</strong>
                            <p>Update your password to help protect your account.</p>
                        </div>
                    </div>

                    <a href="change_password.php" class="security-button">
                        Change Password
                    </a>
                </div>
            </section>

            <footer class="profile-footer">
                <span>UniServe</span>
                <span>Unified Business and Service Management Application</span>
            </footer>

        </section>
    </main>

</div>

</body>
</html>

<?php
$conn->close();
?>