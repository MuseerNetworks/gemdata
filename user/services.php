<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$dashboard = app(\GemData\Classes\DashboardController::class)->dataFor($user);
$services = $dashboard['services'] ?? [];
$serviceMeta = $dashboard['service_meta'] ?? [];

$serviceCards = [
    'airtime' => ['label' => 'Airtime', 'copy' => 'Top up any network instantly.', 'icon' => 'airtime', 'tone' => 'user-icon-green', 'href' => 'user/buy-airtime.php'],
    'data' => ['label' => 'Data', 'copy' => 'Buy affordable data bundles.', 'icon' => 'data', 'tone' => 'user-icon-blue', 'href' => 'user/buy-data.php'],
    'cable_tv' => ['label' => 'Cable TV', 'copy' => 'Renew DStv, GOtv, and more.', 'icon' => 'services', 'tone' => 'user-icon-purple', 'href' => 'user/cable-tv.php'],
    'electricity' => ['label' => 'Electricity', 'copy' => 'Pay prepaid or postpaid bills.', 'icon' => 'wallet', 'tone' => 'user-icon-orange', 'href' => 'user/electricity.php'],
    'exam_pin' => ['label' => 'Exam PIN', 'copy' => 'Buy WAEC, NECO, and exam tokens.', 'icon' => 'shield', 'tone' => 'user-icon-orange', 'href' => 'user/exam-pin.php'],
    'bulk_sms' => ['label' => 'Bulk SMS', 'copy' => 'Send messages to many contacts.', 'icon' => 'notification', 'tone' => 'user-icon-purple', 'href' => 'user/bulk-sms.php'],
    'data_card' => ['label' => 'Data Card', 'copy' => 'Generate data cards for resale.', 'icon' => 'services', 'tone' => 'user-icon-green', 'href' => 'user/data-card.php'],
    'recharge_card' => ['label' => 'Recharge Card', 'copy' => 'Generate airtime recharge cards.', 'icon' => 'airtime', 'tone' => 'user-icon-blue', 'href' => 'user/recharge-card.php'],
];

render_header('Buy Services', 'user');
?>
<div class="mobile-services-page space-y-5">
    <section class="user-premium-card bg-white rounded-2xl shadow-card border border-gem-border p-5">
        <p class="text-[12px] font-bold uppercase tracking-wider text-gem-blue">GemData Services</p>
        <h1 class="text-2xl font-extrabold text-gem-text mt-1">What do you want to buy?</h1>
        <p class="text-[13px] text-gem-muted mt-1">Choose a service and complete payment from your wallet.</p>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3" data-skeleton-scope>
        <?php foreach ($services as $service): ?>
            <?php
            $slug = (string) ($service['slug'] ?? '');
            $card = $serviceCards[$slug] ?? [
                'label' => (string) ($service['name'] ?? 'Service'),
                'copy' => (string) ($service['description'] ?? 'Open service'),
                'icon' => 'services',
                'tone' => 'user-icon-blue',
                'href' => 'user/services.php',
            ];
            $copy = (string) ($serviceMeta[$slug]['summary'] ?? $card['copy']);
            ?>
            <a class="user-premium-card user-premium-link bg-white rounded-2xl p-4 shadow-card border border-gem-border flex items-center gap-3" href="<?= e(base_url($card['href'])); ?>" data-search-item data-search="<?= e(($service['name'] ?? '') . ' ' . ($service['description'] ?? '') . ' ' . $copy); ?>">
                <span class="user-icon-box <?= e($card['tone']); ?> !w-11 !h-11 !rounded-2xl flex-shrink-0"><?= icon_svg($card['icon']); ?></span>
                <span class="flex-1 min-w-0">
                    <span class="block text-[14px] font-extrabold text-gem-text"><?= e($card['label']); ?></span>
                    <span class="block text-[12px] text-gem-muted mt-0.5"><?= e($copy); ?></span>
                </span>
                <span class="w-4 h-4 text-gem-muted flex-shrink-0"><?= icon_svg('chevron'); ?></span>
            </a>
        <?php endforeach; ?>

        <?php if ($services === []): ?>
            <div class="user-empty-state bg-white rounded-2xl border border-gem-border p-6 text-center text-[13px] text-gem-muted">
                Services are not available right now. Please check back shortly.
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
render_footer();
