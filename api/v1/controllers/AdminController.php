<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/Response.php';
require_once __DIR__ . '/../../config/AuthMiddleware.php';

/**
 * Controller: AdminController
 * Executive Dashboard KPIs, Global User & Merchant Account Control, Audit Logs, and APK Release Management.
 */
class AdminController {
    private PDO $db;
    private array $user;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->user = AuthMiddleware::requireRole(['admin']);
    }

    private function getJsonInput(): array {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : $_POST;
    }

    /**
     * GET /api/v1/admin/dashboard
     */
    public function dashboard(): void {
        $stmtRev = $this->db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status IN ('confirmed', 'completed', 'ready')");
        $totalRevenue = (float)$stmtRev->fetchColumn();

        $stmtMerchants = $this->db->query("SELECT COUNT(id) FROM merchant_profiles WHERE is_verified = 1");
        $activeMerchants = (int)$stmtMerchants->fetchColumn();

        $stmtCustomers = $this->db->query("SELECT COUNT(id) FROM users WHERE role = 'customer' AND status = 'active'");
        $activeCustomers = (int)$stmtCustomers->fetchColumn();

        $stmtOrders = $this->db->query("SELECT COUNT(id) FROM orders");
        $totalOrders = (int)$stmtOrders->fetchColumn();

        $stmtRes = $this->db->query("SELECT COUNT(id) FROM reservations WHERE status IN ('pending', 'approved', 'in_service')");
        $activeReservations = (int)$stmtRes->fetchColumn();

        // Recent System Activity
        $stmtLogs = $this->db->query("SELECT a.*, u.full_name AS user_name, u.email AS user_email 
            FROM audit_logs a 
            LEFT JOIN users u ON a.user_id = u.id 
            ORDER BY a.created_at DESC LIMIT 10");
        $recentAudit = $stmtLogs->fetchAll();

        Response::success('Admin executive dashboard retrieved.', [
            'total_revenue' => $totalRevenue,
            'active_merchants' => $activeMerchants,
            'active_customers' => $activeCustomers,
            'total_orders' => $totalOrders,
            'active_reservations' => $activeReservations,
            'recent_audit' => $recentAudit
        ]);
    }

    /**
     * GET /api/v1/admin/users
     */
    public function getUsers(): void {
        $role = $_GET['role'] ?? null;
        $sql = 'SELECT id, uuid, role, full_name, email, phone, status, failed_login_attempts, locked_until, created_at FROM users';
        $params = [];

        if ($role && in_array($role, ['admin', 'merchant', 'customer'], true)) {
            $sql .= ' WHERE role = ?';
            $params[] = $role;
        }

        $sql .= ' ORDER BY id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        Response::success('Users list retrieved.', $stmt->fetchAll());
    }

    /**
     * PATCH /api/v1/admin/users/{id}/status
     */
    public function updateUserStatus(array $params): void {
        $userId = (int)($params['id'] ?? 0);
        $input = $this->getJsonInput();
        $status = trim($input['status'] ?? '');

        if (!in_array($status, ['active', 'pending', 'suspended'], true)) {
            Response::error('Invalid user status.', 400);
        }

        $stmt = $this->db->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute([$status, $userId]);

        Response::success("User #{$userId} status updated to '{$status}'.");
    }

    /**
     * GET /api/v1/admin/merchants
     */
    public function getMerchants(): void {
        $stmt = $this->db->query("SELECT m.*, u.full_name AS owner_name, u.email AS owner_email, u.status AS account_status,
            (SELECT COUNT(c.id) FROM catalog_items c WHERE c.merchant_id = m.id) AS catalog_count 
            FROM merchant_profiles m 
            JOIN users u ON m.user_id = u.id 
            ORDER BY m.id DESC");
        Response::success('Merchants list retrieved.', $stmt->fetchAll());
    }

    /**
     * PATCH /api/v1/admin/merchants/{id}/verify
     */
    public function verifyMerchant(array $params): void {
        $merchantId = (int)($params['id'] ?? 0);
        $input = $this->getJsonInput();
        $isVerified = !empty($input['is_verified']) ? 1 : 0;

        $stmt = $this->db->prepare('UPDATE merchant_profiles SET is_verified = ? WHERE id = ?');
        $stmt->execute([$isVerified, $merchantId]);

        Response::success("Merchant #{$merchantId} verification updated.");
    }

    /**
     * GET /api/v1/admin/apk-releases
     */
    public function getApkReleases(): void {
        $stmt = $this->db->query("SELECT a.*, u.full_name AS uploaded_by_name 
            FROM apk_releases a 
            LEFT JOIN users u ON a.uploaded_by = u.id 
            ORDER BY a.created_at DESC");
        Response::success('APK releases retrieved.', $stmt->fetchAll());
    }

    /**
     * POST /api/v1/admin/apk-releases
     */
    public function uploadApkRelease(): void {
        $versionName = trim($_POST['version_name'] ?? '');
        $versionCode = (int)($_POST['version_code'] ?? 1);
        $releaseNotes = trim($_POST['release_notes'] ?? '');

        if ($versionName === '' || !isset($_FILES['apk_file'])) {
            Response::error('Version name and APK file are required.', 400);
        }

        $file = $_FILES['apk_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('APK upload failed: ' . $file['error'], 400);
        }

        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'apk') {
            Response::error('Only .apk files are permitted.', 400);
        }

        $uploadDir = __DIR__ . '/../../downloads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $destFileName = 'UniServe-' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $versionName) . '.apk';
        $destPath = $uploadDir . '/' . $destFileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Response::serverError('Failed to save uploaded APK to destination.');
        }

        $fileSizeBytes = filesize($destPath);
        $sha256 = hash_file('sha256', $destPath);
        $relPath = 'downloads/' . $destFileName;

        // Reset previous latest flags
        $this->db->exec('UPDATE apk_releases SET is_latest = 0');

        $stmt = $this->db->prepare('INSERT INTO apk_releases (version_name, version_code, file_name, file_path, file_size_bytes, sha256_checksum, release_notes, is_latest, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)');
        $stmt->execute([$versionName, $versionCode, $destFileName, $relPath, $fileSizeBytes, $sha256, $releaseNotes, $this->user['uid']]);
        $releaseId = (int)$this->db->lastInsertId();

        Response::success('New APK release published successfully.', [
            'release_id' => $releaseId,
            'version_name' => $versionName,
            'file_name' => $destFileName,
            'file_size_bytes' => $fileSizeBytes,
            'sha256_checksum' => $sha256
        ], 201);
    }
}
