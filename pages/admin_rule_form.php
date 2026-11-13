<?php
$user = require_role('admin');

// $path is set by the router: the same page serves "new" and "edit"
$isEdit = $path === '/admin/rules/edit';
$id     = null;

$ruleTypes = ['allowance' => 'Allowance (adds to pay)', 'deduction' => 'Deduction (taken from pay)'];
$methods   = ['fixed' => 'Fixed amount', 'percentage' => 'Percentage'];
$bases     = ['basic' => 'Basic pay', 'gross' => 'Gross pay'];

// Defaults for a new rule
$old = [
    'name'      => '',
    'rule_type' => 'deduction',
    'method'    => 'fixed',
    'value'     => '',
    'base'      => 'basic',
    'applies'   => 'all',
    'is_active' => true,
    'employees' => [],
];

// All employees, for the "selected employees" list
$employees = db()->query(
    'SELECT id, employee_number, first_name, last_name, status
     FROM employees ORDER BY employee_number'
)->fetchAll();
$validIds = [];
foreach ($employees as $emp) {
    $validIds[(int)$emp['id']] = true;
}

if ($isEdit) {
    $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        not_found();
    }

    $stmt = db()->prepare(
        'SELECT id, name, rule_type, method, value, percentage_base, applies_to_all, is_active
         FROM deduction_rules WHERE id = :id'
    );
    $stmt->execute(['id' => $id]);
    $rule = $stmt->fetch();
    if (!$rule) {
        not_found();
    }

    $stmt = db()->prepare('SELECT employee_id FROM employee_rules WHERE rule_id = :id');
    $stmt->execute(['id' => $id]);

    $old = [
        'name'      => $rule['name'],
        'rule_type' => $rule['rule_type'],
        'method'    => $rule['method'],
        'value'     => $rule['value'],
        'base'      => $rule['percentage_base'] ?? 'basic',
        'applies'   => truthy($rule['applies_to_all']) ? 'all' : 'selected',
        'is_active' => truthy($rule['is_active']),
        'employees' => array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['name']      = trim((string)($_POST['name'] ?? ''));
    $old['rule_type'] = (string)($_POST['rule_type'] ?? '');
    $old['method']    = (string)($_POST['method'] ?? '');
    $old['value']     = trim((string)($_POST['value'] ?? ''));
    $old['base']      = (string)($_POST['percentage_base'] ?? '');
    $old['applies']   = (string)($_POST['applies'] ?? '');
    $old['is_active'] = isset($_POST['is_active']);

    // Keep only employee ids that really exist
    $old['employees'] = [];
    $picked = $_POST['employees'] ?? [];
    if (is_array($picked)) {
        foreach ($picked as $p) {
            if (is_string($p) && ctype_digit($p) && isset($validIds[(int)$p])) {
                $old['employees'][(int)$p] = (int)$p;
            }
        }
        $old['employees'] = array_values($old['employees']);
    }

    // ---- Server-side validation ----
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors['name'] = 'Enter a rule name (up to 100 characters).';
    }
    if (!isset($ruleTypes[$old['rule_type']])) {
        $errors['rule_type'] = 'Choose a rule type.';
    }
    if (!isset($methods[$old['method']])) {
        $errors['method'] = 'Choose fixed amount or percentage.';
    }

    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $old['value']) || (float)$old['value'] <= 0) {
        $errors['value'] = 'Enter a number above 0, with up to 2 decimals and no commas.';
    } elseif ($old['method'] === 'percentage' && (float)$old['value'] > 100) {
        $errors['value'] = 'A percentage cannot be more than 100.';
    }

    if ($old['method'] === 'percentage') {
        if (!isset($bases[$old['base']])) {
            $errors['percentage_base'] = 'Choose basic pay or gross pay.';
        } elseif ($old['rule_type'] === 'allowance' && $old['base'] === 'gross') {
            $errors['percentage_base'] = 'An allowance can only use basic pay, because gross pay already includes allowances.';
        }
    }

    if (!in_array($old['applies'], ['all', 'selected'], true)) {
        $errors['applies'] = 'Choose who the rule applies to.';
    } elseif ($old['applies'] === 'selected' && !$old['employees']) {
        $errors['employees'] = 'Select at least one employee.';
    }

    // ---- Save the rule and its employee list together, or nothing ----
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $params = [
                'name'   => $old['name'],
                'type'   => $old['rule_type'],
                'method' => $old['method'],
                'value'  => $old['value'],
                'base'   => $old['method'] === 'percentage' ? $old['base'] : null,
                'all'    => $old['applies'] === 'all' ? 'true' : 'false',
                'active' => $old['is_active'] ? 'true' : 'false',
            ];

            if ($isEdit) {
                $stmt = $pdo->prepare(
                    'UPDATE deduction_rules
                     SET name = :name, rule_type = :type, method = :method, value = :value,
                         percentage_base = :base, applies_to_all = :all, is_active = :active
                     WHERE id = :id'
                );
                $stmt->execute($params + ['id' => $id]);
                $ruleId = $id;
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO deduction_rules
                         (name, rule_type, method, value, percentage_base, applies_to_all, is_active)
                     VALUES (:name, :type, :method, :value, :base, :all, :active)
                     RETURNING id'
                );
                $stmt->execute($params);
                $ruleId = (int)$stmt->fetchColumn();
            }

            // Replace the employee list
            $stmt = $pdo->prepare('DELETE FROM employee_rules WHERE rule_id = :id');
            $stmt->execute(['id' => $ruleId]);

            if ($old['applies'] === 'selected') {
                $stmt = $pdo->prepare('INSERT INTO employee_rules (employee_id, rule_id) VALUES (:emp, :rule)');
                foreach ($old['employees'] as $employeeId) {
                    $stmt->execute(['emp' => $employeeId, 'rule' => $ruleId]);
                }
            }

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23505') {   // unique violation
                $errors['name'] = 'A rule with that name already exists.';
            } else {
                throw $e;
            }
        }

        if (!$errors) {
            audit($user['id'], $isEdit ? 'rule_updated' : 'rule_created', 'rule', $ruleId, ['name' => $old['name']]);
            flash('success', 'Rule "' . $old['name'] . '" was saved.');
            redirect('/admin/rules');
        }
    }
}

