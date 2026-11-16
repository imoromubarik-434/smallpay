<?php
// Needs $navUser (the logged-in user or null) and $onLanding (true on the landing page)
?>
<header class="site-header">
    <div class="container site-bar">
        <a class="brand" href="<?= e(url('/')) ?>">smallpay<span>.</span></a>

        <input type="checkbox" id="nav-toggle" class="nav-check" aria-label="Open the menu">
        <label for="nav-toggle" class="nav-btn">Menu</label>

        <nav class="site-nav" aria-label="Main">
            <?php if ($onLanding): ?>
                <a href="#try">Try it</a>
                <a href="#flow">How it works</a>
            <?php else: ?>
                <a href="<?= e(url('/')) ?>">Home</a>
            <?php endif; ?>

            <?php if ($navUser !== null): ?>
                <a href="<?= e(url(home_for_role($navUser['role']))) ?>">Dashboard</a>
                <?php if ($navUser['role'] === 'employee'): ?>
                    <a href="<?= e(url('/employee/payslips')) ?>">My payslips</a>
                    <a href="<?= e(url('/employee/advances')) ?>">Advances</a>
                    <a href="<?= e(url('/employee/profile')) ?>">Profile</a>
                <?php endif; ?>
                <a href="<?= e(url('/account/password')) ?>">Password</a>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="nav-link">Log out</button>
                </form>
            <?php else: ?>
                <a class="nav-cta" href="<?= e(url('/login')) ?>">Log in</a>
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
