<?php
$user = require_role('admin');

// This page only accepts form submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/employees');
}
csrf_verify();

$id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = (string)($_POST['action'] ?? '');

if ($id === false || !in_array($action, ['deactivate', 'reactivate'], true)) {
    not_found();
}
$activate = $action === 'reactivate';

$stmt = db()->prepare('SELECT id, first_name, last_name, user_id FROM employees WHERE id = :id');
$stmt->execute(['id' => $id]);
$employee = $stmt->fetch();
if (!$employee) {
    not_found();
}

// An admin must not lock themselves out
if (!$activate && $employee['user_id'] !== null && (int)$employee['user_id'] === $user['id']) {
    flash('error', 'You cannot deactivate your own account.');
    redirect('/admin/employees/edit?id=' . $id);
}

// Employee and login account change together, or not at all
$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('UPDATE employees SET status = :status WHERE id = :id');
    $stmt->execute(['status' => $activate ? 'active' : 'inactive', 'id' => $id]);

    if ($employee['user_id'] !== null) {
        $stmt = $pdo->prepare('UPDATE users SET is_active = :active WHERE id = :uid');
        $stmt->execute(['active' => $activate ? 'true' : 'false', 'uid' => $employee['user_id']]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

audit($user['id'], $activate ? 'employee_reactivated' : 'employee_deactivated', 'employee', $id);

$name = $employee['first_name'] . ' ' . $employee['last_name'];
flash('success', $name . ($activate ? ' is active again.' : ' was deactivated.'));
redirect('/admin/employees' . ($activate ? '' : '?status=inactive'));
