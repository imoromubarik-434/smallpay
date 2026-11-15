<?php
$user = require_role('admin');

// This page only accepts form submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/advances');
}
csrf_verify();

require_once ROOT . '/includes/payroll.php';   // for to_cents()

$id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = (string)($_POST['action'] ?? '');

if ($id === false || !in_array($action, ['approve', 'reject'], true)) {
    not_found();
}

$stmt = db()->prepare(
    'SELECT a.id, a.amount, a.status, e.status AS employee_status, e.first_name, e.last_name
     FROM advances a
     JOIN employees e ON e.id = a.employee_id
     WHERE a.id = :id'
);
$stmt->execute(['id' => $id]);
$advance = $stmt->fetch();
if (!$advance) {
    not_found();
}

$who = $advance['first_name'] . ' ' . $advance['last_name'];

if ($advance['status'] !== 'pending') {
    flash('error', 'This request has already been decided.');
    redirect('/admin/advances');
}

if ($action === 'reject') {
    $stmt = db()->prepare(
        "UPDATE advances SET status = 'rejected', decided_by = :admin, decided_at = NOW()
         WHERE id = :id AND status = 'pending'"
    );
    $stmt->execute(['admin' => $user['id'], 'id' => $id]);

    if ($stmt->rowCount() === 1) {
        audit($user['id'], 'advance_rejected', 'advance', $id, ['amount' => $advance['amount']]);
        flash('success', 'The advance request from ' . $who . ' was rejected.');
    } else {
        flash('error', 'This request has already been decided.');
    }
    redirect('/admin/advances');
}

// ---- Approve: the repayment per month must be valid ----
$installment = trim((string)($_POST['installment'] ?? ''));

if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $installment) || to_cents($installment) <= 0) {
    flash('error', 'Enter a repayment per month above 0, with up to 2 decimals and no commas.');
    redirect('/admin/advances');
}
if (to_cents($installment) > to_cents((string)$advance['amount'])) {
    flash('error', 'The monthly repayment cannot be more than the advance (' . money($advance['amount']) . ').');
    redirect('/admin/advances');
}
if ($advance['employee_status'] !== 'active') {
    flash('error', $who . ' is inactive. Reactivate the employee before approving an advance.');
    redirect('/admin/advances');
}

$stmt = db()->prepare(
    "UPDATE advances
     SET status = 'approved', installment_amount = :inst, decided_by = :admin, decided_at = NOW()
     WHERE id = :id AND status = 'pending'"
);
$stmt->execute(['inst' => $installment, 'admin' => $user['id'], 'id' => $id]);

if ($stmt->rowCount() !== 1) {
    flash('error', 'This request has already been decided.');
    redirect('/admin/advances');
}

audit($user['id'], 'advance_approved', 'advance', $id, [
    'amount'      => $advance['amount'],
    'installment' => $installment,
]);
flash('success', 'The advance for ' . $who . ' was approved: ' . money($advance['amount'])
    . ', repaid ' . money($installment) . ' per month.');
redirect('/admin/advances');
