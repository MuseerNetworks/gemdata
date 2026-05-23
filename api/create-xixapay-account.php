<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$response = app(\GemData\Classes\Response::class);

if (!is_post()) {
    $response->json('error', 'Method not allowed.', [], [], [], 405);
}

verify_csrf();

$user = require_user();
$payload = $_POST;

try {
    if ((bool) config('security.email_verification_required_for_money_movement', true) && db()->columnExists('users', 'email_verified_at') && empty($user['email_verified_at'])) {
        $response->json('error', 'Verify your email address before requesting a virtual account.', [], [], [], 403);
    }

    $account = app(\GemData\Classes\XixaPay::class)->ensureStaticAccountForUser(
        (int) $user['id'],
        (string) ($payload['id_type'] ?? ''),
        (string) ($payload['id_number'] ?? ''),
        (string) ($payload['force_retry'] ?? '0') === '1'
    );

    $response->json('success', 'XixaPay account request processed.', [
        'account' => [
            'provider' => $account['provider'] ?? 'xixapay',
            'status' => $account['status'] ?? 'pending',
            'account_number' => $account['dedicated_account_number'] ?? null,
            'account_name' => $account['account_name'] ?? null,
            'bank_name' => $account['bank_name'] ?? null,
            'last_error_message' => $account['last_error_message'] ?? null,
        ],
    ]);
} catch (Throwable $throwable) {
    app_logger()->warning('XixaPay account creation failed.', [
        'user_id' => $user['id'] ?? null,
        'error' => $throwable->getMessage(),
    ]);
    $response->json('error', 'Virtual account request could not be processed right now.', [], [], [], 422);
}
