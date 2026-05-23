<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_user();
app(\GemData\Classes\RoleMiddleware::class)->requireRole($user, 'api');
require __DIR__ . '/api-logs.php';
