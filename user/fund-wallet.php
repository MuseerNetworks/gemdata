<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$payments = app(\GemData\Classes\PaymentGatewayService::class);
$fundingAccounts = app(\GemData\Classes\PaystackDedicatedAccountService::class);

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action !== 'generate_funding_account') {
        http_response_code(400);
        exit;
    }

    try {
        $existing = $fundingAccounts->getForUser((int) $user['id']);
        $account = $fundingAccounts->ensureAccountForUser(
            (int) $user['id'],
            !$existing || ($existing['status'] ?? '') !== 'assigned'
        );
        if (($account['status'] ?? '') === 'assigned') {
            flash('success', 'Your funding account is ready.');
        } elseif (($account['status'] ?? '') === 'pending') {
            flash('success', 'We are preparing your funding account.');
        } else {
            flash('error', 'We could not generate your funding account. Please contact support or try again.');
        }
    } catch (Throwable $throwable) {
        app_logger()->warning('Funding account generation failed.', [
            'user_id' => $user['id'] ?? null,
            'error' => $throwable->getMessage(),
        ]);
        flash('error', 'We could not generate your funding account. Please contact support or try again.');
    }

    redirect(base_url('user/fund-wallet.php'));
}

$recentFunding = $payments->userFundingRequests((int) $user['id']);
$walletBalance = app(\GemData\Classes\Wallet::class)->balance((int) $user['id']);
$account = $fundingAccounts->getForUser((int) $user['id']);
$status = (string) ($account['status'] ?? '');
$accountNumber = (string) ($account['dedicated_account_number'] ?? '');
$bankName = (string) ($account['bank_name'] ?? '');
$accountName = (string) ($account['account_name'] ?? '');
$fullDetails = trim($bankName . "\n" . $accountNumber . "\n" . $accountName);
$accountAssigned = $status === 'assigned' && $accountNumber !== '';

