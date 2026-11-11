<?php
declare(strict_types=1);

/** Read a setting from the environment (Vercel) or from .env.local (XAMPP). */
function env(string $key, string $default = ''): string
{
    static $local = null;

    if ($local === null) {
        $local = [];
        $file = dirname(__DIR__) . '/.env.local';
        if (is_readable($file)) {
            $parsed = parse_ini_file($file);
            $local = is_array($parsed) ? $parsed : [];
        }
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return isset($local[$key]) && $local[$key] !== '' ? (string)$local[$key] : $default;
}