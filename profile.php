<?php
/**
 * UniServe Enterprise Admin — Profile & Identity Settings
 */
$pageTitle = "Administrator Profile";
$pageSubtitle = "Review administrative identity and security configuration";
$activeNav = "profile";

require_once __DIR__ . '/includes/admin_header.php';

$userId = (int)$_SESSION["user_id"];

$stmt = $conn->prepare("SELECT user_id, full_name, email, role, created_at FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$adminUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

$initial = strtoupper(substr($adminUser['full_name'] ?? 'A', 0, 1));
?>

<div style="max-width: 720px; margin: 0 auto; width: 100%;">
    <div class="admin-panel" style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 20px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 24px; margin-bottom: 24px;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #38bdf8); color: #ffffff; display: grid; place-items: center; font-size: 26px; font-weight: 800; border: 3px solid rgba(255, 255, 255, 0.15); flex-shrink: 0;">
                <?= $initial ?>
            </div>
            <div>
                <h3 style="font-size: 20px; font-weight: 800; color: #ffffff; margin-bottom: 4px;">
                    <?= htmlspecialchars($adminUser['full_name'] ?? 'Administrator', ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="admin-badge badge-admin">Master Administrator</span>
                    <span style="font-size: 12px; color: var(--text-muted);">ID: #UID-<?= (int)$adminUser['user_id'] ?></span>
                </div>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px;">
            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.04);">
                <span style="font-size: 13px; color: var(--text-muted);">Email Address</span>
                <strong style="font-size: 13.5px; color: #ffffff; font-family: monospace;">
                    <?= htmlspecialchars($adminUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.04);">
                <span style="font-size: 13px; color: var(--text-muted);">Access Privilege</span>
                <span style="font-size: 13px; color: #34d399; font-weight: 600;">Full System Governance (Web)</span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.04);">
                <span style="font-size: 13px; color: var(--text-muted);">Registration Date</span>
                <span style="font-size: 13px; color: var(--text-main);">
                    <?= !empty($adminUser['created_at']) ? date("F d, Y h:i A", strtotime($adminUser['created_at'])) : 'N/A' ?>
                </span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 0;">
                <span style="font-size: 13px; color: var(--text-muted);">Session Security</span>
                <span style="font-size: 12px; color: #60a5fa;">Strict RBAC + Bcrypt Hash</span>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-subtle);">
            <a href="change_password.php" class="btn-primary" style="padding: 10px 20px;">
                Change Password &rarr;
            </a>
            <a href="logout.php" class="btn-secondary" style="color: #f43f5e; border-color: rgba(244, 63, 94, 0.3);">
                Sign Out
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
