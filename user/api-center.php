<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/user-page-components.php';
$user = require_user();
app(\GemData\Classes\RoleMiddleware::class)->requireRole($user, 'api');
render_user_coming_soon_page('API Center', 'Developer tools, usage stats, and integration controls.', [
    ['label' => 'API Keys', 'href' => base_url('user/api-keys.php'), 'copy' => 'Manage credentials', 'icon' => 'key'],
    ['label' => 'Request Logs', 'href' => base_url('user/request-logs.php'), 'copy' => 'Review API activity', 'icon' => 'transactions'],
    ['label' => 'API Docs', 'href' => base_url('user/api-docs.php'), 'copy' => 'Read endpoints', 'icon' => 'server'],
]);
