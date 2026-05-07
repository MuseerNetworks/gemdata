<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$apiUser = db()->first('SELECT * FROM api_users WHERE user_id = :user_id LIMIT 1', ['user_id' => $user['id']]);
$apiKey = null;
if ($apiUser) {
    $apiKey = db()->first('SELECT * FROM api_keys WHERE api_user_id = :api_user_id LIMIT 1', ['api_user_id' => $apiUser['id']]);
}

render_header('API Access', 'user');
?>
<div class="grid gap-6 lg:grid-cols-2">
    <section class="surface-card p-6" data-search-item data-search="profile api access reseller credentials commission">
        <p class="eyebrow">Profile</p>
        <h1 class="mt-3 text-3xl font-black text-white">Reseller API Access</h1>
        <?php if (!$apiUser): ?>
            <p class="mt-4 text-slate-300">Your account has not been upgraded to API access yet. An admin can enable reseller access and generate credentials for you.</p>
        <?php else: ?>
            <div class="mt-6 space-y-3">
                <div class="rounded-xl bg-slate-900/70 p-4">
                    <p class="text-sm text-slate-400">API Key</p>
                    <p class="mt-2 font-mono text-sm"><?= e($apiKey['api_key'] ?? 'Not generated'); ?></p>
                </div>
                <div class="rounded-xl bg-slate-900/70 p-4">
                    <p class="text-sm text-slate-400">Status</p>
                    <p class="mt-2"><?= e($apiKey['status'] ?? 'Unknown'); ?></p>
                </div>
                <?php if ($secret = flash('generated_api_secret')): ?>
                    <div class="notice notice-success">
                        <strong>New API secret</strong><br>
                        <span class="font-mono text-sm"><?= e($secret); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
    <section class="surface-card p-6">
        <h2 class="text-2xl font-bold text-white">Commission Snapshot</h2>
        <div class="table-shell mt-4">
            <table>
                <thead>
                    <tr class="text-slate-400">
                        <th>Service</th>
                        <th>Rate (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (db()->query('SELECT s.name, COALESCE(cu.rate_percent, cd.rate_percent, 0.00) AS rate_percent FROM services s LEFT JOIN commissions cu ON cu.service_id = s.id AND cu.user_id = :user_id LEFT JOIN commissions cd ON cd.service_id = s.id AND cd.user_id IS NULL ORDER BY s.name', ['user_id' => $user['id']]) as $row): ?>
                        <tr data-search-item data-search="<?= e($row['name'] . ' ' . $row['rate_percent']); ?>">
                            <td><?= e($row['name']); ?></td>
                            <td><?= e((string) $row['rate_percent']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php render_footer(); ?>
