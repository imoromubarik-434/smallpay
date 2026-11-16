<?php $navUser = current_user(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | SmallPay</title>
    <meta name="description" content="SmallPay turns hours, rates and advances into a payslip your whole team can read. Payroll for small shops, salons and workshops.">
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,700;1,9..144,500&family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/landing.css')) ?>">
    <script src="<?= e(asset('js/landing.js')) ?>" defer></script>
</head>
<body class="landing">
<a class="skip-link" href="#main">Skip to content</a>

<header class="l-header">
    <div class="l-wrap l-nav">
        <a class="l-brand" href="<?= e(url('/')) ?>">smallpay<span>.</span></a>
        <nav class="l-links" aria-label="Main">
            <a href="#try">Try it</a>
            <a href="#flow">How it works</a>
            <?php if ($navUser !== null): ?>
                <a class="l-login" href="<?= e(url(home_for_role($navUser['role']))) ?>">Dashboard</a>
            <?php else: ?>
                <a class="l-login" href="<?= e(url('/login')) ?>">Log in</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php foreach (pull_flash() as $message): ?>
    <div class="l-wrap">
        <div class="l-alert l-alert-<?= e($message['type']) ?>" role="alert"><?= e($message['message']) ?></div>
    </div>
<?php endforeach; ?>

<main id="main">
