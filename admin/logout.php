<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
if (!is_post()) {
    redirect(base_url('admin/dashboard.php'));
}
verify_csrf();
auth()->logoutAdmin();
redirect(base_url('admin/login.php'));
