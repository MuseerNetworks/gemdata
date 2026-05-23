<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (!is_post()) {
    redirect(base_url('user/dashboard.php'));
}
verify_csrf();
auth()->logoutUser();
redirect(base_url('user/login.php'));
