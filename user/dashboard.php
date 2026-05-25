<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$dashboard = app(\GemData\Classes\DashboardController::class)->dataFor($user);
$role = $dashboard['role'];
$wallet = $dashboard['wallet'];
$fundingAccount = $dashboard['funding_account'];
$fundingAccounts = $dashboard['funding_accounts'] ?? [];
$multiProviderFunding = (bool) ($dashboard['funding_multi_provider'] ?? false);
$services = $dashboard['services'];
$serviceMeta = $dashboard['service_meta'];
$serviceNetworks = $dashboard['service_networks'];
$dataPlanCatalog = $dashboard['data_plan_catalog'];
$recentTransactions = $dashboard['recent_transactions'];
$stats = $dashboard['stats'];
$upgrade = $dashboard['upgrade'];
$reseller = $dashboard['reseller'];
$api = $dashboard['api'];

$accountAssigned = ($fundingAccount['status'] ?? '') === 'assigned'
    && trim((string) ($fundingAccount['dedicated_account_number'] ?? '')) !== '';
$accountNumber = (string) ($fundingAccount['dedicated_account_number'] ?? '');
$accountName = (string) ($fundingAccount['account_name'] ?? '');
$bankName = (string) ($fundingAccount['bank_name'] ?? '');
$fullAccountCopy = trim($bankName . "\n" . $accountNumber . "\n" . $accountName);
$assignedFundingAccounts = array_values(array_filter($fundingAccounts, static function (array $row): bool {
    return ($row['status'] ?? '') === 'assigned' && trim((string) ($row['dedicated_account_number'] ?? '')) !== '';
}));
$successRate = $stats['transactions'] > 0 ? 98.7 : 0;
$firstName = trim(explode(' ', (string) ($user['full_name'] ?? 'GemData user'))[0] ?? 'GemData');
$recentRecipients = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['recipient'] ?? ''), $recentTransactions))));
$savedCustomersCount = count($recentRecipients);
$apiKeys = $api['keys'] ?? [];
$apiKeyPreview = 'No active key';
if ($apiKeys !== []) {
    $rawApiKey = (string) ($apiKeys[0]['api_key'] ?? '');
    $apiKeyPreview = strlen($rawApiKey) > 10 ? substr($rawApiKey, 0, 10) . '****' . substr($rawApiKey, -2) : 'sk_live_****';
}
$apiUsage = $api['usage'] ?? [];

function dashboard_template_icon(string $name, string $class = 'w-5 h-5 text-white'): string
{
    $attrs = 'class="' . e($class) . '" fill="none" viewBox="0 0 24 24" stroke="currentColor"';

    return match ($name) {
        'airtime' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M3 10h18M3 15h12M3 20h7"/><circle cx="19.5" cy="17.5" r="3"/></svg>',
        'data' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12 20.25h.008v.008H12v-.008z"/></svg>',
        'cable_tv' => '<svg ' . $attrs . ' stroke-width="2"><rect x="2" y="7" width="20" height="15" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v4m-2-2h4"/></svg>',
        'electricity' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>',
        'data_card' => '<svg ' . $attrs . ' stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path stroke-linecap="round" d="M2 10h20"/></svg>',
        'exam_pin' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>',
        'recharge_card' => '<svg ' . $attrs . ' stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path stroke-linecap="round" d="M2 10h20M7 15h.01M11 15h2"/></svg>',
        'bulk_sms' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>',
        'funding_account' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/></svg>',
        'referrals' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>',
        'transactions_stat' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>',
        'total_spent_stat' => '<svg ' . $attrs . ' stroke-width="2"><rect x="3" y="6" width="18" height="13" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M8 3v3m8-3v3"/></svg>',
        'success_stat' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>',
        'account_status_stat' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>',
        'plus' => '<svg ' . $attrs . ' stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>',
        'transfer' => '<svg ' . $attrs . ' stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>',
        'withdraw' => '<svg ' . $attrs . ' stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>',
        'history' => '<svg ' . $attrs . ' stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'eye' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'arrow' => '<svg ' . $attrs . ' stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>',
        'copy' => '<svg ' . $attrs . ' stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>',
        default => '<svg ' . $attrs . ' stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>',
    };
}

$serviceCards = [
    'airtime' => ['label' => 'Airtime', 'copy' => 'Top up any network', 'icon' => 'airtime', 'color' => 'bg-green-500'],
    'data' => ['label' => 'Data', 'copy' => 'Buy data bundles', 'icon' => 'data', 'color' => 'bg-blue-500'],
    'cable_tv' => ['label' => 'Cable TV', 'copy' => 'Subscribe and pay', 'icon' => 'cable_tv', 'color' => 'bg-purple-600'],
    'electricity' => ['label' => 'Electricity', 'copy' => 'Pay bills easily', 'icon' => 'electricity', 'color' => 'bg-yellow-400'],
    'exam_pin' => ['label' => 'Exam PIN', 'copy' => 'Purchase WAEC, NECO', 'icon' => 'exam_pin', 'color' => 'bg-orange-500'],
    'bulk_sms' => ['label' => 'Bulk SMS', 'copy' => 'Send SMS in bulk', 'icon' => 'bulk_sms', 'color' => 'bg-indigo-500'],
    'data_card' => ['label' => 'Data Card', 'copy' => 'Fund data card', 'icon' => 'data_card', 'color' => 'bg-teal-500'],
    'recharge_card' => ['label' => 'Recharge Card', 'copy' => 'Recharge cards', 'icon' => 'recharge_card', 'color' => 'bg-red-500'],
];

