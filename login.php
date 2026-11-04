<?php
session_start();
require_once __DIR__ . "/config.php";

// If already authenticated as admin, redirect to admin dashboard
if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    if ($_SESSION["role"] === "admin") {
        header("Location: admin_dashboard.php");
        exit();
    } else {
        session_unset();
        session_destroy();
    }
}

$error = "";
$info = "";

if (isset($_GET["msg"]) && $_GET["msg"] === "mobile_only") {
    $info = "Customer and Merchant portals are exclusively accessible via the UniServe Mobile App. This web portal is strictly for Platform Administration.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your administrator email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid administrator email address.";
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email, password, role FROM users WHERE email = ? LIMIT 1"
        );

        if (!$stmt) {
            $error = "Unable to process authentication. Please try again.";
        } else {
            $stmt->bind_param("s", $email);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                if (!$user || !password_verify($password, $user["password"])) {
                    $error = "Invalid administrator credentials.";
                } elseif ($user["role"] !== "admin") {
                    $error = "Access denied. Only Administrators can access this web portal. Please use the UniServe Mobile App for Customer or Merchant accounts.";
                } else {
                    session_regenerate_id(true);
                    $_SESSION["user_id"] = (int) $user["user_id"];
                    $_SESSION["full_name"] = $user["full_name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];

                    header("Location: admin_dashboard.php");
                    exit();
                }
            } else {
                $error = "Authentication failed. Please try again.";
            }
            $stmt->close();
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
    <title>Admin Portal — UniServe</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        .admin-badge-tag {
            display: inline-block;
            background: rgba(57, 132, 246, 0.15);
            border: 1px solid rgba(57, 132, 246, 0.35);
            color: #7fb1ff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 14px;
        }
        .login-info-box {
            background: rgba(56, 189, 248, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: #bae6fd;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .mobile-notice {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid rgba(41, 73, 105, 0.45);
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
        }
        .mobile-notice strong {
            color: #f1f5ff;
        }
    </style>
</head>

<body class="login-page">
    <div class="login-layout">

        <!-- Left Column: Branding & System Overview -->
        <aside class="login-aside">
            <div class="login-brand">
                <div class="login-brand-mark" aria-hidden="true">U</div>
                <div class="login-brand-text">
                    <strong>UniServe</strong>
                    <small>ADMINISTRATION & GOVERNANCE</small>
                </div>
            </div>

            <div class="welcome-content">
                <div class="welcome-eyebrow">
                    <span class="eyebrow-line" aria-hidden="true"></span>
                    <span>PLATFORM CONTROL CENTER</span>
                </div>

                <h1>
                    Centralized control.<br>
                    Verified operations.
                </h1>

                <p class="welcome-lead">
                    Manage merchants, audit service requests, configure marketplace categories, and oversee system transactions from a secure executive dashboard.
                </p>

                <ul class="feature-list">
                    <li class="feature-item">
                        <span class="feature-check" aria-hidden="true">✓</span>
                        <span>Merchant verification & storefront approvals</span>
                    </li>
                    <li class="feature-item">
                        <span class="feature-check" aria-hidden="true">✓</span>
                        <span>Full transactional audit & payment reconciliation</span>
                    </li>
                    <li class="feature-item">
                        <span class="feature-check" aria-hidden="true">✓</span>
                        <span>Service taxonomies & category governance</span>
                    </li>
                </ul>
            </div>

            <p class="welcome-footer">
                &copy; <?= date("Y") ?> UniServe Platform. Administrator Access Only.
            </p>
        </aside>

        <!-- Right Column: Login Card -->
        <main class="login-main" aria-labelledby="login-title">
            <div class="login-card">

                <div class="login-card-heading">
                    <span class="admin-badge-tag">ADMINISTRATOR ACCESS</span>
                    <h2 id="login-title">Sign in</h2>
                    <p>Enter your administrator credentials to access the control panel.</p>
                </div>

                <!-- Info message -->
                <?php if ($info !== ""): ?>
                    <div class="login-info-box" role="status">
                        <?= e($info) ?>
                    </div>
                <?php endif; ?>

                <!-- Error message -->
                <?php if ($error !== ""): ?>
                    <div class="login-message login-error" role="alert">
                        <span class="message-symbol" aria-hidden="true">!</span>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="login-form">

                    <!-- Email Address -->
                    <div class="login-field">
                        <label for="email">Admin Email Address</label>
                        <div class="login-input-wrap">
                            <svg class="login-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                <path d="m4 7 8 6 8-6"></path>
                            </svg>
                            <input class="login-input" type="email" id="email" name="email" placeholder="admin@uniserve.com" maxlength="255" autocomplete="username" value="<?= e($_POST["email"] ?? "") ?>" required>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="login-field">
                        <label for="password">Password</label>
                        <div class="login-input-wrap">
                            <svg class="login-input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <rect x="4" y="10" width="16" height="11" rx="2"></rect>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                            </svg>
                            <input class="login-input login-password-input" type="password" id="password" name="password" placeholder="Enter administrator password" maxlength="72" autocomplete="current-password" required>
                            <button class="password-toggle" type="button" aria-label="Toggle password visibility" onclick="toggleVisibility('password', this)">Show</button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button class="login-submit" type="submit">
                        <span>Access Dashboard</span>
                        <svg class="submit-arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M5 12h14"></path>
                            <path d="m12 5 7 7-7 7"></path>
                        </svg>
                    </button>
                </form>

                <div class="mobile-notice">
                    Are you a Customer or Merchant?<br>
                    <strong>Please use the UniServe Mobile App</strong> to browse services or manage your business storefront.
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
