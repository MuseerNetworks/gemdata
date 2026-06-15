<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$countRow = db()->first('SELECT COUNT(*) AS total FROM transactions WHERE user_id = :user_id', ['user_id' => $user['id']]);
$pagination = pagination_meta((int) ($countRow['total'] ?? 0), $page, $perPage);
$rows = db()->query(
    'SELECT t.*, s.name AS service_name FROM transactions t
     INNER JOIN services s ON s.id = t.service_id
     WHERE t.user_id = :user_id ORDER BY t.id DESC
     LIMIT ' . $pagination['offset'] . ', ' . $pagination['per_page'],
    ['user_id' => $user['id']]
);

render_header('Transactions', 'user');
?>
<div class="space-y-6">
    <div class="stagger-1">
        <h1 class="text-2xl font-extrabold text-gem-text">Transactions</h1>
        <p class="text-[14px] text-gem-muted mt-0.5">Track wallet purchases, API orders, and service activity.</p>
    </div>

    <section class="stagger-2">
        <div class="user-premium-card bg-white rounded-2xl shadow-card border border-gem-border overflow-hidden">
            <div class="user-table-head hidden sm:grid grid-cols-6 gap-4 px-5 py-3 bg-gem-gray border-b border-gem-border text-[11px] font-bold text-gem-muted uppercase tracking-wider">
                <div class="col-span-2">Service / Description</div>
                <div>Amount</div>
                <div>Status</div>
                <div>Channel</div>
                <div>Date</div>
            </div>
            <div class="divide-y divide-gem-border">
                <?php if ($rows === []): ?>
                    <div class="user-empty-state px-5 py-8 text-center text-[13px] text-gem-muted">No transactions yet. Fund your wallet and make your first purchase.</div>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $status = (string) $row['status'];
                    $statusColor = $status === 'successful' ? 'green' : ($status === 'failed' ? 'red' : 'amber');
                    $channel = strtolower((string) ($row['channel'] ?? ''));
                    $serviceName = (string) ($row['service_name'] ?? 'Transaction');
                    $isCredit = str_contains($channel, 'fund') || str_contains(strtolower($serviceName), 'fund');
                    $amountPrefix = $isCredit ? '+' : '-';
                    $amountClass = $isCredit ? 'transaction-amount-credit' : 'transaction-amount-debit';
                    ?>
                    <div class="transaction-list-card user-list-row relative grid grid-cols-1 sm:grid-cols-6 gap-2 sm:gap-4 px-5 py-4" data-search-item data-search="<?= e($row['reference'] . ' ' . $row['service_name'] . ' ' . $row['status'] . ' ' . $row['channel'] . ' ' . $row['recipient']); ?>">
                        <div class="col-span-2 flex items-center gap-3 pr-16 sm:pr-0">
                            <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0 text-blue-600"><?= icon_svg('services'); ?></div>
                            <div>
                                <div class="text-[13px] font-semibold text-gem-text"><?= e($row['service_name']); ?></div>
                                <div class="text-[11px] text-gem-muted"><?= e($row['recipient']); ?> · <?= e($row['reference']); ?></div>
                                <?php if ($status === 'successful'): ?>
                                    <a class="mt-1 inline-flex text-[11px] font-bold text-gem-blue hover:underline" href="<?= e(base_url('user/receipt.php?reference=' . rawurlencode((string) $row['reference']))); ?>">View Receipt</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="sm:flex sm:items-center absolute sm:static right-5 top-4"><span class="text-[13px] font-bold font-mono <?= e($amountClass); ?>"><?= e($amountPrefix . money((float) $row['amount'])); ?></span></div>
                        <div class="sm:flex sm:items-center">
                            <span class="inline-flex items-center gap-1 bg-<?= e($statusColor); ?>-50 text-<?= e($statusColor === 'green' ? 'gem-green' : ($statusColor === 'red' ? 'gem-red' : 'amber-600')); ?> text-[11px] font-semibold px-2.5 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-<?= e($statusColor === 'green' ? 'gem-green' : ($statusColor === 'red' ? 'gem-red' : 'amber-500')); ?>"></span>
                                <?= e(ucfirst($status)); ?>
                            </span>
                        </div>
                        <div class="sm:flex sm:items-center"><span class="text-[12px] text-gem-muted"><?= e(ucfirst((string) $row['channel'])); ?></span></div>
                        <div class="sm:flex sm:items-center"><span class="text-[12px] text-gem-muted" title="<?= e($row['created_at']); ?>"><?= e(human_datetime((string) $row['created_at'])); ?></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="mt-6">
            <?php render_pagination($pagination, 'user/transactions.php'); ?>
        </div>
    </section>
</div>
<?php render_footer(); ?>
