<?php
/**
 * UniServe Keep-Alive & Health Check Endpoint
 * Used by Render.com and external CronJobs (e.g., cron-job.org)
 * to keep free-tier instances warm and verify operational status.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$startTime = microtime(true);
$response = [
    'service' => 'UniServe System',
    'status' => 'ok',
    'server_time' => date('c'),
    'database' => 'disconnected'
];

try {
    require_once __DIR__ . '/config.php';
    if (isset($conn) && !$conn->connect_error) {
        $test = $conn->query("SELECT 1 AS alive");
        if ($test) {
            $response['database'] = 'connected';
        }
    }
} catch (Throwable $e) {
    $response['database_error'] = $e->getMessage();
}

$response['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit();
