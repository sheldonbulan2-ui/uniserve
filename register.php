<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/Csrf.php';
require_once __DIR__ . '/config/RateLimitMiddleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RateLimitMiddleware::checkIpRateLimit(15, 60);

    if (!Csrf::validate()) {
        $error = 'Security session validation failed. Please refresh and try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $role = trim($_POST['role'] ?? 'customer');
        $businessName = trim($_POST['business_name'] ?? '');

        if (!in_array($role, ['merchant', 'customer'], true)) {
            $role = 'customer';
        }

        if ($fullName === '' || $email === '' || $password === '') {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } else {
            // Check uniqueness
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $error = 'This email address is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );

                $pdo->beginTransaction();
                try {
                    $ins = $pdo->prepare('INSERT INTO users (uuid, role, full_name, email, phone, password_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $ins->execute([$uuid, $role, $fullName, $email, $phone, $hash, 'active']);
                    $userId = (int)$pdo->lastInsertId();

                    if ($role === 'merchant') {
                        $bName = $businessName !== '' ? $businessName : "{$fullName}'s Services";
                        $mIns = $pdo->prepare('INSERT INTO merchant_profiles (user_id, business_name, contact_phone, is_verified) VALUES (?, ?, ?, 1)');
                        $mIns->execute([$userId, $bName, $phone]);
                    }

                    $pdo->commit();
                    $success = 'Account created successfully! You can now sign in.';

                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $error = 'Registration failed: ' . $e->getMessage();
                }
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
    <title>Create Account — UniServe Platform</title>
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
            max-width: 500px;
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
                <h1 style="font-size: 1.5rem; font-weight: 800;">Create Account</h1>
                <p style="color: var(--color-text-muted); font-size: 0.875rem;">Join the UniServe unified ecosystem</p>
            </div>

            <?php if ($error): ?>
                <div style="background: var(--color-danger-bg); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--color-danger); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); font-size: 0.8125rem; margin-bottom: var(--space-4);">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background: var(--color-success-bg); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--color-success); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); font-size: 0.8125rem; margin-bottom: var(--space-4);">
                    <?= htmlspecialchars($success) ?> <a href="login.php" style="font-weight: 700; color: #FFF; text-decoration: underline;">Proceed to Sign In &rarr;</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <?= Csrf::renderFormField() ?>

                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name</label>
                    <input class="form-control" type="text" id="full_name" name="full_name" required placeholder="John Doe" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-control" type="email" id="email" name="email" required placeholder="john@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Contact Phone Number</label>
                    <input class="form-control" type="text" id="phone" name="phone" placeholder="09171234567" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Select Account Role</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
                        <label style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3); cursor: pointer; text-align: center; background: var(--color-bg-surface-elevated);">
                            <input type="radio" name="role" value="customer" checked onchange="toggleStoreField(false)">
                            <strong style="display: block; margin-top: 4px;">👤 Customer</strong>
                        </label>
                        <label style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3); cursor: pointer; text-align: center; background: var(--color-bg-surface-elevated);">
                            <input type="radio" name="role" value="merchant" onchange="toggleStoreField(true)">
                            <strong style="display: block; margin-top: 4px;">🏪 Merchant</strong>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="storeFieldWrap" style="display: none;">
                    <label class="form-label" for="business_name">Storefront / Business Name</label>
                    <input class="form-control" type="text" id="business_name" name="business_name" placeholder="e.g. Bean Yard Cafe">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password (min 8 characters)</label>
                    <input class="form-control" type="password" id="password" name="password" required placeholder="••••••••">
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--space-4); padding: 0.75rem;">
                    Create Account
                </button>
            </form>

            <div style="margin-top: var(--space-6); text-align: center; font-size: 0.8125rem; color: var(--color-text-muted);">
                Already registered? <a href="login.php">Sign In</a> &bull; <a href="index.php">Back to Home</a>
            </div>
        </div>
    </div>

    <script>
        function toggleStoreField(isMerchant) {
            document.getElementById('storeFieldWrap').style.display = isMerchant ? 'block' : 'none';
        }
    </script>
</body>
</html>
