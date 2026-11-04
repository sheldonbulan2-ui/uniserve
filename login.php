<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/Csrf.php';
require_once __DIR__ . '/config/RateLimitMiddleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } elseif ($_SESSION['role'] === 'merchant') {
        header('Location: merchant_dashboard.php');
    } else {
        header('Location: customer_dashboard.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RateLimitMiddleware::checkIpRateLimit(20, 60);

    if (!Csrf::validate()) {
        $error = 'Security session validation failed. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Please enter both your email address and password.';
        } else {
            $stmt = $pdo->prepare('SELECT id, uuid, role, full_name, email, password_hash, status, failed_login_attempts, locked_until FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Invalid email address or password.';
            } elseif (RateLimitMiddleware::isAccountLocked($user)) {
                $error = 'Account is temporarily locked due to multiple failed attempts. Please wait 15 minutes.';
            } elseif ($user['status'] === 'suspended') {
                $error = 'This account has been suspended. Please contact platform support.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                RateLimitMiddleware::recordFailedLogin($pdo, (int)$user['id'], (int)$user['failed_login_attempts']);
                $error = 'Invalid email address or password.';
            } else {
                RateLimitMiddleware::resetFailedAttempts($pdo, (int)$user['id']);
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['uuid'] = $user['uuid'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];

                if ($user['role'] === 'merchant') {
                    $mStmt = $pdo->prepare('SELECT id, business_name FROM merchant_profiles WHERE user_id = ? LIMIT 1');
                    $mStmt->execute([$user['id']]);
                    $mProfile = $mStmt->fetch();
                    if ($mProfile) {
                        $_SESSION['merchant_id'] = (int)$mProfile['id'];
                        $_SESSION['business_name'] = $mProfile['business_name'];
                    }
                    header('Location: merchant_dashboard.php');
                } elseif ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: customer_dashboard.php');
                }
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — UniServe Platform</title>
    <link rel="stylesheet" href="assets/css/base.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4);
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            background: var(--color-bg-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            box-shadow: var(--shadow-lg);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div style="text-align: center; margin-bottom: var(--space-6);">
                <div style="width: 44px; height: 44px; background: linear-gradient(135deg, var(--color-primary), var(--color-accent)); border-radius: var(--radius-md); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.5rem; color: #FFF; margin-bottom: 12px;">U</div>
                <h1 style="font-size: 1.5rem; font-weight: 800;">Welcome to UniServe</h1>
                <p style="color: var(--color-text-muted); font-size: 0.875rem;">Sign in to your authorized account</p>
            </div>

            <?php if ($error): ?>
                <div style="background: var(--color-danger-bg); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--color-danger); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); font-size: 0.8125rem; margin-bottom: var(--space-4);">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <?= Csrf::renderFormField() ?>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-control" type="email" id="email" name="email" required placeholder="admin@uniserve.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required placeholder="Enter your password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--space-4); padding: 0.75rem;">
                    Sign In to Portal
                </button>
            </form>

            <div style="margin-top: var(--space-6); text-align: center; font-size: 0.8125rem; color: var(--color-text-muted);">
                Don't have an account? <a href="register.php">Create Account</a> &bull; <a href="index.php">Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
