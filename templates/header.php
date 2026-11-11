<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | SmallPay</title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="<?= e(url('/')) ?>">SmallPay</a>
        <nav aria-label="Main">
            <a href="<?= e(url('/')) ?>">Home</a>
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