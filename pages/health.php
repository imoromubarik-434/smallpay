<?php
if (env('APP_DEBUG') !== '1') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

$_SESSION['visits'] = ($_SESSION['visits'] ?? 0) + 1;

$dbOk = false;
$employees = 0;
$error = '';

try {
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM employees WHERE status = :status');
    $stmt->execute(['status' => 'active']);
    $employees = (int)$stmt->fetch()['total'];
    $dbOk = true;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

page_header('System check');
?>
<h1>System check</h1>
<div class="table-wrap">
    <table class="table">
        <tr><th>PHP version</th><td><?= e(PHP_VERSION) ?></td></tr>
        <tr><th>Database</th><td><?= $dbOk ? 'Connected' : 'Failed: ' . e($error) ?></td></tr>
        <tr><th>Active employees</th><td><?= $employees ?></td></tr>
        <tr><th>Session visits (refresh to watch it count)</th><td><?= (int)$_SESSION['visits'] ?></td></tr>
        <tr><th>Escaping test (should show as plain text)</th><td><?= e('<script>alert(1)</script>') ?></td></tr>
    </table>
</div>
<?php page_footer(); ?>