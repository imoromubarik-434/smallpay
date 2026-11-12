<?php
$user = require_login(true);   // allowed even while a password change is required

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    // Too many wrong "current password" attempts in the last 15 minutes?
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM audit_log
         WHERE action = 'password_change_failed' AND user_id = :uid
           AND created_at > CAST(:since AS TIMESTAMPTZ)"
    );
    $stmt->execute(['uid' => $user['id'], 'since' => gmdate('c', time() - 900)]);

    if ((int)$stmt->fetchColumn() >= 5) {
        $errors['current_password'] = 'Too many failed attempts. Please wait 15 minutes.';
    } else {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);

        if (!password_verify($current, (string)$stmt->fetchColumn())) {
            $errors['current_password'] = 'Your current password is incorrect.';
            audit($user['id'], 'password_change_failed', 'user', $user['id']);
        }
    }

    // Rules for the new password (bcrypt only reads the first 72 bytes)
    if (strlen($new) < 10) {
        $errors['new_password'] = 'Use at least 10 characters.';
    } elseif (strlen($new) > 72) {
        $errors['new_password'] = 'Use at most 72 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $errors['new_password'] = 'Include at least one letter and one number.';
    } elseif ($new === $current) {
        $errors['new_password'] = 'The new password must be different from the current one.';
    } elseif (strtolower($new) === strtolower($user['email'])) {
        $errors['new_password'] = 'The password cannot be your email address.';
    }

    if (!hash_equals($new, $confirm)) {
        $errors['confirm_password'] = 'The passwords do not match.';
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = FALSE WHERE id = :id'
        );
        $stmt->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user['id']]);

        session_regenerate_id(true);   // new session ID after a credential change
        unset($_SESSION['csrf']);

        audit($user['id'], 'password_changed', 'user', $user['id']);
        flash('success', 'Your password was changed.');
        redirect(home_for_role($user['role']));
    }
}

$isAdmin = $user['role'] === 'admin';
$isAdmin ? admin_header('Change password', 'password') : page_header('Change password');
?>
<section class="card auth-card">
    <h1>Change password</h1>

    <?php if ($user['must_change']): ?>
        <p class="notice">You are using a temporary password. Choose your own to continue.</p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/account/password')) ?>" data-validate novalidate>
        <?= csrf_field() ?>
        <?php
        field('current_password', 'Current password', '', $errors, 'password',
            'autocomplete="current-password" required');
        field('new_password', 'New password', '', $errors, 'password',
            'autocomplete="new-password" minlength="10" maxlength="72"
             pattern="(?=.*[A-Za-z])(?=.*[0-9]).{10,72}"
             title="At least 10 characters, with at least one letter and one number" required');
        ?>
        <p class="hint">Use at least 10 characters, with a letter and a number. Longer is better.</p>
        <?php
        field('confirm_password', 'Confirm new password', '', $errors, 'password',
            'autocomplete="new-password" data-match="new_password" required');
        ?>
        <button class="btn btn-block" type="submit">Save new password</button>
    </form>
</section>
<?php $isAdmin ? admin_footer() : page_footer(); ?>
