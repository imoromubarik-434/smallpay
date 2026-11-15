<?php
$user = require_role('admin');

// This page only accepts form submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/payroll');
}
csrf_verify();

$year  = filter_var($_POST['year'] ?? '', FILTER_VALIDATE_INT);
$month = filter_var($_POST['month'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);

$today = new DateTime('today');

if ($year === false || $month === false || $year < (int)$today->format('Y') - 2) {
    flash('error', 'Choose a valid month and year.');
    redirect('/admin/payroll');
}

$first = new DateTime(sprintf('%04d-%02d-01', $year, $month));
if ($first > $today) {
    flash('error', 'You cannot run payroll for a month that has not started yet.');
    redirect('/admin/payroll');
}
$end   = $first->format('Y-m-t');   // last day of that month
$label = period_label($year, $month);

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'INSERT INTO payroll_runs (period_year, period_month, created_by)
         VALUES (:year, :month, :user)
         RETURNING id'
    );
    $stmt->execute(['year' => $year, 'month' => $month, 'user' => $user['id']]);
    $runId = (int)$stmt->fetchColumn();

    // One draft payslip per active employee, with the pay rate in force at month end
    $stmt = $pdo->prepare(
        'INSERT INTO payslips (run_id, employee_id, pay_type, rate)
         SELECT CAST(:run AS BIGINT), e.id, ps.pay_type, ps.rate
         FROM employees e
         JOIN LATERAL (
             SELECT pay_type, rate
             FROM pay_structures
             WHERE employee_id = e.id AND effective_from <= CAST(:end1 AS DATE)
             ORDER BY effective_from DESC
             LIMIT 1
         ) ps ON TRUE
         WHERE e.status = \'active\' AND e.hire_date <= CAST(:end2 AS DATE)'
    );
    $stmt->execute(['run' => $runId, 'end1' => $end, 'end2' => $end]);
    $created = $stmt->rowCount();

    if ($created === 0) {
        $pdo->rollBack();
        flash('error', 'No active employees with a pay rate were found for ' . $label . '.');
        redirect('/admin/payroll');
    }

    // Active employees hired by month end who were left out (no pay rate yet)
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM employees
         WHERE status = \'active\' AND hire_date <= CAST(:end AS DATE)'
    );
    $stmt->execute(['end' => $end]);
    $skipped = (int)$stmt->fetchColumn() - $created;

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23505') {   // unique violation: this month already has a run
        flash('error', 'A payroll run for ' . $label . ' already exists.');
        redirect('/admin/payroll');
    }
    throw $e;
}

audit($user['id'], 'payroll_started', 'payroll_run', $runId, ['period' => $label, 'employees' => $created]);

$message = 'Draft payroll for ' . $label . ' was created for ' . $created
         . ' employee' . ($created === 1 ? '' : 's') . '.';
if ($skipped > 0) {
    $message .= ' ' . $skipped . ' active employee' . ($skipped === 1 ? ' has' : 's have')
              . ' no pay rate for that month and ' . ($skipped === 1 ? 'was' : 'were') . ' left out.';
}
flash('success', $message);
redirect('/admin/payroll');
