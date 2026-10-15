<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/src/env.php';
env_load(APP_ROOT . '/.env');

date_default_timezone_set(env('APP_TZ', 'Asia/Kuala_Lumpur'));

$isProduction = env('APP_ENV', 'local') === 'production';

error_reporting(E_ALL);
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');
if ($isProduction) {
    // Container logs are collected from stderr by the cloud platform.
    ini_set('error_log', 'php://stderr');
}

require_once APP_ROOT . '/src/helpers.php';
require_once APP_ROOT . '/src/db.php';
require_once APP_ROOT . '/src/auth.php';
require_once APP_ROOT . '/src/models.php';

// Defensive response headers. The stylesheet and script are served from the
// same origin, so a strict content security policy costs nothing here.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");

session_boot();

/**
 * Anything uncaught becomes a neutral error page. Stack traces and SQL
 * messages are written to the log instead of to the browser, so the
 * deployment never leaks schema or credential details to a visitor.
 */
set_exception_handler(static function (Throwable $e) use ($isProduction): void {
    error_log('Unhandled exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    $detail = $isProduction ? null : $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    require APP_ROOT . '/views/error.php';
    exit;
});
