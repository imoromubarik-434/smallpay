<?php
['user' => $user, 'employee' => $employee] = require_employee();

// Always the record linked to the logged-in user; no id is ever read from the request
$stmt = db()->prepare(
    'SELECT employee_number, first_name, last_name, job_title, phone, hire_date
     FROM employees WHERE id = :id'
);
$stmt->execute(['id' => $employee['id']]);
$profile = $stmt->fetch();

$errors = [];
$phone  = $profile['phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $phone = trim((string)($_POST['phone'] ?? ''));

    // ---- Server-side validation ----
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{5,30}$/', $phone)) {
        $errors['phone'] = 'Enter a valid phone number, or leave it empty.';
    }

    if (!$errors) {
        $stmt = db()->prepare('UPDATE employees SET phone = :phone WHERE id = :id');
        $stmt->execute([
            'phone' => $phone !== '' ? $phone : null,
            'id'    => $employee['id'],
        ]);

        audit($user['id'], 'profile_updated', 'employee', (int)$employee['id']);
        flash('success', 'Your phone number was saved.');
        redirect('/employee/profile');
    }
}

page_header('My profile');
?>
<h1>My profile</h1>

<section class="card">
    <h2>My details</h2>
    <dl class="payslip-meta">
        <dt>Name</dt><dd><?= e($profile['first_name'] . ' ' . $profile['last_name']) ?></dd>
        <dt>Employee number</dt><dd><?= e($profile['employee_number']) ?></dd>
        <dt>Job title</dt><dd><?= e($profile['job_title'] ?? '-') ?></dd>
        <dt>Hire date</dt><dd><?= e($profile['hire_date']) ?></dd>
        <dt>Login email</dt><dd><?= e($user['email']) ?></dd>
    </dl>
    <p class="hint">To change your name, job title or hire date, please ask your administrator.</p>
</section>

<section class="card status-box">
    <h2>Contact</h2>
    <form method="post" action="<?= e(url('/employee/profile')) ?>" data-validate novalidate>
        <?= csrf_field() ?>
        <?php
        field('phone', 'Phone number (optional)', $phone, $errors, 'tel',
            'maxlength="30" pattern="[0-9+\-\s\(\)]{5,30}" title="Digits, spaces, + - ( ) only"');
        ?>
        <button class="btn" type="submit">Save phone number</button>
    </form>
</section>

<section class="card status-box">
    <h2>Password</h2>
    <p>Change your password at any time.</p>
    <a class="btn btn-secondary" href="<?= e(url('/account/password')) ?>">Change password</a>
</section>
<?php page_footer(); ?>
