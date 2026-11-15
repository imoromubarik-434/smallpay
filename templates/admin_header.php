<?php
$items = [
    ['dashboard', 'Dashboard',    '/admin',           true],
    ['employees', 'Employees',    '/admin/employees', true],
    ['rules',     'Pay rules',    '/admin/rules',     true],
    ['payroll',   'Payroll runs', '/admin/payroll',   true],
    ['advances',  'Advances',     '/admin/advances',  true],
    ['reports',   'Reports',      '/admin/reports',   false],
    ['password',  'Change password', '/account/password', true],
];

require __DIR__ . '/head.php';
?>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="<?= e(url('/admin')) ?>">SmallPay</a>
        <nav class="admin-nav" aria-label="Admin">
            <?php foreach ($items as [$key, $label, $path, $ready]): ?>
                <?php if ($ready): ?>
                    <a href="<?= e(url($path)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php else: ?>
                    <span class="nav-disabled" title="Coming soon"><?= e($label) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>

            <form method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="link-button">Log out</button>
            </form>
        </nav>
    </aside>

    <div class="admin-main">
        <?php foreach (pull_flash() as $message): ?>
            <div class="alert alert-<?= e($message['type']) ?>" role="alert">
                <?= e($message['message']) ?>
            </div>
        <?php endforeach; ?>
