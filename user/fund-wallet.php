<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$payments = app(\GemData\Classes\PaymentGatewayService::class);
$dedicatedAccounts = app(\GemData\Classes\PaystackDedicatedAccountService::class);

if (is_post()) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'retry_dedicated_account') {
        try {
            $account = $dedicatedAccounts->ensureForUser((int) $user['id'], true);
            $statusCopy = (string) ($account['status'] ?? 'pending');
            flash('success', 'Dedicated account sync requested. Current status: ' . $statusCopy . '.');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
        }
        redirect(base_url('user/fund-wallet.php'));
    }

    header('Content-Type: application/json');
    if ($payments->isProductionBankTransferOnly()) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Production wallet funding uses your dedicated bank transfer account only.',
        ]);
        exit;
    }
    $amount = (float) ($_POST['amount'] ?? 0);
    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Enter a valid funding amount.']);
        exit;
    }
    $request = $payments->createFundingRequest((int) $user['id'], $amount, (string) config('payments.default_gateway', 'mock_paystack'));
    $_SESSION['wallet_funding_session_tokens'][(string) $request['reference']] = (string) ($request['callback_session_token'] ?? '');
    $redirect = base_url('user/fund-wallet.php?reference=' . urlencode((string) $request['reference']));
    echo json_encode([
        'status' => 'success',
        'message' => 'Funding request created and queued for provider verification.',
        'meta' => ['redirect_url' => $redirect],
    ]);
    exit;
}

$reference = trim((string) ($_GET['reference'] ?? ''));
$selectedRequest = $reference !== '' ? $payments->findUserFundingRequest((int) $user['id'], $reference) : null;
if ($selectedRequest && (string) $selectedRequest['status'] !== 'initiated') {
    unset($_SESSION['wallet_funding_session_tokens'][$reference]);
}
$referenceSessionToken = $reference !== '' ? (string) ($_SESSION['wallet_funding_session_tokens'][$reference] ?? '') : '';
$recentFunding = $payments->userFundingRequests((int) $user['id']);
$walletBalance = app(\GemData\Classes\Wallet::class)->balance((int) $user['id']);
$gatewayName = (string) config('payments.display_gateway_name', 'Paystack');
$requestStatus = (string) ($selectedRequest['status'] ?? '');
$dedicatedAccount = $dedicatedAccounts->getForUser((int) $user['id']);
$accountStatus = (string) ($dedicatedAccount['status'] ?? '');
$showMockFunding = !$payments->isProductionBankTransferOnly();

