<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/user-page-components.php';
require_user();
render_user_coming_soon_page('Referrals', 'Track referral growth and earnings.', [
    ['label' => 'Upgrade', 'href' => base_url('user/upgrade-request.php'), 'copy' => 'Unlock business tools', 'icon' => 'shield'],
    ['label' => 'Dashboard', 'href' => base_url('user/dashboard.php'), 'copy' => 'Return to overview', 'icon' => 'dashboard'],
]);
