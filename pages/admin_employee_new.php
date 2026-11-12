<?php
$user = require_role('admin');

$types  = ['monthly' => 'Monthly salary', 'daily' => 'Daily rate', 'hourly' => 'Hourly rate'];
$errors = [];
$old    = [
    'employee_number' => '',
    'first_name'      => '',
    'last_name'       => '',
    'job_title'       => '',
    'phone'           => '',
    'hire_date'       => date('Y-m-d'),
    'pay_type'        => '',
    'rate'            => '',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Suggest the next employee number, e.g. SP-006
    $next = (int)db()->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(employee_number FROM '^SP-([0-9]+)$') AS INTEGER)), 0) + 1
         FROM employees"
    )->fetchColumn();
    $old['employee_number'] = sprintf('SP-%03d', $next);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach ($old as $key => $default) {
        $value     = $_POST[$key] ?? '';
        $old[$key] = is_string($value) ? trim($value) : '';
    }
    $old['employee_number'] = strtoupper($old['employee_number']);

    // ---- Server-side validation (the browser checks can be bypassed) ----
    if (!preg_match('/^[A-Z0-9\-]{3,20}$/', $old['employee_number'])) {
        $errors['employee_number'] = 'Use 3 to 20 letters, numbers or hyphens.';
    }
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

    $hire   = DateTime::createFromFormat('!Y-m-d', $old['hire_date']);
    $dateOk = $hire && $hire->format('Y-m-d') === $old['hire_date'];
    if (!$dateOk) {
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

    // ---- Save: the employee and the pay rate together, or nothing ----
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO employees (employee_number, first_name, last_name, phone, job_title, hire_date)
                 VALUES (:number, :first, :last, :phone, :title, :hired)
                 RETURNING id'
            );
            $stmt->execute([
                'number' => $old['employee_number'],
                'first'  => $old['first_name'],
                'last'   => $old['last_name'],
                'phone'  => $old['phone'] !== '' ? $old['phone'] : null,
                'title'  => $old['job_title'] !== '' ? $old['job_title'] : null,
                'hired'  => $old['hire_date'],
            ]);
            $employeeId = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO pay_structures (employee_id, pay_type, rate, effective_from)
                 VALUES (:employee, :type, :rate, :from)'
            );
            $stmt->execute([
                'employee' => $employeeId,
                'type'     => $old['pay_type'],
                'rate'     => $old['rate'],
                'from'     => $old['hire_date'],
            ]);

            $pdo->commit();

            audit($user['id'], 'employee_created', 'employee', $employeeId, [
                'employee_number' => $old['employee_number'],
            ]);
            flash('success', 'Employee ' . $old['first_name'] . ' ' . $old['last_name'] . ' was added.');
            redirect('/admin/employees');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23505') {   // unique violation
                $errors['employee_number'] = 'That employee number is already in use.';
            } else {
                throw $e;
            }
        }
    }
}

admin_header('Add employee', 'employees');
?>
<div class="page-head">
    <h1>Add employee</h1>
</div>

<form class="card" method="post" action="<?= e(url('/admin/employees/new')) ?>" data-validate novalidate>
    <?= csrf_field() ?>

    <div class="form-grid">
        <div class="form-group">
            <label for="employee_number">Employee number</label>
            <input type="text" id="employee_number" name="employee_number"
                   value="<?= e($old['employee_number']) ?>" maxlength="20"
                   pattern="[A-Za-z0-9\-]{3,20}" title="3 to 20 letters, numbers or hyphens"
                   required<?= invalid_attr($errors, 'employee_number') ?>>
            <p class="field-error" aria-live="polite"><?= e($errors['employee_number'] ?? '') ?></p>
        </div>

        <div class="form-group">
            <label for="hire_date">Hire date</label>
            <input type="date" id="hire_date" name="hire_date"
                   value="<?= e($old['hire_date']) ?>" required<?= invalid_attr($errors, 'hire_date') ?>>
            <p class="field-error" aria-live="polite"><?= e($errors['hire_date'] ?? '') ?></p>
        </div>

        <div class="form-group">
            <label for="first_name">First name</label>
            <input type="text" id="first_name" name="first_name"
                   value="<?= e($old['first_name']) ?>" maxlength="80"
                   required<?= invalid_attr($errors, 'first_name') ?>>
            <p class="field-error" aria-live="polite"><?= e($errors['first_name'] ?? '') ?></p>
        </div>

        <div class="form-group">
            <label for="last_name">Last name</label>
            <input type="text" id="last_name" name="last_name"
                   value="<?= e($old['last_name']) ?>" maxlength="80"
                   required<?= invalid_attr($errors, 'last_name') ?>>
            <p class="field-error" aria-live="polite"><?= e($errors['last_name'] ?? '') ?></p>
        </div>

        <div class="form-group">
            <label for="job_title">Job title (optional)</label>
            <input type="text" id="job_title" name="job_title"
                   value="<?= e($old['job_title']) ?>" maxlength="100"<?= invalid_attr($errors, 'job_title') ?>>
            <p class="field-error" aria-live="polite"><?= e($errors['job_title'] ?? '') ?></p>
        </div>

        <div class="form-group">
            <label for="phone">Phone (optional)</label>
            <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" maxlength="30"
                   pattern="[0-9+\-\s\(\)]{5,30}" title="Digits, spaces, + - ( ) only"<?= invalid_attr($errors, 'phone') ?>>
            <p class="field-error" aria-live="polite"><?= e($errors['phone'] ?? '') ?></p>
        </div>

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

        <div class="form-group">
            <label for="rate">Rate (per month, day or hour)</label>
            <input type="text" id="rate" name="rate" value="<?= e($old['rate']) ?>" inputmode="decimal"
                   pattern="\d{1,10}(\.\d{1,2})?" title="A number with up to 2 decimals, for example 80 or 12.50"
                   required<?= invalid_attr($errors, 'rate') ?>>
            <p class="field-error" aria-live="polite"><?= e($errors['rate'] ?? '') ?></p>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn" type="submit">Save employee</button>
        <a class="btn btn-secondary" href="<?= e(url('/admin/employees')) ?>">Cancel</a>
    </div>
</form>
<?php admin_footer(); ?>
