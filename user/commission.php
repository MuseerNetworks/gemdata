<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$db = db();
$role = app(\GemData\Classes\UserRoleManager::class)->roleFor($user);
if (!in_array($role, ['reseller', 'api'], true)) {
    redirect(base_url('user/dashboard.php'));
}

$commWallet = new \GemData\Classes\CommissionWallet($db);
$withdrawSvc = new \GemData\Classes\WithdrawalService($db, $commWallet);
$featureFlag = new \GemData\Classes\FeatureFlag($db);

$balance = $commWallet->balance((int) $user['id']);
$totalEarned = $commWallet->totalEarned((int) $user['id']);
$totalWithdrawn = $commWallet->totalWithdrawn((int) $user['id']);
$history = $commWallet->history((int) $user['id'], 20);
$pendingWdr = $withdrawSvc->listByUser((int) $user['id'], 1);
$hasPending = !empty($pendingWdr) && ($pendingWdr[0]['status'] ?? '') === 'pending';
$pendingAmount = $hasPending ? (float) ($pendingWdr[0]['amount'] ?? 0) : 0.0;
$minimumWithdrawal = $withdrawSvc->minimumAmount();
$withdrawEnabled = $featureFlag->enabled('withdrawal_enabled');

render_header('Commission Wallet', 'user');
?>
<div class="space-y-6">
    <div class="stagger-1 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue"><?= $role === 'api' ? 'API Commission' : 'Reseller Reports'; ?></p>
            <h1 class="mt-1 text-2xl font-extrabold text-gem-text">Commission Wallet</h1>
            <p class="text-[14px] text-gem-muted mt-0.5">Track commission earnings, withdrawals, and business progress separately from your main wallet.</p>
        </div>
        <?php if ($withdrawEnabled): ?>
            <?php if ($hasPending): ?>
                <span class="inline-flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-[13px] font-bold text-amber-700">Withdrawal Pending Review</span>
            <?php elseif ($balance >= $minimumWithdrawal): ?>
                <a href="<?= e(base_url('user/withdrawals.php')); ?>" class="primary-action">Request Withdrawal</a>
            <?php else: ?>
                <span class="inline-flex items-center justify-center rounded-xl border border-gem-border bg-gem-gray px-4 py-2.5 text-[13px] font-bold text-gem-muted">Min. <?= e(money($minimumWithdrawal)); ?> balance to withdraw</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <?php
        $cards = [
            ['label' => 'Available Balance', 'value' => money($balance), 'note' => 'Ready to withdraw', 'icon' => 'wallet', 'tone' => 'green'],
            ['label' => 'Total Earned', 'value' => money($totalEarned), 'note' => 'All-time commission', 'icon' => 'profit', 'tone' => 'blue'],
            ['label' => 'Pending Withdrawal', 'value' => money($pendingAmount), 'note' => $hasPending ? 'Awaiting admin review' : 'No active request', 'icon' => 'pending', 'tone' => 'amber'],
            ['label' => 'Total Withdrawn', 'value' => money($totalWithdrawn), 'note' => 'Paid out to you', 'icon' => 'refund', 'tone' => 'amber'],
        ];
        ?>
        <?php foreach ($cards as $card): ?>
            <div class="user-premium-card rounded-2xl p-5 min-h-[9rem] flex flex-col justify-between">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="user-muted-label"><?= e($card['label']); ?></p>
                        <p class="user-metric-value mt-3"><?= e($card['value']); ?></p>
                    </div>
                    <span class="user-icon-box user-icon-<?= e($card['tone']); ?>"><?= icon_svg($card['icon']); ?></span>
                </div>
                <p class="mt-3 text-[13px] font-semibold text-gem-muted"><?= e($card['note']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="user-premium-card rounded-2xl overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gem-border">
            <div>
                <h2 class="text-[16px] font-bold text-gem-text">Commission History</h2>
                <p class="text-[13px] text-gem-muted mt-0.5">Credits and withdrawals from your commission activity.</p>
            </div>
            <a href="<?= e(base_url('user/pricing.php')); ?>" class="text-[13px] font-bold text-gem-blue">View Pricing</a>
        </div>
        <div class="hidden sm:grid grid-cols-5 gap-4 px-5 py-3 user-table-head">
            <div>Date</div><div>Type</div><div>Narration</div><div class="text-right">Amount</div><div class="text-right">Balance After</div>
        </div>
        <div class="divide-y divide-gem-border">
            <?php if ($history === []): ?>
                <div class="user-empty-state">No commission transactions yet. Complete sales to earn commission.</div>
            <?php endif; ?>
            <?php foreach ($history as $row): ?>
                <?php $isCredit = $row['type'] === 'credit'; ?>
                <div class="user-list-row grid-cols-1 sm:grid-cols-5">
                    <div class="text-[12px] text-gem-muted"><?= e(human_datetime((string) $row['created_at'])); ?></div>
                    <div><span class="inline-flex rounded-full <?= $isCredit ? 'bg-green-50 text-gem-green' : 'bg-amber-50 text-amber-700'; ?> px-2.5 py-1 text-[11px] font-bold uppercase"><?= $isCredit ? 'Earned' : 'Withdrawn'; ?></span></div>
                    <div class="text-[13px] font-semibold text-gem-text"><?= e((string) $row['narration']); ?></div>
                    <div class="text-left sm:text-right font-mono text-[13px] font-bold <?= $isCredit ? 'text-gem-green' : 'text-gem-red'; ?>"><?= $isCredit ? '+' : '-'; ?><?= e(money((float) $row['amount'])); ?></div>
                    <div class="text-left sm:text-right font-mono text-[13px] text-gem-muted"><?= e(money((float) $row['balance_after'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php render_footer(); ?>
