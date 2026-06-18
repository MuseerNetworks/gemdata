<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$reference = strtoupper(trim((string) ($_GET['reference'] ?? '')));
if ($reference === '') {
    http_response_code(404);
    render_header('Receipt Not Found', 'user');
    ?>
    <div class="user-premium-card rounded-2xl border border-gem-border bg-white p-6 shadow-card">
        <h1 class="text-xl font-extrabold text-gem-text">Receipt not found</h1>
        <p class="mt-2 text-sm text-gem-muted">A transaction reference is required.</p>
    </div>
    <?php
    render_footer();
    exit;
}

$transaction = db()->first(
    'SELECT t.*, s.name AS service_name, s.slug AS service_slug
     FROM transactions t
     INNER JOIN services s ON s.id = t.service_id
     WHERE t.reference = :reference AND t.user_id = :user_id
     LIMIT 1',
    ['reference' => $reference, 'user_id' => (int) $user['id']]
);

if (!$transaction) {
    http_response_code(404);
    render_header('Receipt Not Found', 'user');
    ?>
    <div class="user-premium-card rounded-2xl border border-gem-border bg-white p-6 shadow-card">
        <h1 class="text-xl font-extrabold text-gem-text">Receipt not found</h1>
        <p class="mt-2 text-sm text-gem-muted">This receipt is unavailable or does not belong to your account.</p>
    </div>
    <?php
    render_footer();
    exit;
}

$payload = json_decode_array((string) ($transaction['payload_json'] ?? '{}'));
$networkCode = (string) ($payload['network'] ?? $payload['provider'] ?? '');
$planCode = (string) ($payload['local_plan_code'] ?? $payload['package'] ?? $payload['exam_type'] ?? $payload['plan'] ?? '');
$mapping = null;
if ((int) ($transaction['provider_account_id'] ?? 0) > 0 && $planCode !== '') {
    $mapping = app(\GemData\Classes\ProviderPlanService::class)->resolveForProvider(
        (int) $transaction['provider_account_id'],
        (int) $transaction['service_id'],
        $networkCode,
        $planCode
    );
}

$planName = trim((string) ($payload['local_plan_name'] ?? ''));
if ($planName === '' && is_array($mapping)) {
    $planName = trim((string) ($mapping['local_plan_name'] ?? ''));
}
if ($planName === '') {
    $planName = trim($planCode);
}

$validityLabel = trim((string) ($payload['validity_label'] ?? ''));
if ($validityLabel === '' && is_array($mapping)) {
    $validityLabel = trim((string) ($mapping['validity_label'] ?? ''));
}

$status = strtolower((string) ($transaction['status'] ?? 'pending'));
$statusColor = $status === 'successful' ? 'text-gem-green bg-green-50' : ($status === 'failed' ? 'text-gem-red bg-red-50' : 'text-amber-600 bg-amber-50');

render_header('Transaction Receipt', 'user');
?>
<div class="receipt-page mx-auto max-w-2xl space-y-3">
    <div class="receipt-actions flex flex-wrap items-center justify-between gap-2">
        <a class="purchase-back-link" href="<?= e(base_url('user/dashboard.php#recent-transactions')); ?>"><?= icon_svg('chevron'); ?> Back to Dashboard</a>
        <button class="secondary-action receipt-download-button" type="button" onclick="window.print()">Download Receipt</button>
    </div>

    <section class="receipt-card user-premium-card rounded-2xl border border-gem-border bg-white p-5 shadow-card">
        <div class="receipt-card-head flex flex-wrap items-start justify-between gap-3 border-b border-gem-border pb-4">
            <div>
                <p class="receipt-kicker text-[11px] font-bold uppercase tracking-wider text-gem-blue">GemData</p>
                <h1 class="receipt-title mt-1 text-xl font-extrabold text-gem-text">Transaction Receipt</h1>
            </div>
            <span class="receipt-status inline-flex rounded-full px-3 py-1 text-[12px] font-bold <?= e($statusColor); ?>"><?= e(ucfirst($status)); ?></span>
        </div>

        <?php if ($status !== 'successful'): ?>
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">
                This transaction is <?= e($status); ?>. A final receipt is available after successful completion.
            </div>
        <?php endif; ?>

        <dl class="receipt-details mt-4 grid gap-3 sm:grid-cols-2">
            <div class="receipt-detail rounded-xl bg-gem-gray p-3">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gem-muted">Reference</dt>
                <dd class="mt-1 font-mono text-[14px] font-extrabold text-gem-text"><?= e((string) $transaction['reference']); ?></dd>
            </div>
            <div class="receipt-detail rounded-xl bg-gem-gray p-3">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gem-muted">Date / Time</dt>
                <dd class="mt-1 text-[14px] font-bold text-gem-text"><?= e(local_datetime((string) ($transaction['processed_at'] ?: $transaction['created_at']), 'M j, Y g:i A')); ?></dd>
            </div>
            <div class="receipt-detail rounded-xl bg-gem-gray p-3">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gem-muted">Service</dt>
                <dd class="mt-1 text-[14px] font-bold text-gem-text"><?= e((string) $transaction['service_name']); ?></dd>
            </div>
            <div class="receipt-detail rounded-xl bg-gem-gray p-3">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gem-muted">Plan / Package</dt>
                <dd class="mt-1 text-[14px] font-bold text-gem-text"><?= e($planName !== '' ? $planName : 'N/A'); ?></dd>
            </div>
            <?php if ($validityLabel !== ''): ?>
            <div class="receipt-detail rounded-xl bg-gem-gray p-3">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gem-muted">Validity</dt>
                <dd class="mt-1 text-[14px] font-bold text-gem-text"><?= e($validityLabel); ?></dd>
            </div>
            <?php endif; ?>
            <div class="receipt-detail rounded-xl bg-gem-gray p-3">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gem-muted">Recipient</dt>
                <dd class="mt-1 text-[14px] font-bold text-gem-text"><?= e((string) ($transaction['recipient'] ?: ($transaction['customer_name'] ?? 'N/A'))); ?></dd>
            </div>
            <div class="receipt-detail rounded-xl bg-gem-gray p-3">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gem-muted">Amount</dt>
                <dd class="mt-1 font-mono text-[16px] font-extrabold text-gem-text"><?= e(money((float) $transaction['amount'])); ?></dd>
            </div>
            <div class="receipt-detail rounded-xl bg-gem-gray p-3">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gem-muted">Status</dt>
                <dd class="mt-1 text-[14px] font-bold text-gem-text"><?= e(ucfirst($status)); ?></dd>
            </div>
        </dl>
    </section>
</div>
<?php render_footer(); ?>
