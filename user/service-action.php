<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
verify_csrf();

$serviceSlug = trim($_POST['service_slug'] ?? '');
$payload = $_POST;

if ($serviceSlug === 'cable_tv' && empty($payload['provider']) && !empty($payload['plan'])) {
    $payload['provider'] = $payload['plan'];
}

try {
    $result = app(\GemData\Classes\TransactionService::class)->purchase($serviceSlug, (int) $user['id'], $payload, 'web', false);
    app(\GemData\Classes\Response::class)->json('success', 'Transaction accepted and queued for processing.', $result, [], ['reload' => true]);
} catch (InvalidArgumentException $exception) {
    app(\GemData\Classes\Response::class)->json('error', 'Validation failed.', [], json_decode((string) $exception->getMessage(), true) ?: [], [], 422);
} catch (Throwable $throwable) {
    app(\GemData\Classes\Response::class)->json('error', $throwable->getMessage(), [], [], [], 400);
}
