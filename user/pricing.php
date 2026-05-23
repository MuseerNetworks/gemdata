<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
app(\GemData\Classes\RoleMiddleware::class)->requireRole($user, 'reseller');
$dashboard = app(\GemData\Classes\DashboardController::class)->dataFor($user);
$reseller = $dashboard['reseller'] ?? ['rates' => [], 'commission_balance' => 0, 'estimated_profit' => 0];
$rates = $reseller['rates'] ?? [];
$configuredRates = array_values(array_filter($rates, static fn(array $rate): bool => (bool) ($rate['commission_enabled'] ?? false)));
$rateValues = array_map(static fn(array $rate): float => (float) ($rate['rate_percent'] ?? 0), $configuredRates);
$bestRate = $rateValues === [] ? 0 : max($rateValues);
$averageRate = $rateValues === [] ? 0 : array_sum($rateValues) / count($rateValues);

render_header('Pricing', 'user');
?>
<div class="space-y-6">
    <div class="stagger-1">
        <h1 class="text-2xl font-extrabold text-gem-text">Pricing</h1>
        <p class="text-[14px] text-gem-muted mt-0.5">Your GemData business rates and commission wallet summary.</p>
    </div>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-4 stagger-2">
        <div class="bg-white rounded-2xl p-5 shadow-card border border-gem-border">
            <div class="text-[12px] text-gem-muted font-bold uppercase tracking-wider">Tier</div>
            <div class="text-[22px] font-extrabold text-gem-text mt-2"><?= e($dashboard['role_label']); ?></div>
            <div class="text-[12px] text-gem-muted mt-1">Active business pricing</div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-card border border-gem-border">
            <div class="text-[12px] text-gem-muted font-bold uppercase tracking-wider">Best Rate</div>
            <div class="text-[22px] font-extrabold text-gem-text mt-2"><?= e(number_format($bestRate, 2)); ?>%</div>
            <div class="text-[12px] text-gem-muted mt-1">Highest configured service discount</div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-card border border-gem-border">
            <div class="text-[12px] text-gem-muted font-bold uppercase tracking-wider">Commission Wallet</div>
            <div class="text-[22px] font-extrabold text-gem-text mt-2 font-mono"><?= e(money((float) ($reseller['commission_balance'] ?? 0))); ?></div>
            <div class="text-[12px] text-gem-muted mt-1">Available reseller earnings</div>
        </div>
    </section>

    <section class="bg-white rounded-2xl shadow-card border border-gem-border p-5 stagger-3">
        <div class="flex items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-[16px] font-bold text-gem-text">Service Rates</h2>
                <p class="text-[13px] text-gem-muted mt-0.5">Configured commission rates for enabled services. Unconfigured services stay visible for clarity.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-green-50 text-gem-green px-3 py-1 text-[12px] font-bold">Avg <?= e(number_format($averageRate, 2)); ?>%</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php if ($rates === []): ?>
                <div class="col-span-full text-center py-8 text-[13px] text-gem-muted">Pricing will appear after admin configures reseller rates.</div>
            <?php endif; ?>
            <?php foreach ($rates as $rate): ?>
                <?php $percent = (float) ($rate['rate_percent'] ?? 0); ?>
                <div class="rounded-xl border border-gem-border bg-gem-gray p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[13px] font-bold text-gem-text"><?= e((string) $rate['name']); ?></div>
                            <div class="text-[11px] text-gem-muted mt-1"><?= e((string) ($rate['source_label'] ?? 'Not configured')); ?></div>
                        </div>
                        <?php if (!empty($rate['commission_enabled'])): ?>
                            <strong class="text-[14px] text-gem-blue"><?= e(number_format($percent, 2)); ?>%</strong>
                        <?php else: ?>
                            <strong class="text-[12px] text-gem-muted">Not configured</strong>
                        <?php endif; ?>
                    </div>
                    <div class="progress-bar mt-3"><span style="width: <?= e((string) min(100, max(0, $percent * 10))); ?>%"></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php render_footer(); ?>
