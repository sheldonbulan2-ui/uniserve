<?php
declare(strict_types=1);

/**
 * Cross-Site Request Forgery (CSRF) Protection for UniServe Web Platform
 */
class Csrf {
    private static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function getToken(): string {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(?string $token = null): bool {
        self::startSession();
        $stored = $_SESSION['csrf_token'] ?? '';
        if ($stored === '') {
            return false;
        }

        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (!$token || !is_string($token)) {
            return false;
        }

        return hash_equals($stored, $token);
    }

    public static function renderFormField(): string {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}
