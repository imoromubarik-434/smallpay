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

// Only a draft can be deleted. Its payslips are removed with it by the database.
$stmt = db()->prepare(
    "DELETE FROM payroll_runs WHERE id = :id AND status = 'draft'
     RETURNING period_year, period_month"
);
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    flash('error', 'Only a draft run can be discarded.');
    redirect('/admin/payroll');
}

$label = period_label((int)$row['period_year'], (int)$row['period_month']);

audit($user['id'], 'payroll_discarded', 'payroll_run', $id, ['period' => $label]);
flash('success', 'The draft for ' . $label . ' was discarded.');
redirect('/admin/payroll');
