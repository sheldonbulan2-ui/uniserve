<?php
declare(strict_types=1);

/**
 * Brute-Force & Rate Limiting Middleware
 */
class RateLimitMiddleware {
    private static function getClientIp(): string {
        return $_SERVER['HTTP_CF_CONNECTING_IP'] 
            ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? '127.0.0.1';
    }

    /**
     * In-memory or file-backed rate limiter for auth requests (max requests per minute)
     */
    public static function checkIpRateLimit(int $maxAttempts = 30, int $decaySeconds = 60): void {
        $ip = self::getClientIp();
        $cacheDir = sys_get_temp_dir() . '/uniserve_ratelimit';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $hash = md5($ip);
        $file = "{$cacheDir}/ip_{$hash}.json";

        $now = time();
        $data = ['count' => 0, 'first_seen' => $now];

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        if ($now - $data['first_seen'] > $decaySeconds) {
            $data = ['count' => 1, 'first_seen' => $now];
        } else {
            $data['count']++;
        }

        @file_put_contents($file, json_encode($data));

        if ($data['count'] > $maxAttempts) {
            header('Retry-After: ' . ($decaySeconds - ($now - $data['first_seen'])));
            Response::error('Too many requests. Please wait a minute before retrying.', 429);
        }
    }

    /**
     * Account lock check based on users.locked_until
     */
    public static function isAccountLocked(array $user): bool {
        if (!empty($user['locked_until'])) {
            $lockTime = strtotime((string)$user['locked_until']);
            if ($lockTime && $lockTime > time()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Record a failed login attempt and lock account if attempts >= 5
     */
    public static function recordFailedLogin(PDO $pdo, int $userId, int $currentAttempts): void {
        $newAttempts = $currentAttempts + 1;
        if ($newAttempts >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', time() + (15 * 60)); // 15 mins lock
            $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?');
            $stmt->execute([$newAttempts, $lockedUntil, $userId]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = ? WHERE id = ?');
            $stmt->execute([$newAttempts, $userId]);
        }
    }

    /**
     * Reset failed attempts on successful login
     */
    public static function resetFailedAttempts(PDO $pdo, int $userId): void {
        $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?');
        $stmt->execute([$userId]);
    }
}
