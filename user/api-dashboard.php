<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
app(\GemData\Classes\RoleMiddleware::class)->requireRole($user, 'api');

$db = db();
$userId = (int) $user['id'];
$apiUser = $db->first('SELECT * FROM api_users WHERE user_id = :uid LIMIT 1', ['uid' => $userId]);
$apiKeys = $db->safeQuery(
    'SELECT ak.*, (SELECT COUNT(*) FROM api_rate_limits WHERE api_key_id = ak.id) AS rate_limit_windows
       FROM api_keys ak
      WHERE ak.api_user_id = :api_user_id
      ORDER BY ak.created_at DESC',
    ['api_user_id' => $apiUser['id'] ?? 0]
);
$stats = $db->first(
    'SELECT
       COUNT(*) AS total_requests,
       SUM(CASE WHEN status = "successful" THEN 1 ELSE 0 END) AS successful,
       SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed,
       SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_count,
       COALESCE(SUM(amount), 0) AS total_volume
     FROM transactions
     WHERE user_id = :uid AND channel = "api"
       AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    ['uid' => $userId]
);
$successRate = (int) ($stats['total_requests'] ?? 0) > 0
    ? round(((int) $stats['successful'] / (int) $stats['total_requests']) * 100, 1)
    : 0;
$recent = $db->safeQuery(
    'SELECT t.*, s.name AS service_name
       FROM transactions t
       JOIN services s ON s.id = t.service_id
      WHERE t.user_id = :uid AND t.channel = "api"
      ORDER BY t.created_at DESC
      LIMIT 5',
    ['uid' => $userId]
);
$wallet = $db->first('SELECT balance FROM wallets WHERE user_id = :uid LIMIT 1', ['uid' => $userId]);
$apiStatus = (string) ($apiUser['status'] ?? 'inactive');

