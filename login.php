<?php
session_start();
require_once __DIR__ . "/config.php";

/* Redirect user based on role */
function redirect_by_role($role)
{
    switch ($role) {
        case "admin":
            header("Location: admin_dashboard.php");
            exit();

        case "business_owner":
            header("Location: business_dashboard.php");
            exit();

        case "customer":
            header("Location: customer_dashboard.php");
            exit();

        default:
            session_unset();
            session_destroy();
            header("Location: login.php");
            exit();
    }
}

/* Redirect if already logged in */
if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    redirect_by_role($_SESSION["role"]);
}

$error = "";
$success = "";

/* Registration success message */
if (isset($_GET["registered"]) && $_GET["registered"] === "1") {
    $success = "Registration successful! You can now log in.";
}

/* Process login form */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if (!$stmt) {
            $error = "Unable to process your login. Please try again.";
        } else {
            $stmt->bind_param("s", $email);

            if (!$stmt->execute()) {
                $error = "Unable to process your login. Please try again.";
                $stmt->close();
            } else {
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                if (
                    $user &&
                    password_verify($password, $user["password"]) &&
                    in_array(
                        $user["role"],
                        ["admin", "business_owner", "customer"],
                        true
                    )
                ) {
                    session_regenerate_id(true);

                    $_SESSION["user_id"] = (int) $user["user_id"];
                    $_SESSION["full_name"] = $user["full_name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];

                    redirect_by_role($user["role"]);
                } else {
                    $error = "Invalid email address or password.";
                }
            }
        }
    }
}

/* Safely display text */
function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign In | UniServe</title>

   

    <!-- Login page-specific stylesheet -->
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body class="login-page">

<main class="login-layout">

    <!-- LEFT WELCOME PANEL -->
    <section class="login-welcome">

        <a href="index.php" class="login-brand" aria-label="UniServe home">
            <span class="login-brand-mark" aria-hidden="true">U</span>

            <span class="login-brand-text">
                <strong>UniServe</strong>
                <small>BUSINESS &amp; SERVICE MANAGEMENT</small>
            </span>
        </a>

        <div class="welcome-content">
            <span class="welcome-eyebrow">
                YOUR BUSINESS, ONE PLATFORM
            </span>

            <h1>
                Everything you need.
                <span>All in one place.</span>
            </h1>

            <p class="welcome-description">
                Welcome to UniServe, your unified business and service
                management application. Discover services, manage
                business activities, and keep your requests organized
                in one convenient platform.
            </p>

            <div class="welcome-features">
                <div class="welcome-feature">
                    <span class="feature-check" aria-hidden="true">✓</span>
                    <span>Manage business activities</span>
                </div>

                <div class="welcome-feature">
                    <span class="feature-check" aria-hidden="true">✓</span>
                    <span>Discover and request services</span>
                </div>

                <div class="welcome-feature">
                    <span class="feature-check" aria-hidden="true">✓</span>
                    <span>Keep everything organized</span>
                </div>
            </div>
        </div>

        <p class="welcome-footer">
            UNISERVE &copy; <?= date("Y") ?> · UNIFIED BUSINESS AND SERVICE MANAGEMENT
        </p>

    </section>

    <!-- RIGHT LOGIN PANEL -->
    <section class="login-main" aria-labelledby="login-title">

        <div class="login-card">

            <div class="login-card-heading">
                <span class="login-label">ACCOUNT ACCESS</span>

                <h2 id="login-title">Welcome back</h2>

                <p>
                    Sign in to your account to continue to UniServe.
                </p>
            </div>

            <!-- Error message -->
            <?php if ($error !== ""): ?>
                <div class="login-message login-error" role="alert">
                    <span class="message-symbol" aria-hidden="true">!</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Success message -->
            <?php if ($success !== ""): ?>
                <div class="login-message login-success" role="status">
                    <span class="message-symbol" aria-hidden="true">✓</span>
                    <span><?= e($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="login-form">

                <!-- Email -->
                <div class="login-field">
                    <label for="email">Email Address</label>

                    <div class="login-input-wrap">
                        <svg
                            class="login-input-icon"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                            <path d="m4 7 8 6 8-6"></path>
                        </svg>

                        <input
                            class="login-input"
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            maxlength="255"
                            autocomplete="username"
                            value="<?= e($_POST["email"] ?? "") ?>"
                            required
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="login-field">
                    <label for="password">Password</label>

                    <div class="login-input-wrap">
                        <svg
                            class="login-input-icon"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <rect x="4" y="10" width="16" height="11" rx="2"></rect>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                        </svg>

                        <input
                            class="login-input login-password-input"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            id="togglePassword"
                            aria-label="Show password"
                            aria-controls="password"
                            aria-pressed="false"
                        >
                            Show
                        </button>
                    </div>
                </div>

                <div class="login-options">
                    <a href="forgot_password.php" class="forgot-link">
                        Forgot Password?
                    </a>
                </div>

                <button type="submit" class="login-submit">
                    <span>Sign In</span>

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="M5 12h14"></path>
                        <path d="m13 6 6 6-6 6"></path>
                    </svg>
                </button>

            </form>

            <div class="login-divider">
                <span>NEW TO UNISERVE?</span>
            </div>

            <p class="register-prompt">
                Don't have an account?
                <a href="register.php">Create an account</a>
            </p>

            <a href="index.php" class="back-home">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    aria-hidden="true"
                >
                    <path d="m15 18-6-6 6-6"></path>
                </svg>

                Back to Home
            </a>

        </div>

        <p class="login-security-note">
            Secure access to your UniServe account
        </p>

    </section>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("password");

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener("click", function () {
            const isHidden = passwordInput.type === "password";

            passwordInput.type = isHidden ? "text" : "password";
            togglePassword.textContent = isHidden ? "Hide" : "Show";

            togglePassword.setAttribute(
                "aria-label",
                isHidden ? "Hide password" : "Show password"
            );

            togglePassword.setAttribute(
                "aria-pressed",
                isHidden ? "true" : "false"
            );
        });
    }
});
</script>

</body>
</html>
