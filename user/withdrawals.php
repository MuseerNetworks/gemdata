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

if (!$featureFlag->enabled('withdrawal_enabled')) {
    redirect(base_url('user/commission.php'));
}

$userId = (int) $user['id'];
$balance = $commWallet->balance($userId);
$minimum = $withdrawSvc->minimumAmount();
$history = $withdrawSvc->listByUser($userId, 30);
$error = '';
$success = '';

if (is_post()) {
    try {
        verify_csrf();

        $amount = (float) ($_POST['amount'] ?? 0);
        $bankName = trim((string) ($_POST['bank_name'] ?? ''));
        $acctNo = trim((string) ($_POST['account_number'] ?? ''));
        $acctName = trim((string) ($_POST['account_name'] ?? ''));

        if ($bankName === '' || $acctNo === '' || $acctName === '') {
            throw new InvalidArgumentException('All bank details are required.');
        }

        $withdrawSvc->request($userId, $amount, $bankName, $acctNo, $acctName);
        $success = 'Commission withdrawal request submitted. The amount has been reserved from your commission wallet for admin review.';
        $history = $withdrawSvc->listByUser($userId, 30);
        $balance = $commWallet->balance($userId);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

render_header('Request Commission Withdrawal', 'user');
?>
<div class="space-y-6">
    <div class="stagger-1 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-gem-blue">Commission Wallet</p>
            <h1 class="mt-1 text-2xl font-extrabold text-gem-text">Request Commission Withdrawal</h1>
            <p class="mt-0.5 text-[14px] text-gem-muted">Withdraw from commission earnings only. Your main wallet balance is not used here.</p>
        </div>
        <a class="secondary-action" href="<?= e(base_url('user/commission.php')); ?>">Back to Commission Wallet</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-[13px] font-semibold text-gem-red"><?= e($error); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="rounded-2xl border border-green-100 bg-green-50 px-5 py-4 text-[13px] font-semibold text-gem-green"><?= e($success); ?></div>
    <?php endif; ?>

    <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <article class="lg:col-span-2 rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <div class="mb-5 rounded-2xl border border-gem-border bg-gem-gray p-4">
                <div class="text-[12px] font-bold uppercase tracking-wider text-gem-muted">Withdrawable Commission</div>
                <div class="mt-2 font-mono text-3xl font-extrabold text-gem-text"><?= e(money($balance)); ?></div>
                <div class="mt-1 text-[12px] font-semibold text-gem-muted">Minimum withdrawal: <?= e(money($minimum)); ?></div>
            </div>

            <?php if ($balance < $minimum): ?>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] font-semibold text-amber-800">
                    Your withdrawable commission is below the minimum withdrawal amount.
                </div>
            <?php endif; ?>

            <form method="post" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2" data-loading-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <label class="text-[12px] font-semibold uppercase tracking-wider text-gem-muted">Amount
                    <input class="mt-1.5 w-full rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text focus:border-gem-blue focus:outline-none focus:ring-2 focus:ring-gem-blue/10" name="amount" type="number" min="<?= e((string) $minimum); ?>" step="0.01" value="<?= e((string) max($minimum, min($balance, $minimum))); ?>" required>
                </label>
                <label class="text-[12px] font-semibold uppercase tracking-wider text-gem-muted">Bank Name
                    <input class="mt-1.5 w-full rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text focus:border-gem-blue focus:outline-none focus:ring-2 focus:ring-gem-blue/10" name="bank_name" value="<?= e((string) ($_POST['bank_name'] ?? '')); ?>" required>
                </label>
                <label class="text-[12px] font-semibold uppercase tracking-wider text-gem-muted">Account Number
                    <input class="mt-1.5 w-full rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text focus:border-gem-blue focus:outline-none focus:ring-2 focus:ring-gem-blue/10" name="account_number" inputmode="numeric" value="<?= e((string) ($_POST['account_number'] ?? '')); ?>" required>
                </label>
                <label class="text-[12px] font-semibold uppercase tracking-wider text-gem-muted">Account Name
                    <input class="mt-1.5 w-full rounded-xl border border-gem-border bg-gem-gray px-4 py-3 text-[13px] text-gem-text focus:border-gem-blue focus:outline-none focus:ring-2 focus:ring-gem-blue/10" name="account_name" value="<?= e((string) ($_POST['account_name'] ?? '')); ?>" required>
                </label>
                <div class="sm:col-span-2">
                    <button class="gd-button w-full" type="submit" data-loading-label="Submitting request..." <?= $balance < $minimum ? 'disabled' : ''; ?>>Submit Withdrawal Request</button>
                </div>
            </form>
        </article>

        <aside class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
            <h2 class="text-[16px] font-bold text-gem-text">Withdrawal Rules</h2>
            <div class="mt-4 space-y-3 text-[13px] text-gem-muted">
                <p>Only commission wallet earnings can be withdrawn from this page.</p>
                <p>Submitted requests reserve the amount until admin approval or rejection.</p>
                <p>If rejected, the reserved commission is returned to your commission wallet.</p>
            </div>
        </aside>
    </section>

    <section class="rounded-2xl border border-gem-border bg-white p-5 shadow-card">
        <h2 class="text-[16px] font-bold text-gem-text">Withdrawal History</h2>
        <?php if ($history === []): ?>
            <div class="mt-4 rounded-2xl border border-dashed border-gem-border bg-gem-gray px-5 py-8 text-center text-[13px] font-semibold text-gem-muted">No commission withdrawal requests yet.</div>
        <?php else: ?>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-[13px]">
                    <thead class="text-[11px] uppercase tracking-wider text-gem-muted">
                        <tr>
                            <th class="py-3 pr-4">Amount</th>
                            <th class="py-3 pr-4">Bank</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Requested</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gem-border">
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td class="py-3 pr-4 font-mono font-bold text-gem-text"><?= e(money((float) ($row['amount'] ?? 0))); ?></td>
                                <td class="py-3 pr-4 text-gem-muted"><?= e((string) ($row['bank_name'] ?? '')); ?></td>
                                <td class="py-3 pr-4"><span class="rounded-full bg-gem-gray px-3 py-1 text-[11px] font-bold uppercase text-gem-muted"><?= e((string) ($row['status'] ?? 'pending')); ?></span></td>
                                <td class="py-3 pr-4 text-gem-muted"><?= e(format_date((string) ($row['created_at'] ?? ''))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php render_footer(); ?>
