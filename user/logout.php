<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
auth()->logoutUser();
redirect(base_url('user/login.php'));
