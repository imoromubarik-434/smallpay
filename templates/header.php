<?php require __DIR__ . '/head.php'; $navUser = current_user(); ?>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="<?= e(url('/')) ?>"><span class="brand-mark" aria-hidden="true">S</span>SmallPay</a>
        <nav aria-label="Main">
            <a href="<?= e(url('/')) ?>">Home</a>
            <?php if ($navUser !== null): ?>
                <a href="<?= e(url(home_for_role($navUser['role']))) ?>">Dashboard</a>
                <?php if ($navUser['role'] === 'employee'): ?>
                    <a href="<?= e(url('/employee/payslips')) ?>">My payslips</a>
                    <a href="<?= e(url('/employee/advances')) ?>">Advances</a>
                    <a href="<?= e(url('/employee/profile')) ?>">Profile</a>
                <?php endif; ?>
                <a href="<?= e(url('/account/password')) ?>">Password</a>
                <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Log out</button>
                </form>
            <?php else: ?>
                <a class="btn btn-sm" href="<?= e(url('/login')) ?>">Log in</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php foreach (pull_flash() as $message): ?>
    <div class="container">
        <div class="alert alert-<?= e($message['type']) ?>" role="alert">
            <?= e($message['message']) ?>
        </div>
    </div>
<?php endforeach; ?>

<main class="container">
