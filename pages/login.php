<?php
if (current_user() !== null) {
    redirect(home_for_role(current_user()['role']));
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    // Server-side validation (the JavaScript checks can be bypassed)
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($password === '') {
        $errors[] = 'Enter your password.';
    }

    // Lock out after 5 failed attempts for this email in 15 minutes
    if (!$errors) {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM audit_log
             WHERE action = 'login_failed'
               AND details->>'email' = :email
               AND created_at > CAST(:since AS TIMESTAMPTZ)"
        );
        $stmt->execute(['email' => $email, 'since' => gmdate('c', time() - 900)]);

        if ((int)$stmt->fetchColumn() >= 5) {
            $errors[] = 'Too many failed attempts. Please wait 15 minutes and try again.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'SELECT id, email, password_hash, role, is_active
             FROM users WHERE LOWER(email) = :email'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Always run a password check, so unknown emails take the same time
        $hash   = $user['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $valid  = password_verify($password, $hash);
        $active = $user && in_array($user['is_active'], [true, 't', 1, '1'], true);

        if ($user && $valid && $active) {
            login_user($user);
            audit((int)$user['id'], 'login', 'user', (int)$user['id']);
            redirect(home_for_role($user['role']));
        }

        audit(
            $user ? (int)$user['id'] : null,
            'login_failed',
            'user',
            $user ? (int)$user['id'] : null,
            ['email' => $email]
        );
        $errors[] = 'Incorrect email or password.';   // same message for every failure
    }
}

page_header('Log in');
?>
<section class="card auth-card">
    <h1>Log in</h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= e(url('/login')) ?>" data-validate novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>"
                   autocomplete="username" required>
            <p class="field-error" aria-live="polite"></p>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>
            <p class="field-error" aria-live="polite"></p>
        </div>

        <button class="btn btn-block" type="submit">Log in</button>
    </form>
</section>
<?php page_footer(); ?>
