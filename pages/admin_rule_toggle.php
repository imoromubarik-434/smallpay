<?php
$user = require_role('admin');

// This page only accepts form submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/rules');
}
csrf_verify();

$id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = (string)($_POST['action'] ?? '');

if ($id === false || !in_array($action, ['on', 'off'], true)) {
    not_found();
}

$stmt = db()->prepare('UPDATE deduction_rules SET is_active = :active WHERE id = :id RETURNING name');
$stmt->execute(['active' => $action === 'on' ? 'true' : 'false', 'id' => $id]);
$name = $stmt->fetchColumn();

if ($name === false) {
    not_found();   // no rule with that id
}

audit($user['id'], 'rule_' . $action, 'rule', $id, ['name' => $name]);
flash('success', $name . ' is now ' . ($action === 'on' ? 'on.' : 'off.'));
redirect('/admin/rules');
