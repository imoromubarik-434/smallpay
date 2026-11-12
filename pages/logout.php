<?php
// Logging out must be a POST with a valid CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}
csrf_verify();

$user = current_user();
if ($user !== null) {
    audit($user['id'], 'logout', 'user', $user['id']);
}

logout_user();
flash('info', 'You have been logged out.');
redirect('/');
