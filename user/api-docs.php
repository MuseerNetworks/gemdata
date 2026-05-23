<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/user-page-components.php';
$user = require_user();
app(\GemData\Classes\RoleMiddleware::class)->requireRole($user, 'api');
render_user_coming_soon_page('API Docs', 'Endpoint list and integration documentation for approved API users.', [
    ['label' => 'Public API Docs', 'href' => base_url('docs/api.php'), 'copy' => 'Open existing docs', 'icon' => 'server'],
    ['label' => 'API Keys', 'href' => base_url('user/api-keys.php'), 'copy' => 'Manage keys', 'icon' => 'key'],
]);
