<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = current_user();

if (!$user) {
    redirect('/login.php');
}

switch ($user['role']) {
    case 'admin':
        redirect('/admin/index.php');
    default:
        redirect('/student/index.php');
}
