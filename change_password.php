
<?php
session_start();
require_once "config.php";

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["customer", "admin"], true)
) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"];
$full_name = $_SESSION["full_name"] ?? "User";
$initial = strtoupper(substr($full_name, 0, 1));

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        $current_password === "" ||
        $new_password === "" ||
        $confirm_password === ""
    ) {
        $message = "Please fill in all fields.";
        $message_type = "error";
    } elseif (strlen($new_password) < 8) {
        $message = "New password must be at least 8 characters.";
        $message_type = "error";
    } elseif (strlen($new_password) > 72) {
        $message = "New password must not exceed 72 bytes.";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare(
            "SELECT password FROM users WHERE user_id = ?"
        );

        if (!$stmt) {
            $message = "Unable to verify your account. Please try again.";
            $message_type = "error";
        } else {
            $stmt->bind_param("i", $user_id);

            if (!$stmt->execute()) {
                $message = "Unable to verify your account. Please try again.";
                $message_type = "error";
                $stmt->close();
            } else {
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                if (
                    !$user ||
                    !password_verify($current_password, $user["password"])
                ) {
                    $message = "Current password is incorrect.";
                    $message_type = "error";
                } else {
                    $hashed_password = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                    if ($hashed_password === false) {
                        $message = "Unable to change password. Please try again.";
                        $message_type = "error";
                    } else {
                        $update = $conn->prepare(
                            "UPDATE users SET password = ? WHERE user_id = ?"
                        );

                        if (!$update) {
                            $message = "Unable to change password. Please try again.";
                            $message_type = "error";
                        } else {
                            $update->bind_param(
                                "si",
                                $hashed_password,
                                $user_id
                            );

                            if ($update->execute()) {
                                $message = "Password changed successfully.";
                                $message_type = "success";
                            } else {
                                $message = "Unable to change password. Please try again.";
                                $message_type = "error";
                            }

                            $update->close();
                        }
                    }
                }
            }
        }
    }
}

$dashboard = $role === "admin"
    ? "admin_dashboard.php"
    : "customer_dashboard.php";

$menu_label = $role === "admin" ? "Admin Menu" : "Customer Menu";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | UniServe</title>
    
    <link rel="stylesheet" href="assets/css/change_password.css">
</head>

<body class="change-password-page">

<div class="layout">

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Change Password</h1>
                <p>Manage your account security</p>
            </div>

            <div class="topbar-user">
                <div class="user-avatar"><?= escape($initial) ?></div>
                <span><?= escape($full_name) ?></span>
            </div>
        </header>

        <div class="change-password-content">

            <div class="page-heading">
                <p class="eyebrow">ACCOUNT SECURITY</p>
                <br>
                <p>
                    Keep your UniServe account secure by choosing a strong,
                    private password.
                </p>
            </div>

            <section class="password-card">

                <div class="password-card-heading">
                    <div class="password-icon">▣</div>
                    <div>
                        <h3>Change Password</h3>
                        <p>Complete all three fields below.</p>
                    </div>
                </div>

                <?php if ($message !== ""): ?>
                    <div
                        class="password-message <?= escape($message_type) ?>"
                        role="status"
                    >
                        <?= escape($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="change_password.php">

                    <div class="password-field">
                        <label for="current_password">Current Password</label>

                        <div class="password-input-wrap">
                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                            >
                            <button
                                class="toggle-password"
                                type="button"
                                data-target="current_password"
                            >Show</button>
                        </div>
                    </div>

                    <div class="password-field">
                        <label for="new_password">New Password</label>

                        <div class="password-input-wrap">
                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                minlength="8"
                                required
                                autocomplete="new-password"
                            >
                            <button
                                class="toggle-password"
                                type="button"
                                data-target="new_password"
                            >Show</button>
                        </div>

                        <p class="password-hint">
                            Use at least 8 characters. Maximum length: 72 bytes.
                        </p>
                    </div>

                    <div class="password-field">
                        <label for="confirm_password">Confirm New Password</label>

                        <div class="password-input-wrap">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                minlength="8"
                                required
                                autocomplete="new-password"
                            >
                            <button
                                class="toggle-password"
                                type="button"
                                data-target="confirm_password"
                            >Show</button>
                        </div>
                    </div>

                    <div class="security-note">
                        <strong>Security Reminder</strong>
                        <p>
                            Your current password must be verified before
                            the new password can be saved.
                        </p>
                    </div>

                    <button class="password-submit" type="submit">
                        Update Password
                    </button>

                </form>
            </section>

            <a class="password-back-link" href="profile.php">
                ← Back to My Profile
            </a>

        </div>

    </main>
</div>

<script>
document.querySelectorAll(".toggle-password").forEach(function(button) {
    button.addEventListener("click", function() {
        const input = document.getElementById(
            this.getAttribute("data-target")
        );

        if (!input) {
            return;
        }

        const showing = input.type === "password";
        input.type = showing ? "text" : "password";
        this.textContent = showing ? "Hide" : "Show";
        this.setAttribute(
            "aria-label",
            (showing ? "Hide " : "Show ") + input.name.replaceAll("_", " ")
        );
    });
});
</script>

</body>
</html>
