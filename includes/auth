<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Write an entry to the audit log (who did what, and when). */
function audit(?int $userId, string $action, string $entity, ?int $entityId = null, array $details = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_log (user_id, action, entity, entity_id, details)
             VALUES (:user_id, :action, :entity, :entity_id, CAST(:details AS JSONB))'
        );
        $stmt->execute([
            'user_id'   => $userId,
            'action'    => $action,
            'entity'    => $entity,
            'entity_id' => $entityId,
            'details'   => json_encode($details, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('audit failed: ' . $e->getMessage());
    }
}

/** The logged-in user, re-checked against the database on every request. */
function current_user(): ?array
{
    static $checked = false;
    static $user = null;

    if ($checked) {
        return $user;
    }
    $checked = true;

    $session = $_SESSION['user'] ?? null;
    if (!is_array($session) || empty($session['id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, email, role, is_active FROM users WHERE id = :id');
    $stmt->execute(['id' => $session['id']]);
    $row = $stmt->fetch();

    if (!$row || !in_array($row['is_active'], [true, 't', 1, '1'], true)) {
        unset($_SESSION['user']);
        return null;
    }

    $user = [
        'id'          => (int)$row['id'],
        'email'       => $row['email'],
        'role'        => $row['role'],          // always taken from the database
        'employee_id' => $session['employee_id'] ?? null,
        'name'        => $session['name'] ?? $row['email'],
    ];

    return $user;
}

/** Page where each role lands after login. */
function home_for_role(string $role): string
{
    return $role === 'admin' ? '/admin' : '/employee';
}

/** Block the page unless someone is logged in. */
function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        flash('error', 'Please log in to continue.');
        redirect('/login');
    }

    return $user;
}

/** Block the page unless the logged-in user has this role. */
function require_role(string $role): array
{
    $user = require_login();

    if ($user['role'] !== $role) {
        http_response_code(403);
        page_header('Access denied');
        echo '<h1>403: Access denied</h1>';
        echo '<p>You do not have permission to view this page.</p>';
        page_footer();
        exit;
    }

    return $user;
}

/** Start a logged-in session for a verified user. */
function login_user(array $user): void
{
    session_regenerate_id(true);   // new session ID: prevents session fixation

    $stmt = db()->prepare('SELECT id, first_name, last_name FROM employees WHERE user_id = :uid');
    $stmt->execute(['uid' => $user['id']]);
    $employee = $stmt->fetch() ?: null;

    $_SESSION['user'] = [
        'id'          => (int)$user['id'],
        'employee_id' => $employee ? (int)$employee['id'] : null,
        'name'        => $employee ? $employee['first_name'] . ' ' . $employee['last_name'] : $user['email'],
    ];
    unset($_SESSION['csrf']);      // fresh CSRF token after login

    $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);
}

/** End the logged-in session. */
function logout_user(): void
{
    unset($_SESSION['user'], $_SESSION['csrf']);
    session_regenerate_id(true);
}
