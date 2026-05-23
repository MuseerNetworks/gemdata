<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
app(\GemData\Classes\RoleMiddleware::class)->requireRole($user, 'api');
$userId = (int) $user['id'];
$transactions = db()->safeQuery(
    'SELECT t.*, s.name AS service_name
       FROM transactions t
       JOIN services s ON s.id = t.service_id
      WHERE t.user_id = :uid AND t.channel = "api"
      ORDER BY t.created_at DESC
      LIMIT 50',
    ['uid' => $userId]
);

render_header('Request Logs', 'user');
?>
<div class="space-y-6">
    <div class="stagger-1">
        <h1 class="text-2xl font-extrabold text-gem-text">Request Logs</h1>
        <p class="text-[14px] text-gem-muted mt-0.5">Full history of API channel transactions.</p>
    </div>
    <section class="bg-white rounded-2xl shadow-card border border-gem-border overflow-hidden stagger-2">
        <div class="hidden sm:grid grid-cols-6 gap-4 px-5 py-3 bg-gem-gray border-b border-gem-border text-[11px] font-bold text-gem-muted uppercase tracking-wider">
            <div class="col-span-2">Reference / Service</div><div>Recipient</div><div>Amount</div><div>Status</div><div>Date</div>
        </div>
        <div class="divide-y divide-gem-border">
            <?php if ($transactions === []): ?>
                <div class="px-5 py-8 text-center text-[13px] text-gem-muted">No API transactions found.</div>
            <?php endif; ?>
            <?php foreach ($transactions as $tx): ?>
                <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 sm:gap-4 px-5 py-4 hover:bg-gem-gray/50 transition-colors">
                    <div class="col-span-2"><div class="text-[13px] font-semibold text-gem-text"><?= e($tx['service_name']); ?></div><div class="text-[11px] text-gem-muted font-mono"><?= e($tx['reference']); ?></div></div>
                    <div class="sm:flex sm:items-center"><span class="text-[12px] text-gem-muted"><?= e($tx['recipient']); ?></span></div>
                    <div class="sm:flex sm:items-center"><span class="text-[13px] font-bold text-gem-text font-mono"><?= e(money($tx['amount'])); ?></span></div>
                    <div class="sm:flex sm:items-center"><span class="inline-flex items-center gap-1 bg-amber-50 text-amber-600 text-[11px] font-semibold px-2.5 py-1 rounded-full"><?= e(ucfirst((string) $tx['status'])); ?></span></div>
                    <div class="sm:flex sm:items-center"><span class="text-[12px] text-gem-muted"><?= e(human_datetime((string) $tx['created_at'])); ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php render_footer(); ?>
