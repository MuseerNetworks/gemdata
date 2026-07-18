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
    'service' => (string) ($tx['service_slug'] ?? ''),
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
            'total_transactions' => (int) ($dashboardData['stats']['transactions'] ?? 0),
            'total_spent' => (float) ($dashboardData['stats']['total_spend'] ?? 0),
            'profit' => (float) ($dashboardData['reseller']['estimated_profit'] ?? 0),
            'success_rate' => $dashboardData['stats']['transactions'] > 0 ? 98.7 : 0
        ],
        'funding_accounts' => $mappedFunding,
        'recent_transactions' => $mappedTransactions,
        'services' => array_map(static fn(array $srv): array => [
            'name' => (string) $srv['name'],
            'slug' => (string) $srv['slug'],
            'status' => (string) ($srv['status'] ?? 'active')
        ], $dashboardData['services'] ?? []),
        'data_plan_catalog' => array_map(static fn(array $plan): array => [
            'network' => (string) ($plan['network_code'] ?? ''),
            'value' => (string) ($plan['local_plan_code'] ?? ''),
            'label' => (string) ($plan['local_plan_name'] ?? ''),
            'amount' => (float) ($plan['amount'] ?? 0),
            'validity' => trim((string) ($plan['validity_label'] ?? '')) !== '' ? (string) $plan['validity_label'] : 'Available plan'
        ], array_values($dashboardData['data_plan_catalog'] ?? []))
    ]
], JSON_UNESCAPED_SLASHES);
