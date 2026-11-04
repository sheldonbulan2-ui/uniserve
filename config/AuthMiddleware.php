<?php
declare(strict_types=1);

require_once __DIR__ . '/JwtHandler.php';
require_once __DIR__ . '/Response.php';

/**
 * Authentication and Role-Based Authorization Middleware
 */
class AuthMiddleware {
    private static ?array $currentUser = null;

    public static function getPlatform(): string {
        $platform = strtolower(trim($_SERVER['HTTP_X_CLIENT_PLATFORM'] ?? 'web'));
        return in_array($platform, ['android', 'ios', 'mobile'], true) ? 'android' : 'web';
    }

    /**
     * Authenticate request using JWT Bearer token or active Web Session
     */
    public static function authenticate(bool $allowWebSession = true): array {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = JwtHandler::decode($token);
            if (!$payload || !isset($payload['uid'], $payload['role'])) {
                Response::unauthorized('Invalid or expired authentication token.');
            }

            // Strict Role Guard: Block Admin on Android App
            $platform = self::getPlatform();
            if ($platform === 'android' && ($payload['role'] ?? '') === 'admin') {
                Response::forbidden('Admin accounts are restricted to the UniServe Web Portal.');
            }

            self::$currentUser = $payload;
            return $payload;
        }

        // Web session fallback if enabled
        if ($allowWebSession) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['user_id'], $_SESSION['role'])) {
                $user = [
                    'uid' => (int)$_SESSION['user_id'],
                    'role' => (string)$_SESSION['role'],
                    'email' => (string)($_SESSION['email'] ?? ''),
                    'full_name' => (string)($_SESSION['full_name'] ?? ''),
                    'merchant_id' => isset($_SESSION['merchant_id']) ? (int)$_SESSION['merchant_id'] : null
                ];
                self::$currentUser = $user;
                return $user;
            }
        }

        Response::unauthorized('Authentication required. Missing Bearer token.');
        exit();
    }

    /**
     * Enforce role-based access control
     */
    public static function requireRole(array $allowedRoles): array {
        $user = self::authenticate();
        $userRole = $user['role'] ?? '';

        if (!in_array($userRole, $allowedRoles, true)) {
            Response::forbidden("Access denied for role: '{$userRole}'.");
        }

        return $user;
    }

    public static function user(): ?array {
        return self::$currentUser;
    }
}
