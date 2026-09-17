<?php
require __DIR__ . '/includes/bootstrap.php';
$user = current_user();
if ($user && $user['profile'] === 'ADMIN') {
    redirect('admin/index.php');
}
redirect('store.php');
