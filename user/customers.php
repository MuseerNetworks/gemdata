<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/user-page-components.php';
$user = require_user();
app(\GemData\Classes\RoleMiddleware::class)->requireRole($user, 'reseller');
render_user_coming_soon_page('Customers', 'Saved customers and beneficiaries for reseller accounts.', [
    ['label' => 'Bulk SMS', 'href' => base_url('user/bulk-sms.php'), 'copy' => 'Message customers', 'icon' => 'notification'],
    ['label' => 'Reports', 'href' => base_url('user/commission.php'), 'copy' => 'View sales reports', 'icon' => 'chart'],
]);
