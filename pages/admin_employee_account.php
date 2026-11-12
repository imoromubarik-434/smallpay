<?php
$user = require_role('admin');

// This page only accepts form submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/employees');
}
csrf_verify();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    not_found();
}

$stmt = db()->prepare('SELECT id, first_name, last_name, status, user_id FROM employees WHERE id = :id');
$stmt->execute(['id' => $id]);
$employee = $stmt->fetch();
if (!$employee) {
    not_found();
}

$back = '/admin/employees/edit?id=' . $id;

if ($employee['user_id'] !== null) {
    flash('error', 'This employee already has a login account.');
    redirect($back);
}
if ($employee['status'] !== 'active') {
    flash('error', 'Reactivate this employee before creating a login.');
    redirect($back);
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
if ($email === '' || mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Enter a valid email address.');
    redirect($back);
}

// Create the account and link it to the employee, together or not at all
$password = random_password();
$pdo      = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, password_hash, role, must_change_password)
         VALUES (:email, :hash, 'employee', TRUE)
         RETURNING id"
    );
    $stmt->execute([
        'email' => $email,
        'hash'  => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $newUserId = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('UPDATE employees SET user_id = :uid WHERE id = :id AND user_id IS NULL');
    $stmt->execute(['uid' => $newUserId, 'id' => $id]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Employee was linked to a login by someone else.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e instanceof PDOException && $e->getCode() === '23505') {   // unique violation
        flash('error', 'That email address is already used by another account.');
        redirect($back);
    }
    throw $e;
}

// The password is deliberately NOT written to the audit log
audit($user['id'], 'account_created', 'employee', $id, ['email' => $email]);

// Show the password once, directly on this response (no redirect, nothing stored)
admin_header('Login created', 'employees');
?>
<h1>Login account created</h1>

<div class="card">
    <p>
        Give these details to <?= e($employee['first_name'] . ' ' . $employee['last_name']) ?>.
        The password is shown <strong>only once</strong> and cannot be recovered later.
    </p>

    <dl class="credentials">
        <dt>Email</dt>
        <dd><?= e($email) ?></dd>
        <dt>Temporary password</dt>
        <dd><code><?= e($password) ?></code></dd>
    </dl>

    <p class="hint">Ask them to change it after their first login.</p>
    <a class="btn" href="<?= e(url($back)) ?>">Back to employee</a>
</div>
<?php admin_footer(); ?>
