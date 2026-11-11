<?php
declare(strict_types=1);

header('Content-Type: application/json');

// Read a setting from the environment (Vercel) or from .env.local (XAMPP)
function env(string $key): string
{
    static $local = null;
    if ($local === null) {
        $local = [];
        $file = __DIR__ . '/../.env.local';
        if (is_readable($file)) {
            $parsed = parse_ini_file($file);
            $local = is_array($parsed) ? $parsed : [];
        }
    }
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    return (string)($local[$key] ?? '');
}

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
    env('DB_HOST'),
    env('DB_PORT') ?: '6543',
    env('DB_NAME') ?: 'postgres'
);

try {
    $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Sends the query and its parameters together, which the Supabase
        // transaction pooler requires. Parameters are still kept separate
        // from the SQL text, so SQL injection protection is unchanged.
        PDO::PGSQL_ATTR_DISABLE_PREPARES => true,
    ]);

    // Test 1: basic connection
    $version = $pdo->query('SELECT version() AS v')->fetch()['v'];

    // Test 2: parameterised query against a real table
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM employees WHERE status = :status');
    $stmt->execute(['status' => 'active']);
    $count = (int)$stmt->fetch()['total'];

    echo json_encode([
        'status'           => 'ok',
        'postgres_version' => $version,
        'active_employees' => $count,
        'expected'         => 5,
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
