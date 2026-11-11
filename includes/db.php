<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/** One shared database connection per request. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
        env('DB_HOST'),
        env('DB_PORT', '6543'),
        env('DB_NAME', 'postgres')
    );

    $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE     => PDO::FETCH_ASSOC,
        // Needed for the Supabase transaction pooler. Parameters are still
        // sent separately from the SQL text, so injection protection holds.
        PDO::PGSQL_ATTR_DISABLE_PREPARES => true,
    ]);

    return $pdo;
}