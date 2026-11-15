<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require ROOT . '/includes/env.php';
require ROOT . '/includes/db.php';
require ROOT . '/includes/helpers.php';
require ROOT . '/includes/session.php';
require ROOT . '/includes/auth.php';

// Folder the app lives in: empty on Vercel, "/smallpay" on XAMPP
define('BASE_PATH', rtrim(env('APP_BASE_PATH'), '/'));

// Basic security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Whitelist: only these pages can ever be loaded
$routes = [
    '/'         => 'home',
    '/health'   => 'health',
    '/login'    => 'login',
    '/logout'   => 'logout',
    '/admin'    => 'admin/dashboard',
    '/employee' => 'employee/dashboard',
    '/admin/employees' => 'admin_employees',
    '/admin/employees/new' => 'admin_employee_new',
    '/admin/employees/edit' => 'admin_employee_edit',
    '/admin/employees/status' => 'admin_employee_status',
    '/admin/employees/account' => 'admin_employee_account',
    '/account/password' => 'account_password',
    '/admin/rules'        => 'admin_rules',
    '/admin/rules/toggle' => 'admin_rule_toggle',
    '/admin/rules/new'  => 'admin_rule_form',
    '/admin/rules/edit' => 'admin_rule_form',
    '/admin/payroll'       => 'admin_payroll',
    '/admin/payroll/start' => 'admin_payroll_start',
    '/admin/payroll/run'     => 'admin_payroll_run',
    '/admin/payroll/discard' => 'admin_payroll_discard',
];

// Work out which page was requested
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
    $path = substr($path, strlen(BASE_PATH));
}
$path = '/' . trim($path, '/');

// Serve files from /assets ourselves (works on Vercel and XAMPP)
if (str_starts_with($path, '/assets/')) {
    $types = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff2' => 'font/woff2',
    ];

    $assetsDir = realpath(ROOT . '/assets');
    $file      = realpath(ROOT . $path);
    $ext       = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';

    // Only real files inside /assets with an allowed type are served
    if (
        $file && $assetsDir
        && str_starts_with($file, $assetsDir . DIRECTORY_SEPARATOR)
        && is_file($file)
        && isset($types[$ext])
    ) {
        header('Content-Type: ' . $types[$ext]);
        header('Cache-Control: public, max-age=3600');
        readfile($file);
    } else {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
    }
    exit;
}

// Dynamic pages must never be cached (protects pages after logout)
header('Cache-Control: no-store');

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
