<?php
declare(strict_types=1);

/**
 * UniServe Defense-in-Depth Security & CORS Middleware
 */

// Allow cross-origin requests from mobile app and web clients
$allowedOrigins = ['*'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

header('Access-Control-Allow-Origin: ' . (in_array($origin, $allowedOrigins, true) || $allowedOrigins[0] === '*' ? $origin : ''));
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, X-Client-Platform');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

// Comprehensive Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Respond to preflight OPTIONS request immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}
