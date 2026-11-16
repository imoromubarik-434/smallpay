<?php
$user = require_role('admin');

require_once ROOT . '/includes/payroll.php';   // to_cents() and from_cents()

// Years that have at least one finalized run
$years = array_map('intval', db()->query(
    "SELECT DISTINCT period_year FROM payroll_runs WHERE status = 'finalized' ORDER BY period_year DESC"
)->fetchAll(PDO::FETCH_COLUMN));

// The year comes from the URL and must be one of those years
$year = filter_var($_GET['year'] ?? '', FILTER_VALIDATE_INT);
if ($year === false || !in_array($year, $years, true)) {
    $year = $years[0] ?? (int)date('Y');
}

// One row per finalized month
$stmt = db()->prepare(
    "SELECT r.id, r.period_month,
            COUNT(p.id) AS staff,
            COALESCE(SUM(p.gross_pay), 0) AS gross,
            COALESCE(SUM(p.total_deductions), 0) AS deductions,
            COALESCE(SUM(p.advance_repayment), 0) AS advances,
            COALESCE(SUM(p.net_pay), 0) AS net,
            COALESCE(SUM(CASE WHEN p.payment_status = 'paid' THEN p.net_pay ELSE 0 END), 0) AS paid
     FROM payroll_runs r
     JOIN payslips p ON p.run_id = r.id
     WHERE r.status = 'finalized' AND r.period_year = :year
     GROUP BY r.id
     ORDER BY r.period_month"
);
$stmt->execute(['year' => $year]);
$runs = $stmt->fetchAll();

// Totals for the year, added up in whole cents
$total = ['gross' => 0, 'deductions' => 0, 'advances' => 0, 'net' => 0, 'paid' => 0];
foreach ($runs as $r) {
    foreach ($total as $key => $sum) {
        $total[$key] += to_cents((string)$r[$key]);
    }
}
$unpaid = $total['net'] - $total['paid'];

// What was added and taken off, rule by rule
$stmt = db()->prepare(
    "SELECT i.label, i.item_type, SUM(i.amount) AS amount
     FROM payslip_items i
     JOIN payslips p ON p.id = i.payslip_id
     JOIN payroll_runs r ON r.id = p.run_id
     WHERE r.status = 'finalized' AND r.period_year = :year
     GROUP BY i.label, i.item_type
     ORDER BY i.item_type, i.label"
);
$stmt->execute(['year' => $year]);
$byRule = $stmt->fetchAll();

$yearOptions = [];
foreach ($years as $y) {
    $yearOptions[$y] = (string)$y;
}

admin_header('Reports', 'reports');
?>
<div class="page-head">
    <div>
        <h1>Payroll reports</h1>
        <p>Finalized payrolls only.</p>
    </div>
</div>

<?php if (!$years): ?>
    <p class="card">No finalized payroll yet. Reports appear here once you finalize a run.</p>
<?php else: ?>
    <form class="toolbar" method="get" action="<?= e(url('/admin/reports')) ?>">
        <?php select_field('year', 'Year', $yearOptions, (string)$year, []); ?>
        <button class="btn" type="submit">Show</button>
    </form>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Months finalized in <?= (int)$year ?></div>
            <div class="stat-value"><?= count($runs) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Total net pay</div>
            <div class="stat-value"><?= e(money(from_cents($total['net']))) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Paid out</div>
            <div class="stat-value"><?= e(money(from_cents($total['paid']))) ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Still to pay</div>
            <div class="stat-value"><?= e(money(from_cents($unpaid))) ?></div>
        </div>
    </div>

    <h2>Month by month</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Month</th><th class="num">Employees</th><th class="num">Gross</th>
                    <th class="num">Deductions</th><th class="num">Advances repaid</th>
                    <th class="num">Net pay</th><th class="num">Paid</th><th class="num">Unpaid</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($runs as $r): ?>
                    <?php
                    $monthLabel  = period_label((int)$year, (int)$r['period_month']);
                    $runUnpaid   = to_cents((string)$r['net']) - to_cents((string)$r['paid']);
                    ?>
                    <tr>
                        <td><?= e($monthLabel) ?></td>
                        <td class="num"><?= (int)$r['staff'] ?></td>
                        <td class="num"><?= e(money($r['gross'])) ?></td>
                        <td class="num"><?= e(money($r['deductions'])) ?></td>
                        <td class="num"><?= e(money($r['advances'])) ?></td>
                        <td class="num"><strong><?= e(money($r['net'])) ?></strong></td>
                        <td class="num"><?= e(money($r['paid'])) ?></td>
                        <td class="num"><?= $runUnpaid > 0 ? e(money(from_cents($runUnpaid))) : '-' ?></td>
                        <td>
                            <a href="<?= e(url('/admin/payroll/run') . '?id=' . (int)$r['id']) ?>"
                               aria-label="Payroll details for <?= e($monthLabel) ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>Total <?= (int)$year ?></td>
                    <td></td>
                    <td class="num"><?= e(money(from_cents($total['gross']))) ?></td>
                    <td class="num"><?= e(money(from_cents($total['deductions']))) ?></td>
                    <td class="num"><?= e(money(from_cents($total['advances']))) ?></td>
                    <td class="num"><?= e(money(from_cents($total['net']))) ?></td>
                    <td class="num"><?= e(money(from_cents($total['paid']))) ?></td>
                    <td class="num"><?= $unpaid > 0 ? e(money(from_cents($unpaid))) : '-' ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <h2>Allowances and deductions in <?= (int)$year ?></h2>
    <?php if (!$byRule): ?>
        <p class="card">No allowances or deductions were applied.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Rule</th><th>Type</th><th class="num">Total</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($byRule as $row): ?>
                        <tr>
                            <td><?= e($row['label']) ?></td>
                            <td><?= e(ucfirst($row['item_type'])) ?></td>
                            <td class="num"><?= e(money($row['amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="hint">
            These are the amounts withheld from or added to pay. Use the deduction totals when you
            pay each one on to its recipient.
        </p>
    <?php endif; ?>
<?php endif; ?>
<?php admin_footer(); ?>
