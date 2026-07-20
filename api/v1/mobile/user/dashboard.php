<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Allow only GET method
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ]);
    exit;
}

$user = require_mobile_user();

// Retrieve dashboard dataset using shared DashboardController logic
$controller = app(\GemData\Classes\DashboardController::class);
$dashboardData = $controller->dataFor($user);

// Sanitize and map the response data payload
$mappedTransactions = array_map(static fn(array $tx): array => [
    'reference' => (string) ($tx['reference'] ?? ''),
    'service' => (string) ($tx['service_name'] ?? $tx['service_slug'] ?? ''),
    'service_slug' => (string) ($tx['service_slug'] ?? ''),
    'channel' => (string) ($tx['channel'] ?? ''),
    'recipient' => (string) ($tx['recipient'] ?? ''),
    'amount' => (float) ($tx['amount'] ?? 0),
    'status' => (string) ($tx['status'] ?? 'pending'),
    'created_at' => (string) ($tx['created_at'] ?? '')
], $dashboardData['recent_transactions'] ?? []);

$walletPinConfigured = false;
if (db()->columnExists('users', 'transaction_pin_hash')) {
    $pinRow = db()->first('SELECT transaction_pin_hash FROM users WHERE id = :id LIMIT 1', ['id' => (int) $user['id']]);
    $walletPinConfigured = trim((string) ($pinRow['transaction_pin_hash'] ?? '')) !== '';
}

$assignedFundingAccounts = array_values(array_filter($dashboardData['funding_accounts'] ?? [], static function (array $row): bool {
    return ($row['status'] ?? '') === 'assigned' && trim((string) ($row['dedicated_account_number'] ?? '')) !== '';
}));

$mappedFunding = array_map(static fn(array $row): array => [
    'bank_name' => (string) ($row['bank_name'] ?? ''),
    'account_number' => (string) ($row['dedicated_account_number'] ?? ''),
    'account_name' => (string) ($row['account_name'] ?? '')
], $assignedFundingAccounts);

$mapPlan = static fn(array $plan): array => [
    'network_code' => (string) ($plan['network_code'] ?? ''),
    'network_name' => (string) ($plan['network_name'] ?? ''),
    'local_plan_code' => (string) ($plan['local_plan_code'] ?? ''),
    'local_plan_name' => (string) ($plan['local_plan_name'] ?? ''),
    'amount' => (float) ($plan['amount'] ?? 0),
    'validity_label' => trim((string) ($plan['validity_label'] ?? '')) !== '' ? (string) $plan['validity_label'] : '',
];

$providerPlanCatalogs = [];
foreach (($dashboardData['provider_plan_catalogs'] ?? []) as $slug => $catalog) {
    $providerPlanCatalogs[(string) $slug] = array_map($mapPlan, array_values((array) $catalog));
}

$totalTransactions = (int) ($dashboardData['stats']['transactions'] ?? 0);
$successfulTransactions = 0;
if ($totalTransactions > 0) {
    $successfulTransactions = (int) db()->safeFirst(
        'SELECT COUNT(*) AS total FROM transactions WHERE user_id = :user_id AND status = "successful"',
        ['user_id' => (int) $user['id']]
    )['total'] ?? 0;
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Dashboard metrics fetched.',
    'data' => [
        'user' => [
            'full_name' => (string) $user['full_name'],
            'email' => (string) $user['email'],
            'role' => (string) ($dashboardData['role'] ?? 'smart'),
            'role_label' => (string) ($dashboardData['role_label'] ?? 'Smart User'),
            'wallet_pin_configured' => $walletPinConfigured
        ],
        'wallet' => [
            'balance' => (float) ($dashboardData['wallet']['balance'] ?? 0),
        ],
        'stats' => [
            'total_transactions' => $totalTransactions,
            'total_spent' => (float) ($dashboardData['stats']['total_spend'] ?? 0),
            'profit' => (float) ($dashboardData['reseller']['estimated_profit'] ?? 0),
            'success_rate' => $totalTransactions > 0 ? round(($successfulTransactions / $totalTransactions) * 100, 1) : 0
        ],
        'funding_accounts' => $mappedFunding,
        'recent_transactions' => $mappedTransactions,
        'services' => array_map(static fn(array $srv): array => [
            'name' => (string) $srv['name'],
            'slug' => (string) $srv['slug'],
            'status' => (string) ($srv['status'] ?? 'active')
        ], $dashboardData['services'] ?? []),
        'service_meta' => $dashboardData['service_meta'] ?? [],
        'service_networks' => $dashboardData['service_networks'] ?? [],
        'provider_plan_catalogs' => $providerPlanCatalogs,
        'data_plan_catalog' => array_map($mapPlan, array_values($dashboardData['data_plan_catalog'] ?? [])),
        'upgrade' => $dashboardData['upgrade'] ?? null,
        'reseller' => $dashboardData['reseller'] ?? null
    ]
], JSON_UNESCAPED_SLASHES);
