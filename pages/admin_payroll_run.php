<?php
$user = require_role('admin');

require_once ROOT . '/includes/payroll.php';

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    not_found();
}

$stmt = db()->prepare('SELECT id, period_year, period_month, status FROM payroll_runs WHERE id = :id');
$stmt->execute(['id' => $id]);
$run = $stmt->fetch();
if (!$run) {
    not_found();
}

$isDraft     = $run['status'] === 'draft';
$label       = period_label((int)$run['period_year'], (int)$run['period_month']);
$daysInMonth = (int)(new DateTime(sprintf('%04d-%02d-01', $run['period_year'], $run['period_month'])))->format('t');

$stmt = db()->prepare(
    'SELECT p.id, p.pay_type, p.rate, p.units_worked, p.basic_pay, p.gross_pay,
            p.total_deductions, p.net_pay, p.calculated_at,
            e.employee_number, e.first_name, e.last_name
     FROM payslips p
     JOIN employees e ON e.id = p.employee_id
     WHERE p.run_id = :run
     ORDER BY e.employee_number'
);
$stmt->execute(['run' => $id]);
$slips = $stmt->fetchAll();

// Values shown in the boxes (keyed by payslip id)
$input  = [];
$errors = [];
foreach ($slips as $s) {
    $input[(int)$s['id']] = $s['units_worked'] === null ? '' : rtrim(rtrim($s['units_worked'], '0'), '.');
}

if ($isDraft && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $posted = $_POST['units'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }

    // Only payslips that belong to THIS run are looked at; anything else posted is ignored
    $clean = [];
    foreach ($slips as $s) {
        $sid = (int)$s['id'];
        if ($s['pay_type'] === 'monthly') {
            continue;   // monthly salaries need no hours
        }

        $raw = $posted[$sid] ?? '';
        $raw = is_string($raw) ? trim($raw) : '';
        $input[$sid] = $raw;

        if ($raw === '') {
            $clean[$sid] = null;   // not entered yet
            continue;
        }
        if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $raw)) {
            $errors[$sid] = 'Enter a number with up to 2 decimals.';
            continue;
        }

        $max = $s['pay_type'] === 'daily' ? $daysInMonth : $daysInMonth * 24;
        if ((float)$raw > $max) {
            $errors[$sid] = 'The most possible this month is ' . $max . '.';
            continue;
        }
        $clean[$sid] = $raw;
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            // The run must still be a draft at the moment of saving.
            // Saving hours means the run needs calculating again.
            $stmt = $pdo->prepare(
                "UPDATE payslips SET units_worked = :units, calculated_at = NULL
                 WHERE id = :id AND run_id = :run
                   AND EXISTS (SELECT 1 FROM payroll_runs r
                               WHERE r.id = payslips.run_id AND r.status = 'draft')"
            );
            foreach ($clean as $sid => $units) {
                $stmt->execute(['units' => $units, 'id' => $sid, 'run' => $id]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        audit($user['id'], 'payroll_hours_saved', 'payroll_run', $id, ['period' => $label]);
        flash('success', 'Hours saved for ' . $label . '. Calculate the payroll again to update the amounts.');
        redirect('/admin/payroll/run?id=' . $id);
    }
}

// Progress figures
$need = 0;
$have = 0;
$calculated = 0;
$totalNet = 0;
foreach ($slips as $s) {
    if ($s['pay_type'] !== 'monthly') {
        $need++;
        if ($input[(int)$s['id']] !== '') {
            $have++;
        }
    }
    if ($s['calculated_at'] !== null) {
        $calculated++;
        $totalNet += to_cents((string)$s['net_pay']);
    }
}
$allCalculated = $slips && $calculated === count($slips);

admin_header('Payroll ' . $label, 'payroll');
?>
<div class="page-head">
    <div>
        <h1>Payroll: <?= e($label) ?></h1>
        <p>
            <span class="chip <?= $isDraft ? 'chip-amber' : 'chip-green' ?>">
                <?= $isDraft ? 'Draft' : 'Finalized' ?>
            </span>
            <?= count($slips) ?> employee<?= count($slips) === 1 ? '' : 's' ?>
        </p>
    </div>
    <a class="btn btn-secondary" href="<?= e(url('/admin/payroll')) ?>">Back to runs</a>
</div>

<?php if ($isDraft): ?>
    <p class="hint">
        Enter the days worked (daily pay) or hours worked (hourly pay) for <?= e($label) ?>, and
        click <strong>Save hours</strong>. Monthly salaries need nothing. Hours entered:
        <strong><?= $have ?> of <?= $need ?></strong>. The month has <?= $daysInMonth ?> days.
        Then click <strong>Calculate</strong>.
    </p>
<?php endif; ?>

