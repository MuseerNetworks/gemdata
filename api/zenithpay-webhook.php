<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * ZenithPay Webhook Receiver
 *
 * Set this URL in your ZenithPay Dashboard → Settings → Webhook URL:
 *   https://gemdata.com.ng/api/zenithpay-webhook.php
 *
 * Security: ZenithPay signs requests by IP only.
 * Add their public IPs to config: payments.zenithpay_allowed_ips
 */

$payload   = file_get_contents('php://input') ?: '{}';
$remoteIp  = trim((string) (
    $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? ''
));

// Strip port from IP if present (e.g. "41.58.1.2:50000" → "41.58.1.2")
$remoteIp = strtok($remoteIp, ':') ?: $remoteIp;

// If behind a proxy/load balancer, X-Forwarded-For may have multiple IPs — take first
if (str_contains($remoteIp, ',')) {
    $remoteIp = trim(explode(',', $remoteIp)[0]);
}

try {
    $result = app(\GemData\Classes\ZenithPayWebhookService::class)->handle($payload, $remoteIp);

    app(\GemData\Classes\Response::class)->json(
        'success',
        !empty($result['duplicate']) ? 'Duplicate webhook ignored.' : 'Webhook processed successfully.',
        [
            'event'     => $result['event']     ?? 'unknown',
            'event_key' => $result['event_key'] ?? null,
            'duplicate' => !empty($result['duplicate']),
            'credited'  => !empty($result['credited']),
        ]
    );
} catch (Throwable $throwable) {
    app_logger()->warning('ZenithPay webhook rejected.', [
        'error'     => $throwable->getMessage(),
        'remote_ip' => $remoteIp,
    ]);
    app(\GemData\Classes\Response::class)->json('error', 'Webhook processing failed.', [], [], [], 400);
}