$dedicatedServiceUrls = [
    'airtime' => base_url('user/buy-airtime.php'),
    'data' => base_url('user/buy-data.php'),
    'cable_tv' => base_url('user/cable-tv.php'),
    'electricity' => base_url('user/electricity.php'),
    'exam_pin' => base_url('user/exam-pin.php'),
    'bulk_sms' => base_url('user/bulk-sms.php'),
    'data_card' => base_url('user/data-card.php'),
    'recharge_card' => base_url('user/recharge-card.php'),
];

$purchaseSchemas = [];
foreach ($services as $service) {
    $slug = (string) $service['slug'];
    $card = $serviceCards[$slug] ?? ['label' => (string) $service['name'], 'copy' => (string) ($service['description'] ?? 'Complete service'), 'icon' => 'funding_account', 'color' => 'bg-blue-600'];
    $networkOptions = array_map(static fn(array $network): array => [
        'value' => (string) $network['network_code'],
        'label' => (string) $network['network_name'],
    ], $serviceNetworks[$slug] ?? []);

    $purchaseSchemas[$slug] = [
        'slug' => $slug,
        'label' => $card['label'],
        'description' => $serviceMeta[$slug]['description'] ?? $card['copy'],
        'icon' => $card['icon'],
        'color' => $card['color'],
        'actionLabel' => match ($slug) {
            'airtime' => 'Purchase Airtime',
            'data' => 'Purchase Data',
            'cable_tv' => 'Renew Subscription Now',
            'electricity' => 'Pay Electricity',
            'exam_pin' => 'Buy Exam PIN Now',
            'bulk_sms' => 'Send Bulk SMS',
            'data_card' => 'Generate Data Card',
            'recharge_card' => 'Generate Recharge Card',
            default => 'Continue',
        },
        'networks' => $networkOptions,
        'plans' => $slug === 'data' ? array_map(static fn(array $plan): array => [
            'network' => (string) $plan['network_code'],
            'value' => (string) $plan['local_plan_code'],
            'label' => (string) $plan['local_plan_name'],
            'amount' => (float) $plan['amount'],
            'displayAmount' => money((float) $plan['amount']),
            'validity' => 'Available plan',
        ], $dataPlanCatalog) : [],
        'fields' => match ($slug) {
            'airtime' => [
                ['name' => 'network', 'label' => 'Select Network', 'type' => 'network_buttons', 'required' => true],
                ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'placeholder' => '08030000000', 'required' => true],
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'amount', 'placeholder' => '1000', 'required' => true],
            ],
            'data' => [
                ['name' => 'network', 'label' => 'Select Network', 'type' => 'network_buttons', 'required' => true],
                ['name' => 'plan', 'label' => 'Select Data Plan', 'type' => 'data_plan', 'required' => true],
                ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'placeholder' => '08030000000', 'required' => true],
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'readonly_amount', 'required' => true],
            ],
            'cable_tv' => [
                ['name' => 'provider', 'label' => 'Select Provider', 'type' => 'option_buttons', 'required' => true, 'options' => $networkOptions !== [] ? $networkOptions : [['value' => 'dstv', 'label' => 'DStv'], ['value' => 'gotv', 'label' => 'GOtv'], ['value' => 'startimes', 'label' => 'Startimes']]],
                ['name' => 'smartcard_number', 'label' => 'IUC / Smartcard Number', 'type' => 'text', 'placeholder' => '1234567890', 'required' => true],
                ['name' => 'package', 'label' => 'Select Subscription Package', 'type' => 'select', 'required' => true, 'options' => []],
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'amount', 'placeholder' => '8500', 'required' => true],
            ],
            'electricity' => [
                ['name' => 'disco', 'label' => 'Distribution Company', 'type' => 'option_buttons', 'required' => true, 'options' => $networkOptions],
                ['name' => 'meter_type', 'label' => 'Meter Type', 'type' => 'select', 'required' => true, 'options' => [['value' => 'prepaid', 'label' => 'Prepaid'], ['value' => 'postpaid', 'label' => 'Postpaid']]],
                ['name' => 'meter_number', 'label' => 'Meter Number', 'type' => 'text', 'placeholder' => '12345678901', 'required' => true],
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'amount', 'placeholder' => '5000', 'required' => true],
            ],
            'exam_pin' => [
                ['name' => 'exam_type', 'label' => 'Select Provider', 'type' => 'select', 'required' => true, 'options' => [['value' => 'waec', 'label' => 'WAEC'], ['value' => 'neco', 'label' => 'NECO'], ['value' => 'nabteb', 'label' => 'NABTEB'], ['value' => 'jamb', 'label' => 'JAMB']]],
                ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'placeholder' => '1', 'required' => true],
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'amount', 'placeholder' => '4500', 'required' => true],
            ],
            'bulk_sms' => [
                ['name' => 'sender', 'label' => 'Sender ID', 'type' => 'text', 'placeholder' => 'GemData', 'required' => true],
                ['name' => 'recipients', 'label' => 'Recipients', 'type' => 'text', 'placeholder' => '08030000001,08030000002', 'required' => true],
                ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'placeholder' => 'Type your message', 'required' => true],
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'amount', 'placeholder' => '1200', 'required' => true],
            ],
            'data_card' => [
                ['name' => 'network', 'label' => 'Select Network', 'type' => 'network_buttons', 'required' => true],
                ['name' => 'plan', 'label' => 'Plan', 'type' => 'text', 'placeholder' => 'Package or denomination', 'required' => true],
                ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'placeholder' => '5', 'required' => true],
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'amount', 'placeholder' => '3000', 'required' => true],
            ],
            'recharge_card' => [
                ['name' => 'network', 'label' => 'Select Network', 'type' => 'network_buttons', 'required' => true],
                ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'placeholder' => '10', 'required' => true],
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'amount', 'placeholder' => '3000', 'required' => true],
            ],
            default => [
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'amount', 'placeholder' => '1000', 'required' => true],
            ],
        },
    ];
}

