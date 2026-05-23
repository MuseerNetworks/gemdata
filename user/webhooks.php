<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/user-page-components.php';
$user = require_user();
app(\GemData\Classes\RoleMiddleware::class)->requireRole($user, 'api');
render_user_coming_soon_page('Webhooks', 'Webhook URLs, callbacks, and delivery settings.', [
    ['label' => 'API Center', 'href' => base_url('user/api-center.php'), 'copy' => 'Back to developer tools', 'icon' => 'code'],
]);
