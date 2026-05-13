<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user              = require_user();
$payments          = app(\GemData\Classes\PaymentGatewayService::class);
$dedicatedAccounts = app(\GemData\Classes\PaystackDedicatedAccountService::class);
$zenithPay         = app(\GemData\Classes\ZenithPayVirtualAccountService::class);

if (is_post()) {
    verify_csrf();

    // ── Paystack dedicated account retry ─────────────────────────────────────
    if (($_POST['action'] ?? '') === 'retry_dedicated_account') {
        try {
            $account    = $dedicatedAccounts->ensureForUser((int) $user['id'], true);
            $statusCopy = (string) ($account['status'] ?? 'pending');
            flash('success', 'Paystack account sync requested. Status: ' . $statusCopy . '.');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
        }
        redirect(base_url('user/fund-wallet.php'));
    }

    // ── ZenithPay account assign / retry ─────────────────────────────────────
    if (($_POST['action'] ?? '') === 'assign_zenithpay_account') {
        $bvn        = trim((string) ($_POST['bvn'] ?? ''));
        $forceRetry = ($_POST['force_retry'] ?? '0') === '1';
        try {
            $account    = $zenithPay->ensureForUser((int) $user['id'], $bvn, $forceRetry);
            $statusCopy = (string) ($account['status'] ?? 'pending');
            flash('success', 'ZenithPay account request processed. Status: ' . $statusCopy . '.');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
        }
        redirect(base_url('user/fund-wallet.php'));
    }

    // Any other POST is not supported
    http_response_code(400);
    exit;
}

$recentFunding    = $payments->userFundingRequests((int) $user['id']);
$walletBalance    = app(\GemData\Classes\Wallet::class)->balance((int) $user['id']);
$dedicatedAccount = $dedicatedAccounts->getForUser((int) $user['id']);
$accountStatus    = (string) ($dedicatedAccount['status'] ?? '');
$zenithAccount    = $zenithPay->getForUser((int) $user['id']);
$zenithStatus     = (string) ($zenithAccount['status'] ?? '');

