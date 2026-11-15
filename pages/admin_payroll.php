<?php
$user = require_role('admin');

// No user input in this query, so no parameters are needed
$runs = db()->query(
    'SELECT r.id, r.period_year, r.period_month, r.status,
            COUNT(p.id) AS staff, COALESCE(SUM(p.net_pay), 0) AS net_total
     FROM payroll_runs r
     LEFT JOIN payslips p ON p.run_id = r.id
     GROUP BY r.id
     ORDER BY r.period_year DESC, r.period_month DESC'
)->fetchAll();

// Choices for the "start a run" form
$thisYear = (int)date('Y');
$months   = [];
for ($m = 1; $m <= 12; $m++) {
    $months[$m] = date('F', mktime(0, 0, 0, $m, 1));
}
$years = [$thisYear - 1 => (string)($thisYear - 1), $thisYear => (string)$thisYear];

admin_header('Payroll runs', 'payroll');
?>
<div class="page-head">
    <div>
        <h1>Payroll runs</h1>
        <p>One run for each month.</p>
    </div>
</div>

<section class="card">
    <h2>Start a new payroll run</h2>
    <form class="toolbar" method="post" action="<?= e(url('/admin/payroll/start')) ?>" data-validate novalidate>
        <?= csrf_field() ?>
        <?php
        select_field('month', 'Month', $months, date('n'), [], 'required');
        select_field('year', 'Year', $years, (string)$thisYear, [], 'required');
        ?>
        <button class="btn" type="submit">Start run</button>
    </form>
    <p class="hint">
        A draft payslip is created for every active employee who has a pay rate for that month.
        Nothing is final until you finalize the run.
    </p>
</section>

<?php if (!$runs): ?>
    <p class="card">No payroll runs yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Period</th><th>Employees</th><th>Net total</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($runs as $run): ?>
                    <?php $final = $run['status'] === 'finalized'; ?>
                    <?php $runLabel = period_label((int)$run['period_year'], (int)$run['period_month']); ?>
                    <tr>
                        <td><?= e($runLabel) ?></td>
                        <td><?= (int)$run['staff'] ?></td>
                        <td><?= $final ? e(money($run['net_total'])) : 'Not calculated' ?></td>
                        <td>
                            <span class="chip <?= $final ? 'chip-green' : 'chip-amber' ?>">
                                <?= $final ? 'Finalized' : 'Draft' ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= e(url('/admin/payroll/run') . '?id=' . (int)$run['id']) ?>"
                               aria-label="<?= $final ? 'View' : 'Review' ?> payroll for <?= e($runLabel) ?>">
                                <?= $final ? 'View' : 'Review' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php admin_footer(); ?>
