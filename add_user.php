<?php
/**
 * UniServe Enterprise Admin — Provision New User Account
 */
$pageTitle = "Provision User Account";
$pageSubtitle = "Create a new verified administrative, merchant, or customer principal";
$activeNav = "users";

require_once __DIR__ . '/includes/admin_header.php';

$message = "";
$messageType = "error";
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
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (!in_array($role, $allowed_roles, true)) {
        $message = "Please select a valid role.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        if ($check) {
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "An account with this email address already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssss", $full_name, $email, $hash, $role);
                    if ($stmt->execute()) {
                        $newId = (int)($stmt->insert_id ?: $conn->insert_id);
                        $message = "Account successfully provisioned (#UID-{$newId}).";
                        $messageType = "success";
                        $full_name = "";
                        $email = "";
                        $role = "customer";
                    } else {
                        $message = "Failed to create account: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
            $check->close();
        }
    }
}
?>

<div style="max-width: 680px; margin: 0 auto; width: 100%;">
    <?php if ($message): ?>
        <div class="admin-alert alert-<?= $messageType ?>">
            <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($messageType === 'success'): ?>
                <a href="manage_users.php" class="btn-primary" style="padding: 4px 12px; font-size: 11px;">View Directory &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="admin-panel">
        <div class="admin-panel-header">
            <div>
                <h3>Account Provisioning Form</h3>
                <p>Fill in the required profile credentials below to establish a new platform principal.</p>
            </div>
            <a href="manage_users.php" class="btn-secondary" style="font-size: 12px;">
                &larr; Back to Directory
            </a>
        </div>

        <form method="POST" action="add_user.php" style="display: flex; flex-direction: column; gap: 20px;">
            <div class="admin-form-group">
                <label for="full_name">Full Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="full_name" name="full_name" class="admin-input" 
                       placeholder="e.g. John Doe" 
                       value="<?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="admin-form-group">
                <label for="email">Email Address <span style="color: #ef4444;">*</span></label>
                <input type="email" id="email" name="email" class="admin-input" 
                       placeholder="name@example.com" 
                       value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
                <small>This email will be used for authentication on mobile or web.</small>
            </div>

            <div class="admin-form-group">
                <label for="role">Assigned Platform Role <span style="color: #ef4444;">*</span></label>
                <select id="role" name="role" class="admin-select" required>
                    <option value="customer" <?= $role === "customer" ? "selected" : "" ?>>Customer (Mobile APK Exclusive)</option>
                    <option value="business_owner" <?= $role === "business_owner" ? "selected" : "" ?>>Merchant / Business Owner (Mobile APK Exclusive)</option>
                    <option value="admin" <?= $role === "admin" ? "selected" : "" ?>>Administrator (Web Console Governance)</option>
                </select>
            </div>

            <div class="admin-form-grid">
                <div class="admin-form-group">
                    <label for="password">Password <span style="color: #ef4444;">*</span></label>
                    <input type="password" id="password" name="password" class="admin-input" 
                           placeholder="At least 8 characters" minlength="8" required>
                </div>

                <div class="admin-form-group">
                    <label for="confirm_password">Confirm Password <span style="color: #ef4444;">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="admin-input" 
                           placeholder="Repeat password" minlength="8" required>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px; border-top: 1px solid var(--border-subtle); padding-top: 20px;">
                <a href="manage_users.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" style="padding: 10px 24px;">Create Account</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
