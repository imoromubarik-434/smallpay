<?php
declare(strict_types=1);

/** "3500.00" -> 350000 (whole cents, no floating point). */
function to_cents(string $amount): int
{
    $parts = explode('.', trim($amount));
    $whole = (int)$parts[0];
    $frac  = (int)str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');

    return $whole * 100 + $frac;
}

/** 350000 -> "3500.00" */
function from_cents(int $cents): string
{
    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
}

/** Integer division that rounds half up (for amounts that are not negative). */
function div_round(int $number, int $divisor): int
{
    return intdiv($number + intdiv($divisor, 2), $divisor);
}

/** Does this rule apply to this employee? */
function rule_applies(array $rule, array $assigned, int $employeeId): bool
{
    return truthy($rule['applies_to_all']) || isset($assigned[(int)$rule['id']][$employeeId]);
}

/** Amount of one rule in cents. $base is the pay the percentage is taken from. */
function rule_amount(array $rule, int $base): int
{
    $value = to_cents((string)$rule['value']);   // 5.50 becomes 550 (hundredths of a percent)

    return $rule['method'] === 'fixed' ? $value : div_round($base * $value, 10000);
}

/**
 * Work out one payslip. Returns an array with basic, allowances, gross, deductions,
 * advance, net (all in cents), advance_id and items, or ['error' => 'message'].
 * $advance is the employee's approved advance (id, installment, balance in cents) or null.
 */
function calculate_payslip(
    array $slip,
    array $rules,
    array $assigned,
    int $year,
    int $month,
    ?array $advance = null
): array {
    $name = $slip['first_name'] . ' ' . $slip['last_name'];
    $rate = to_cents((string)$slip['rate']);

    // ---- 1. Basic pay ----
    if ($slip['pay_type'] === 'monthly') {
        $basic       = $rate;
        $monthStart  = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $daysInMonth = (int)$monthStart->format('t');
        $hired       = new DateTime((string)$slip['hire_date']);

        if ($hired > $monthStart) {   // joined during the month: pay for the days employed
            $daysEmployed = $daysInMonth - (int)$hired->format('j') + 1;
            $basic        = div_round($rate * $daysEmployed, $daysInMonth);
        }
    } else {
        if ($slip['units_worked'] === null) {
            $what = $slip['pay_type'] === 'daily' ? 'days' : 'hours';
            return ['error' => $name . ': enter the ' . $what . ' worked.'];
        }
        $basic = div_round($rate * to_cents((string)$slip['units_worked']), 100);
    }

    $items = [];

    // ---- 2. Allowances (percentages are always of basic pay) ----
    $allowances = 0;
    foreach ($rules as $rule) {
        if ($rule['rule_type'] !== 'allowance' || !rule_applies($rule, $assigned, (int)$slip['employee_id'])) {
            continue;
        }
        $amount = rule_amount($rule, $basic);
        if ($amount > 0) {
            $items[]     = ['rule_id' => (int)$rule['id'], 'label' => $rule['name'], 'type' => 'allowance', 'amount' => $amount];
            $allowances += $amount;
        }
    }

    // ---- 3. Gross pay ----
    $gross = $basic + $allowances;

    // ---- 4. Deductions (percentages of basic or of gross) ----
    $deductions = 0;
    foreach ($rules as $rule) {
        if ($rule['rule_type'] !== 'deduction' || !rule_applies($rule, $assigned, (int)$slip['employee_id'])) {
            continue;
        }
        $base   = $rule['percentage_base'] === 'gross' ? $gross : $basic;
        $amount = rule_amount($rule, $base);
        if ($amount > 0) {
            $items[]     = ['rule_id' => (int)$rule['id'], 'label' => $rule['name'], 'type' => 'deduction', 'amount' => $amount];
            $deductions += $amount;
        }
    }

    $beforeAdvance = $gross - $deductions;
    if ($beforeAdvance < 0) {
        return ['error' => $name . ': deductions (' . from_cents($deductions)
            . ') are more than gross pay (' . from_cents($gross) . ').'];
    }

    // ---- 5. Advance repayment: the smallest of installment, balance and what is affordable ----
    $repay     = 0;
    $advanceId = null;
    if ($advance !== null) {
        $repay = min($advance['installment'], $advance['balance'], $beforeAdvance);
        if ($repay > 0) {
            $advanceId = $advance['id'];
        } else {
            $repay = 0;
        }
    }

    // ---- 6. Net pay ----
    return [
        'basic'      => $basic,
        'allowances' => $allowances,
        'gross'      => $gross,
        'deductions' => $deductions,
        'advance'    => $repay,
        'advance_id' => $advanceId,
        'net'        => $beforeAdvance - $repay,
        'items'      => $items,
    ];
}

/** Calculate every payslip of a run with the rules and advances as they are right now. */
function compute_run(PDO $pdo, int $runId, int $year, int $month): array
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.employee_id, p.pay_type, p.rate, p.units_worked,
                e.first_name, e.last_name, e.hire_date
         FROM payslips p
         JOIN employees e ON e.id = p.employee_id
         WHERE p.run_id = :run
         ORDER BY e.employee_number'
    );
    $stmt->execute(['run' => $runId]);
    $slips = $stmt->fetchAll();

    $rules = $pdo->query(
        'SELECT id, name, rule_type, method, value, percentage_base, applies_to_all
         FROM deduction_rules WHERE is_active ORDER BY rule_type, name'
    )->fetchAll();

    $assigned = [];
    foreach ($pdo->query('SELECT rule_id, employee_id FROM employee_rules')->fetchAll() as $row) {
        $assigned[(int)$row['rule_id']][(int)$row['employee_id']] = true;
    }

    // Approved advances still being repaid (balance comes from the advance_balances view)
    $advances = [];
    $rows = $pdo->query(
        "SELECT a.id, a.employee_id, a.installment_amount, b.balance
         FROM advances a
         JOIN advance_balances b ON b.advance_id = a.id
         WHERE a.status = 'approved'"
    )->fetchAll();
    foreach ($rows as $row) {
        $advances[(int)$row['employee_id']] = [
            'id'          => (int)$row['id'],
            'installment' => to_cents((string)$row['installment_amount']),
            'balance'     => to_cents((string)$row['balance']),
        ];
    }

    $results  = [];
    $problems = [];
    foreach ($slips as $slip) {
        $r = calculate_payslip(
            $slip, $rules, $assigned, $year, $month,
            $advances[(int)$slip['employee_id']] ?? null
        );
        if (isset($r['error'])) {
            $problems[] = $r['error'];
        } else {
            $results[(int)$slip['id']] = $r;
        }
    }

    return ['count' => count($slips), 'results' => $results, 'problems' => $problems];
}
