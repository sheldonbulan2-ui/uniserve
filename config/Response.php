<?php
declare(strict_types=1);

/**
 * Standardized JSON API Response Helper
 */
class Response {
    public static function json(bool $success, string $message, mixed $data = null, int $statusCode = 200, array $extra = []): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $payload = array_merge([
            'success' => $success,
            'message' => $message,
            'timestamp' => time(),
            'data' => $data
        ], $extra);

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit();
    }

    public static function success(string $message = 'Success', mixed $data = null, int $statusCode = 200, array $extra = []): void {
        self::json(true, $message, $data, $statusCode, $extra);
    }

    public static function error(string $message = 'An error occurred', int $statusCode = 400, mixed $data = null): void {
        self::json(false, $message, $data, $statusCode);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void {
        self::json(false, $message, null, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void {
        self::json(false, $message, null, 403);
    }

    public static function notFound(string $message = 'Resource not found'): void {
        self::json(false, $message, null, 404);
    }

    public static function serverError(string $message = 'Internal server error'): void {
        self::json(false, $message, null, 500);
    }
}
