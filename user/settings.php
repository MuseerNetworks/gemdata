<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$role = app(\GemData\Classes\UserRoleManager::class)->roleFor($user);
$walletBalance = app(\GemData\Classes\Wallet::class)->balance((int) $user['id']);

render_header('Settings', 'user');
?>
<div class="space-y-6">
    <div class="stagger-1">
        <h1 class="text-2xl font-extrabold text-gem-text">Settings</h1>
        <p class="text-[14px] text-gem-muted mt-0.5">Profile, security posture, and account preferences.</p>
    </div>

    <section class="user-premium-card bg-white rounded-2xl shadow-card border border-gem-border p-5 stagger-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php
            $fields = [
                'Full Name' => $user['full_name'] ?? '',
                'Email Address' => $user['email'] ?? '',
                'Phone Number' => $user['phone'] ?? '',
                'Wallet Balance' => money($walletBalance),
                'Account Type' => ucfirst($role),
                'Date Joined' => human_datetime($user['created_at'] ?? null),
            ];
            ?>
            <?php foreach ($fields as $label => $value): ?>
                <label class="text-[12px] font-semibold text-gem-muted uppercase tracking-wider">
                    <?= e($label); ?>
                    <input class="mt-1.5 w-full rounded-xl bg-gem-gray border border-gem-border px-4 py-3 text-[13px] text-gem-text" value="<?= e((string) $value); ?>" readonly>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="flex flex-wrap gap-2 mt-5">
            <?php if ($role === 'api'): ?>
                <a class="bg-gem-blue hover:bg-gem-blueDk text-white text-[13px] font-bold px-4 py-2.5 rounded-xl shadow-panel" href="<?= e(base_url('user/api-keys.php')); ?>">Open API Keys</a>
            <?php else: ?>
                <a class="bg-gem-blue hover:bg-gem-blueDk text-white text-[13px] font-bold px-4 py-2.5 rounded-xl shadow-panel" href="<?= e(base_url('user/upgrade-request.php')); ?>">Upgrade Account</a>
            <?php endif; ?>
            <a class="border border-gem-border text-gem-text text-[13px] font-bold px-4 py-2.5 rounded-xl hover:bg-gem-gray" href="<?= e(base_url('user/notifications.php')); ?>">Manage Alerts</a>
        </div>
    </section>
</div>
<?php render_footer(); ?>
