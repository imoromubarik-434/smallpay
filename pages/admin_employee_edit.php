<?php
$user = require_role('admin');

// The employee id comes from the URL and must be a positive whole number
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    not_found();
}

$stmt = db()->prepare(
    'SELECT id, employee_number, first_name, last_name, phone, job_title, hire_date, status, user_id
     FROM employees WHERE id = :id'
);
$stmt->execute(['id' => $id]);
$employee = $stmt->fetch();
if (!$employee) {
    not_found();
}

// Login account, if the employee has one
$account = null;
if ($employee['user_id'] !== null) {
    $stmt = db()->prepare('SELECT email, is_active, last_login_at FROM users WHERE id = :uid');
    $stmt->execute(['uid' => $employee['user_id']]);
    $account = $stmt->fetch() ?: null;
}

// Unpaid advance balance (shown as a warning before deactivating)
$stmt = db()->prepare(
    "SELECT COALESCE(SUM(balance), 0) FROM advance_balances
     WHERE employee_id = :id AND status = 'approved'"
);
$stmt->execute(['id' => $id]);
$outstanding = $stmt->fetchColumn();

// Pay structure in force today
$stmt = db()->prepare(
    'SELECT pay_type, rate FROM pay_structures
     WHERE employee_id = :id AND effective_from <= CURRENT_DATE
     ORDER BY effective_from DESC LIMIT 1'
);
$stmt->execute(['id' => $id]);
$current = $stmt->fetch() ?: ['pay_type' => '', 'rate' => ''];

