<?php
declare(strict_types=1);

header('Content-Type: application/json');

echo json_encode([
    'status'      => 'ok',
    'php_version' => PHP_VERSION,
    'pdo_drivers' => PDO::getAvailableDrivers(),
    'has_pgsql'   => in_array('pgsql', PDO::getAvailableDrivers(), true),
], JSON_PRETTY_PRINT);
