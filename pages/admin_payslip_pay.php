<?php
$user = require_role('admin');

// This page only accepts form submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/payroll');
}
csrf_verify();

$id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = (string)($_POST['action'] ?? '');

if ($id === false || !in_array($action, ['paid', 'unpaid'], true)) {
    not_found();
}

$stmt = db()->prepare(
    'SELECT p.id, r.status AS run_status, r.period_year, r.period_month,
            e.first_name, e.last_name
     FROM payslips p
     JOIN payroll_runs r ON r.id = p.run_id
     JOIN employees e ON e.id = p.employee_id
     WHERE p.id = :id'
);
$stmt->execute(['id' => $id]);
$slip = $stmt->fetch();
if (!$slip) {
    not_found();
}

$back = '/admin/payslip?id=' . $id;
$name = $slip['first_name'] . ' ' . $slip['last_name'];

if ($slip['run_status'] !== 'finalized') {
    flash('error', 'Payments can only be recorded after the payroll is finalized.');
    redirect($back);
}

if ($action === 'paid') {
    $method = (string)($_POST['method'] ?? '');
    if (!in_array($method, ['cash', 'mobile_money', 'bank'], true)) {
        flash('error', 'Choose how the payment was made.');
        redirect($back);
    }

    $date        = parse_date(trim((string)($_POST['paid_on'] ?? '')));
    $periodStart = new DateTime(sprintf('%04d-%02d-01', $slip['period_year'], $slip['period_month']));

    if ($date === null) {
        flash('error', 'Enter a valid payment date.');
        redirect($back);
    }
    if ($date > new DateTime('today')) {
        flash('error', 'The payment date cannot be in the future.');
        redirect($back);
    }
    if ($date < $periodStart) {
        flash('error', 'The payment date cannot be before the pay period starts.');
        redirect($back);
    }

    $stmt = db()->prepare(
        "UPDATE payslips
         SET payment_status = 'paid', payment_method = :method, paid_at = CAST(:paid AS TIMESTAMPTZ)
         WHERE id = :id"
    );
    $stmt->execute(['method' => $method, 'paid' => $date->format('Y-m-d'), 'id' => $id]);

    audit($user['id'], 'payment_recorded', 'payslip', $id, [
        'method' => $method,
        'date'   => $date->format('Y-m-d'),
    ]);
    flash('success', $name . ' was marked as paid.');
} else {
    $stmt = db()->prepare(
        "UPDATE payslips
         SET payment_status = 'unpaid', payment_method = NULL, paid_at = NULL
         WHERE id = :id"
    );
    $stmt->execute(['id' => $id]);

    audit($user['id'], 'payment_undone', 'payslip', $id);
    flash('success', $name . ' was marked as unpaid.');
}

redirect($back);