render_header('Fund Wallet', 'user');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?>
        <div class="notice notice-success"><?= e($message); ?></div>
    <?php endif; ?>
    <?php if ($message = flash('error')): ?>
        <div class="notice notice-error"><?= e($message); ?></div>
    <?php endif; ?>

    <section class="surface-card p-8" data-search-item data-search="wallet fund bank transfer virtual account balance">
        <div class="dashboard-section-header dashboard-section-header-start">
            <div>
                <p class="eyebrow">Wallet Funding</p>
                <h1 class="surface-section-title">Fund your wallet</h1>
                <p class="surface-section-copy">Transfer money into one of your dedicated virtual accounts below. Your wallet is credited automatically once the bank confirms the transfer.</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900/60 px-5 py-4 text-right">
                <p class="text-sm text-slate-400">Current balance</p>
                <p class="mt-2 text-2xl font-black text-white"><?= e(money($walletBalance)); ?></p>
            </div>
        </div>

        <!-- ── How it works ─────────────────────────────────────────────── -->
        <div class="funding-steps mt-6">
            <div class="funding-step is-complete">
                <strong>1. Get your account</strong>
                <span>A unique bank account number is assigned to your profile below.</span>
            </div>
            <div class="funding-step is-complete">
                <strong>2. Transfer any amount</strong>
                <span>Send from any Nigerian bank to your dedicated account number.</span>
            </div>
            <div class="funding-step is-complete">
                <strong>3. Wallet auto-credited</strong>
                <span>Your balance updates automatically once the bank confirms the transfer.</span>
            </div>
        </div>

        <!-- ── Virtual Account Cards ─────────────────────────────────────── -->
        <div class="dedicated-accounts-grid mt-6" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.25rem;">

            <!-- Paystack Dedicated Account -->
            <div class="dedicated-account-card" data-search-item data-search="paystack dedicated account bank transfer account number virtual account">
                <div class="dashboard-section-header dashboard-section-header-start" style="margin-bottom:.75rem">
                    <div>
                        <p class="eyebrow">Paystack</p>
                        <h2 class="surface-section-title" style="font-size:1.1rem">Dedicated Transfer Account</h2>
                    </div>
                    <?php if ($accountStatus !== 'assigned'): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="retry_dedicated_account">
                            <button class="secondary-action" type="submit" style="font-size:.8rem;padding:.4rem .9rem">Retry Sync</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($accountStatus === 'assigned'): ?>
                    <div class="dedicated-account-grid">
                        <div class="dedicated-account-tile">
                            <span class="metric-label">Account Number</span>
                            <strong><?= e((string) $dedicatedAccount['dedicated_account_number']); ?></strong>
                        </div>
                        <div class="dedicated-account-tile">
                            <span class="metric-label">Bank</span>
                            <strong><?= e((string) $dedicatedAccount['bank_name']); ?></strong>
                        </div>
                        <div class="dedicated-account-tile">
                            <span class="metric-label">Account Name</span>
                            <strong><?= e((string) $dedicatedAccount['account_name']); ?></strong>
                        </div>
                    </div>
                    <div class="notice notice-success" style="margin-top:.75rem">Account ready — transfer to fund your wallet instantly.</div>
                <?php elseif ($accountStatus === 'pending'): ?>
                    <div class="notice notice-success">Being assigned by Paystack. Refresh or retry shortly.</div>
                <?php elseif ($accountStatus === 'failed'): ?>
                    <div class="notice notice-error">Assignment failed.<?php if (!empty($dedicatedAccount['last_error_message'])): ?> Reason: <?= e((string) $dedicatedAccount['last_error_message']); ?><?php endif; ?></div>
                <?php else: ?>
                    <div class="notice notice-error">Not yet assigned. Click "Retry Sync" once Paystack credentials are configured.</div>
                <?php endif; ?>
            </div>

            <!-- ZenithPay Virtual Account -->
            <div class="dedicated-account-card" data-search-item data-search="zenithpay virtual account bank transfer palmpay bvn">
                <div class="dashboard-section-header dashboard-section-header-start" style="margin-bottom:.75rem">
                    <div>
                        <p class="eyebrow">ZenithPay</p>
                        <h2 class="surface-section-title" style="font-size:1.1rem">Virtual Bank Account</h2>
                    </div>
                    <?php if ($zenithStatus === 'assigned'): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="assign_zenithpay_account">
                            <input type="hidden" name="bvn" value="<?= e((string) ($zenithAccount['bvn'] ?? '')); ?>">
                            <input type="hidden" name="force_retry" value="1">
                            <button class="secondary-action" type="submit" style="font-size:.8rem;padding:.4rem .9rem">Retry</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($zenithStatus === 'assigned'): ?>
                    <div class="dedicated-account-grid">
                        <div class="dedicated-account-tile">
                            <span class="metric-label">Account Number</span>
                            <strong><?= e((string) $zenithAccount['dedicated_account_number']); ?></strong>
                        </div>
                        <div class="dedicated-account-tile">
                            <span class="metric-label">Bank</span>
                            <strong><?= e((string) $zenithAccount['bank_name']); ?></strong>
                        </div>
                        <div class="dedicated-account-tile">
                            <span class="metric-label">Account Name</span>
                            <strong><?= e((string) $zenithAccount['account_name']); ?></strong>
                        </div>
                    </div>
                    <div class="notice notice-success" style="margin-top:.75rem">ZenithPay account ready — transfer to fund your wallet instantly.</div>
                <?php elseif ($zenithStatus === 'failed'): ?>
                    <div class="notice notice-error" style="margin-bottom:.75rem">
                        Previous attempt failed.<?php if (!empty($zenithAccount['last_error_message'])): ?> Reason: <?= e((string) $zenithAccount['last_error_message']); ?><?php endif; ?>
                    </div>
                    <form method="post" class="grid gap-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="assign_zenithpay_account">
                        <input type="hidden" name="force_retry" value="1">
                        <label class="metric-label" for="zenith-bvn-retry">Your 11-digit BVN</label>
                        <input id="zenith-bvn-retry" class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="bvn" maxlength="11" placeholder="e.g. 12345678901" value="<?= e((string) ($zenithAccount['bvn'] ?? '')); ?>">
                        <button class="primary-action" type="submit">Retry ZenithPay Account</button>
                    </form>
                <?php else: ?>
                    <!-- No ZenithPay account yet — show BVN form -->
                    <p class="surface-section-copy" style="margin-bottom:.75rem;font-size:.9rem">Get a free virtual bank account linked to your wallet. Your BVN is required by ZenithPay for regulatory compliance.</p>
                    <form method="post" class="grid gap-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="assign_zenithpay_account">
                        <label class="metric-label" for="zenith-bvn">Your 11-digit BVN</label>
                        <input id="zenith-bvn" class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="bvn" maxlength="11" placeholder="e.g. 12345678901" required>
                        <button class="primary-action" type="submit"><?= $zenithPay->isConfigured() ? 'Get ZenithPay Account' : 'ZenithPay Not Yet Configured'; ?></button>
                    </form>
                <?php endif; ?>
            </div>

        </div><!-- end .dedicated-accounts-grid -->
    </section>

    <!-- ── Funding History ───────────────────────────────────────────────── -->
    <section class="surface-card p-8">
        <div class="dashboard-section-header dashboard-section-header-start">
            <div>
                <p class="eyebrow">Funding History</p>
                <h2 class="surface-section-title">Transfer log</h2>
            </div>
        </div>
        <div class="table-shell mt-6">
            <table>
                <thead>
                    <tr class="text-slate-400">
                        <th>Reference</th>
                        <th>Provider</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentFunding === []): ?>
                        <tr><td colspan="5" class="text-slate-400">No funding transfers yet. Transfer to one of your accounts above to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentFunding as $row): ?>
                            <tr>
                                <td><span class="font-mono text-xs text-slate-300"><?= e((string) $row['reference']); ?></span></td>
                                <td><?= e(ucwords(str_replace('_', ' ', (string) $row['provider']))); ?></td>
                                <td><span class="status-chip status-<?= e((string) $row['status']); ?>"><?= e(ucfirst((string) $row['status'])); ?></span></td>
                                <td><?= e(money($row['amount'])); ?></td>
                                <td><span class="timestamp" title="<?= e((string) $row['created_at']); ?>"><?= e(human_datetime((string) $row['created_at'])); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php render_footer(); ?>
