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

// Retrieve input data
$input = $_POST;
if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: [];
}

$currentPin = trim((string) ($input['current_pin'] ?? ''));
$newPin = trim((string) ($input['wallet_pin'] ?? ''));
$newPinConfirm = trim((string) ($input['wallet_pin_confirmation'] ?? ''));

$db = db();
$securityUser = $db->first('SELECT transaction_pin_hash FROM users WHERE id = :id LIMIT 1', ['id' => (int) $user['id']]);
$pinHash = (string) ($securityUser['transaction_pin_hash'] ?? '');
$hasPin = ($pinHash !== '');

$errors = [];

if ($hasPin) {
    if ($currentPin === '' || !password_verify($currentPin, $pinHash)) {
        $errors['current_pin'][] = 'Current Wallet PIN is incorrect.';
    }
}

$pinValid = static function (string $pin): bool {
    return preg_match('/^\d{4,6}$/', $pin) === 1;
};

if (!$pinValid($newPin)) {
    $errors['wallet_pin'][] = 'Wallet PIN must be between 4 and 6 digits.';
}

if ($newPin !== $newPinConfirm) {
    $errors['wallet_pin_confirmation'][] = 'Wallet PINs do not match.';
}

if ($errors !== []) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    exit;
}

try {
    $db->execute(
        'UPDATE users SET transaction_pin_hash = :pin_hash WHERE id = :id',
        [
            'pin_hash' => password_hash($newPin, PASSWORD_DEFAULT),
            'id' => (int) $user['id']
        ]
    );

    app(\GemData\Classes\ActivityLogger::class)->log(
        'user', 
        (int) $user['id'], 
        $hasPin ? 'wallet_pin_changed_mobile' : 'wallet_pin_set_mobile', 
        $hasPin ? 'User changed Wallet PIN via mobile.' : 'User set Wallet PIN via mobile.'
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $hasPin ? 'Wallet PIN updated successfully.' : 'Wallet PIN set successfully.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update PIN. Please try again later.'
    ]);
}
