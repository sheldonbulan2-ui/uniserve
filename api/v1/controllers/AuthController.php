<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/cors_security.php';
require_once __DIR__ . '/../../config/Response.php';
require_once __DIR__ . '/../../config/JwtHandler.php';
require_once __DIR__ . '/../../config/AuthMiddleware.php';
require_once __DIR__ . '/../../config/RateLimitMiddleware.php';

/**
 * Controller: AuthController
 * Handles Registration, Login, Token Refresh, and Session Revocation
 * Strictly blocks Admin credentials from Android Mobile App logins.
 */
class AuthController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    private function getJsonInput(): array {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = $_POST;
        }
        return $data;
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(): void {
        RateLimitMiddleware::checkIpRateLimit(25, 60);
        $input = $this->getJsonInput();

        $email = trim($input['email'] ?? '');
        $password = (string)($input['password'] ?? '');
        $deviceInfo = trim($input['device_info'] ?? 'Unknown Client');

        if ($email === '' || $password === '') {
            Response::error('Email and password are required.', 400);
        }

        $stmt = $this->db->prepare('SELECT id, uuid, role, full_name, email, password_hash, status, failed_login_attempts, locked_until FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Invalid email or password.', 401);
        }

        // Check if account is temporarily locked
        if (RateLimitMiddleware::isAccountLocked($user)) {
            Response::error('Account is temporarily locked due to multiple failed login attempts. Please try again later.', 423);
        }

        if ($user['status'] === 'suspended') {
            Response::forbidden('Your account has been suspended. Please contact platform support.');
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            RateLimitMiddleware::recordFailedLogin($this->db, (int)$user['id'], (int)$user['failed_login_attempts']);
            Response::error('Invalid email or password.', 401);
        }

        // Reset failed login counter on success
        RateLimitMiddleware::resetFailedAttempts($this->db, (int)$user['id']);

        // Strict Role Gatekeeper: Reject Admin on Android APK
        $platform = AuthMiddleware::getPlatform();
        if ($platform === 'android' && $user['role'] === 'admin') {
            Response::forbidden('Admin accounts are restricted to the UniServe Web Portal.');
        }

        // If Merchant, retrieve merchant_profile ID
        $merchantId = null;
        $businessName = null;
        if ($user['role'] === 'merchant') {
            $mStmt = $this->db->prepare('SELECT id, business_name FROM merchant_profiles WHERE user_id = ? LIMIT 1');
            $mStmt->execute([$user['id']]);
            $profile = $mStmt->fetch();
            if ($profile) {
                $merchantId = (int)$profile['id'];
                $businessName = $profile['business_name'];
            }
        }

        // Generate JWT Access Token (15 min) & Opaque Refresh Token (7 days)
        $tokenPayload = [
            'uid' => (int)$user['id'],
            'uuid' => $user['uuid'],
            'role' => $user['role'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'merchant_id' => $merchantId
        ];

        $accessToken = JwtHandler::encode($tokenPayload, 900);
        $refreshToken = JwtHandler::generateRefreshToken();
        $refreshHash = JwtHandler::hashRefreshToken($refreshToken);
        $expiresAt = date('Y-m-d H:i:s', time() + (7 * 86400));
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $tStmt = $this->db->prepare('INSERT INTO auth_tokens (user_id, refresh_token_hash, client_platform, device_info, ip_address, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
        $tStmt->execute([$user['id'], $refreshHash, $platform, $deviceInfo, $clientIp, $expiresAt]);

        Response::success('Authentication successful.', [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 900,
            'user' => [
                'id' => (int)$user['id'],
                'uuid' => $user['uuid'],
                'role' => $user['role'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'merchant_id' => $merchantId,
                'business_name' => $businessName
            ]
        ]);
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(): void {
        RateLimitMiddleware::checkIpRateLimit(15, 60);
        $input = $this->getJsonInput();

        $fullName = trim($input['full_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = (string)($input['password'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $role = trim($input['role'] ?? 'customer');
        $businessName = trim($input['business_name'] ?? '');
        $businessType = trim($input['business_type'] ?? 'General Services');

        if (!in_array($role, ['merchant', 'customer'], true)) {
            $role = 'customer';
        }

        if ($fullName === '' || $email === '' || $password === '') {
            Response::error('Full name, email, and password are required.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Please enter a valid email address.', 400);
        }

        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters long.', 400);
        }

        // Check email uniqueness
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::error('This email address is already registered.', 409);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $this->db->beginTransaction();
        try {
            $uStmt = $this->db->prepare('INSERT INTO users (uuid, role, full_name, email, phone, password_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $status = ($role === 'merchant') ? 'active' : 'active';
            $uStmt->execute([$uuid, $role, $fullName, $email, $phone, $passwordHash, $status]);
            $userId = (int)$this->db->lastInsertId();

            $merchantId = null;
            if ($role === 'merchant') {
                $bName = $businessName !== '' ? $businessName : "{$fullName}'s Services";
                $mStmt = $this->db->prepare('INSERT INTO merchant_profiles (user_id, business_name, business_type, contact_phone, is_verified) VALUES (?, ?, ?, ?, 1)');
                $mStmt->execute([$userId, $bName, $businessType, $phone]);
                $merchantId = (int)$this->db->lastInsertId();
            }

            $this->db->commit();

            Response::success('Account registered successfully. You may now log in.', [
                'user_id' => $userId,
                'role' => $role,
                'email' => $email,
                'merchant_id' => $merchantId
            ], 201);

        } catch (Throwable $e) {
            $this->db->rollBack();
            Response::serverError('Registration failed: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh(): void {
        $input = $this->getJsonInput();
        $refreshToken = trim($input['refresh_token'] ?? '');

        if ($refreshToken === '') {
            Response::error('Refresh token is required.', 400);
        }

        $refreshHash = JwtHandler::hashRefreshToken($refreshToken);
        $stmt = $this->db->prepare('SELECT t.id, t.user_id, t.expires_at, t.revoked, u.uuid, u.role, u.full_name, u.email, u.status 
            FROM auth_tokens t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.refresh_token_hash = ? LIMIT 1');
        $stmt->execute([$refreshHash]);
        $tokenRecord = $stmt->fetch();

        if (!$tokenRecord || (int)$tokenRecord['revoked'] === 1 || strtotime($tokenRecord['expires_at']) < time()) {
            Response::unauthorized('Invalid or expired refresh token.');
        }

        if ($tokenRecord['status'] === 'suspended') {
            Response::forbidden('User account is suspended.');
        }

        // Get merchant_id if applicable
        $merchantId = null;
        if ($tokenRecord['role'] === 'merchant') {
            $mStmt = $this->db->prepare('SELECT id FROM merchant_profiles WHERE user_id = ? LIMIT 1');
            $mStmt->execute([$tokenRecord['user_id']]);
            $prof = $mStmt->fetch();
            $merchantId = $prof ? (int)$prof['id'] : null;
        }

        // Revoke used token and issue new pair
        $upd = $this->db->prepare('UPDATE auth_tokens SET revoked = 1 WHERE id = ?');
        $upd->execute([$tokenRecord['id']]);

        $newRefreshToken = JwtHandler::generateRefreshToken();
        $newRefreshHash = JwtHandler::hashRefreshToken($newRefreshToken);
        $expiresAt = date('Y-m-d H:i:s', time() + (7 * 86400));
        $platform = AuthMiddleware::getPlatform();
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $ins = $this->db->prepare('INSERT INTO auth_tokens (user_id, refresh_token_hash, client_platform, ip_address, expires_at) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$tokenRecord['user_id'], $newRefreshHash, $platform, $clientIp, $expiresAt]);

        $newAccessToken = JwtHandler::encode([
            'uid' => (int)$tokenRecord['user_id'],
            'uuid' => $tokenRecord['uuid'],
            'role' => $tokenRecord['role'],
            'email' => $tokenRecord['email'],
            'full_name' => $tokenRecord['full_name'],
            'merchant_id' => $merchantId
        ], 900);

        Response::success('Token refreshed successfully.', [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => 900
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(): void {
        $input = $this->getJsonInput();
        $refreshToken = trim($input['refresh_token'] ?? '');

        if ($refreshToken !== '') {
            $refreshHash = JwtHandler::hashRefreshToken($refreshToken);
            $stmt = $this->db->prepare('UPDATE auth_tokens SET revoked = 1 WHERE refresh_token_hash = ?');
            $stmt->execute([$refreshHash]);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        Response::success('Logged out successfully.');
    }
}
