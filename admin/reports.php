<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

require_permission('reports.view');
$reports = app(\GemData\Classes\ReportService::class);
$reportType = (string) ($_GET['report'] ?? 'daily');
$daily = $reports->dailySeries(14);
$monthly = $reports->monthlySummary();
$providers = $reports->providerPerformance();
$topUsers = $reports->topUsers(10);
$activity = $reports->activity(30);
$topServices = $reports->topServices(10);
$failures = $reports->failedBreakdown();
$refunds = $reports->refundReport();
$growth = $reports->userGrowth();
$apiUsage = $reports->apiUsage();
$responseTimes = $reports->providerResponseTimes();
$queue = $reports->queueReadiness();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $datasets = [
        'daily' => $daily,
        'monthly' => $monthly,
        'providers' => $providers,
        'services' => $topServices,
        'failures' => $failures,
        'refunds' => $refunds,
        'growth' => $growth,
        'api' => $apiUsage,
        'response-times' => $responseTimes,
    ];
    $rows = $datasets[$reportType] ?? $daily;
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=gemdata-report-' . preg_replace('/[^a-z0-9-]/', '', $reportType) . '.csv');
    $out = fopen('php://output', 'wb');
    if ($rows !== []) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
    }
    fclose($out);
    exit;
}
render_header('Reports', 'admin');
?>
<div class="space-y-6">
    <section class="surface-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black text-white">Reports & Analytics</h1>
                <p class="mt-2 text-slate-400">Revenue, profit, providers, refunds, failures, API usage, and queue readiness.</p>
            </div>
            <form method="get" class="flex flex-wrap gap-3">
                <select class="rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="report">
                    <?php foreach (['daily','monthly','providers','services','failures','refunds','growth','api','response-times'] as $option): ?>
                        <option value="<?= e($option); ?>"<?= $reportType === $option ? ' selected' : ''; ?>><?= e(ucwords(str_replace('-', ' ', $option))); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="secondary-action" type="submit" name="export" value="csv">Export CSV</button>
            </form>
        </div>
        <div class="grid gap-4 md:grid-cols-3 mt-6">
            <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><p class="text-slate-400">Pending Queue</p><p class="mt-2 text-2xl font-black text-white"><?= (int) $queue['pending_transactions']; ?></p></div>
            <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><p class="text-slate-400">Stale Pending</p><p class="mt-2 text-2xl font-black text-white"><?= (int) $queue['stale_pending']; ?></p></div>
            <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4"><p class="text-slate-400">Webhook Dead Letters</p><p class="mt-2 text-2xl font-black text-white"><?= (int) $queue['webhook_dead_letters']; ?></p></div>
        </div>
        <div class="grid gap-6 xl:grid-cols-2 mt-6">
            <div class="table-shell"><table><thead><tr class="text-slate-400"><th>Day</th><th>Txns</th><th>Revenue</th><th>Profit</th></tr></thead><tbody><?php foreach ($daily as $row): ?><tr><td><?= e($row['period']); ?></td><td><?= (int) $row['total_transactions']; ?></td><td><?= e(money($row['revenue'])); ?></td><td><?= e(money($row['profit'])); ?></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="table-shell"><table><thead><tr class="text-slate-400"><th>Month</th><th>Txns</th><th>Revenue</th><th>Profit</th></tr></thead><tbody><?php foreach ($monthly as $row): ?><tr><td><?= e($row['period']); ?></td><td><?= (int) $row['total_transactions']; ?></td><td><?= e(money($row['revenue'])); ?></td><td><?= e(money($row['profit'])); ?></td></tr><?php endforeach; ?></tbody></table></div>
        </div>
    </section>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Provider Performance</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Provider</th><th>Total</th><th>Success</th><th>Failed</th><th>Profit</th></tr></thead><tbody><?php foreach ($providers as $row): ?><tr><td><?= e($row['provider_code'] ?: 'unassigned'); ?></td><td><?= (int) $row['total_transactions']; ?></td><td><?= (int) $row['successful_transactions']; ?></td><td><?= (int) $row['failed_transactions']; ?></td><td><?= e(money($row['profit'])); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Top Users</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>User</th><th>Tier</th><th>Txns</th><th>Revenue</th></tr></thead><tbody><?php foreach ($topUsers as $row): ?><tr><td><?= e($row['full_name']); ?></td><td><?= e($row['tier']); ?></td><td><?= (int) $row['transaction_count']; ?></td><td><?= e(money($row['revenue'])); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
    </div>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Top Services</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Service</th><th>Txns</th><th>Revenue</th><th>Profit</th></tr></thead><tbody><?php foreach ($topServices as $row): ?><tr><td><?= e($row['name']); ?></td><td><?= (int) $row['total_transactions']; ?></td><td><?= e(money($row['revenue'])); ?></td><td><?= e(money($row['profit'])); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Failed Service Breakdown</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Service</th><th>Failure</th><th>Total</th></tr></thead><tbody><?php foreach ($failures as $row): ?><tr><td><?= e($row['name']); ?></td><td><?= e($row['failure_code']); ?></td><td><?= (int) $row['total']; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Refund Report</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Day</th><th>Refunds</th><th>Amount</th></tr></thead><tbody><?php foreach ($refunds as $row): ?><tr><td><?= e($row['period']); ?></td><td><?= (int) $row['refunds']; ?></td><td><?= e(money($row['amount'])); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">User Growth</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Day</th><th>Users</th></tr></thead><tbody><?php foreach ($growth as $row): ?><tr><td><?= e($row['period']); ?></td><td><?= (int) $row['users']; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">API Usage</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>User</th><th>Date</th><th>Requests</th><th>Volume</th></tr></thead><tbody><?php foreach ($apiUsage as $row): ?><tr><td><?= e($row['full_name']); ?></td><td><?= e($row['usage_date']); ?></td><td><?= (int) $row['request_count']; ?></td><td><?= e(money($row['volume_amount'])); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Provider Response Times</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Provider</th><th>Attempts</th><th>Avg MS</th><th>Success</th><th>Failed</th></tr></thead><tbody><?php foreach ($responseTimes as $row): ?><tr><td><?= e($row['provider_code'] ?: 'unassigned'); ?></td><td><?= (int) $row['attempts']; ?></td><td><?= number_format((float) $row['avg_response_ms'], 0); ?></td><td><?= (int) $row['successful_attempts']; ?></td><td><?= (int) $row['failed_attempts']; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
    </div>
    <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Activity Logs</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Actor</th><th>Action</th><th>Description</th><th>Time</th></tr></thead><tbody><?php foreach ($activity as $row): ?><tr><td><?= e($row['actor_type'] . '#' . $row['actor_id']); ?></td><td><?= e($row['action']); ?></td><td><?= e($row['description']); ?></td><td><?= e($row['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</div>
<?php render_footer(); ?>
