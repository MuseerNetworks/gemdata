<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$payload = file_get_contents('php://input') ?: '{}';
$signature = trim((string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? ''));

try {
    $result = app(\GemData\Classes\PaystackWebhookService::class)->handle($payload, $signature);
    app(\GemData\Classes\Response::class)->json(
        'success',
        !empty($result['duplicate']) ? 'Duplicate webhook ignored.' : 'Webhook processed successfully.',
        [
            'event' => $result['event'] ?? 'unknown',
            'event_key' => $result['event_key'] ?? null,
            'duplicate' => !empty($result['duplicate']),
            'credited' => !empty($result['credited']),
        ]
    );
} catch (Throwable $throwable) {
    app_logger()->warning('Webhook request rejected.', [
        'error' => $throwable->getMessage(),
    ]);
    app(\GemData\Classes\Response::class)->json('error', 'Webhook processing failed.', [], [], [], 400);
}
