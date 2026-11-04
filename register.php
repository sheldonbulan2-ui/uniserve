<?php
session_start();
require_once __DIR__ . "/config.php";

if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    header("Location: index.php");
    exit();
}

$full_name = "";
$email = "";
$role = "customer";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = trim($_POST["role"] ?? "customer");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (!in_array($role, ["customer", "business_owner"], true)) {
        $role = "customer";
    }

    if (
        $full_name === "" ||
        $email === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $error = "Please complete all required fields.";
    } elseif (strlen($full_name) > 150) {
        $error = "Full name must not exceed 150 characters.";
    } elseif (strlen($email) > 255) {
        $error = "Email address must not exceed 255 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (strlen($password) > 72) {
        $error = "Password must not exceed 72 bytes.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ? LIMIT 1"
        );

        if (!$check) {
            $error = "Unable to process registration. Please try again.";
        } else {
            $check->bind_param("s", $email);

            if (!$check->execute()) {
                $error = "Unable to process registration. Please try again.";
                $check->close();
            } else {
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = "This email address is already registered.";
                    $check->close();
                } else {
                    $check->close();

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare(
                        "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)"
                    );

                    if (!$stmt) {
                        $error = "Registration failed. Please try again.";
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
                            header("Location: login.php?registered=1");
                            exit();
                        } else {
                            if ($stmt->errno === 1062 || strpos($stmt->error, "unique") !== false) {
                                $error = "This email address is already registered.";
                            } else {
                                $error = "Registration failed. Please try again.";
                            }
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an Account — UniServe</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        .login-page select.login-input {
            cursor: pointer;
            background: #081e38;
            color: #f3f7ff;
            padding: 0 12px;
        }
        .login-page select.login-input option {
            background: #0d2849;
            color: #f3f7ff;
            padding: 8px;
        }
        .login-page .login-card {
            max-width: 440px;
        }
    </style>
</head>

<body class="login-page">
    <div class="login-layout">

        <!-- Left Column: Branding & Value Props -->
        <aside class="login-aside">
            <div class="login-brand">
                <div class="login-brand-mark" aria-hidden="true">U</div>
                <div class="login-brand-text">
                    <strong>UniServe</strong>
                    <small>BUSINESS & SERVICE MANAGEMENT</small>
                </div>
            </div>

            <div class="welcome-content">
                <div class="welcome-eyebrow">
                    <span class="eyebrow-line" aria-hidden="true"></span>
                    <span>JOIN THE PLATFORM</span>
                </div>

                <h1>
                    Start connecting.<br>
                    Grow your business.
                </h1>

                <p class="welcome-lead">
                    Create an account to browse local services, book requests, or manage your own storefront with powerful tools.
                </p>

                <ul class="feature-list">
                    <li class="feature-item">
                        <span class="feature-check" aria-hidden="true">✓</span>
                        <span>Access trusted business services</span>
                    </li>
                    <li class="feature-item">
                        <span class="feature-check" aria-hidden="true">✓</span>
                        <span>Real-time request tracking & updates</span>
                    </li>
                    <li class="feature-item">
                        <span class="feature-check" aria-hidden="true">✓</span>
                        <span>Easy storefront setup for merchants</span>
                    </li>
                </ul>
            </div>

            <p class="welcome-footer">
                &copy; <?= date("Y") ?> UniServe Platform. All rights reserved.
            </p>
        </aside>

        <!-- Right Column: Registration Card -->
        <main class="login-main" aria-labelledby="register-title">
            <div class="login-card">

                <div class="login-card-heading">
                    <span class="login-label">ACCOUNT CREATION</span>
                    <h2 id="register-title">Get started</h2>
                    <p>Fill in your details to create your UniServe account.</p>
                </div>

                <!-- Error message -->
                <?php if ($error !== ""): ?>
                    <div class="login-message login-error" role="alert">
                        <span class="message-symbol" aria-hidden="true">!</span>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php" class="login-form">

                    <!-- Full Name -->
                    <div class="login-field">
                        <label for="full_name">Full Name</label>
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
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <input
                                class="login-input"
                                type="text"
                                id="full_name"
                                name="full_name"
                                placeholder="Enter your full name"
                                maxlength="150"
                                autocomplete="name"
                                value="<?= e($full_name) ?>"
                                required
                            >
                        </div>
                    </div>

                    <!-- Email Address -->
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
                                autocomplete="email"
                                value="<?= e($email) ?>"
                                required
                            >
                        </div>
                    </div>

                    <!-- Role / Account Type -->
                    <div class="login-field">
                        <label for="role">Account Type</label>
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
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                            <select
                                class="login-input"
                                id="role"
                                name="role"
                                required
                            >
                                <option value="customer" <?= $role === "customer" ? "selected" : "" ?>>Customer (Browse & Order Services)</option>
                                <option value="business_owner" <?= $role === "business_owner" ? "selected" : "" ?>>Business Owner (List & Sell Services)</option>
                            </select>
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
                                placeholder="At least 8 characters"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                required
                            >
                            <button
                                class="password-toggle"
                                type="button"
                                aria-label="Toggle password visibility"
                                onclick="toggleVisibility('password', this)"
                            >
                                Show
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="login-field">
                        <label for="confirm_password">Confirm Password</label>
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
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Re-enter password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                required
                            >
                            <button
                                class="password-toggle"
                                type="button"
                                aria-label="Toggle confirm password visibility"
                                onclick="toggleVisibility('confirm_password', this)"
                            >
                                Show
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button class="login-submit" type="submit">
                        <span>Create Account</span>
                        <svg
                            class="submit-arrow"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="M5 12h14"></path>
                            <path d="m12 5 7 7-7 7"></path>
                        </svg>
                    </button>
                </form>

                <!-- Navigation Links -->
                <div class="login-footer">
                    <p class="login-register-prompt">
                        Already have an account?
                        <a href="login.php">Sign in here</a>
                    </p>
                    <a class="login-home-link" href="index.php">
                        &larr; Back to Home
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = 'Hide';
            } else {
                input.type = 'password';
                btn.textContent = 'Show';
            }
        }
    </script>
</body>
</html>
