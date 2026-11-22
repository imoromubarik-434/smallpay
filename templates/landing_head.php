<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | SmallPay</title>
    <meta name="description" content="SmallPay turns hours, rates and advances into a payslip your whole team can read. Payroll for small shops, salons and workshops.">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%2314110f'/%3E%3Ccircle cx='32' cy='32' r='19' fill='%23ffd84d'/%3E%3Ctext x='32' y='42' font-family='Georgia,serif' font-size='28' font-weight='700' text-anchor='middle' fill='%2314110f'%3E$%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,700;1,9..144,500&family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@400;500;600;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/landing.css')) ?>">
    <script src="<?= e(asset('js/landing.js')) ?>" defer></script>
</head>
<body class="landing">
<a class="skip-link" href="#main">Skip to content</a>
<?php
$navUser   = current_user();
$onLanding = true;
require __DIR__ . '/site_header.php';
?>
<main id="main">
