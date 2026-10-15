<?php
declare(strict_types=1);

/**
 * Health check endpoint.
 *
 * The cloud platform polls this to decide whether a deployment is live, and it
 * doubles as a lightweight target for response-time measurements because it
 * renders no HTML and loads no assets.
 *
 * It deliberately starts no session and reveals no host names or credentials.
 */

require_once __DIR__ . '/../src/env.php';
env_load(dirname(__DIR__) . '/.env');
require_once __DIR__ . '/../src/db.php';

date_default_timezone_set(env('APP_TZ', 'Asia/Kuala_Lumpur'));

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$start  = microtime(true);
$status = 'ok';
$db     = 'ok';

try {
    db()->query('SELECT 1')->fetchColumn();
} catch (Throwable $e) {
    error_log('Health check database failure: ' . $e->getMessage());
    $status = 'degraded';
    $db     = 'unreachable';
    http_response_code(503);
}

echo json_encode([
    'status'      => $status,
    'database'    => $db,
    'environment' => env('APP_ENV', 'local'),
    'php'         => PHP_VERSION,
    'time'        => date('c'),
    'check_ms'    => round((microtime(true) - $start) * 1000, 2),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
