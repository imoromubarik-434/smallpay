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
    // Lock the run, so it cannot be finalized or discarded while we calculate
    $stmt = $pdo->prepare('SELECT period_year, period_month, status FROM payroll_runs WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $run = $stmt->fetch();

    if (!$run) {
        $pdo->rollBack();
        not_found();
    }
    if ($run['status'] !== 'draft') {
        $fail('Only a draft run can be calculated.');
    }

    $year  = (int)$run['period_year'];
    $month = (int)$run['period_month'];
    $label = period_label($year, $month);

    $stmt = $pdo->prepare(
        'SELECT p.id, p.employee_id, p.pay_type, p.rate, p.units_worked,
                e.first_name, e.last_name, e.hire_date
         FROM payslips p
         JOIN employees e ON e.id = p.employee_id
         WHERE p.run_id = :run
         ORDER BY e.employee_number'
    );
    $stmt->execute(['run' => $id]);
    $slips = $stmt->fetchAll();

    if (!$slips) {
        $fail('This run has no payslips.');
    }

    // Rules that are switched on, and which employees the selective ones cover
    $rules = $pdo->query(
        'SELECT id, name, rule_type, method, value, percentage_base, applies_to_all
         FROM deduction_rules WHERE is_active ORDER BY rule_type, name'
    )->fetchAll();

    $assigned = [];
    foreach ($pdo->query('SELECT rule_id, employee_id FROM employee_rules')->fetchAll() as $row) {
        $assigned[(int)$row['rule_id']][(int)$row['employee_id']] = true;
    }

    // Work everything out first; write only if every payslip is fine
    $results  = [];
    $problems = [];
    foreach ($slips as $slip) {
        $result = calculate_payslip($slip, $rules, $assigned, $year, $month);
        if (isset($result['error'])) {
            $problems[] = $result['error'];
        } else {
            $results[(int)$slip['id']] = $result;
        }
    }

    if ($problems) {
        $shown = array_slice($problems, 0, 5);
        $more  = count($problems) - count($shown);
        $fail('Nothing was calculated. ' . implode(' ', $shown) . ($more > 0 ? ' And ' . $more . ' more.' : ''));
    }

    // ---- Write the results ----
    $clear  = $pdo->prepare('DELETE FROM payslip_items WHERE payslip_id = :id');
    $insert = $pdo->prepare(
        'INSERT INTO payslip_items (payslip_id, rule_id, label, item_type, amount)
         VALUES (:payslip, :rule, :label, :type, :amount)'
    );
    $update = $pdo->prepare(
        'UPDATE payslips
         SET basic_pay = :basic, gross_pay = :gross, total_deductions = :deductions,
             advance_repayment = 0, net_pay = :net, calculated_at = NOW()
         WHERE id = :id'
    );

    $totalNet = 0;
    foreach ($results as $payslipId => $r) {
        $clear->execute(['id' => $payslipId]);

        foreach ($r['items'] as $item) {
            $insert->execute([
                'payslip' => $payslipId,
                'rule'    => $item['rule_id'],
                'label'   => $item['label'],
                'type'    => $item['type'],
                'amount'  => from_cents($item['amount']),
            ]);
        }

        $update->execute([
            'basic'      => from_cents($r['basic']),
            'gross'      => from_cents($r['gross']),
            'deductions' => from_cents($r['deductions']),
            'net'        => from_cents($r['net']),
            'id'         => $payslipId,
        ]);
        $totalNet += $r['net'];
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

audit($user['id'], 'payroll_calculated', 'payroll_run', $id, [
    'period'    => $label,
    'employees' => count($results),
    'net_total' => from_cents($totalNet),
]);
flash('success', 'Payroll for ' . $label . ' was calculated for ' . count($results)
    . ' employee' . (count($results) === 1 ? '' : 's') . '. Net total: ' . money(from_cents($totalNet)) . '.');
redirect($back);
