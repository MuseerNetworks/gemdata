<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/user-page-components.php';
require_user();
render_user_coming_soon_page('Profile', 'Your GemData profile and account posture.', [
    ['label' => 'Settings', 'href' => base_url('user/settings.php'), 'copy' => 'Review account preferences', 'icon' => 'settings'],
    ['label' => 'Wallet', 'href' => base_url('user/fund-wallet.php'), 'copy' => 'Open funding account', 'icon' => 'wallet'],
]);
