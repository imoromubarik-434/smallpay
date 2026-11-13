<?php
$user = require_role('admin');

// No user input in this query, so no parameters are needed
$rules = db()->query(
    'SELECT r.id, r.name, r.rule_type, r.method, r.value, r.percentage_base,
            r.applies_to_all, r.is_active, COUNT(er.employee_id) AS assigned
     FROM deduction_rules r
     LEFT JOIN employee_rules er ON er.rule_id = r.id
     GROUP BY r.id
     ORDER BY r.rule_type, r.name'
)->fetchAll();

admin_header('Pay rules', 'rules');
?>
<div class="page-head">
    <div>
        <h1>Pay rules</h1>
        <p>Allowances add to pay. Deductions are taken from it.</p>
    </div>
</div>

<p class="hint">
    Switching a rule off affects future payroll only. Payslips that are already finalized never change.
</p>

<?php if (!$rules): ?>
    <p class="card">No pay rules yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Rule</th><th>Type</th><th>Amount</th><th>Applies to</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $rule): ?>
                    <?php $on = in_array($rule['is_active'], [true, 't', 1, '1'], true); ?>
                    <tr>
                        <td><?= e($rule['name']) ?></td>
                        <td><?= e(ucfirst($rule['rule_type'])) ?></td>
                        <td>
                            <?php if ($rule['method'] === 'fixed'): ?>
                                <?= e(money($rule['value'])) ?> fixed
                            <?php else: ?>
                                <?= e(rtrim(rtrim($rule['value'], '0'), '.')) ?>% of <?= e($rule['percentage_base']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (in_array($rule['applies_to_all'], [true, 't', 1, '1'], true)): ?>
                                Everyone
                            <?php else: ?>
                                <?= (int)$rule['assigned'] ?> employee<?= (int)$rule['assigned'] === 1 ? '' : 's' ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="chip <?= $on ? 'chip-green' : 'chip-gray' ?>">
                                <?= $on ? 'On' : 'Off' ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" action="<?= e(url('/admin/rules/toggle')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                                <input type="hidden" name="action" value="<?= $on ? 'off' : 'on' ?>">
                                <button type="submit" class="link-button table-action"
                                        aria-label="Turn <?= $on ? 'off' : 'on' ?> <?= e($rule['name']) ?>">
                                    Turn <?= $on ? 'off' : 'on' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php admin_footer(); ?>
