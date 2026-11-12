<?php
$user = require_role('admin');

// Filters come from the URL; the status is checked against a fixed list
$status = (string)($_GET['status'] ?? 'active');
if (!in_array($status, ['active', 'inactive', 'all'], true)) {
    $status = 'active';
}
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);

// The SQL text below is built only from fixed strings.
// Every value from the user goes through a parameter.
$where  = [];
$params = [];

if ($status !== 'all') {
    $where[]          = 'e.status = :status';
    $params['status'] = $status;
}
if ($q !== '') {
    $like    = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(e.first_name ILIKE :like1 OR e.last_name ILIKE :like2 OR e.employee_number ILIKE :like3)';
    $params += ['like1' => $like, 'like2' => $like, 'like3' => $like];
}

$sql = 'SELECT e.id, e.employee_number, e.first_name, e.last_name, e.job_title,
               e.status, ps.pay_type, ps.rate
        FROM employees e
        LEFT JOIN LATERAL (
            SELECT pay_type, rate
            FROM pay_structures
            WHERE employee_id = e.id AND effective_from <= CURRENT_DATE
            ORDER BY effective_from DESC
            LIMIT 1
        ) ps ON TRUE'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY e.employee_number';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

admin_header('Employees', 'employees');
?>
<div class="page-head">
    <div>
        <h1>Employees</h1>
        <p><?= count($employees) ?> shown</p>
    </div>
    <a class="btn" href="<?= e(url('/admin/employees/new')) ?>">Add employee</a>
</div>
<form class="toolbar" method="get" action="<?= e(url('/admin/employees')) ?>">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Name or employee number">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn" type="submit">Filter</button>
</form>

<?php if (!$employees): ?>
    <p class="card">No employees match your search.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>No.</th><th>Name</th><th>Job title</th><th>Pay</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp): ?>
    <tr>
        <td><?= e($emp['employee_number']) ?></td>
        <td><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
        <td><?= e($emp['job_title'] ?? '') ?></td>
        <td>
            <?= $emp['pay_type']
                ? e(ucfirst($emp['pay_type']) . ' · ' . money($emp['rate']))
                : 'Not set' ?>
        </td>
        <td>
            <span class="chip <?= $emp['status'] === 'active' ? 'chip-green' : 'chip-gray' ?>">
                <?= e(ucfirst($emp['status'])) ?>
            </span>
        </td>
        <td>
            <a href="<?= e(url('/admin/employees/edit') . '?id=' . (int)$emp['id']) ?>"
               aria-label="Edit <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?>">Edit</a>
        </td>
    </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php admin_footer(); ?>