$types  = ['monthly' => 'Monthly salary', 'daily' => 'Daily rate', 'hourly' => 'Hourly rate'];
$errors = [];
$old    = [
    'first_name'     => $employee['first_name'],
    'last_name'      => $employee['last_name'],
    'job_title'      => $employee['job_title'] ?? '',
    'phone'          => $employee['phone'] ?? '',
    'hire_date'      => $employee['hire_date'],
    'pay_type'       => $current['pay_type'],
    'rate'           => $current['rate'],
    'effective_from' => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach ($old as $key => $default) {
        $value     = $_POST[$key] ?? '';
        $old[$key] = is_string($value) ? trim($value) : '';
    }

    // ---- Server-side validation ----
    if ($old['first_name'] === '' || mb_strlen($old['first_name']) > 80) {
        $errors['first_name'] = 'Enter a first name (up to 80 characters).';
    }
    if ($old['last_name'] === '' || mb_strlen($old['last_name']) > 80) {
        $errors['last_name'] = 'Enter a last name (up to 80 characters).';
    }
    if (mb_strlen($old['job_title']) > 100) {
        $errors['job_title'] = 'Job title can be up to 100 characters.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{5,30}$/', $old['phone'])) {
        $errors['phone'] = 'Enter a valid phone number.';
    }

    $hire = parse_date($old['hire_date']);
    if ($hire === null) {
        $errors['hire_date'] = 'Enter a valid hire date.';
    } elseif ($hire > new DateTime('+1 year')) {
        $errors['hire_date'] = 'The hire date cannot be more than a year ahead.';
    }

    if (!isset($types[$old['pay_type']])) {
        $errors['pay_type'] = 'Choose a pay type.';
    }
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $old['rate']) || (float)$old['rate'] <= 0) {
        $errors['rate'] = 'Enter an amount above 0, with up to 2 decimals and no commas.';
    }

    // The effective date only matters when the pay type or rate changes
    $payChanged = !isset($errors['pay_type'], $errors['rate'])
        && ($old['pay_type'] !== $current['pay_type'] || (float)$old['rate'] !== (float)$current['rate']);

    if ($payChanged) {
        $from = parse_date($old['effective_from']);
        if ($from === null) {
            $errors['effective_from'] = 'Enter a valid date.';
        } elseif ($hire !== null && $from < $hire) {
            $errors['effective_from'] = 'The new rate cannot start before the hire date.';
        }
    }

    // ---- Save everything together, or nothing ----
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'UPDATE employees
                 SET first_name = :first, last_name = :last, phone = :phone,
                     job_title = :title, hire_date = :hired
                 WHERE id = :id'
            );
            $stmt->execute([
                'first' => $old['first_name'],
                'last'  => $old['last_name'],
                'phone' => $old['phone'] !== '' ? $old['phone'] : null,
                'title' => $old['job_title'] !== '' ? $old['job_title'] : null,
                'hired' => $old['hire_date'],
                'id'    => $id,
            ]);

            if ($payChanged) {
                // A second change on the same day updates that day's row
                $stmt = $pdo->prepare(
                    'INSERT INTO pay_structures (employee_id, pay_type, rate, effective_from)
                     VALUES (:employee, :type, :rate, :from)
                     ON CONFLICT (employee_id, effective_from)
                     DO UPDATE SET pay_type = EXCLUDED.pay_type, rate = EXCLUDED.rate'
                );
                $stmt->execute([
                    'employee' => $id,
                    'type'     => $old['pay_type'],
                    'rate'     => $old['rate'],
                    'from'     => $old['effective_from'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        audit($user['id'], 'employee_updated', 'employee', $id, [
            'pay_changed' => $payChanged,
            'from'        => $payChanged ? $current['pay_type'] . ' ' . $current['rate'] : null,
            'to'          => $payChanged ? $old['pay_type'] . ' ' . $old['rate'] : null,
        ]);
        flash('success', 'Changes to ' . $old['first_name'] . ' ' . $old['last_name'] . ' were saved.');
        redirect('/admin/employees');
    }
}

// Rate history, newest first
$stmt = db()->prepare(
    'SELECT pay_type, rate, effective_from FROM pay_structures
     WHERE employee_id = :id ORDER BY effective_from DESC'
);
$stmt->execute(['id' => $id]);
$history = $stmt->fetchAll();

admin_header('Edit employee', 'employees');
?>
<div class="page-head">
    <div>
        <h1>Edit employee</h1>
        <p>
            <?= e($employee['employee_number']) ?>
            <span class="chip <?= $employee['status'] === 'active' ? 'chip-green' : 'chip-gray' ?>">
                <?= e(ucfirst($employee['status'])) ?>
            </span>
        </p>
    </div>
</div>

<form class="card" method="post"
      action="<?= e(url('/admin/employees/edit') . '?id=' . $id) ?>" data-validate novalidate>
    <?= csrf_field() ?>

    <div class="form-grid">
        <?php
        field('first_name', 'First name', $old['first_name'], $errors, 'text', 'maxlength="80" required');
        field('last_name', 'Last name', $old['last_name'], $errors, 'text', 'maxlength="80" required');
        field('job_title', 'Job title (optional)', $old['job_title'], $errors, 'text', 'maxlength="100"');
        field('phone', 'Phone (optional)', $old['phone'], $errors, 'tel',
            'maxlength="30" pattern="[0-9+\-\s\(\)]{5,30}" title="Digits, spaces, + - ( ) only"');
        field('hire_date', 'Hire date', $old['hire_date'], $errors, 'date', 'required');
        ?>
    </div>

    <h2>Pay</h2>
    <div class="form-grid">
        <div class="form-group">
            <label for="pay_type">Pay type</label>
            <select id="pay_type" name="pay_type" required<?= invalid_attr($errors, 'pay_type') ?>>
                <option value="">Choose...</option>
                <?php foreach ($types as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $old['pay_type'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="field-error" aria-live="polite"><?= e($errors['pay_type'] ?? '') ?></p>
        </div>

        <?php
        field('rate', 'Rate (per month, day or hour)', $old['rate'], $errors, 'text',
            'inputmode="decimal" pattern="\d{1,10}(\.\d{1,2})?" title="A number with up to 2 decimals, for example 80 or 12.50" required');
        field('effective_from', 'New rate starts on', $old['effective_from'], $errors, 'date', 'required');
        ?>
    </div>
    <p class="hint">
        The start date is used only if you change the pay type or rate. A change applies to
        payroll from that date, and payslips that are already finalized never change.
    </p>

    <div class="form-actions">
        <button class="btn" type="submit">Save changes</button>
        <a class="btn btn-secondary" href="<?= e(url('/admin/employees')) ?>">Cancel</a>
    </div>
</form>

<section class="card history">
    <h2>Pay history</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Starts</th><th>Pay type</th><th>Rate</th></tr></thead>
            <tbody>
                <?php foreach ($history as $row): ?>
                    <tr>
                        <td><?= e($row['effective_from']) ?></td>
                        <td><?= e(ucfirst($row['pay_type'])) ?></td>
                        <td><?= e(money($row['rate'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card account-box">
    <h2>Login account</h2>

    <?php if ($account): ?>
        <p>Login email: <strong><?= e($account['email']) ?></strong></p>
        <p>Last login:
            <?= $account['last_login_at'] ? e(substr($account['last_login_at'], 0, 16)) . ' UTC' : 'Never' ?>
        </p>
    <?php elseif ($employee['status'] === 'active'): ?>
        <p>
            This employee cannot log in yet. Enter their email address and a temporary
            password will be generated and shown once.
        </p>
        <form method="post" action="<?= e(url('/admin/employees/account')) ?>" data-validate novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <?php field('email', 'Login email', '', [], 'email', 'maxlength="255" required autocomplete="off"'); ?>
            <button class="btn" type="submit">Create login</button>
        </form>
    <?php else: ?>
        <p>Reactivate this employee to create a login.</p>
    <?php endif; ?>
</section>

<section class="card status-box">
    <h2>Employment status</h2>

    <?php if ($employee['status'] === 'active'): ?>
        <p>
            Inactive employees are left out of payroll runs and cannot log in.
            Their payslip history is kept.
        </p>
        <?php if ((float)$outstanding > 0): ?>
            <p class="notice">
                This employee has an unpaid advance of <?= e(money($outstanding)) ?>.
                It is not recovered while they are inactive.
            </p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/admin/employees/status')) ?>"
              data-confirm="Deactivate this employee? They will be left out of payroll and cannot log in.">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <input type="hidden" name="action" value="deactivate">
            <button class="btn btn-danger" type="submit">Deactivate employee</button>
        </form>
    <?php else: ?>
        <p>This employee is inactive. Reactivating puts them back into payroll and restores their login.</p>
        <form method="post" action="<?= e(url('/admin/employees/status')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <input type="hidden" name="action" value="reactivate">
            <button class="btn" type="submit">Reactivate employee</button>
        </form>
    <?php endif; ?>
</section>

<?php admin_footer(); ?>
