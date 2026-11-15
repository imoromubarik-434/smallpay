<?php
$user = require_role('admin');

require_once ROOT . '/includes/payslip.php';

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    not_found();
}

$slip = load_payslip($id);
if (!$slip) {
    not_found();
}

$name      = $slip['first_name'] . ' ' . $slip['last_name'];
$finalized = $slip['run_status'] === 'finalized';
$paid      = $slip['payment_status'] === 'paid';
$methods   = ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'bank' => 'Bank'];
$backUrl   = url('/admin/payroll/run') . '?id=' . (int)$slip['run_id'];

admin_header('Payslip', 'payroll');

// A payslip that has not been calculated has nothing to show yet
if ($slip['calculated_at'] === null) {
    ?>
    <h1>Payslip</h1>
    <p class="card">
        This payslip for <?= e($name) ?> has not been calculated yet.
        <a href="<?= e($backUrl) ?>">Back to the payroll run</a>
    </p>
    <?php
    admin_footer();
    exit;
}
?>
<div class="page-head no-print">
    <h1>Payslip</h1>
    <div class="row-actions">
        <a class="btn btn-secondary" href="<?= e($backUrl) ?>">Back to payroll</a>
        <button class="btn" type="button" data-print>Print payslip</button>
    </div>
</div>

<?php if (!$finalized): ?>
    <p class="notice no-print">
        This payroll is still a draft, so these amounts may change. Finalize the payroll to make them permanent.
    </p>
<?php endif; ?>

<?php render_payslip($slip); ?>

<?php if ($finalized): ?>
    <section class="card status-box no-print">
        <h2>Payment</h2>

        <?php if ($paid): ?>
            <p>
                Paid<?= $slip['payment_method'] ? ' by ' . e($methods[$slip['payment_method']] ?? $slip['payment_method']) : '' ?>
                <?= $slip['paid_at'] ? ' on ' . e(substr($slip['paid_at'], 0, 10)) : '' ?>.
            </p>
            <form method="post" action="<?= e(url('/admin/payslip/pay')) ?>"
                  data-confirm="Mark this payslip as unpaid again?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <input type="hidden" name="action" value="unpaid">
                <button class="btn btn-secondary" type="submit">Mark as unpaid</button>
            </form>
        <?php else: ?>
            <p>Record how and when <?= e($name) ?> was paid <?= e(money($slip['net_pay'])) ?>.</p>
            <form method="post" action="<?= e(url('/admin/payslip/pay')) ?>" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <input type="hidden" name="action" value="paid">

                <div class="form-grid">
                    <?php
                    select_field('method', 'Paid by', ['' => 'Choose...'] + $methods, '', [], 'required');
                    field('paid_on', 'Date paid', date('Y-m-d'), [], 'date', 'required max="' . date('Y-m-d') . '"');
                    ?>
                </div>
                <button class="btn" type="submit">Mark as paid</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php admin_footer(); ?>