render_header('Fund Wallet', 'user');
?>
<div class="space-y-6">
    <?php if ($message = flash('success')): ?>
        <div class="notice notice-success"><?= e($message); ?></div>
    <?php endif; ?>
    <?php if ($message = flash('error')): ?>
        <div class="notice notice-error"><?= e($message); ?></div>
    <?php endif; ?>

    <section class="surface-card p-8" data-search-item data-search="wallet fund paystack amount balance callback verification">
        <div class="dashboard-section-header dashboard-section-header-start">
            <div>
                <p class="eyebrow">Wallet Funding</p>
                <h1 class="surface-section-title">Fund your wallet safely</h1>
                <p class="surface-section-copy">Funding requests are created first, confirmed by the payment provider, and only then credited to your wallet. That makes each stage clear and trustworthy.</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900/60 px-5 py-4 text-right">
                <p class="text-sm text-slate-400">Current wallet</p>
                <p class="mt-2 text-2xl font-black text-white"><?= e(money($walletBalance)); ?></p>
            </div>
        </div>
        <div class="funding-steps">
            <div class="funding-step <?= $selectedRequest ? 'is-complete' : 'is-active'; ?>">
                <strong>1. Create request</strong>
                <span>Enter your amount to create a tracked funding request reference.</span>
            </div>
            <div class="funding-step <?= $requestStatus === 'initiated' ? 'is-active' : ($requestStatus !== '' && $requestStatus !== 'failed' ? 'is-complete' : ''); ?>">
                <strong>2. Await confirmation</strong>
                <span>The provider verifies payment before any wallet credit is applied.</span>
            </div>
            <div class="funding-step <?= $requestStatus === 'credited' ? 'is-complete is-active' : ''; ?>">
                <strong>3. Wallet credited</strong>
                <span>Your balance updates only after a successful verified callback.</span>
            </div>
        </div>
        <div class="dedicated-account-card mt-6" data-search-item data-search="dedicated account bank transfer account number paystack virtual account">
            <div class="dashboard-section-header dashboard-section-header-start">
                <div>
                    <p class="eyebrow">Bank Transfer Funding</p>
                    <h2 class="surface-section-title">Your dedicated transfer account</h2>
                    <p class="dedicated-account-copy">Transfer into this account to fund your GemData wallet with a bank transfer-friendly flow.</p>
                </div>
                <?php if ($accountStatus !== 'assigned'): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="retry_dedicated_account">
                        <button class="secondary-action" type="submit">Retry Account Sync</button>
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
                <div class="notice notice-success">Your dedicated transfer account is ready. Use the exact account number above and allow normal provider confirmation time for wallet updates.</div>
            <?php elseif ($accountStatus === 'pending'): ?>
                <div class="notice notice-success">Your GemData account is ready, and your dedicated transfer account is still being assigned by Paystack. Refresh later or retry sync if this stays pending for too long.</div>
            <?php elseif ($accountStatus === 'failed'): ?>
                <div class="notice notice-error">We could not assign your dedicated transfer account yet. Registration and wallet access still work normally. Use the retry button to request assignment again.<?php if (!empty($dedicatedAccount['last_error_message'])): ?> Reason: <?= e((string) $dedicatedAccount['last_error_message']); ?><?php endif; ?></div>
            <?php else: ?>
                <div class="notice notice-error">Dedicated transfer account assignment has not started yet for this profile. Use the retry button to request it when Paystack credentials are configured.</div>
            <?php endif; ?>
        </div>
        <?php if ($showMockFunding): ?>
            <div id="wallet-feedback" class="mt-6"></div>
            <form class="mt-6 grid gap-4 md:grid-cols-[1fr,auto]" method="post" data-ajax-form data-target="#wallet-feedback" data-reset-on-success="true" data-offline-queue="false">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="idempotency_key" value="" data-idempotency-field data-idempotency-prefix="fund">
                <input class="w-full rounded-lg border border-white/10 bg-slate-900 px-4 py-3" name="amount" placeholder="Enter amount e.g. 5000">
                <button class="rounded-lg bg-emerald-400 px-5 py-3 font-semibold text-slate-950" type="submit">Create Funding Request</button>
            </form>
        <?php else: ?>
            <div class="notice notice-success mt-6">Production funding is bank-transfer-only. Send funds to your dedicated account above and wait for Paystack verification before wallet credit appears.</div>
        <?php endif; ?>
    </section>

    <?php if ($selectedRequest && $showMockFunding): ?>
        <section class="surface-card p-8">
            <div class="dashboard-section-header dashboard-section-header-start">
                <div>
                    <p class="eyebrow">Current Request</p>
                    <h2 class="surface-section-title"><?= e($selectedRequest['reference']); ?></h2>
                    <p class="mt-2 text-slate-300">Status: <span class="font-semibold text-white"><?= e(ucfirst((string) $selectedRequest['status'])); ?></span></p>
                </div>
                <?php if ((string) $selectedRequest['status'] === 'initiated' && $referenceSessionToken !== ''): ?>
                    <form method="post" action="<?= e(base_url('api/payment-callback.php')); ?>" class="inline-flex">
                        <input type="hidden" name="reference" value="<?= e((string) $selectedRequest['reference']); ?>">
                        <input type="hidden" name="token" value="<?= e($referenceSessionToken); ?>">
                        <input type="hidden" name="status" value="success">
                        <button class="primary-action inline-flex items-center justify-center" type="submit">Complete Mock Payment</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <div class="metric-card"><p class="metric-label">Amount</p><p class="metric-value"><?= e(money($selectedRequest['amount'])); ?></p></div>
                <div class="metric-card"><p class="metric-label">Gateway</p><p class="metric-value"><?= e(ucwords(str_replace('_', ' ', (string) $selectedRequest['provider']))); ?></p></div>
                <div class="metric-card"><p class="metric-label">Created</p><p class="metric-value text-base"><span class="timestamp" title="<?= e((string) $selectedRequest['created_at']); ?>"><?= e(human_datetime((string) $selectedRequest['created_at'])); ?></span></p></div>
            </div>
            <div class="notice notice-success mt-6">
                <?php if ((string) $selectedRequest['status'] === 'initiated'): ?>
                    <?php if ($referenceSessionToken !== ''): ?>
                        Local testing uses a mock <?= e($gatewayName); ?> confirmation action. In production, this reference would be confirmed by the provider callback before wallet credit.
                    <?php else: ?>
                        This initiated request is waiting for provider confirmation. In local testing, start a new request in this browser session to generate a fresh confirmation action.
                    <?php endif; ?>
                <?php elseif ((string) $selectedRequest['status'] === 'credited'): ?>
                    This request has been verified and the wallet has already been credited.
                <?php else: ?>
                    This funding request did not complete successfully. Start a new request if needed.
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="surface-card p-8">
        <div class="dashboard-section-header dashboard-section-header-start">
            <div>
                <p class="eyebrow">Recent Funding Requests</p>
                <h2 class="surface-section-title">Funding log</h2>
            </div>
        </div>
        <div class="table-shell mt-6">
            <table>
                <thead>
                    <tr class="text-slate-400">
                        <th>Reference</th>
                        <th>Gateway</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentFunding === []): ?>
                        <tr><td colspan="5" class="text-slate-400">No funding requests yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentFunding as $row): ?>
                            <tr>
                                <td><a class="text-cyan-300" href="<?= e(base_url('user/fund-wallet.php?reference=' . urlencode((string) $row['reference']))); ?>"><?= e((string) $row['reference']); ?></a></td>
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
