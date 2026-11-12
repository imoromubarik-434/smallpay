<?php
$user = require_role('admin');

// These queries contain no user input, so no parameters are needed
$count = static fn (string $sql): int => (int)db()->query($sql)->fetchColumn();

$activeEmployees = $count("SELECT COUNT(*) FROM employees WHERE status = 'active'");
$pendingAdvances = $count("SELECT COUNT(*) FROM advances WHERE status = 'pending'");
$unpaidPayslips  = $count(
    "SELECT COUNT(*) FROM payslips p
     JOIN payroll_runs r ON r.id = p.run_id
     WHERE r.status = 'finalized' AND p.payment_status = 'unpaid'"
);

$last = db()->query(
    "SELECT r.period_year, r.period_month, COALESCE(SUM(p.net_pay), 0) AS net_total
     FROM payroll_runs r
     LEFT JOIN payslips p ON p.run_id = r.id
     WHERE r.status = 'finalized'
     GROUP BY r.id
     ORDER BY r.period_year DESC, r.period_month DESC
     LIMIT 1"
)->fetch();

admin_header('Dashboard', 'dashboard');
?>
<h1>Admin dashboard</h1>
<p>Signed in as <?= e($user['email']) ?></p>

<div class="stats">
    <div class="stat">
        <div class="stat-label">Active employees</div>
        <div class="stat-value"><?= $activeEmployees ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Last payroll (net)</div>
        <div class="stat-value"><?= $last ? e(money($last['net_total'])) : 'None yet' ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Pending advances</div>
        <div class="stat-value"><?= $pendingAdvances ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Unpaid payslips</div>
        <div class="stat-value"><?= $unpaidPayslips ?></div>
    </div>
</div>
<?php admin_footer(); ?>
