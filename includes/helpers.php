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

/** Build a link to a file in the assets folder. */
function asset(string $path): string
{
    return BASE_PATH . '/assets/' . ltrim($path, '/');
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

/** Print the top of an admin page (sidebar layout). $active highlights a menu item. */
function admin_header(string $pageTitle, string $active = ''): void
{
    require ROOT . '/templates/admin_header.php';
}

/** Print the bottom of an admin page. */
function admin_footer(): void
{
    require ROOT . '/templates/admin_footer.php';
}

/** Marks a form field as invalid for screen readers when it has an error. */
function invalid_attr(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' aria-invalid="true"' : '';
}

/** Show the 404 page and stop. */
function not_found(): never
{
    http_response_code(404);
    require ROOT . '/pages/404.php';
    exit;
}

/** Parse a YYYY-MM-DD date strictly; returns null if it is not a real date. */
function parse_date(string $value): ?DateTime
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $date : null;
}

/**
 * Print a labelled input with its error message.
 * $attrs is raw HTML written by us in the page (never user input).
 */
function field(string $name, string $label, string $value, array $errors, string $type = 'text', string $attrs = ''): void
{
    $invalid = isset($errors[$name]) ? ' aria-invalid="true"' : '';

    echo '<div class="form-group">'
        . '<label for="' . e($name) . '">' . e($label) . '</label>'
        . '<input type="' . e($type) . '" id="' . e($name) . '" name="' . e($name) . '"'
        . ' value="' . e($value) . '"' . $invalid . ' ' . $attrs . '>'
        . '<p class="field-error" aria-live="polite">' . e($errors[$name] ?? '') . '</p>'
        . '</div>';
}

/** A random password without look-alike characters (no 0/O, 1/l/I). */
function random_password(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $max      = strlen($alphabet) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];   // cryptographically secure
    }

    return $password;
}

/** True for a PostgreSQL boolean however the driver returns it. */
function truthy(mixed $value): bool
{
    return in_array($value, [true, 't', 'true', 1, '1'], true);
}

/**
 * Print a labelled dropdown with its error message.
 * $options is [value => text]. $attrs is raw HTML written by us (never user input).
 */
function select_field(string $name, string $label, array $options, string $selected, array $errors, string $attrs = ''): void
{
    $invalid = isset($errors[$name]) ? ' aria-invalid="true"' : '';

    echo '<div class="form-group">'
        . '<label for="' . e($name) . '">' . e($label) . '</label>'
        . '<select id="' . e($name) . '" name="' . e($name) . '"' . $invalid . ' ' . $attrs . '>';

    foreach ($options as $value => $text) {
        echo '<option value="' . e((string)$value) . '"'
            . ((string)$value === $selected ? ' selected' : '') . '>' . e($text) . '</option>';
    }

    echo '</select><p class="field-error" aria-live="polite">' . e($errors[$name] ?? '') . '</p></div>';
}
