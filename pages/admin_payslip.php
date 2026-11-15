<?php
$user = require_role('admin');

require_once ROOT . '/includes/payroll.php';

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    not_found();
}

$stmt = db()->prepare(
    'SELECT p.id, p.run_id, p.pay_type, p.rate, p.units_worked, p.basic_pay, p.gross_pay,
            p.total_deductions, p.advance_repayment, p.net_pay,
            p.payment_status, p.payment_method, p.paid_at, p.calculated_at,
            r.period_year, r.period_month, r.status AS run_status,
            e.employee_number, e.first_name, e.last_name, e.job_title
     FROM payslips p
     JOIN payroll_runs r ON r.id = p.run_id
     JOIN employees e ON e.id = p.employee_id
     WHERE p.id = :id'
);
$stmt->execute(['id' => $id]);
$slip = $stmt->fetch();
if (!$slip) {
    not_found();
}

$label     = period_label((int)$slip['period_year'], (int)$slip['period_month']);
$name      = $slip['first_name'] . ' ' . $slip['last_name'];
$finalized = $slip['run_status'] === 'finalized';
$backUrl   = url('/admin/payroll/run') . '?id=' . (int)$slip['run_id'];

admin_header('Payslip', 'payroll');

// A payslip that has not been calculated has nothing to show yet
if ($slip['calculated_at'] === null) {
    ?>
    <h1>Payslip</h1>
    <p class="card">
        This payslip for <?= e($name) ?> has not been calculated yet.
        <a href="<?= e($backUrl) ?>">Back to the payroll run</a>
    </p>
    <?php
    admin_footer();
    exit;
}

// The lines saved on this payslip (allowances first, then deductions)
$stmt = db()->prepare(
    'SELECT label, item_type, amount FROM payslip_items
     WHERE payslip_id = :id ORDER BY item_type, label'
);
$stmt->execute(['id' => $id]);
$allowances = [];
$deductions = [];
foreach ($stmt->fetchAll() as $item) {
    if ($item['item_type'] === 'allowance') {
        $allowances[] = $item;
    } else {
        $deductions[] = $item;
    }
}

// How the basic pay was worked out
$units = $slip['units_worked'] === null ? '' : rtrim(rtrim($slip['units_worked'], '0'), '.');
if ($slip['pay_type'] === 'monthly') {
    $basicNote = to_cents((string)$slip['basic_pay']) !== to_cents((string)$slip['rate'])
        ? 'Monthly salary (part month)'
        : 'Monthly salary';
} else {
    $unitName  = $slip['pay_type'] === 'daily' ? 'days' : 'hours';
    $basicNote = $units . ' ' . $unitName . ' × ' . money($slip['rate']);
}

$methods = ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'bank' => 'Bank'];
$paid    = $slip['payment_status'] === 'paid';
?>
<div class="page-head no-print">
    <h1>Payslip</h1>
    <div class="row-actions">
        <a class="btn btn-secondary" href="<?= e($backUrl) ?>">Back to payroll</a>
        <button class="btn" type="button" data-print>Print payslip</button>
    </div>
</div>

<?php if (!$finalized): ?>
    <p class="notice no-print">
        This payroll is still a draft, so these amounts may change. Finalize the payroll to make them permanent.
    </p>
<?php endif; ?>

<article class="payslip card">
    <header class="payslip-head">
        <div>
            <strong class="payslip-brand">SmallPay</strong>
            <div>Payslip for <?= e($label) ?></div>
        </div>
        <span class="chip <?= $finalized ? 'chip-green' : 'chip-amber' ?>">
            <?= $finalized ? 'Final' : 'Draft' ?>
        </span>
    </header>

    <dl class="payslip-meta">
        <dt>Employee</dt><dd><?= e($name) ?></dd>
        <dt>Employee number</dt><dd><?= e($slip['employee_number']) ?></dd>
        <dt>Job title</dt><dd><?= e($slip['job_title'] ?? '-') ?></dd>
        <dt>Pay type</dt><dd><?= e(ucfirst($slip['pay_type'])) ?>, rate <?= e(money($slip['rate'])) ?></dd>
    </dl>

    <div class="table-wrap">
        <table class="table payslip-table">
            <thead>
                <tr><th>Description</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Basic pay <span class="muted">(<?= e($basicNote) ?>)</span></td>
                    <td class="num"><?= e(money($slip['basic_pay'])) ?></td>
                </tr>

                <?php if ($allowances): ?>
                    <tr class="section"><th colspan="2">Allowances</th></tr>
                    <?php foreach ($allowances as $item): ?>
                        <tr>
                            <td><?= e($item['label']) ?></td>
                            <td class="num">+<?= e(money($item['amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <tr class="total">
                    <td>Gross pay</td>
                    <td class="num"><?= e(money($slip['gross_pay'])) ?></td>
                </tr>

                <?php if ($deductions || to_cents((string)$slip['advance_repayment']) > 0): ?>
                    <tr class="section"><th colspan="2">Deductions</th></tr>
                    <?php foreach ($deductions as $item): ?>
                        <tr>
                            <td><?= e($item['label']) ?></td>
                            <td class="num">−<?= e(money($item['amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (to_cents((string)$slip['advance_repayment']) > 0): ?>
                        <tr>
                            <td>Salary advance repayment</td>
                            <td class="num">−<?= e(money($slip['advance_repayment'])) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>

                <tr class="total net">
                    <td>Net pay</td>
                    <td class="num"><?= e(money($slip['net_pay'])) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <p class="payslip-foot">
        Payment:
        <?php if ($paid): ?>
            Paid<?= $slip['payment_method'] ? ' by ' . e($methods[$slip['payment_method']] ?? $slip['payment_method']) : '' ?>
            <?= $slip['paid_at'] ? ' on ' . e(substr($slip['paid_at'], 0, 10)) : '' ?>
        <?php else: ?>
            Not yet paid
        <?php endif; ?>
        <br>Printed on <?= e(date('j F Y')) ?>
    </p>
</article>
<?php admin_footer(); ?>
