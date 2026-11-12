<?php
$user = require_role('employee');

page_header('My dashboard');
?>
<h1>Hello, <?= e($user['name']) ?></h1>

<?php if ($user['employee_id'] === null): ?>
    <div class="alert alert-info" role="status">
        Your login is not linked to an employee record yet. Please ask your administrator.
    </div>
<?php else: ?>
    <p>Your payslips and advances will appear here.</p>
<?php endif; ?>
<?php page_footer(); ?>
