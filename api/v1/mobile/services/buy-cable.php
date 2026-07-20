<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Allow only POST method
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Authenticate session
$user = require_mobile_user();

// Check rate limit for transactions
check_mobile_rate_limit('purchase_vtu', 10, 60);

// Retrieve input data
$input = $_POST;
if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: [];
}

// Re-map plan to provider if missing, matching web portal fallback behavior
if (empty($input['provider']) && !empty($input['plan'])) {
    $input['provider'] = $input['plan'];
}

// Wallet PIN validation
$db = db();
if (!$db->columnExists('users', 'transaction_pin_hash')) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Wallet PIN protection is not configured on the server.'
    ]);
    exit;
}

$pinRow = $db->first('SELECT transaction_pin_hash FROM users WHERE id = :id LIMIT 1', ['id' => (int) $user['id']]);
$pinHash = (string) ($pinRow['transaction_pin_hash'] ?? '');
if ($pinHash === '') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Set your Wallet PIN before making purchases.'
    ]);
    exit;
}

$pin = trim((string) ($input['security_pin'] ?? $input['wallet_pin'] ?? ''));
if ($pin === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Wallet PIN is required.'
    ]);
    exit;
}

if (!password_verify($pin, $pinHash)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid wallet PIN.'
    ]);
    exit;
}

// Support validation fallbacks
$input['cable_validation_status'] = $input['cable_validation_status'] ?? 'unavailable';
$input['cable_iuc_confirmed'] = $input['cable_iuc_confirmed'] ?? '1';

try {
    $result = app(\GemData\Classes\TransactionService::class)->purchase('cable_tv', (int) $user['id'], $input, 'mobile', false);
    
    $walletBalance = app(\GemData\Classes\Wallet::class)->balance((int) $user['id']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Transaction accepted and queued for processing.',
        'data' => [
            'reference' => (string) ($result['reference'] ?? ''),
            'amount' => (float) ($result['amount'] ?? 0),
            'status' => (string) ($result['status'] ?? 'pending'),
            'wallet_balance' => (float) $walletBalance
        ]
    ], JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => json_decode($e->getMessage(), true) ?: []
      ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
