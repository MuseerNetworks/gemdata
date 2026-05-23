<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/user-page-components.php';
require_user();
render_user_coming_soon_page('Support', 'Get help with wallet funding, transactions, and services.', [
    ['label' => 'Notifications', 'href' => base_url('user/notifications.php'), 'copy' => 'View support updates', 'icon' => 'notification'],
    ['label' => 'Transactions', 'href' => base_url('user/transactions.php'), 'copy' => 'Check transaction status', 'icon' => 'transactions'],
]);
