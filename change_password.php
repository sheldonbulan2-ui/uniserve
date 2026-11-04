<?php
/**
 * UniServe Enterprise Admin — Update Account Password
 */
$pageTitle = "Change Password";
$pageSubtitle = "Update administrative credentials with bcrypt cryptographic hashing";
$activeNav = "profile";

require_once __DIR__ . '/includes/admin_header.php';

$userId = (int)$_SESSION["user_id"];
$message = "";
$messageType = "error";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
        $message = "All password fields are required.";
    } elseif (strlen($newPassword) < 8) {
        $message = "New password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New password and confirmation do not match.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $curr = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$curr || !password_verify($currentPassword, $curr["password"])) {
            $message = "The current password you entered is incorrect.";
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $upd->bind_param("si", $newHash, $userId);
            if ($upd->execute()) {
                $message = "Your password has been updated successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to update password: " . $upd->error;
            }
            $upd->close();
        }
    }
}
?>

<div style="max-width: 600px; margin: 0 auto; width: 100%;">
    <?php if ($message): ?>
        <div class="admin-alert alert-<?= $messageType ?>">
            <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($messageType === 'success'): ?>
                <a href="profile.php" class="btn-primary" style="padding: 4px 12px; font-size: 11px;">Back to Profile</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="admin-panel">
        <div class="admin-panel-header">
            <div>
                <h3>Update Security Password</h3>
                <p>Ensure your account remains protected with a strong, distinct passphrase.</p>
            </div>
            <a href="profile.php" class="btn-secondary" style="font-size: 12px;">&larr; Profile</a>
        </div>

        <form method="POST" action="change_password.php" style="display: flex; flex-direction: column; gap: 20px;">
            <div class="admin-form-group">
                <label for="current_password">Current Password <span style="color: #ef4444;">*</span></label>
                <input type="password" id="current_password" name="current_password" class="admin-input" required>
            </div>

            <div class="admin-form-group">
                <label for="new_password">New Password <span style="color: #ef4444;">*</span></label>
                <input type="password" id="new_password" name="new_password" class="admin-input" minlength="8" placeholder="Minimum 8 characters" required>
            </div>

            <div class="admin-form-group">
                <label for="confirm_password">Confirm New Password <span style="color: #ef4444;">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="admin-input" minlength="8" placeholder="Repeat new password" required>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px; border-top: 1px solid var(--border-subtle); padding-top: 20px;">
                <a href="profile.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" style="padding: 10px 24px;">Update Password</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
