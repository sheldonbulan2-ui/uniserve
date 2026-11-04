<?php
declare(strict_types=1);

/**
 * UniServe API Gateway & Universal Fallback Forwarder
 * Seamlessly forwards /api/auth/* to /api/v1/auth/*,
 * while maintaining backward compatibility for direct legacy scripts.
 */

$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($uri, PHP_URL_PATH) ?? '';

// Check if request is directed towards auth or v1
if (preg_match('#^/api/(auth/.*)$#', $path, $matches)) {
    $_SERVER['REQUEST_URI'] = '/api/v1/' . $matches[1];
    require_once __DIR__ . '/v1/index.php';
    exit();
}

if (str_starts_with($path, '/api/v1')) {
    require_once __DIR__ . '/v1/index.php';
    exit();
}

// Fallback to legacy endpoint if requested directly
$script = trim($path, '/');
if (str_starts_with($script, 'api/')) {
    $script = substr($script, 4);
}
$targetFile = __DIR__ . '/' . basename($script);
if (file_exists($targetFile) && is_file($targetFile) && $targetFile !== __FILE__) {
    require_once $targetFile;
    exit();
}

// Standard JSON 404 response
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'message' => 'API endpoint not found: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit();
