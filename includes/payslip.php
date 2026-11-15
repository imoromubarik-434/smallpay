<?php
declare(strict_types=1);

require_once __DIR__ . '/payroll.php';

/**
 * Load one payslip with its lines.
 * When $employeeId is given, the payslip must belong to that employee AND be part of a
 * finalized run. The rule is inside the query, so other people's payslips are never read.
 */
function load_payslip(int $id, ?int $employeeId = null): ?array
{
    $sql = 'SELECT p.id, p.run_id, p.pay_type, p.rate, p.units_worked, p.basic_pay, p.gross_pay,
                   p.total_deductions, p.advance_repayment, p.net_pay,
                   p.payment_status, p.payment_method, p.paid_at, p.calculated_at,
                   r.period_year, r.period_month, r.status AS run_status,
                   e.employee_number, e.first_name, e.last_name, e.job_title
            FROM payslips p
            JOIN payroll_runs r ON r.id = p.run_id
            JOIN employees e ON e.id = p.employee_id
            WHERE p.id = :id';
    $params = ['id' => $id];

    if ($employeeId !== null) {
        $sql .= " AND p.employee_id = :emp AND r.status = 'finalized'";
        $params['emp'] = $employeeId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $slip = $stmt->fetch();
    if (!$slip) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT label, item_type, amount FROM payslip_items
         WHERE payslip_id = :id ORDER BY item_type, label'
    );
    $stmt->execute(['id' => $id]);

    $slip['allowances'] = [];
    $slip['deductions'] = [];
    foreach ($stmt->fetchAll() as $item) {
        $slip[$item['item_type'] === 'allowance' ? 'allowances' : 'deductions'][] = $item;
    }

    return $slip;
}

/** Print the payslip document (used by the admin and the employee pages). */
function render_payslip(array $slip): void
{
    $label     = period_label((int)$slip['period_year'], (int)$slip['period_month']);
    $name      = $slip['first_name'] . ' ' . $slip['last_name'];
    $finalized = $slip['run_status'] === 'finalized';
    $paid      = $slip['payment_status'] === 'paid';
    $methods   = ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'bank' => 'Bank'];
    $advance   = to_cents((string)$slip['advance_repayment']);

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
    ?>
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

                    <?php if ($slip['allowances']): ?>
                        <tr class="section"><th colspan="2">Allowances</th></tr>
                        <?php foreach ($slip['allowances'] as $item): ?>
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

                    <?php if ($slip['deductions'] || $advance > 0): ?>
                        <tr class="section"><th colspan="2">Deductions</th></tr>
                        <?php foreach ($slip['deductions'] as $item): ?>
                            <tr>
                                <td><?= e($item['label']) ?></td>
                                <td class="num">−<?= e(money($item['amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($advance > 0): ?>
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
    <?php
}
