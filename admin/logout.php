<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
auth()->logoutAdmin();
redirect(base_url('admin/login.php'));
