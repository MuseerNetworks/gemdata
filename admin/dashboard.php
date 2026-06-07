<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_permission('dashboard.view');
$reportService = app(\GemData\Classes\ReportService::class);
$settings = app(\GemData\Classes\SettingsService::class);
$ops = app(\GemData\Classes\AdminOpsService::class);

$overview = $reportService->overview();
$dailySeries = $reportService->dailySeries(7);
$topUsers = $reportService->topUsers(5);
$opsSummary = $ops->dashboardOpsSummary();
$pendingQueue = $opsSummary['pending_queue'];
$recentFailures = $opsSummary['recent_failures'];
$providerHealth = $opsSummary['provider_health'];
$providerAlerts = array_values(array_filter($providerHealth, static fn(array $provider): bool => $provider['is_low']));

$completedTransactions = max(1, (int) $overview['success_transactions'] + (int) $overview['failed_transactions']);
$successRate = round(((int) $overview['success_transactions'] / $completedTransactions) * 100, 1);
$activeUsersToday = (int) ((db()->first('SELECT COUNT(*) AS total FROM users WHERE DATE(last_login_at) = CURDATE()')['total'] ?? 0));
$pendingCount = (int) ((db()->first('SELECT COUNT(*) AS total FROM transactions WHERE status = "pending"')['total'] ?? 0));
$refundVolume = (float) ((db()->first('SELECT COALESCE(SUM(amount), 0) AS total FROM wallet_transactions WHERE type = "refund" AND DATE(created_at) = CURDATE()')['total'] ?? 0));
$todayRevenue = (float) ((db()->first('SELECT COALESCE(SUM(selling_price), 0) AS total FROM transactions WHERE status = "successful" AND DATE(created_at) = CURDATE()')['total'] ?? 0));
$todayProfit = (float) ((db()->first('SELECT COALESCE(SUM(profit_amount), 0) AS total FROM transactions WHERE status = "successful" AND DATE(created_at) = CURDATE()')['total'] ?? 0));
$readyProviders = array_values(array_filter($providerHealth, static function (array $provider): bool {
    return in_array((string) $provider['status'], ['ready', 'successful', 'active'], true) && !$provider['is_low'];
}));
$providerHealthScore = $providerHealth === [] ? 0 : round((count($readyProviders) / count($providerHealth)) * 100);

$metricCards = [
    ['label' => 'Total Users', 'value' => number_format((int) $overview['total_users']), 'note' => 'Registered accounts', 'icon' => 'users', 'tone' => 'users', 'href' => base_url('admin/users.php'), 'aria' => 'Open user management'],
    ['label' => 'Active Today', 'value' => number_format($activeUsersToday), 'note' => 'Users with login today', 'icon' => 'profile', 'tone' => 'users', 'href' => base_url('admin/users.php'), 'aria' => 'Open user management for active users'],
    ['label' => 'Wallet Liability', 'value' => money((float) $overview['system_wallet_balance']), 'note' => 'Total user wallet balances', 'icon' => 'wallet', 'tone' => 'wallet', 'href' => base_url('admin/wallet.php'), 'aria' => 'Open wallet control'],
    ['label' => 'Revenue Today', 'value' => money($todayRevenue), 'note' => 'Successful sales today', 'icon' => 'revenue', 'tone' => 'revenue', 'href' => base_url('admin/reports.php'), 'aria' => 'Open revenue reports'],
    ['label' => 'Profit Today', 'value' => money($todayProfit), 'note' => 'Successful profit today', 'icon' => 'profit', 'tone' => 'profit', 'href' => base_url('admin/reports.php'), 'aria' => 'Open profit reports'],
    ['label' => 'Successful Txns', 'value' => number_format((int) $overview['success_transactions']), 'note' => number_format($successRate, 1) . '% success rate', 'icon' => 'transactions', 'tone' => 'transactions', 'href' => base_url('admin/transactions.php?status=success'), 'aria' => 'Open successful transactions'],
    ['label' => 'Failed Txns', 'value' => number_format((int) $overview['failed_transactions']), 'note' => count($recentFailures) . ' recent exceptions', 'icon' => 'failed', 'tone' => 'failed', 'href' => base_url('admin/transactions.php?status=failed'), 'aria' => 'Open failed transactions'],
    ['label' => 'Pending Txns', 'value' => number_format($pendingCount), 'note' => 'Awaiting processing', 'icon' => 'pending', 'tone' => 'pending', 'href' => base_url('admin/transactions.php?status=pending'), 'aria' => 'Open pending transactions'],
    ['label' => 'Refund Volume', 'value' => money($refundVolume), 'note' => 'Refunded today', 'icon' => 'refund', 'tone' => 'wallet', 'href' => base_url('admin/wallet.php'), 'aria' => 'Open refund and wallet logs'],
    ['label' => 'Provider Health', 'value' => $providerHealthScore . '%', 'note' => count($providerHealth) . ' monitored providers', 'icon' => 'server', 'tone' => 'providers', 'href' => base_url('admin/providers.php'), 'aria' => 'Open provider management'],
    ['label' => 'Low Balance Alerts', 'value' => number_format(count($providerAlerts)), 'note' => 'Provider balance warnings', 'icon' => 'notification', 'tone' => 'alerts', 'href' => base_url('admin/alerts.php'), 'aria' => 'Open alerts'],
];

