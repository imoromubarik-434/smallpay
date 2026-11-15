<?php
['user' => $user, 'employee' => $employee] = require_employee();

require_once ROOT . '/includes/payslip.php';

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    not_found();
}

// Must belong to THIS employee and be from a finalized run (the rule is in the query)
$slip = load_payslip($id, (int)$employee['id']);

if (!$slip) {
    // Same plain 404 whether it does not exist or belongs to someone else; the attempt is logged
    audit($user['id'], 'payslip_access_denied', 'payslip', $id);
    not_found();
}

page_header('My payslip');
?>
<div class="page-head no-print">
    <h1>My payslip</h1>
    <div class="row-actions">
        <a class="btn btn-secondary" href="<?= e(url('/employee/payslips')) ?>">All payslips</a>
        <button class="btn" type="button" data-print>Print payslip</button>
    </div>
</div>

<?php render_payslip($slip); ?>
<?php page_footer(); ?>
