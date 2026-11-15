<?php
['user' => $user, 'employee' => $employee] = require_employee();

// Latest finalized payslip of this employee
$stmt = db()->prepare(
    "SELECT p.id, p.net_pay, p.payment_status, r.period_year, r.period_month
     FROM payslips p
     JOIN payroll_runs r ON r.id = p.run_id
     WHERE p.employee_id = :emp AND r.status = 'finalized'
     ORDER BY r.period_year DESC, r.period_month DESC
     LIMIT 1"
);
$stmt->execute(['emp' => $employee['id']]);
$latest = $stmt->fetch();

page_header('My dashboard');
?>
<h1>Hello, <?= e($employee['first_name']) ?></h1>
<p><?= e($employee['employee_number']) ?></p>

<?php if ($latest): ?>
    <section class="card">
        <h2>Latest payslip: <?= e(period_label((int)$latest['period_year'], (int)$latest['period_month'])) ?></h2>
        <p>
            Net pay <strong><?= e(money($latest['net_pay'])) ?></strong>
            <span class="chip <?= $latest['payment_status'] === 'paid' ? 'chip-green' : 'chip-amber' ?>">
                <?= $latest['payment_status'] === 'paid' ? 'Paid' : 'Unpaid' ?>
            </span>
        </p>
        <div class="form-actions">
            <a class="btn" href="<?= e(url('/employee/payslip') . '?id=' . (int)$latest['id']) ?>">View payslip</a>
            <a class="btn btn-secondary" href="<?= e(url('/employee/payslips')) ?>">All payslips</a>
        </div>
    </section>
<?php else: ?>
    <p class="card">No payslips yet. They appear here once your employer finalizes a payroll.</p>
<?php endif; ?>
<?php page_footer(); ?>
