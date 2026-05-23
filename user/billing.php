<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/user-page-components.php';
$user = require_user();
app(\GemData\Classes\RoleMiddleware::class)->requireRole($user, 'api');
render_user_coming_soon_page('Billing', 'API billing, usage, and settlement overview.', [
    ['label' => 'Wallet', 'href' => base_url('user/fund-wallet.php'), 'copy' => 'Fund API transactions', 'icon' => 'wallet'],
    ['label' => 'Request Logs', 'href' => base_url('user/request-logs.php'), 'copy' => 'Review API usage', 'icon' => 'transactions'],
]);
