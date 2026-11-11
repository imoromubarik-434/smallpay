<?php
declare(strict_types=1);

/** Escape text for safe output in HTML (prevents XSS). */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build a link to a page of the app. */
function url(string $path = '/'): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/** Build a link to a file in public/assets. */
function asset(string $path): string
{
    return BASE_PATH . (IS_LOCAL ? '/public' : '') . '/assets/' . ltrim($path, '/');
}

/** Redirect to another page and stop. */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Format a money amount for display only (calculations use exact values). */
function money(string|int|float $amount): string
{
    return number_format((float)$amount, 2);
}

/** Store a one-time message to show on the next page. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Read and clear the stored messages. */
function pull_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** CSRF token for the current session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Hidden input to place inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';

    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Invalid or expired form. Please go back and try again.');
    }
}

/** Print the top of a page (header, navigation, flash messages). */
function page_header(string $pageTitle): void
{
    require ROOT . '/templates/header.php';
}

/** Print the bottom of a page. */
function page_footer(): void
{
    require ROOT . '/templates/footer.php';
}