$metricToneClasses = [
    'users' => 'from-blue-600 to-sky-400 shadow-blue-500/20',
    'wallet' => 'from-green-600 to-emerald-400 shadow-green-500/20',
    'revenue' => 'from-emerald-600 to-teal-400 shadow-emerald-500/20',
    'profit' => 'from-lime-600 to-lime-400 shadow-lime-500/20',
    'transactions' => 'from-indigo-600 to-violet-400 shadow-indigo-500/20',
    'failed' => 'from-red-600 to-rose-400 shadow-red-500/20',
    'pending' => 'from-amber-600 to-yellow-400 shadow-amber-500/20',
    'alerts' => 'from-orange-600 to-orange-400 shadow-orange-500/20',
    'providers' => 'from-purple-600 to-violet-400 shadow-purple-500/20',
];

render_header('Admin Dashboard', 'admin');
?>
<script nonce="<?= e(csp_nonce()); ?>" src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">Operations Center</p>
            <h1 class="mt-1 text-2xl font-extrabold text-gem-text">Admin Dashboard</h1>
            <p class="mt-1 text-[14px] text-gem-muted">Monitor wallet liability, provider health, revenue, and transaction pressure.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="inline-flex items-center justify-center gap-2 rounded-xl border border-gem-border bg-white px-4 py-2.5 text-[13px] font-bold text-gem-text shadow-card hover:bg-gem-gray" href="<?= e(base_url('admin/transactions.php?status=pending')); ?>">Review Queue</a>
            <a class="inline-flex items-center justify-center gap-2 rounded-xl bg-gem-blue px-4 py-2.5 text-[13px] font-bold text-white shadow-panel hover:bg-gem-blueDk" href="<?= e(base_url('admin/providers.php')); ?>">Manage Providers</a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($metricCards as $card): ?>
            <?php $iconTone = $metricToneClasses[$card['tone']] ?? 'from-slate-600 to-slate-400 shadow-slate-500/20'; ?>
            <a class="admin-click-card admin-metric-card group relative flex min-h-[9.25rem] flex-col justify-between gap-4 overflow-hidden rounded-2xl border border-gem-border bg-white p-5 text-gem-text no-underline shadow-card transition-all duration-200 hover:-translate-y-1 hover:border-gem-blue/30 hover:shadow-panel focus-visible:outline focus-visible:outline-4 focus-visible:outline-gem-blue/20" href="<?= e($card['href']); ?>" aria-label="<?= e($card['aria']); ?>">
                <div class="relative z-[1] flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="admin-metric-label m-0 text-[12px] font-extrabold uppercase tracking-wide text-gem-muted"><?= e($card['label']); ?></p>
                        <p class="admin-metric-value mt-3 max-w-full break-words font-mono text-[clamp(1.45rem,2.2vw,2rem)] font-black leading-tight text-gem-text"><?= e($card['value']); ?></p>
                    </div>
                    <div class="admin-icon-box admin-icon-<?= e($card['tone']); ?> inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br <?= e($iconTone); ?> text-white shadow-lg">
                        <?= icon_svg($card['icon']); ?>
                    </div>
                </div>
                <div class="admin-card-footer relative z-[1] flex items-center justify-between gap-3">
                    <p class="min-w-0 text-[13px] font-semibold text-gem-muted"><?= e($card['note']); ?></p>
                    <span class="admin-card-chev inline-flex h-5 w-5 shrink-0 text-gem-muted transition-transform duration-200 group-hover:translate-x-1 group-hover:text-gem-blue"><?= icon_svg('chevron'); ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.1fr,0.9fr]">
        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">Queue</p>
                    <h2 class="mt-1 text-[18px] font-extrabold text-gem-text">Pending Transaction Queue</h2>
                    <p class="mt-1 text-[13px] text-gem-muted">First triage surface before the full transaction command center.</p>
                </div>
                <a class="inline-flex items-center justify-center rounded-xl border border-gem-border px-3 py-2 text-[12px] font-bold text-gem-blue hover:bg-gem-blueLt" href="<?= e(base_url('admin/transactions.php?status=pending')); ?>">Open Queue</a>
            </div>
            <div class="mt-4 overflow-hidden rounded-2xl border border-gem-border">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gem-border text-left text-[13px]">
                        <thead class="bg-gem-gray text-[11px] uppercase tracking-widest text-gem-muted">
                            <tr><th class="px-4 py-3">Reference</th><th class="px-4 py-3">User</th><th class="px-4 py-3">Service</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">When</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gem-border bg-white text-gem-text">
                        <?php if ($pendingQueue === []): ?>
                            <tr><td colspan="5" class="px-4 py-5 text-center text-gem-muted">No pending transactions right now. Queue pressure is low.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pendingQueue as $row): ?>
                                <tr>
                                    <td class="px-4 py-3 font-mono text-[12px]"><?= e($row['reference']); ?></td>
                                    <td class="px-4 py-3 font-semibold"><?= e($row['full_name']); ?></td>
                                    <td class="px-4 py-3"><?= e($row['service_name']); ?></td>
                                    <td class="px-4 py-3 font-mono font-bold"><?= e(money($row['amount'])); ?></td>
                                    <td class="px-4 py-3 text-gem-muted"><?= e($row['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-gem-red">Exceptions</p>
                    <h2 class="mt-1 text-[18px] font-extrabold text-gem-text">Recent Failures</h2>
                    <p class="mt-1 text-[13px] text-gem-muted">Failed transactions surfaced for quick operations review.</p>
                </div>
                <a class="inline-flex items-center justify-center rounded-xl border border-gem-border px-3 py-2 text-[12px] font-bold text-gem-blue hover:bg-gem-blueLt" href="<?= e(base_url('admin/transactions.php?status=failed')); ?>">View Failures</a>
            </div>
            <div class="mt-4 space-y-3">
                <?php if ($recentFailures === []): ?>
                    <div class="rounded-2xl border border-green-100 bg-green-50 p-4 text-[13px] font-semibold text-gem-green">No recent failures detected.</div>
                <?php else: ?>
                    <?php foreach ($recentFailures as $row): ?>
                        <div class="rounded-2xl border border-gem-border bg-gem-gray p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-bold text-gem-text"><?= e($row['service_name']); ?> <span class="ml-2 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold uppercase text-gem-red">Failed</span></p>
                                    <p class="mt-1 truncate text-[12px] text-gem-muted"><?= e($row['full_name']); ?> | <?= e($row['reference']); ?></p>
                                    <p class="mt-1 text-[11px] text-gem-muted"><?= e($row['provider_code'] ?? 'unassigned'); ?> | <?= e($row['created_at']); ?></p>
                                </div>
                                <p class="shrink-0 font-mono font-bold text-gem-text"><?= e(money($row['amount'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="grid gap-4 xl:grid-cols-[0.95fr,1.05fr]">
        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">Provider Health</p>
                    <h2 class="mt-1 text-[18px] font-extrabold text-gem-text">Balances And Alerts</h2>
                    <p class="mt-1 text-[13px] text-gem-muted">Provider state and low-balance signals before they become failures.</p>
                </div>
                <a class="inline-flex items-center justify-center rounded-xl border border-gem-border px-3 py-2 text-[12px] font-bold text-gem-blue hover:bg-gem-blueLt" href="<?= e(base_url('admin/providers.php')); ?>">Manage</a>
            </div>
            <div class="mt-4 space-y-3">
                <?php if ($providerHealth === []): ?>
                    <div class="rounded-2xl border border-gem-border bg-gem-gray p-4 text-[13px] text-gem-muted">No providers configured yet.</div>
                <?php else: ?>
                    <?php foreach ($providerHealth as $provider): ?>
                        <div class="rounded-2xl border border-gem-border bg-gem-gray p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-bold text-gem-text"><?= e($provider['name']); ?></p>
                                    <p class="mt-1 text-[11px] text-gem-muted"><?= e($provider['code']); ?> | <span class="rounded-full bg-white px-2 py-0.5 font-bold uppercase"><?= e($provider['status']); ?></span></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-mono font-bold <?= $provider['is_low'] ? 'text-gem-orange' : 'text-gem-text'; ?>"><?= e(money($provider['balance'])); ?></p>
                                    <p class="text-[11px] text-gem-muted">Threshold <?= e(money($provider['threshold'])); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">System Watch</p>
                <h2 class="mt-1 text-[18px] font-extrabold text-gem-text">Alerts And Top Users</h2>
                <p class="mt-1 text-[13px] text-gem-muted">Administrative alerts alongside highest-activity accounts.</p>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="space-y-3">
                    <div class="rounded-2xl border border-gem-border bg-gem-gray p-4">
                        <p class="text-[12px] font-semibold text-gem-muted">Maintenance Mode</p>
                        <p class="mt-1 text-[15px] font-extrabold text-gem-text"><?= $settings->bool('maintenance_mode') ? 'Enabled' : 'Disabled'; ?></p>
                    </div>
                    <?php if ($providerAlerts === []): ?>
                        <div class="rounded-2xl border border-green-100 bg-green-50 p-4 text-[13px] font-semibold text-gem-green">No low-balance provider alerts right now.</div>
                    <?php else: ?>
                        <?php foreach ($providerAlerts as $alert): ?>
                            <div class="rounded-2xl border border-orange-100 bg-orange-50 p-4 text-[13px] font-semibold text-gem-orange">
                                <?= e($alert['name']); ?> balance is <?= e(money($alert['balance'])); ?>, below threshold <?= e(money($alert['threshold'])); ?>.
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="space-y-3">
                    <?php foreach ($topUsers as $row): ?>
                        <div class="rounded-2xl border border-gem-border bg-gem-gray p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-gem-text"><?= e($row['full_name']); ?></p>
                                    <p class="truncate text-[12px] text-gem-muted"><?= e($row['email']); ?> | <?= e($row['tier']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[11px] text-gem-muted"><?= (int) $row['transaction_count']; ?> txns</p>
                                    <p class="font-mono font-bold text-gem-blue"><?= e(money($row['revenue'])); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>

    <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">Trends</p>
                <h2 class="mt-1 text-[18px] font-extrabold text-gem-text">Daily Revenue And Profit</h2>
                <p class="mt-1 text-[13px] text-gem-muted">Seven-day operational trend for successful transactions.</p>
            </div>
            <div class="rounded-xl bg-gem-blueLt px-4 py-2 text-right">
                <p class="text-[11px] font-bold uppercase text-gem-blue">Today Txns</p>
                <p class="font-mono text-lg font-extrabold text-gem-text"><?= (int) $overview['today_transactions']; ?></p>
            </div>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-3">
            <a class="inline-flex items-center justify-center rounded-xl border border-gem-border bg-white px-4 py-2.5 text-[13px] font-bold text-gem-text hover:bg-gem-gray" href="<?= e(base_url('admin/transactions.php?status=pending')); ?>">Review Pending Queue</a>
            <a class="inline-flex items-center justify-center rounded-xl border border-gem-border bg-white px-4 py-2.5 text-[13px] font-bold text-gem-text hover:bg-gem-gray" href="<?= e(base_url('admin/providers.php')); ?>">Check Providers</a>
            <a class="inline-flex items-center justify-center rounded-xl border border-gem-border bg-white px-4 py-2.5 text-[13px] font-bold text-gem-text hover:bg-gem-gray" href="<?= e(base_url('admin/reports.php')); ?>">Open Reports</a>
        </div>
        <div class="mt-5 h-[260px] rounded-2xl border border-gem-border bg-gem-gray p-4">
            <canvas id="admin-overview-chart" aria-label="Daily revenue and profit chart"></canvas>
        </div>
    </section>
</div>
<script nonce="<?= e(csp_nonce()); ?>">
document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('admin-overview-chart');
    if (!canvas || typeof Chart === 'undefined') return;
    var labels = <?= json_encode(array_map(static fn(array $row): string => (string) $row['period'], $dailySeries)); ?>;
    var revenue = <?= json_encode(array_map(static fn(array $row): float => (float) $row['revenue'], $dailySeries)); ?>;
    var profit = <?= json_encode(array_map(static fn(array $row): float => (float) $row['profit'], $dailySeries)); ?>;
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { label: 'Revenue', data: revenue, borderColor: '#1B4DFF', backgroundColor: 'rgba(27,77,255,0.10)', tension: 0.35, fill: true },
                { label: 'Profit', data: profit, borderColor: '#00C6AE', backgroundColor: 'rgba(0,198,174,0.10)', tension: 0.35, fill: true }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#64748B' } } },
            scales: {
                x: { ticks: { color: '#64748B' }, grid: { color: 'rgba(226,232,240,0.8)' } },
                y: { ticks: { color: '#64748B' }, grid: { color: 'rgba(226,232,240,0.8)' } }
            }
        }
    });
});
</script>
<?php render_footer(); ?>