$upgradeButton = match ($role) {
    'api' => ['label' => 'Open API Center', 'href' => base_url('user/api-center.php')],
    'reseller' => ['label' => 'Request API Access', 'href' => base_url('user/upgrade-request.php')],
    default => ['label' => 'Upgrade Now', 'href' => base_url('user/upgrade-request.php')],
};

render_header('Dashboard', 'user');
?>
<div data-services-dashboard class="space-y-6">
    <div class="stagger-1">
        <h1 class="text-2xl font-extrabold text-gem-text">Dashboard</h1>
        <p class="text-[14px] text-gem-muted mt-0.5">Welcome back, <?= e($firstName); ?>!</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 stagger-2">
        <div class="wallet-card rounded-2xl p-4 text-white xl:col-span-2 shadow-panel">
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-[13px] font-semibold text-blue-200">Main Wallet Balance</span>
                        <span class="text-blue-200"><?= dashboard_template_icon('eye', 'w-4 h-4'); ?></span>
                    </div>
                    <span class="flex items-center gap-1 bg-white/15 rounded-lg px-2.5 py-1 text-[12px] font-semibold">NGN <?= icon_svg('chevron'); ?></span>
                </div>

                <div class="mb-1">
                    <div class="text-[24px] font-extrabold tracking-tight font-mono"><?= e(money($wallet['balance'])); ?></div>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="bg-gem-green/20 text-green-300 text-[11px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1">Live</span>
                        <span class="text-blue-200 text-[12px]"><?= e($dashboard['role_label']); ?></span>
                        <?php if ($role === 'reseller'): ?><span class="bg-white/15 text-white text-[11px] font-bold px-2 py-0.5 rounded-full">Verified Reseller</span><?php endif; ?>
                        <?php if ($role === 'api'): ?><span class="bg-white/15 text-white text-[11px] font-bold px-2 py-0.5 rounded-full">Developer Account</span><?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-4">
                    <a href="<?= e(base_url('user/fund-wallet.php')); ?>" class="flex flex-col items-center gap-1.5 bg-white/15 hover:bg-white/25 rounded-xl py-2.5 px-1 transition-colors group">
                        <span class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center group-hover:scale-110 transition-transform"><?= dashboard_template_icon('plus', 'w-4 h-4'); ?></span>
                        <span class="text-[11px] font-medium">Fund Wallet</span>
                    </a>
                    <span class="flex flex-col items-center gap-1.5 bg-white/10 rounded-xl py-2.5 px-1 opacity-70">
                        <span class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center"><?= dashboard_template_icon('transfer', 'w-4 h-4'); ?></span>
                        <span class="text-[11px] font-medium">Transfer</span>
                    </span>
                    <span class="flex flex-col items-center gap-1.5 bg-white/10 rounded-xl py-2.5 px-1 opacity-70">
                        <span class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center"><?= dashboard_template_icon('withdraw', 'w-4 h-4'); ?></span>
                        <span class="text-[11px] font-medium">Withdraw</span>
                    </span>
                    <a href="<?= e(base_url('user/transactions.php')); ?>" class="flex flex-col items-center gap-1.5 bg-white/15 hover:bg-white/25 rounded-xl py-2.5 px-1 transition-colors group">
                        <span class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center group-hover:scale-110 transition-transform"><?= dashboard_template_icon('history', 'w-4 h-4'); ?></span>
                        <span class="text-[11px] font-medium">History</span>
                    </a>
                </div>

                <div class="mt-4 pt-3 border-t border-white/15">
                    <div class="flex items-center justify-between gap-3 mb-2.5">
                        <div>
                            <div class="text-[13px] font-bold text-white"><?= $multiProviderFunding ? 'Your Funding Accounts' : 'Your Funding Account'; ?></div>
                            <div class="text-[11px] text-blue-200">Bank transfer funding</div>
                        </div>
                        <?php if ($assignedFundingAccounts !== []): ?>
                            <span class="bg-green-400/20 text-green-200 text-[11px] font-bold px-2.5 py-1 rounded-full">Active</span>
                        <?php else: ?>
                            <span class="bg-amber-400/20 text-amber-100 text-[11px] font-bold px-2.5 py-1 rounded-full"><?= ($fundingAccount['status'] ?? '') === 'failed' ? 'Failed' : 'Pending'; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($assignedFundingAccounts !== []): ?>
                        <div class="space-y-2">
                            <?php foreach ($assignedFundingAccounts as $fundingRow): ?>
                                <?php
                                $rowBankName = (string) ($fundingRow['bank_name'] ?? '');
                                $rowAccountNumber = (string) ($fundingRow['dedicated_account_number'] ?? '');
                                $rowAccountName = (string) ($fundingRow['account_name'] ?? '');
                                $rowFullAccountCopy = trim($rowBankName . "\n" . $rowAccountNumber . "\n" . $rowAccountName);
                                ?>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <div class="bg-white/10 rounded-xl p-2.5"><div class="text-[10px] text-blue-200 uppercase font-bold">Bank Name</div><div class="text-[12px] font-bold mt-1"><?= e($rowBankName); ?></div></div>
                                    <div class="bg-white/10 rounded-xl p-2.5"><div class="text-[10px] text-blue-200 uppercase font-bold">Account Number</div><div class="text-[12px] font-mono font-bold mt-1"><?= e($rowAccountNumber); ?></div></div>
                                    <div class="bg-white/10 rounded-xl p-2.5"><div class="text-[10px] text-blue-200 uppercase font-bold">Account Name</div><div class="text-[12px] font-bold mt-1"><?= e($rowAccountName); ?></div></div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button class="gd-copy-button inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 rounded-lg px-2.5 py-1.5 text-[12px] font-semibold" type="button" data-copy-value="<?= e($rowAccountNumber); ?>"><?= dashboard_template_icon('copy', 'w-4 h-4'); ?>Copy Account Number</button>
                                    <button class="gd-copy-button inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 rounded-lg px-2.5 py-1.5 text-[12px] font-semibold" type="button" data-copy-value="<?= e($rowFullAccountCopy); ?>"><?= dashboard_template_icon('copy', 'w-4 h-4'); ?>Copy Full Details</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (($fundingAccount['status'] ?? '') === 'failed'): ?>
                        <p class="text-[12px] text-blue-100">We could not generate your funding account. Please contact support or try again.</p>
                    <?php else: ?>
                        <p class="text-[12px] text-blue-100">We are preparing your funding account.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="xl:col-span-3 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-3">
            <div class="stat-card user-premium-card rounded-2xl p-4 flex flex-col gap-3">
                <div class="user-icon-box user-icon-blue"><?= dashboard_template_icon('transactions_stat'); ?></div>
                <div><div class="user-metric-value"><?= $role === 'api' ? (int) ($apiUsage['total_requests'] ?? 0) : (int) $stats['transactions']; ?></div><div class="text-[12px] text-gem-muted font-bold mt-1"><?= $role === 'api' ? 'API Requests Today' : 'Transactions'; ?></div></div>
            </div>
            <div class="stat-card user-premium-card rounded-2xl p-4 flex flex-col gap-3">
                <div class="user-icon-box user-icon-orange"><?= dashboard_template_icon('total_spent_stat'); ?></div>
                <div><div class="user-metric-value"><?= $role === 'api' ? (int) ($apiUsage['successful'] ?? 0) : e(money($role === 'smart' ? $stats['total_spend'] : ($reseller['estimated_profit'] ?? 0))); ?></div><div class="text-[12px] text-gem-muted font-bold mt-1"><?= $role === 'api' ? 'Successful API Calls' : ($role === 'smart' ? 'Total Spent' : 'Estimated Profit'); ?></div></div>
            </div>
            <div class="stat-card user-premium-card rounded-2xl p-4 flex flex-col gap-3">
                <div class="user-icon-box user-icon-green"><?= dashboard_template_icon('success_stat'); ?></div>
                <div><div class="user-metric-value"><?= $role === 'api' ? (int) ($apiUsage['failed'] ?? 0) : e((string) $successRate) . '%'; ?></div><div class="text-[12px] text-gem-muted font-bold mt-1"><?= $role === 'api' ? 'Failed API Calls' : 'Success Rate'; ?></div><div class="progress-bar mt-2.5"><div class="progress-fill" style="width:<?= e((string) ($role === 'api' ? ($api['success_rate'] ?? 0) : $successRate)); ?>%"></div></div></div>
            </div>
            <div class="stat-card user-premium-card rounded-2xl p-4 flex flex-col gap-3">
                <div class="user-icon-box user-icon-purple"><?= dashboard_template_icon('account_status_stat'); ?></div>
                <div><div class="user-metric-value"><?= e($role === 'api' ? (string) ($api['success_rate'] ?? 0) . '%' : ($role === 'reseller' ? 'Active' : $dashboard['kyc_status'])); ?></div><div class="text-[12px] text-gem-muted font-bold mt-1"><?= $role === 'api' ? 'Webhook Success Rate' : ($role === 'reseller' ? 'Reseller Status' : 'Account Status'); ?></div><div class="flex items-center gap-1.5 mt-1.5"><span class="w-2 h-2 rounded-full bg-gem-green flex-shrink-0 animate-pulse"></span><span class="text-[11px] text-gem-muted"><?= e($dashboard['role_label']); ?></span></div></div>
            </div>
        </div>
    </div>

    <div class="activate-banner rounded-2xl flex flex-col sm:flex-row items-start sm:items-center gap-4 px-5 py-4 stagger-3">
        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0 text-gem-yellow"><?= icon_svg('shield'); ?></div>
        <div class="flex-1">
            <div class="text-[14px] font-bold text-gem-text"><?= e($upgrade['title']); ?></div>
            <div class="text-[13px] text-gem-muted mt-0.5"><?= e($upgrade['text']); ?></div>
        </div>
        <a href="<?= e($upgradeButton['href']); ?>" class="flex items-center gap-2 border border-gem-yellow text-gem-orange text-[13px] font-bold px-4 py-2 rounded-xl hover:bg-amber-50 transition-colors flex-shrink-0"><?= e($upgradeButton['label']); ?> <?= icon_svg('chevron'); ?></a>
    </div>

    <?php if (in_array($role, ['reseller', 'api'], true) && $reseller !== null): ?>
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5 stagger-3">
            <?php
            $commissionCards = [
                ['label' => 'Main Wallet Balance', 'value' => money((float) ($wallet['balance'] ?? 0)), 'note' => 'Spendable wallet funds', 'icon' => 'wallet', 'tone' => 'blue'],
                ['label' => 'Commission Wallet', 'value' => money((float) ($reseller['commission_balance'] ?? 0)), 'note' => 'Separate earnings balance', 'icon' => 'profit', 'tone' => 'green'],
                ['label' => 'Total Commission Earned', 'value' => money((float) ($reseller['total_earned'] ?? 0)), 'note' => 'All-time commission', 'icon' => 'transactions', 'tone' => 'purple'],
                ['label' => 'Withdrawable Commission', 'value' => money((float) ($reseller['commission_balance'] ?? 0)), 'note' => 'Available for request', 'icon' => 'refund', 'tone' => 'amber'],
                ['label' => 'Pending Withdrawal', 'value' => money((float) ($reseller['pending_withdrawal'] ?? 0)), 'note' => 'Awaiting admin review', 'icon' => 'pending', 'tone' => 'orange'],
            ];
            ?>
            <?php foreach ($commissionCards as $card): ?>
                <a class="user-premium-card user-premium-link rounded-2xl border border-gem-border bg-white p-4 shadow-card" href="<?= e(base_url($card['label'] === 'Pending Withdrawal' || $card['label'] === 'Withdrawable Commission' ? 'user/withdrawals.php' : 'user/commission.php')); ?>">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-gem-muted"><?= e($card['label']); ?></div>
                            <div class="mt-2 font-mono text-[18px] font-extrabold text-gem-text"><?= e($card['value']); ?></div>
                            <div class="mt-1 text-[11px] font-semibold text-gem-muted"><?= e($card['note']); ?></div>
                        </div>
                        <span class="user-icon-box user-icon-<?= e($card['tone']); ?>"><?= icon_svg($card['icon']); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div id="services" class="stagger-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-[16px] font-bold text-gem-text">Quick Services</h2>
            <a href="<?= e(base_url('user/dashboard.php#services')); ?>" class="text-gem-blue text-[13px] font-semibold hover:underline">Edit Services</a>
        </div>
        <div id="ajax-feedback" class="mb-4"></div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3" data-skeleton-scope>
            <?php foreach ($services as $service): ?>
                <?php
                $slug = (string) $service['slug'];
                $card = $serviceCards[$slug] ?? ['label' => (string) $service['name'], 'copy' => 'Complete service', 'icon' => 'funding_account', 'color' => 'bg-blue-600'];
                $dedicatedUrl = $dedicatedServiceUrls[$slug] ?? base_url('user/dashboard.php#services');
                ?>
                <a class="service-icon user-premium-card user-premium-link bg-white rounded-2xl p-4 shadow-card border border-gem-border cursor-pointer text-left block" href="<?= e($dedicatedUrl); ?>" data-service-slug="<?= e($slug); ?>" data-search-item data-search="<?= e($service['name'] . ' ' . ($service['description'] ?? '') . ' ' . ($serviceMeta[$slug]['summary'] ?? '')); ?>">
                    <div class="w-10 h-10 rounded-xl <?= e($card['color']); ?> flex items-center justify-center mb-3"><?= dashboard_template_icon($card['icon']); ?></div>
                    <div class="text-[13px] font-bold text-gem-text"><?= e($card['label']); ?></div>
                    <div class="text-[11px] text-gem-muted mt-0.5"><?= e($card['copy']); ?></div>
                    <div class="flex items-center justify-end mt-2"><?= dashboard_template_icon('arrow', 'w-3.5 h-3.5 text-gem-muted'); ?></div>
                </a>
            <?php endforeach; ?>
            <a href="<?= e(base_url('user/fund-wallet.php')); ?>" class="service-icon user-premium-card user-premium-link bg-white rounded-2xl p-4 shadow-card border border-gem-border cursor-pointer">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center mb-3"><?= dashboard_template_icon('funding_account'); ?></div>
                <div class="text-[13px] font-bold text-gem-text">Virtual Account</div>
                <div class="text-[11px] text-gem-muted mt-0.5">Funding account</div>
                <div class="flex items-center justify-end mt-2"><?= dashboard_template_icon('arrow', 'w-3.5 h-3.5 text-gem-muted'); ?></div>
            </a>
            <a href="<?= e(base_url('user/referrals.php')); ?>" class="service-icon user-premium-card user-premium-link bg-white rounded-2xl p-4 shadow-card border border-gem-border cursor-pointer">
                <div class="w-10 h-10 rounded-xl bg-green-600 flex items-center justify-center mb-3"><?= dashboard_template_icon('referrals'); ?></div>
                <div class="text-[13px] font-bold text-gem-text">Referrals</div>
                <div class="text-[11px] text-gem-muted mt-0.5">Invite & earn</div>
                <div class="flex items-center justify-end mt-2"><?= dashboard_template_icon('arrow', 'w-3.5 h-3.5 text-gem-muted'); ?></div>
            </a>
        </div>
    </div>

    <?php if ($role === 'reseller'): ?>
        <div id="reseller-tools" class="grid grid-cols-1 lg:grid-cols-2 gap-4 stagger-4">
            <div class="user-premium-card bg-white rounded-2xl p-5 shadow-card border border-gem-border">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[16px] font-bold text-gem-text">Business Tools</h2>
                    <span class="bg-green-50 text-gem-green text-[11px] font-bold px-2.5 py-1 rounded-full">Verified Reseller</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <?php foreach ([['Bulk Airtime','user/buy-airtime.php'], ['Bulk Data','user/buy-data.php'], ['Saved Customers','user/customers.php'], ['Reports','user/commission.php'], ['Profit Summary','user/pricing.php']] as $tool): ?>
                        <a class="user-premium-card user-premium-link rounded-xl bg-gem-gray border border-gem-border p-4 hover:bg-white transition-colors" href="<?= e(base_url($tool[1])); ?>"><div class="text-[13px] font-bold text-gem-text"><?= e($tool[0]); ?></div><div class="text-[11px] text-gem-muted mt-1">Open module</div></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="user-premium-card bg-white rounded-2xl p-5 shadow-card border border-gem-border">
                <h2 class="text-[16px] font-bold text-gem-text mb-4">Customers</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="user-premium-card rounded-xl bg-gem-gray border border-gem-border p-4"><div class="user-metric-value"><?= $savedCustomersCount; ?></div><div class="text-[12px] text-gem-muted">Saved Customers</div></div>
                    <div class="user-premium-card rounded-xl bg-gem-gray border border-gem-border p-4"><div class="user-metric-value"><?= min(5, $savedCustomersCount); ?></div><div class="text-[12px] text-gem-muted">Frequent Beneficiaries</div></div>
                    <div class="user-premium-card rounded-xl bg-gem-gray border border-gem-border p-4"><div class="text-[13px] font-bold text-gem-text"><?= e($recentRecipients[0] ?? 'No recipient yet'); ?></div><div class="text-[12px] text-gem-muted">Recent Recipient</div></div>
                </div>
            </div>
        </div>
        <div id="pricing" class="grid grid-cols-1 lg:grid-cols-2 gap-4 stagger-4">
            <div class="user-premium-card bg-white rounded-2xl p-5 shadow-card border border-gem-border">
                <h2 class="text-[16px] font-bold text-gem-text mb-4">Reseller Pricing</h2>
                <div class="space-y-3">
                    <?php foreach (($reseller['rates'] ?? []) as $rate): ?>
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-gem-gray border border-gem-border px-4 py-3 text-[13px]">
                            <span>
                                <span class="block font-bold text-gem-text"><?= e((string) $rate['name']); ?></span>
                                <span class="block text-[11px] text-gem-muted"><?= e((string) ($rate['source_label'] ?? 'Not configured')); ?></span>
                            </span>
                            <?php if (!empty($rate['commission_enabled'])): ?>
                                <strong class="bg-blue-50 text-gem-blue rounded-full px-2.5 py-1 text-[12px]"><?= e(number_format((float) $rate['rate_percent'], 2)); ?>%</strong>
                            <?php else: ?>
                                <strong class="bg-gem-gray text-gem-muted rounded-full px-2.5 py-1 text-[12px]">Not configured</strong>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (($reseller['rates'] ?? []) === []): ?><p class="text-[13px] text-gem-muted">Discount rates will appear after admin configures pricing.</p><?php endif; ?>
                </div>
            </div>
            <div class="user-premium-card bg-white rounded-2xl p-5 shadow-card border border-gem-border">
                <h2 class="text-[16px] font-bold text-gem-text mb-4">Reports</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="user-premium-card rounded-xl bg-gem-gray border border-gem-border p-4"><div class="text-[13px] text-gem-muted">Today Sales</div><strong class="text-gem-text font-mono"><?= e(money(0)); ?></strong></div>
                    <div class="user-premium-card rounded-xl bg-gem-gray border border-gem-border p-4"><div class="text-[13px] text-gem-muted">Weekly Profit</div><strong class="text-gem-text font-mono"><?= e(money((float) ($reseller['estimated_profit'] ?? 0))); ?></strong></div>
                    <div class="user-premium-card rounded-xl bg-gem-gray border border-gem-border p-4"><div class="text-[13px] text-gem-muted">Monthly Revenue</div><strong class="text-gem-text font-mono"><?= e(money((float) $stats['total_spend'])); ?></strong></div>
                    <div class="user-premium-card rounded-xl bg-gem-gray border border-gem-border p-4"><div class="text-[13px] text-gem-muted">Total Transactions</div><strong class="text-gem-text"><?= (int) $stats['transactions']; ?></strong></div>
                </div>
                <div class="activate-banner rounded-xl mt-4 px-4 py-3"><div class="text-[13px] font-bold text-gem-text">Need automation? Upgrade to API User.</div><a class="text-[12px] font-bold text-gem-blue" href="<?= e(base_url('user/upgrade-request.php')); ?>">Request API Access</a></div>
            </div>
        </div>
    <?php elseif ($role === 'api'): ?>
        <div id="developer-center" class="grid grid-cols-1 lg:grid-cols-2 gap-4 stagger-4">
            <div class="user-premium-card bg-white rounded-2xl p-5 shadow-card border border-gem-border">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[16px] font-bold text-gem-text">API Center</h2>
                    <span class="bg-blue-50 text-gem-blue text-[11px] font-bold px-2.5 py-1 rounded-full">Verified API User</span>
                </div>
                <div class="rounded-xl bg-gem-gray border border-gem-border p-4 mb-3">
                    <div class="text-[12px] text-gem-muted font-bold">API Key Preview</div>
                    <div class="flex items-center justify-between gap-3 mt-2"><strong class="font-mono text-[13px] text-gem-text"><?= e($apiKeyPreview); ?></strong><button type="button" class="gd-copy-button text-[12px] font-bold text-gem-blue" data-copy-value="<?= e($apiKeyPreview); ?>"><?= dashboard_template_icon('copy', 'w-4 h-4 text-gem-blue'); ?> Copy</button></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach ([['API Keys','user/api-keys.php'], ['Webhook Config','user/webhooks.php'], ['Sandbox','user/api-center.php'], ['Request Logs','user/request-logs.php']] as $tool): ?>
                        <a class="user-premium-card user-premium-link rounded-xl bg-gem-gray border border-gem-border p-4 hover:bg-white transition-colors" href="<?= e(base_url($tool[1])); ?>"><div class="text-[13px] font-bold text-gem-text"><?= e($tool[0]); ?></div><div class="text-[11px] text-gem-muted mt-1">Open</div></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="user-premium-card bg-white rounded-2xl p-5 shadow-card border border-gem-border">
                <h2 class="text-[16px] font-bold text-gem-text mb-4">Developer Health</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-green-50 border border-green-100 p-4"><div class="text-[13px] text-gem-muted">API Status</div><strong class="text-gem-green">Operational</strong></div>
                    <div class="rounded-xl bg-green-50 border border-green-100 p-4"><div class="text-[13px] text-gem-muted">Webhook Queue</div><strong class="text-gem-green">Healthy</strong></div>
                    <div class="rounded-xl bg-gem-gray border border-gem-border p-4"><div class="text-[13px] text-gem-muted">Webhook Status</div><strong class="text-gem-text">Not Connected</strong></div>
                    <div class="rounded-xl bg-gem-gray border border-gem-border p-4"><div class="text-[13px] text-gem-muted">Monthly Usage</div><strong class="text-gem-text"><?= (int) ($apiUsage['total_requests'] ?? 0); ?> calls</strong></div>
                </div>
                <a class="primary-action inline-flex mt-4" href="<?= e(base_url('docs/api.php')); ?>">View API Documentation</a>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 stagger-4">
            <div class="user-premium-card bg-white rounded-2xl p-5 shadow-card border border-gem-border">
                <h2 class="text-[16px] font-bold text-gem-text mb-4">Getting Started</h2>
                <div class="grid gap-2 text-[13px] text-gem-text"><div>1. Generate API Key</div><div>2. Read Docs</div><div>3. Configure Webhook</div><div>4. Start Integration</div></div>
            </div>
            <div class="user-premium-card bg-white rounded-2xl p-5 shadow-card border border-gem-border">
                <h2 class="text-[16px] font-bold text-gem-text mb-4">Billing</h2>
                <div class="grid grid-cols-3 gap-3"><div class="rounded-xl bg-gem-gray p-3"><div class="text-[11px] text-gem-muted">Consumption</div><strong><?= (int) ($apiUsage['total_requests'] ?? 0); ?></strong></div><div class="rounded-xl bg-gem-gray p-3"><div class="text-[11px] text-gem-muted">Charges</div><strong><?= e(money((float) ($apiUsage['total_volume'] ?? 0))); ?></strong></div><div class="rounded-xl bg-gem-gray p-3"><div class="text-[11px] text-gem-muted">Remaining</div><strong>Unlimited</strong></div></div>
                <div class="rounded-xl bg-gem-gray border border-gem-border p-4 mt-3"><div class="text-[13px] font-bold text-gem-text">POST /api/data</div><div class="text-[12px] text-gem-muted">200 OK / 2s ago</div></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="stagger-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-[16px] font-bold text-gem-text">Recent Transactions</h2>
            <a href="<?= e(base_url('user/transactions.php')); ?>" class="text-gem-blue text-[13px] font-semibold hover:underline">View All</a>
        </div>
        <div class="user-premium-card bg-white rounded-2xl shadow-card border border-gem-border overflow-hidden">
            <div class="user-table-head hidden sm:grid grid-cols-5 gap-4 px-5 py-3 bg-gem-gray border-b border-gem-border text-[11px] font-bold text-gem-muted uppercase tracking-wider">
                <div class="col-span-2">Service / Description</div><div>Amount</div><div>Status</div><div>Date</div>
            </div>
            <div class="divide-y divide-gem-border" data-recent-transactions>
                <?php if ($recentTransactions === []): ?>
                    <div class="user-empty-state px-5 py-8 text-center text-[13px] text-gem-muted"><?= $role === 'reseller' ? 'Start your reseller journey by funding your wallet and making your first sale.' : 'No transactions yet. Fund your wallet and buy your first service.'; ?></div>
                <?php endif; ?>
                <?php foreach (array_slice($recentTransactions, 0, 5) as $row): ?>
                    <?php $statusColor = ($row['status'] ?? '') === 'successful' ? 'green' : (($row['status'] ?? '') === 'failed' ? 'red' : 'amber'); ?>
                    <div class="user-list-row grid grid-cols-1 sm:grid-cols-5 gap-2 sm:gap-4 px-5 py-4 hover:bg-gem-gray/50 transition-colors" data-search-item data-search="<?= e($row['reference'] . ' ' . $row['service_name'] . ' ' . $row['status']); ?>">
                        <div class="col-span-2 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0 text-blue-600"><?= icon_svg('services'); ?></div>
                            <div><div class="text-[13px] font-semibold text-gem-text"><?= e($row['service_name']); ?></div><div class="text-[11px] text-gem-muted"><?= e((string) ($row['recipient'] ?? $row['reference'])); ?></div></div>
                        </div>
                        <div class="sm:flex sm:items-center"><span class="text-[13px] font-bold text-gem-text font-mono"><?= e(money($row['amount'])); ?></span></div>
                        <div class="sm:flex sm:items-center"><span class="inline-flex items-center gap-1 bg-<?= e($statusColor); ?>-50 text-<?= e($statusColor === 'amber' ? 'amber-600' : 'gem-' . ($statusColor === 'green' ? 'green' : 'red')); ?> text-[11px] font-semibold px-2.5 py-1 rounded-full"><span class="w-1.5 h-1.5 rounded-full bg-<?= e($statusColor === 'amber' ? 'amber-500' : 'gem-' . ($statusColor === 'green' ? 'green' : 'red')); ?>"></span><?= e(ucfirst((string) $row['status'])); ?></span></div>
                        <div class="sm:flex sm:items-center"><span class="text-[12px] text-gem-muted"><?= e(human_datetime((string) $row['created_at'])); ?></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 stagger-6">
        <div class="user-premium-card bg-white rounded-2xl p-4 border border-gem-border shadow-card flex items-center gap-3"><div class="user-icon-box user-icon-amber !w-9 !h-9 !rounded-xl"><?= icon_svg('chart'); ?></div><div><div class="text-[13px] font-bold text-gem-text">99.9% Uptime</div><div class="text-[11px] text-gem-muted">Reliable Services</div></div></div>
        <div class="user-premium-card bg-white rounded-2xl p-4 border border-gem-border shadow-card flex items-center gap-3"><div class="user-icon-box user-icon-blue !w-9 !h-9 !rounded-xl"><?= icon_svg('shield'); ?></div><div><div class="text-[13px] font-bold text-gem-text">Secure Wallet</div><div class="text-[11px] text-gem-muted">Protected access</div></div></div>
        <div class="user-premium-card bg-white rounded-2xl p-4 border border-gem-border shadow-card flex items-center gap-3"><div class="user-icon-box user-icon-green !w-9 !h-9 !rounded-xl"><?= icon_svg('services'); ?></div><div><div class="text-[13px] font-bold text-gem-text">Instant Delivery</div><div class="text-[11px] text-gem-muted">Quick & Reliable</div></div></div>
        <div class="user-premium-card bg-white rounded-2xl p-4 border border-gem-border shadow-card flex items-center gap-3"><div class="user-icon-box user-icon-purple !w-9 !h-9 !rounded-xl"><?= icon_svg('notification'); ?></div><div><div class="text-[13px] font-bold text-gem-text">24/7 Support</div><div class="text-[11px] text-gem-muted">We are here for you</div></div></div>
    </div>

    <div class="purchase-modal" data-purchase-modal hidden>
        <div class="purchase-modal-backdrop" data-purchase-close></div>
        <section class="purchase-modal-panel" role="dialog" aria-modal="true" aria-labelledby="purchase-modal-title" tabindex="-1">
            <button class="purchase-modal-close" type="button" aria-label="Close purchase form" data-purchase-close><?= icon_svg('close'); ?></button>
            <div class="purchase-modal-head">
                <div class="purchase-modal-icon" data-purchase-icon><?= dashboard_template_icon('data'); ?></div>
                <div>
                    <p class="purchase-modal-kicker">Secure Purchase</p>
                    <h3 id="purchase-modal-title" data-purchase-title>Select service</h3>
                    <p data-purchase-description>Complete your transaction from your wallet.</p>
                </div>
            </div>
            <div class="purchase-modal-balance">
                <span>Wallet Balance</span>
                <strong data-wallet-balance><?= e(money((float) $wallet['balance'])); ?></strong>
            </div>
            <div class="purchase-stepper" aria-hidden="true">
                <span class="is-active" data-step-dot="form">Details</span>
                <span data-step-dot="confirm">Confirm</span>
                <span data-step-dot="result">Receipt</span>
            </div>
            <div class="purchase-modal-message" data-purchase-message hidden></div>
            <form class="purchase-dynamic-form" data-purchase-form action="<?= e(base_url('user/service-action.php')); ?>" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="service_slug" value="">
                <input type="hidden" name="idempotency_key" value="">
                <div class="purchase-dynamic-fields" data-purchase-fields></div>
                <div class="purchase-confirm-summary" data-purchase-summary hidden></div>
                <div class="purchase-modal-actions">
                    <button class="secondary-action" type="button" data-purchase-back hidden>Back</button>
                    <button class="primary-action purchase-submit" type="submit" data-purchase-submit>Continue</button>
                </div>
            </form>
        </section>
    </div>
</div>
<script nonce="<?= e(csp_nonce()); ?>" type="application/json" id="purchase-service-config">
<?= json_encode([
    'services' => $purchaseSchemas,
    'icons' => array_map(static fn(array $card): string => $card['icon'], $serviceCards),
    'endpoint' => base_url('user/service-action.php'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
</script>
<?php render_footer(); ?>