render_header('Fund Wallet', 'user');
?>
<div class="space-y-6">
    <div class="stagger-1">
        <h1 class="text-2xl font-extrabold text-gem-text">Fund Wallet</h1>
        <p class="text-[14px] text-gem-muted mt-0.5">Use your funding account to add money to your wallet.</p>
    </div>

    <?php if ($message = flash('success')): ?>
        <div class="bg-green-50 border border-green-100 text-gem-green rounded-2xl px-5 py-4 text-[13px] font-semibold"><?= e($message); ?></div>
    <?php endif; ?>
    <?php if ($message = flash('error')): ?>
        <div class="bg-red-50 border border-red-100 text-gem-red rounded-2xl px-5 py-4 text-[13px] font-semibold"><?= e($message); ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 stagger-2">
        <section class="wallet-card rounded-2xl p-5 text-white xl:col-span-2 shadow-panel">
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[13px] font-semibold text-blue-200">Main Wallet Balance</span>
                    <span class="bg-white/15 rounded-lg px-2.5 py-1.5 text-[12px] font-semibold">NGN</span>
                </div>
                <div class="text-[28px] font-extrabold tracking-tight font-mono"><?= e(money($walletBalance)); ?></div>
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <a href="<?= e(base_url('user/dashboard.php')); ?>" class="flex items-center justify-center gap-2 bg-white/15 hover:bg-white/25 rounded-xl py-3 px-2 text-[12px] font-semibold">Dashboard</a>
                    <a href="<?= e(base_url('user/transactions.php')); ?>" class="flex items-center justify-center gap-2 bg-white/15 hover:bg-white/25 rounded-xl py-3 px-2 text-[12px] font-semibold">History</a>
                </div>
            </div>
        </section>

        <section class="xl:col-span-3 user-premium-card bg-white rounded-2xl shadow-card border border-gem-border p-5">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-[16px] font-bold text-gem-text">Your Funding Account</h2>
                    <p class="text-[13px] text-gem-muted mt-0.5">Transfer from any Nigerian bank to this account.</p>
                </div>
                <?php if ($accountAssigned): ?>
                    <span class="inline-flex items-center gap-1 bg-green-50 text-gem-green text-[11px] font-semibold px-2.5 py-1 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-gem-green"></span>Active</span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-600 text-[11px] font-semibold px-2.5 py-1 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Not ready</span>
                <?php endif; ?>
            </div>

            <?php if ($accountAssigned): ?>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="user-premium-card rounded-2xl bg-gem-gray border border-gem-border p-4">
                        <div class="text-[11px] text-gem-muted font-bold uppercase tracking-wider">Bank Name</div>
                        <div class="text-[15px] font-bold text-gem-text mt-1"><?= e($bankName); ?></div>
                    </div>
                    <div class="user-premium-card rounded-2xl bg-gem-gray border border-gem-border p-4">
                        <div class="text-[11px] text-gem-muted font-bold uppercase tracking-wider">Account Number</div>
                        <div class="text-[15px] font-bold text-gem-text font-mono mt-1"><?= e($accountNumber); ?></div>
                    </div>
                    <div class="user-premium-card rounded-2xl bg-gem-gray border border-gem-border p-4">
                        <div class="text-[11px] text-gem-muted font-bold uppercase tracking-wider">Account Name</div>
                        <div class="text-[15px] font-bold text-gem-text mt-1"><?= e($accountName); ?></div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-4">
                    <button class="inline-flex items-center gap-2 bg-gem-blue hover:bg-gem-blueDk text-white text-[13px] font-bold px-4 py-2.5 rounded-xl shadow-panel" type="button" data-copy-value="<?= e($accountNumber); ?>">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        Copy Account Number
                    </button>
                    <button class="inline-flex items-center gap-2 border border-gem-border text-gem-text text-[13px] font-bold px-4 py-2.5 rounded-xl hover:bg-gem-gray" type="button" data-copy-value="<?= e($fullDetails); ?>">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        Copy Full Details
                    </button>
                </div>
                <p class="text-[12px] text-gem-muted mt-4">Webhook wallet crediting remains disabled until the real funding payload is reviewed.</p>
            <?php else: ?>
                <div class="user-empty-state rounded-2xl bg-gem-gray border border-gem-border p-4 text-[13px] text-gem-muted">
                    <?= $status === 'failed' ? 'We could not generate your funding account. Please contact support or try again.' : 'We are preparing your funding account.'; ?>
                </div>
                <form method="post" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="generate_funding_account">
                    <button class="inline-flex items-center gap-2 bg-gem-blue hover:bg-gem-blueDk text-white text-[13px] font-bold px-4 py-2.5 rounded-xl shadow-panel" type="submit">
                        Generate Funding Account
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <section class="stagger-3">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-[16px] font-bold text-gem-text">Funding History</h2>
        </div>
        <div class="user-premium-card bg-white rounded-2xl shadow-card border border-gem-border overflow-hidden">
            <div class="user-table-head hidden sm:grid grid-cols-5 gap-4 px-5 py-3 bg-gem-gray border-b border-gem-border text-[11px] font-bold text-gem-muted uppercase tracking-wider">
                <div class="col-span-2">Reference / Provider</div><div>Amount</div><div>Status</div><div>Date</div>
            </div>
            <div class="divide-y divide-gem-border">
                <?php if ($recentFunding === []): ?>
                    <div class="user-empty-state px-5 py-8 text-center text-[13px] text-gem-muted">No funding transfers yet.</div>
                <?php endif; ?>
                <?php foreach ($recentFunding as $row): ?>
                    <div class="user-list-row grid grid-cols-1 sm:grid-cols-5 gap-2 sm:gap-4 px-5 py-4 hover:bg-gem-gray/50 transition-colors">
                        <div class="col-span-2"><div class="text-[13px] font-semibold text-gem-text font-mono"><?= e((string) $row['reference']); ?></div><div class="text-[11px] text-gem-muted"><?= e(ucwords(str_replace('_', ' ', (string) $row['provider']))); ?></div></div>
                        <div class="sm:flex sm:items-center"><span class="text-[13px] font-bold text-gem-text font-mono"><?= e(money($row['amount'])); ?></span></div>
                        <div class="sm:flex sm:items-center"><span class="inline-flex items-center gap-1 bg-amber-50 text-amber-600 text-[11px] font-semibold px-2.5 py-1 rounded-full"><?= e(ucfirst((string) $row['status'])); ?></span></div>
                        <div class="sm:flex sm:items-center"><span class="text-[12px] text-gem-muted"><?= e(human_datetime((string) $row['created_at'])); ?></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>
<?php render_footer(); ?>
