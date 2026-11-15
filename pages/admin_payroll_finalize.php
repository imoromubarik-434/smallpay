<?php
$user = require_role('admin');

// This page only accepts form submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/payroll');
}
csrf_verify();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    not_found();
}

require_once ROOT . '/includes/payroll.php';

$back = '/admin/payroll/run?id=' . $id;
$pdo  = db();
$pdo->beginTransaction();

// Stop with a message; the transaction is rolled back first so nothing is saved
$fail = static function (string $message) use ($pdo, $back): never {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $message);
    redirect($back);
};

try {
    // Lock the run so nothing else can change it while we check and finalize
    $stmt = $pdo->prepare('SELECT period_year, period_month, status FROM payroll_runs WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $run = $stmt->fetch();

    if (!$run) {
        $pdo->rollBack();
        not_found();
    }
    if ($run['status'] !== 'draft') {
        $fail('Only a draft run can be finalized.');
    }

    $year  = (int)$run['period_year'];
    $month = (int)$run['period_month'];
    $label = period_label($year, $month);

    // Recalculate with today's rules, to compare with what was saved
    $check = compute_run($pdo, $id, $year, $month);

    if ($check['count'] === 0) {
        $fail('This run has no payslips.');
    }
    if ($check['problems']) {
        $fail('Cannot finalize. ' . implode(' ', array_slice($check['problems'], 0, 3)));
    }

    $stmt = $pdo->prepare(
        'SELECT id, basic_pay, gross_pay, total_deductions, net_pay, calculated_at
         FROM payslips WHERE run_id = :run'
    );
    $stmt->execute(['run' => $id]);

    $notCalculated = 0;
    $changed       = 0;
    $totalNet      = 0;

    foreach ($stmt->fetchAll() as $row) {
        if ($row['calculated_at'] === null) {
            $notCalculated++;
            continue;
        }

        $now = $check['results'][(int)$row['id']] ?? null;
        if (
            $now === null
            || to_cents((string)$row['basic_pay'])        !== $now['basic']
            || to_cents((string)$row['gross_pay'])        !== $now['gross']
            || to_cents((string)$row['total_deductions']) !== $now['deductions']
            || to_cents((string)$row['net_pay'])          !== $now['net']
        ) {
            $changed++;
        }
        $totalNet += to_cents((string)$row['net_pay']);
    }

    if ($notCalculated > 0) {
        $fail('Calculate the payroll first: ' . $notCalculated . ' payslip'
            . ($notCalculated === 1 ? ' has' : 's have') . ' not been calculated yet.');
    }
    if ($changed > 0) {
        $fail('Pay rules or hire dates have changed since you calculated, so the amounts on screen are '
            . 'out of date. Calculate again, check the amounts, then finalize.');
    }

    $stmt = $pdo->prepare(
        "UPDATE payroll_runs SET status = 'finalized', finalized_at = NOW()
         WHERE id = :id AND status = 'draft'"
    );
    $stmt->execute(['id' => $id]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

audit($user['id'], 'payroll_finalized', 'payroll_run', $id, [
    'period'    => $label,
    'employees' => $check['count'],
    'net_total' => from_cents($totalNet),
]);
flash('success', 'Payroll for ' . $label . ' is finalized and locked. Net total: '
    . money(from_cents($totalNet)) . '.');
redirect($back);
