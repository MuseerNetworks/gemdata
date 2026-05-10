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

render_header('Admin Dashboard', 'admin');
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="surface-card p-5">
            <p class="text-slate-400">System Wallet</p>
            <p class="mt-2 text-3xl font-black text-white"><?= e(money($overview['system_wallet_balance'])); ?></p>
            <p class="mt-2 text-xs text-slate-400"><?= (int) $overview['total_users']; ?> active user records</p>
        </div>
        <div class="surface-card p-5">
            <p class="text-slate-400">Pending Queue</p>
            <p class="mt-2 text-3xl font-black text-white"><?= count($pendingQueue); ?></p>
            <p class="mt-2 text-xs text-slate-400">Latest items waiting for action</p>
        </div>
        <div class="surface-card p-5">
            <p class="text-slate-400">Failed Transactions</p>
            <p class="mt-2 text-3xl font-black text-rose-300"><?= (int) $overview['failed_transactions']; ?></p>
            <p class="mt-2 text-xs text-slate-400"><?= count($recentFailures); ?> recent exceptions surfaced below</p>
        </div>
        <div class="surface-card p-5">
            <p class="text-slate-400">Success Rate</p>
            <p class="mt-2 text-3xl font-black text-emerald-300"><?= e(number_format($successRate, 1)); ?>%</p>
            <p class="mt-2 text-xs text-slate-400"><?= (int) $overview['success_transactions']; ?> successful settled txns</p>
        </div>
        <div class="surface-card p-5">
            <p class="text-slate-400">Profit</p>
            <p class="mt-2 text-3xl font-black text-cyan-300"><?= e(money($overview['profit'])); ?></p>
            <p class="mt-2 text-xs text-slate-400">Revenue <?= e(money($overview['revenue'])); ?></p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
        <section class="surface-card p-6">
            <div class="dashboard-section-header dashboard-section-header-start">
                <div>
                    <p class="eyebrow">Queue</p>
                    <h2 class="mt-2 text-2xl font-black text-white">Pending transaction queue</h2>
                    <p class="mt-2 text-slate-400">Use this as the first triage surface before diving into the full transactions page.</p>
                </div>
                <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/transactions.php?status=pending')); ?>">Open queue</a>
            </div>
            <div class="table-shell mt-5">
                <table>
                    <thead><tr class="text-slate-400"><th>Reference</th><th>User</th><th>Service</th><th>Amount</th><th>When</th></tr></thead>
                    <tbody>
                    <?php if ($pendingQueue === []): ?>
                        <tr><td colspan="5" class="text-slate-400">No pending transactions right now. Queue pressure is low.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pendingQueue as $row): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= e($row['reference']); ?></td>
                                <td><?= e($row['full_name']); ?></td>
                                <td><?= e($row['service_name']); ?></td>
                                <td><?= e(money($row['amount'])); ?></td>
                                <td><?= e($row['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card p-6">
            <div class="dashboard-section-header dashboard-section-header-start">
                <div>
                    <p class="eyebrow">Exceptions</p>
                    <h2 class="mt-2 text-2xl font-black text-white">Recent failures</h2>
                    <p class="mt-2 text-slate-400">Surface the most recent failed transactions for quick review and escalation.</p>
                </div>
                <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/transactions.php?status=failed')); ?>">View failures</a>
            </div>
            <div class="space-y-3 mt-5">
                <?php if ($recentFailures === []): ?>
                    <div class="empty-state"><div><strong>No recent failures</strong><span>Recent failed transactions will appear here when the system detects exceptions.</span></div></div>
                <?php else: ?>
                    <?php foreach ($recentFailures as $row): ?>
                        <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-white"><?= e($row['service_name']); ?> <span class="status-chip status-failed ml-2">Failed</span></p>
                                    <p class="mt-1 text-sm text-slate-400"><?= e($row['full_name']); ?> | <?= e($row['reference']); ?></p>
                                    <p class="mt-1 text-xs text-slate-500"><?= e($row['provider_code'] ?? 'mock_main'); ?> | <?= e($row['created_at']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-white"><?= e(money($row['amount'])); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-[0.95fr,1.05fr]">
        <section class="surface-card p-6">
            <div class="dashboard-section-header dashboard-section-header-start">
                <div>
                    <p class="eyebrow">Provider Health</p>
                    <h2 class="mt-2 text-2xl font-black text-white">Balances and alerts</h2>
                    <p class="mt-2 text-slate-400">Low-balance and provider-state signals stay visible here before they become transaction failures.</p>
                </div>
                <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/providers.php')); ?>">Manage providers</a>
            </div>
            <div class="space-y-3 mt-5">
                <?php foreach ($providerHealth as $provider): ?>
                    <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold text-white"><?= e($provider['name']); ?></p>
                                <p class="mt-1 text-xs text-slate-400"><?= e($provider['code']); ?> | <span class="status-chip status-<?= e($provider['status']); ?>"><?= e(ucfirst($provider['status'])); ?></span></p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold <?= $provider['is_low'] ? 'text-amber-300' : 'text-white'; ?>"><?= e(money($provider['balance'])); ?></p>
                                <p class="text-xs text-slate-400">Threshold <?= e(money($provider['threshold'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="surface-card p-6">
            <div class="dashboard-section-header dashboard-section-header-start">
                <div>
                    <p class="eyebrow">System Watch</p>
                    <h2 class="mt-2 text-2xl font-black text-white">Alerts and top users</h2>
                    <p class="mt-2 text-slate-400">Keep administrative alerts visible alongside the highest-activity accounts.</p>
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2 mt-5">
                <div class="space-y-3">
                    <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                        <p class="text-sm text-slate-400">Maintenance mode</p>
                        <p class="mt-1 font-semibold text-white"><?= $settings->bool('maintenance_mode') ? 'Enabled' : 'Disabled'; ?></p>
                    </div>
                    <?php if ($providerAlerts === []): ?>
                        <div class="rounded-xl border border-emerald-300/20 bg-emerald-500/10 p-4 text-emerald-100">No low-balance provider alerts right now.</div>
                    <?php else: ?>
                        <?php foreach ($providerAlerts as $alert): ?>
                            <div class="rounded-xl border border-amber-300/20 bg-amber-500/10 p-4 text-amber-100">
                                <?= e($alert['name']); ?> balance is <?= e(money($alert['balance'])); ?>, below threshold <?= e(money($alert['threshold'])); ?>.
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="space-y-3">
                    <?php foreach ($topUsers as $row): ?>
                        <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-white"><?= e($row['full_name']); ?></p>
                                    <p class="text-sm text-slate-400"><?= e($row['email']); ?> | <?= e($row['tier']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-slate-400"><?= (int) $row['transaction_count']; ?> txns</p>
                                    <p class="font-semibold text-cyan-300"><?= e(money($row['revenue'])); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>

    <section class="surface-card p-6">
        <div class="dashboard-section-header dashboard-section-header-start">
            <div>
                <p class="eyebrow">Trends</p>
                <h2 class="mt-2 text-2xl font-black text-white">Daily revenue and profit</h2>
                <p class="mt-2 text-slate-400">This chart stays lower in the page because it supports the ops story rather than driving it.</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-slate-400">Today transactions</p>
                <p class="text-lg font-bold text-white"><?= (int) $overview['today_transactions']; ?></p>
            </div>
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/transactions.php?status=pending')); ?>">Review pending queue</a>
            <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/providers.php')); ?>">Check providers</a>
            <a class="secondary-action inline-flex items-center justify-center" href="<?= e(base_url('admin/reports.php')); ?>">Open reports</a>
        </div>
        <div class="mt-6 rounded-2xl border border-white/10 bg-slate-900/40 p-4">
            <canvas id="admin-overview-chart" height="76" aria-label="Daily revenue and profit chart"></canvas>
        </div>
    </section>
</div>
<script>
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
                { label: 'Revenue', data: revenue, borderColor: '#22d3ee', backgroundColor: 'rgba(34,211,238,0.12)', tension: 0.35, fill: true },
                { label: 'Profit', data: profit, borderColor: '#34d399', backgroundColor: 'rgba(52,211,153,0.10)', tension: 0.35, fill: true }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#cbd5e1' } } },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.12)' } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.12)' } }
            }
        }
    });
});
</script>
<?php render_footer(); ?>