admin_header($isEdit ? 'Edit rule' : 'Add rule', 'rules');
$formUrl = $isEdit ? url('/admin/rules/edit') . '?id=' . $id : url('/admin/rules/new');
?>
<div class="page-head">
    <h1><?= $isEdit ? 'Edit rule' : 'Add rule' ?></h1>
</div>
<p class="hint">Changes apply to future payroll only. Payslips that are already finalized never change.</p>

<form class="card" method="post" action="<?= e($formUrl) ?>" data-validate novalidate>
    <?= csrf_field() ?>

    <div class="form-grid">
        <?php
        field('name', 'Rule name', $old['name'], $errors, 'text', 'maxlength="100" required');
        select_field('rule_type', 'Type', $ruleTypes, $old['rule_type'], $errors);
        select_field('method', 'How it is calculated', $methods, $old['method'], $errors);
        field('value', 'Amount, or percentage', $old['value'], $errors, 'text',
            'inputmode="decimal" pattern="\d{1,10}(\.\d{1,2})?" title="A number with up to 2 decimals, for example 150 or 5.5" required');
        ?>
        <div data-show-if="method=percentage">
            <?php select_field('percentage_base', 'Percentage of', $bases, $old['base'], $errors); ?>
        </div>
    </div>

    <fieldset>
        <legend>Applies to</legend>
        <label class="choice">
            <input type="radio" name="applies" value="all"<?= $old['applies'] === 'all' ? ' checked' : '' ?>>
            Everyone
        </label>
        <label class="choice">
            <input type="radio" name="applies" value="selected"<?= $old['applies'] === 'selected' ? ' checked' : '' ?>>
            Selected employees only
        </label>
        <p class="field-error" aria-live="polite"><?= e($errors['applies'] ?? '') ?></p>
    </fieldset>

    <div data-show-if="applies=selected">
        <div class="choice-list">
            <?php foreach ($employees as $emp): ?>
                <label class="choice">
                    <input type="checkbox" name="employees[]" value="<?= (int)$emp['id'] ?>"
                        <?= in_array((int)$emp['id'], $old['employees'], true) ? ' checked' : '' ?>>
                    <?= e($emp['employee_number'] . ' ' . $emp['first_name'] . ' ' . $emp['last_name']) ?>
                    <?= $emp['status'] === 'inactive' ? ' (inactive)' : '' ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="field-error" aria-live="polite"><?= e($errors['employees'] ?? '') ?></p>
    </div>

    <label class="choice">
        <input type="checkbox" name="is_active" value="1"<?= $old['is_active'] ? ' checked' : '' ?>>
        Rule is on
    </label>

    <div class="form-actions">
        <button class="btn" type="submit">Save rule</button>
        <a class="btn btn-secondary" href="<?= e(url('/admin/rules')) ?>">Cancel</a>
    </div>
</form>
<?php admin_footer(); ?>
