<?php
$user = require_role('admin');

// No user input in this query, so no parameters are needed
$rows = db()->query(
    "SELECT a.id, a.amount, a.reason, a.status, a.installment_amount, a.requested_at,
            b.balance, e.employee_number, e.first_name, e.last_name
     FROM advances a
     JOIN employees e ON e.id = a.employee_id
     JOIN advance_balances b ON b.advance_id = a.id
     ORDER BY a.requested_at DESC"
)->fetchAll();

$pending = array_filter($rows, static fn (array $r): bool => $r['status'] === 'pending');
$others  = array_filter($rows, static fn (array $r): bool => $r['status'] !== 'pending');
$chips   = ['approved' => 'chip-green', 'rejected' => 'chip-gray', 'repaid' => 'chip-gray'];

admin_header('Advances', 'advances');
?>
<div class="page-head">
    <div>
        <h1>Salary advances</h1>
        <p>Approve a request and set how much is repaid each month.</p>
    </div>
</div>

<h2>Waiting for a decision (<?= count($pending) ?>)</h2>
<?php if (!$pending): ?>
    <p class="card">No requests are waiting.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Employee</th><th>Requested</th><th class="num">Amount</th><th>Reason</th><th>Decision</th></tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $a): ?>
                    <?php $aid = (int)$a['id']; $who = $a['first_name'] . ' ' . $a['last_name']; ?>
                    <tr>
                        <td><?= e($who) ?> <span class="muted"><?= e($a['employee_number']) ?></span></td>
                        <td><?= e(substr($a['requested_at'], 0, 10)) ?></td>
                        <td class="num"><?= e(money($a['amount'])) ?></td>
                        <td><?= e($a['reason'] ?? '-') ?></td>
                        <td>
                            <form method="post" action="<?= e(url('/admin/advances/decide')) ?>"
                                  class="decision-form" data-validate novalidate>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $aid ?>">
                                <div class="form-group">
                                    <label class="sr-only" for="inst-<?= $aid ?>">Repayment per month for <?= e($who) ?></label>
                                    <input type="text" id="inst-<?= $aid ?>" name="installment"
                                           value="<?= e($a['amount']) ?>" inputmode="decimal"
                                           pattern="\d{1,8}(\.\d{1,2})?"
                                           title="Repayment per month, for example 100 or 75.50">
                                    <p class="field-error" aria-live="polite"></p>
                                </div>
                                <button class="btn" type="submit" name="action" value="approve">Approve</button>
                                <button class="btn btn-secondary" type="submit" name="action" value="reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="hint">
        The box beside each request is the repayment per month. It starts as the full amount;
        lower it to spread the repayment over several months.
    </p>
<?php endif; ?>

<h2>All other advances</h2>
<?php if (!$others): ?>
    <p class="card">Nothing here yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th><th>Requested</th><th class="num">Amount</th>
                    <th class="num">Repay per month</th><th>Status</th><th class="num">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($others as $a): ?>
                    <?php $decided = in_array($a['status'], ['approved', 'repaid'], true); ?>
                    <tr>
                        <td><?= e($a['first_name'] . ' ' . $a['last_name']) ?> <span class="muted"><?= e($a['employee_number']) ?></span></td>
                        <td><?= e(substr($a['requested_at'], 0, 10)) ?></td>
                        <td class="num"><?= e(money($a['amount'])) ?></td>
                        <td class="num"><?= $decided ? e(money($a['installment_amount'])) : '-' ?></td>
                        <td>
                            <span class="chip <?= e($chips[$a['status']] ?? 'chip-gray') ?>">
                                <?= e(ucfirst($a['status'])) ?>
                            </span>
                        </td>
                        <td class="num"><?= $decided ? e(money($a['balance'])) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php admin_footer(); ?>