<p class="hint">
    Calculated: <strong><?= $calculated ?> of <?= count($slips) ?></strong>
    <?php if ($allCalculated): ?>
        &middot; Total net pay: <strong><?= e(money(from_cents($totalNet))) ?></strong>
    <?php endif; ?>
</p>

<form method="post" action="<?= e(url('/admin/payroll/run') . '?id=' . $id) ?>" data-validate novalidate>
    <?= csrf_field() ?>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>No.</th><th>Name</th><th>Pay type</th><th class="num">Rate</th><th>Worked</th>
                    <th class="num">Basic</th><th class="num">Gross</th>
                    <th class="num">Deductions</th><th class="num">Net</th><th>Payslip</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slips as $s): ?>
                    <?php
                    $sid  = (int)$s['id'];
                    $name = $s['first_name'] . ' ' . $s['last_name'];
                    $unit = $s['pay_type'] === 'daily' ? 'days' : 'hours';
                    $calc = $s['calculated_at'] !== null;
                    ?>
                    <tr>
                        <td><?= e($s['employee_number']) ?></td>
                        <td><?= e($name) ?></td>
                        <td><?= e(ucfirst($s['pay_type'])) ?></td>
                        <td class="num"><?= e(money($s['rate'])) ?></td>
                        <td>
                            <?php if ($s['pay_type'] === 'monthly'): ?>
                                <span class="muted">No hours needed</span>
                            <?php elseif ($isDraft): ?>
                                <div class="form-group">
                                    <div class="units-cell">
                                        <label class="sr-only" for="units-<?= $sid ?>"><?= e(ucfirst($unit) . ' worked by ' . $name) ?></label>
                                        <input type="text" class="units-input" id="units-<?= $sid ?>"
                                               name="units[<?= $sid ?>]" value="<?= e($input[$sid]) ?>"
                                               inputmode="decimal" pattern="\d{1,4}(\.\d{1,2})?"
                                               title="A number with up to 2 decimals, for example 22 or 7.5"
                                               <?= isset($errors[$sid]) ? 'aria-invalid="true"' : '' ?>>
                                        <span><?= e($unit) ?></span>
                                    </div>
                                    <p class="field-error" aria-live="polite"><?= e($errors[$sid] ?? '') ?></p>
                                </div>
                            <?php else: ?>
                                <?= $s['units_worked'] === null ? '-' : e(rtrim(rtrim($s['units_worked'], '0'), '.') . ' ' . $unit) ?>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= $calc ? e(money($s['basic_pay'])) : '-' ?></td>
                        <td class="num"><?= $calc ? e(money($s['gross_pay'])) : '-' ?></td>
                        <td class="num"><?= $calc ? e(money($s['total_deductions'])) : '-' ?></td>
                        <td class="num"><strong><?= $calc ? e(money($s['net_pay'])) : '-' ?></strong></td>
                         <td>
                            <?php if ($calc): ?>
                                <a href="<?= e(url('/admin/payslip') . '?id=' . $sid) ?>"
                                   aria-label="Payslip for <?= e($name) ?>">View</a>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($isDraft): ?>
        <div class="form-actions">
            <button class="btn btn-secondary" type="submit">Save hours</button>
        </div>
    <?php endif; ?>
</form>

<?php if ($isDraft): ?>
    <section class="card status-box">
        <h2>Calculate</h2>
        <p>
            Works out basic pay, allowances, deductions and net pay for every employee using your
            pay rules. Save any typed hours first. You can calculate as many times as you like
            before finalizing.
        </p>
        <form method="post" action="<?= e(url('/admin/payroll/calculate')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <button class="btn" type="submit">Calculate payroll</button>
        </form>
    </section>

    <section class="card status-box">
        <h2>Finalize</h2>
        <?php if ($allCalculated): ?>
            <p>
                Locks this payroll: the amounts can no longer be changed, and the payslips become final.
                Total net pay: <strong><?= e(money(from_cents($totalNet))) ?></strong>.
                Check the amounts above before you continue.
            </p>
            <form method="post" action="<?= e(url('/admin/payroll/finalize')) ?>"
                  data-confirm="Finalize payroll for <?= e($label) ?>? This cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <button class="btn" type="submit">Finalize payroll</button>
            </form>
        <?php else: ?>
            <p>Finalizing becomes available once every payslip has been calculated.</p>
        <?php endif; ?>
    </section>

    <section class="card status-box">
        <h2>Discard this draft</h2>
        <p>
            Removes the draft and its payslips so you can start the month again.
            Only drafts can be discarded.
        </p>
        <form method="post" action="<?= e(url('/admin/payroll/discard')) ?>"
              data-confirm="Discard the draft payroll for <?= e($label) ?>? Entered hours will be lost.">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <button class="btn btn-danger" type="submit">Discard draft</button>
        </form>
    </section>
<?php endif; ?>
<?php admin_footer(); ?>
