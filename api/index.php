<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require ROOT . '/includes/env.php';
require ROOT . '/includes/db.php';
require ROOT . '/includes/helpers.php';
require ROOT . '/includes/session.php';

// Folder the app lives in: empty on Vercel, "/smallpay" on XAMPP
define('BASE_PATH', rtrim(env('APP_BASE_PATH'), '/'));
define('IS_LOCAL', env('APP_ENV') === 'local');

// Basic security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Whitelist: only these pages can ever be loaded
$routes = [
    '/'       => 'home',
    '/health' => 'health',
];

// Work out which page was requested
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
    $path = substr($path, strlen(BASE_PATH));
}
$path = '/' . trim($path, '/');

try {
    start_session();

    $page = $routes[$path] ?? '404';
    if ($page === '404') {
        http_response_code(404);
    }

    require ROOT . '/pages/' . $page . '.php';
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo env('APP_DEBUG') === '1'
        ? '<pre>' . e($e->getMessage()) . '</pre>'
        : 'Something went wrong. Please try again later.';
}