render_header('API Dashboard', 'user');
?>
<div class="space-y-6">
    <div class="stagger-1 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">Developer Workspace</p>
            <h1 class="mt-1 text-2xl font-extrabold text-gem-text">API Dashboard</h1>
            <p class="text-[14px] text-gem-muted mt-0.5">Monitor API usage, keys, wallet readiness, and recent channel activity.</p>
        </div>
        <a href="<?= e(base_url('user/api-keys.php')); ?>" class="primary-action">Manage API Keys</a>
    </div>

    <?php if ($apiStatus === 'inactive'): ?>
        <div class="activate-banner rounded-2xl px-5 py-4 text-[13px] font-semibold text-gem-text">
            Your API account is pending activation. Contact admin before making live API calls.
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php
        $cards = [
            ['label' => 'Wallet Balance', 'value' => money((float) ($wallet['balance'] ?? 0)), 'note' => 'Available API balance', 'icon' => 'wallet', 'tone' => 'green', 'href' => base_url('user/fund-wallet.php')],
            ['label' => 'Requests', 'value' => number_format((int) ($stats['total_requests'] ?? 0)), 'note' => 'Last 30 days', 'icon' => 'code', 'tone' => 'blue', 'href' => base_url('user/api-logs.php')],
            ['label' => 'Success Rate', 'value' => $successRate . '%', 'note' => (int) ($stats['successful'] ?? 0) . ' successful calls', 'icon' => 'chart', 'tone' => 'emerald', 'href' => base_url('user/api-logs.php')],
            ['label' => 'Volume', 'value' => money((float) ($stats['total_volume'] ?? 0)), 'note' => (int) ($stats['pending_count'] ?? 0) . ' pending', 'icon' => 'transactions', 'tone' => 'indigo', 'href' => base_url('user/transactions.php')],
        ];
        ?>
        <?php foreach ($cards as $card): ?>
            <a class="user-premium-card user-premium-link rounded-2xl p-5 min-h-[9rem] flex flex-col justify-between" href="<?= e($card['href']); ?>">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="user-muted-label"><?= e($card['label']); ?></p>
                        <p class="user-metric-value mt-3"><?= e((string) $card['value']); ?></p>
                    </div>
                    <span class="user-icon-box user-icon-<?= e($card['tone']); ?>"><?= icon_svg($card['icon']); ?></span>
                </div>
                <p class="mt-3 text-[13px] font-semibold text-gem-muted"><?= e($card['note']); ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1.05fr,0.95fr]">
        <section class="user-premium-card rounded-2xl p-5">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-[16px] font-bold text-gem-text">API Keys</h2>
                    <p class="text-[13px] text-gem-muted mt-0.5">Masked credentials and key status.</p>
                </div>
                <a class="text-[13px] font-bold text-gem-blue" href="<?= e(base_url('user/api-keys.php')); ?>">Open</a>
            </div>
            <div class="divide-y divide-gem-border">
                <?php if ($apiKeys === []): ?>
                    <div class="user-empty-state">No API keys yet. Generate your first key from API Keys.</div>
                <?php endif; ?>
                <?php foreach ($apiKeys as $key): ?>
                    <?php $masked = substr((string) $key['api_key'], 0, 10) . '****' . substr((string) $key['api_key'], -4); ?>
                    <div class="user-list-row grid-cols-1 sm:grid-cols-[1fr,auto]">
                        <div>
                            <div class="font-mono text-[13px] font-bold text-gem-text"><?= e($masked); ?></div>
                            <div class="text-[12px] text-gem-muted mt-1">Created <?= e(human_datetime((string) $key['created_at'])); ?> · Last used <?= e($key['last_used_at'] ? human_datetime((string) $key['last_used_at']) : 'Never'); ?></div>
                        </div>
                        <span class="h-fit rounded-full <?= $key['status'] === 'active' ? 'bg-green-50 text-gem-green' : 'bg-gem-gray text-gem-muted'; ?> px-2.5 py-1 text-[11px] font-bold uppercase"><?= e((string) $key['status']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="user-premium-card rounded-2xl p-5">
            <h2 class="text-[16px] font-bold text-gem-text mb-4">Developer Shortcuts</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach ([['Request Logs', 'user/api-logs.php', 'transactions', 'indigo'], ['API Documentation', 'docs/api.php', 'server', 'cyan'], ['Fund Wallet', 'user/fund-wallet.php', 'wallet', 'green'], ['Webhooks', 'user/webhooks.php', 'server', 'purple']] as $tool): ?>
                    <a class="user-premium-card user-premium-link rounded-xl p-4 flex items-center gap-3" href="<?= e(base_url($tool[1])); ?>">
                        <span class="user-icon-box user-icon-<?= e($tool[3]); ?> !w-10 !h-10 !rounded-xl"><?= icon_svg($tool[2]); ?></span>
                        <span><strong class="block text-[13px] text-gem-text"><?= e($tool[0]); ?></strong><small class="text-gem-muted">Open module</small></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <section class="user-premium-card rounded-2xl overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gem-border">
            <div>
                <h2 class="text-[16px] font-bold text-gem-text">Recent API Transactions</h2>
                <p class="text-[13px] text-gem-muted mt-0.5">Latest activity from the API channel.</p>
            </div>
            <a class="text-[13px] font-bold text-gem-blue" href="<?= e(base_url('user/api-logs.php')); ?>">View All</a>
        </div>
        <div class="hidden sm:grid grid-cols-6 gap-4 px-5 py-3 user-table-head">
            <div>Reference</div><div>Service</div><div>Recipient</div><div>Amount</div><div>Status</div><div>Date</div>
        </div>
        <div class="divide-y divide-gem-border">
            <?php if ($recent === []): ?>
                <div class="user-empty-state">No API transactions yet.</div>
            <?php endif; ?>
            <?php foreach ($recent as $tx): ?>
                <?php $statusColor = $tx['status'] === 'successful' ? 'green' : ($tx['status'] === 'failed' ? 'red' : 'amber'); ?>
                <div class="user-list-row grid-cols-1 sm:grid-cols-6">
                    <div class="font-mono text-[12px] font-bold text-gem-text"><?= e((string) $tx['reference']); ?></div>
                    <div class="text-[13px] text-gem-text"><?= e((string) $tx['service_name']); ?></div>
                    <div class="text-[12px] text-gem-muted"><?= e((string) $tx['recipient']); ?></div>
                    <div class="font-mono text-[13px] font-bold text-gem-text"><?= e(money((float) $tx['amount'])); ?></div>
                    <div><span class="inline-flex rounded-full bg-<?= e($statusColor); ?>-50 px-2.5 py-1 text-[11px] font-bold text-<?= e($statusColor === 'green' ? 'gem-green' : ($statusColor === 'red' ? 'gem-red' : 'amber-600')); ?>"><?= e(ucfirst((string) $tx['status'])); ?></span></div>
                    <div class="text-[12px] text-gem-muted"><?= e(human_datetime((string) $tx['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php render_footer(); ?>
