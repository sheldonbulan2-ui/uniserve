<?php
session_start();
require_once "config.php";

function escape_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["admin", "business_owner", "customer"], true)
) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"];

$dashboard_link = "customer_dashboard.php";
$dashboard_label = "Customer Dashboard";
$role_label = "Customer";

if ($role === "admin") {
    $dashboard_link = "admin_dashboard.php";
    $dashboard_label = "Admin Dashboard";
    $role_label = "Administrator";
} elseif ($role === "business_owner") {
    $dashboard_link = "business_dashboard.php";
    $dashboard_label = "Business Dashboard";
    $role_label = "Business Owner";
}

$stmt = $conn->prepare(
    "SELECT full_name, email FROM users WHERE user_id = ?"
);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to load your profile. Please try again later.");
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit("Unable to load your profile. Please try again later.");
}

$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$full_name = $user["full_name"];
$email = $user["email"];

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($full_name === "" || $email === "") {
        $error = "Please complete all fields.";
    } elseif (strlen($full_name) > 150) {
        $error = "Full name must not exceed 150 characters.";
    } elseif (strlen($email) > 255) {
        $error = "Email address must not exceed 255 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $check = $conn->prepare(
            "SELECT user_id
             FROM users
             WHERE email = ? AND user_id <> ?"
        );

        if (!$check) {
            $error = "Unable to validate your email. Please try again.";
        } else {
            $check->bind_param("si", $email, $user_id);

            if (!$check->execute()) {
                $error = "Unable to validate your email. Please try again.";
                $check->close();
            } else {
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = "That email address is already used by another account.";
                    $check->close();
                } else {
                    $check->close();

                    $update = $conn->prepare(
                        "UPDATE users
                         SET full_name = ?, email = ?
                         WHERE user_id = ?"
                    );

                    if (!$update) {
                        $error = "Unable to update your profile. Please try again.";
                    } else {
                        $update->bind_param(
                            "ssi",
                            $full_name,
                            $email,
                            $user_id
                        );

                        if ($update->execute()) {
                            $_SESSION["full_name"] = $full_name;
                            $success = "Your profile has been updated.";
                        } elseif ($update->errno === 1062) {
                            $error = "That email address is already used by another account.";
                        } else {
                            $error = "Unable to update your profile. Please try again.";
                        }

                        $update->close();
                    }
                }
            }
        }
    }
}

$display_name = $_SESSION["full_name"] ?? $full_name;
$initial = strtoupper(substr($display_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Profile | UniServe</title>

    <link rel="stylesheet" href="assets/css/edit_profile.css">
</head>

<body class="edit-profile-page">

<div class="layout">

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Edit Profile</h1>
                <p>Manage your personal information</p>
            </div>

            <div class="topbar-user">
                <div class="user-avatar">
                    <?= escape_html($initial) ?>
                </div>
                <span><?= escape_html($display_name) ?></span>
            </div>
        </header>

        <main class="edit-profile-content">

            <section class="page-heading">
                <p class="eyebrow">ACCOUNT SETTINGS</p>

                <p>
                    Keep your UniServe account information accurate and up to date.
                    You can update your name and email address below.
                </p>
            </section>

            <section class="profile-card">

                <div class="profile-card-heading">
                    <div class="profile-icon" aria-hidden="true">♙</div>

                    <div>
                        <h3>Personal Information</h3>
                        <p>Update the information associated with your account.</p>
                    </div>
                </div>

                <?php if ($error !== ""): ?>
                    <div class="profile-message error" role="alert">
                        <span class="message-indicator" aria-hidden="true">!</span>
                        <div><?= escape_html($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success !== ""): ?>
                    <div class="profile-message success" role="status">
                        <span class="message-indicator" aria-hidden="true">✓</span>
                        <div><?= escape_html($success) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="edit_profile.php">

                    <div class="profile-field">
                        <label for="full_name">Full Name</label>

                        <div class="profile-input-wrap">
                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                maxlength="150"
                                value="<?= escape_html($full_name) ?>"
                                placeholder="Enter your full name"
                                autocomplete="name"
                                required
                            >
                        </div>

                        <p class="profile-hint">
                            Enter the full name you want displayed on your account.
                        </p>
                    </div>

                    <div class="profile-field">
                        <label for="email">Email Address</label>

                        <div class="profile-input-wrap">
                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="255"
                                value="<?= escape_html($email) ?>"
                                placeholder="Enter your email address"
                                autocomplete="email"
                                required
                            >
                        </div>

                        <p class="profile-hint">
                            Your email address must be valid and not registered
                            to another account.
                        </p>
                    </div>

                    <div class="profile-note">
                        <strong>Profile Information</strong>
                        <p>
                            Your updated name will also be reflected in your
                            current session after your changes are saved.
                        </p>
                    </div>

                    <div class="profile-actions">
                        <button class="profile-submit" type="submit">
                            Save Changes
                            <span aria-hidden="true">→</span>
                        </button>

                        <a class="profile-cancel" href="profile.php">
                            Cancel
                        </a>
                    </div>

                </form>
            </section>

            <a class="profile-back-link" href="<?= escape_html($dashboard_link) ?>">
                <span aria-hidden="true">←</span>
                Back to <?= escape_html($dashboard_label) ?>
            </a>

        </main>

    </main>
</div>

</body>
</html>

<?php
$conn->close();
?>
