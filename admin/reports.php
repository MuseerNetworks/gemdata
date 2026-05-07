<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

require_permission('reports.view');
$reports = app(\GemData\Classes\ReportService::class);
$daily = $reports->dailySeries(14);
$monthly = $reports->monthlySummary();
$providers = $reports->providerPerformance();
$topUsers = $reports->topUsers(10);
$activity = $reports->activity(30);
render_header('Reports', 'admin');
?>
<div class="space-y-6">
    <section class="surface-card p-6">
        <h1 class="text-3xl font-black text-white">Reports & Analytics</h1>
        <div class="grid gap-6 xl:grid-cols-2 mt-6">
            <div class="table-shell"><table><thead><tr class="text-slate-400"><th>Day</th><th>Txns</th><th>Revenue</th><th>Profit</th></tr></thead><tbody><?php foreach ($daily as $row): ?><tr><td><?= e($row['period']); ?></td><td><?= (int) $row['total_transactions']; ?></td><td><?= e(money($row['revenue'])); ?></td><td><?= e(money($row['profit'])); ?></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="table-shell"><table><thead><tr class="text-slate-400"><th>Month</th><th>Txns</th><th>Revenue</th><th>Profit</th></tr></thead><tbody><?php foreach ($monthly as $row): ?><tr><td><?= e($row['period']); ?></td><td><?= (int) $row['total_transactions']; ?></td><td><?= e(money($row['revenue'])); ?></td><td><?= e(money($row['profit'])); ?></td></tr><?php endforeach; ?></tbody></table></div>
        </div>
    </section>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Provider Performance</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Provider</th><th>Total</th><th>Success</th><th>Failed</th><th>Profit</th></tr></thead><tbody><?php foreach ($providers as $row): ?><tr><td><?= e($row['provider_code'] ?: 'unassigned'); ?></td><td><?= (int) $row['total_transactions']; ?></td><td><?= (int) $row['successful_transactions']; ?></td><td><?= (int) $row['failed_transactions']; ?></td><td><?= e(money($row['profit'])); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Top Users</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>User</th><th>Tier</th><th>Txns</th><th>Revenue</th></tr></thead><tbody><?php foreach ($topUsers as $row): ?><tr><td><?= e($row['full_name']); ?></td><td><?= e($row['tier']); ?></td><td><?= (int) $row['transaction_count']; ?></td><td><?= e(money($row['revenue'])); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
    </div>
    <section class="surface-card p-6"><h2 class="text-2xl font-black text-white">Activity Logs</h2><div class="table-shell mt-4"><table><thead><tr class="text-slate-400"><th>Actor</th><th>Action</th><th>Description</th><th>Time</th></tr></thead><tbody><?php foreach ($activity as $row): ?><tr><td><?= e($row['actor_type'] . '#' . $row['actor_id']); ?></td><td><?= e($row['action']); ?></td><td><?= e($row['description']); ?></td><td><?= e($row['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</div>
<?php render_footer(); ?>
