<?php
['user' => $user, 'employee' => $employee] = require_employee();

$errors = [];
$old    = ['amount' => '', 'reason' => ''];

// Is there already an open advance (waiting for a decision, or still being repaid)?
$stmt = db()->prepare(
    "SELECT status FROM advances
     WHERE employee_id = :emp AND status IN ('pending', 'approved') LIMIT 1"
);
$stmt->execute(['emp' => $employee['id']]);
$open = $stmt->fetchColumn();   // false when there is none

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if ($open !== false) {
        flash('error', 'You already have an open advance. You can request another once it is repaid or decided.');
        redirect('/employee/advances');
    }

    $old['amount'] = trim((string)($_POST['amount'] ?? ''));
    $old['reason'] = trim((string)($_POST['reason'] ?? ''));

    // ---- Server-side validation ----
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $old['amount']) || (float)$old['amount'] <= 0) {
        $errors['amount'] = 'Enter an amount above 0, with up to 2 decimals and no commas.';
    }
    if (mb_strlen($old['reason']) > 255) {
        $errors['reason'] = 'The reason can be up to 255 characters.';
    }

    if (!$errors) {
        try {
            $stmt = db()->prepare(
                'INSERT INTO advances (employee_id, amount, reason)
                 VALUES (:emp, :amount, :reason)
                 RETURNING id'
            );
            $stmt->execute([
                'emp'    => $employee['id'],
                'amount' => $old['amount'],
                'reason' => $old['reason'] !== '' ? $old['reason'] : null,
            ]);
            $advanceId = (int)$stmt->fetchColumn();

            audit($user['id'], 'advance_requested', 'advance', $advanceId, ['amount' => $old['amount']]);
            flash('success', 'Your advance request was sent. Your employer will review it.');
            redirect('/employee/advances');
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {   // the one-open-advance rule
                flash('error', 'You already have an open advance.');
                redirect('/employee/advances');
            }
            throw $e;
        }
    }
}

// This employee's advances, newest first (the balance is calculated by the view)
$stmt = db()->prepare(
    'SELECT a.id, a.amount, a.reason, a.status, a.installment_amount, a.requested_at, b.balance
     FROM advances a
     JOIN advance_balances b ON b.advance_id = a.id
     WHERE a.employee_id = :emp
     ORDER BY a.requested_at DESC'
);
$stmt->execute(['emp' => $employee['id']]);
$advances = $stmt->fetchAll();

$chips = ['pending' => 'chip-amber', 'approved' => 'chip-green', 'rejected' => 'chip-gray', 'repaid' => 'chip-gray'];

page_header('My advances');
?>
<h1>Salary advances</h1>

<?php if ($open === false): ?>
    <section class="card">
        <h2>Request an advance</h2>
        <form method="post" action="<?= e(url('/employee/advances')) ?>" data-validate novalidate>
            <?= csrf_field() ?>
            <?php
            field('amount', 'Amount you need', $old['amount'], $errors, 'text',
                'inputmode="decimal" pattern="\d{1,8}(\.\d{1,2})?" title="A number with up to 2 decimals, for example 300 or 150.50" required');
            ?>
            <div class="form-group">
                <label for="reason">Reason (optional)</label>
                <textarea id="reason" name="reason" rows="3" maxlength="255"<?= isset($errors['reason']) ? ' aria-invalid="true"' : '' ?>><?= e($old['reason']) ?></textarea>
                <p class="field-error" aria-live="polite"><?= e($errors['reason'] ?? '') ?></p>
            </div>
            <button class="btn" type="submit">Send request</button>
        </form>
        <p class="hint">
            Your employer decides how much you repay each month. Repayments are taken from your pay.
        </p>
    </section>
<?php else: ?>
    <p class="notice">
        You have an open advance, so a new request is not possible until it is repaid or decided.
    </p>
<?php endif; ?>

<h2>My requests</h2>
<?php if (!$advances): ?>
    <p class="card">You have not requested any advance yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Requested</th><th class="num">Amount</th><th>Reason</th>
                    <th>Status</th><th class="num">Repay per month</th><th class="num">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($advances as $a): ?>
                    <?php $decided = in_array($a['status'], ['approved', 'repaid'], true); ?>
                    <tr>
                        <td><?= e(substr($a['requested_at'], 0, 10)) ?></td>
                        <td class="num"><?= e(money($a['amount'])) ?></td>
                        <td><?= e($a['reason'] ?? '-') ?></td>
                        <td>
                            <span class="chip <?= e($chips[$a['status']] ?? 'chip-gray') ?>">
                                <?= e(ucfirst($a['status'])) ?>
                            </span>
                        </td>
                        <td class="num"><?= $decided ? e(money($a['installment_amount'])) : '-' ?></td>
                        <td class="num"><?= $decided ? e(money($a['balance'])) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php page_footer(); ?>
