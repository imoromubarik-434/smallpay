<?php
['user' => $user, 'employee' => $employee] = require_employee();

// Only this employee's payslips, and only from finalized runs
$stmt = db()->prepare(
    "SELECT p.id, p.gross_pay, p.net_pay, p.payment_status, r.period_year, r.period_month
     FROM payslips p
     JOIN payroll_runs r ON r.id = p.run_id
     WHERE p.employee_id = :emp AND r.status = 'finalized'
     ORDER BY r.period_year DESC, r.period_month DESC"
);
$stmt->execute(['emp' => $employee['id']]);
$slips = $stmt->fetchAll();

page_header('My payslips');
?>
<h1>My payslips</h1>
<p><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?> &middot; <?= e($employee['employee_number']) ?></p>

<?php if (!$slips): ?>
    <p class="card">No payslips yet. They appear here once your employer finalizes a payroll.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Period</th><th class="num">Gross</th><th class="num">Net</th>
                    <th>Payment</th><th>Payslip</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slips as $s): ?>
                    <?php $period = period_label((int)$s['period_year'], (int)$s['period_month']); ?>
                    <tr>
                        <td><?= e($period) ?></td>
                        <td class="num"><?= e(money($s['gross_pay'])) ?></td>
                        <td class="num"><strong><?= e(money($s['net_pay'])) ?></strong></td>
                        <td>
                            <span class="chip <?= $s['payment_status'] === 'paid' ? 'chip-green' : 'chip-amber' ?>">
                                <?= $s['payment_status'] === 'paid' ? 'Paid' : 'Unpaid' ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= e(url('/employee/payslip') . '?id=' . (int)$s['id']) ?>"
                               aria-label="Payslip for <?= e($period) ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php page_footer(); ?>
