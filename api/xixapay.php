<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'POST') {
    if (!headers_sent()) {
        header('Allow: POST');
    }
    app(\GemData\Classes\Response::class)->json('error', 'Method not allowed.', [], [], [], 405);
}

$payload = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$secret = trim((string) config('webhooks.shared_secret', ''));
if ($secret === '') {
    app_logger()->warning('XixaPay webhook rejected because webhook secret is not configured.');
    app(\GemData\Classes\Response::class)->json('error', 'Webhook is not configured.', [], [], [], 503);
}

$headerValue = static function (array $headers, string $name): string {
    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === strtolower($name)) {
            return trim((string) $value);
        }
    }
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$serverKey] ?? ''));
};

$providedSecret = $headerValue($headers, 'X-Webhook-Secret') ?: $headerValue($headers, 'X-GemData-Webhook-Secret');
$providedSignature = $headerValue($headers, 'X-XixaPay-Signature') ?: $headerValue($headers, 'X-Hub-Signature-256');
$expectedSignature = hash_hmac('sha256', $payload, $secret);
$normalizedSignature = str_starts_with($providedSignature, 'sha256=')
    ? substr($providedSignature, 7)
    : $providedSignature;

if (
    !hash_equals($secret, $providedSecret)
    && ($normalizedSignature === '' || !hash_equals($expectedSignature, $normalizedSignature))
) {
    app_logger()->warning('XixaPay webhook rejected because signature validation failed.', ['remote_ip' => client_ip()]);
    app(\GemData\Classes\Response::class)->json('error', 'Invalid webhook signature.', [], [], [], 401);
}

$decoded = json_decode($payload, true);
if (!is_array($decoded)) {
    app(\GemData\Classes\Response::class)->json('error', 'Invalid JSON payload.', [], [], [], 422);
}

$data = is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
$eventKey = trim((string) (
    $headerValue($headers, 'X-XixaPay-Event-Id')
    ?: ($decoded['event_id'] ?? $decoded['id'] ?? $data['event_id'] ?? $data['id'] ?? $data['reference'] ?? $data['transaction_reference'] ?? '')
));
if ($eventKey === '') {
    $eventKey = 'payload:' . hash('sha256', $payload);
}

$safeHeaders = $headers;
foreach ($safeHeaders as $key => $value) {
    if (preg_match('/secret|signature|authorization|token/i', (string) $key)) {
        $safeHeaders[$key] = '[redacted]';
    }
}

$db = db();
try {
    $db->execute(
        'INSERT INTO webhook_events (source, event_key, signature, payload_json, processing_status)
         VALUES (:source, :event_key, :signature, :payload_json, :processing_status)',
        [
            'source' => 'xixapay',
            'event_key' => $eventKey,
            'signature' => $normalizedSignature !== '' ? hash('sha256', $normalizedSignature) : null,
            'payload_json' => $payload,
            'processing_status' => 'pending',
        ]
    );
    $webhookEventId = $db->lastInsertId();
} catch (Throwable $throwable) {
    $existing = $db->first(
        'SELECT id, processing_status FROM webhook_events WHERE source = :source AND event_key = :event_key LIMIT 1',
        ['source' => 'xixapay', 'event_key' => $eventKey]
    );
    if ($existing) {
        app(\GemData\Classes\Response::class)->json('success', 'Duplicate webhook ignored.', [
            'event_key' => $eventKey,
            'processing_status' => $existing['processing_status'],
        ]);
    }
    throw $throwable;
}

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/xixapay-webhook.log';

$entry = [
    'timestamp' => date('c'),
    'remote_ip' => client_ip(),
    'method' => $method,
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'event_key' => $eventKey,
    'headers' => $safeHeaders,
    'payload' => $payload,
];

file_put_contents(
    $logFile,
    json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL . str_repeat('-', 80) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

$status = strtolower(trim((string) ($data['status'] ?? $data['payment_status'] ?? $decoded['status'] ?? '')));
$isSuccessful = in_array($status, ['success', 'successful', 'paid', 'completed'], true);

if (!$isSuccessful) {
    $db->execute(
        'UPDATE webhook_events SET processing_status = :status, processed_at = NOW() WHERE id = :id',
        ['status' => 'processed', 'id' => $webhookEventId]
    );
    app(\GemData\Classes\Response::class)->json('success', 'Webhook received; no wallet credit required.', ['event_key' => $eventKey]);
}

$fundingPayload = [
    'provider' => 'xixapay',
    'event_key' => $eventKey,
    'provider_reference' => (string) ($data['provider_reference'] ?? $data['transaction_reference'] ?? $data['reference'] ?? $data['id'] ?? $eventKey),
    'reference' => (string) ($data['reference'] ?? $data['merchant_reference'] ?? $data['metadata']['reference'] ?? ''),
    'account_number' => (string) ($data['account_number'] ?? $data['account']['number'] ?? $data['virtual_account_number'] ?? ''),
    'customer_email' => (string) ($data['customer_email'] ?? $data['customer']['email'] ?? $data['email'] ?? ''),
    'amount' => (float) ($data['amount'] ?? $data['amount_paid'] ?? 0),
    'currency' => (string) ($data['currency'] ?? config('app.currency', 'NGN')),
    'meta_json' => json_encode(['event_key' => $eventKey, 'payload' => $decoded]),
];

try {
    $result = app(\GemData\Classes\PaymentGatewayService::class)->reconcileIncomingFunding($fundingPayload);
    $db->execute(
        'UPDATE webhook_events SET processing_status = :status, processed_at = NOW() WHERE id = :id',
        ['status' => 'processed', 'id' => $webhookEventId]
    );
    app_logger()->info('XixaPay webhook processed.', [
        'event_key' => $eventKey,
        'credited' => (bool) ($result['credited'] ?? false),
        'payload_bytes' => strlen($payload),
    ]);
    app(\GemData\Classes\Response::class)->json('success', 'XixaPay webhook processed.', $result);
} catch (Throwable $throwable) {
    $db->execute(
        'UPDATE webhook_events SET processing_status = :status, processed_at = NOW() WHERE id = :id',
        ['status' => 'failed', 'id' => $webhookEventId]
    );
    $db->safeExecute(
        'INSERT INTO webhook_dead_letters (webhook_event_id, source, target_url, last_error, status)
         VALUES (:webhook_event_id, :source, :target_url, :last_error, :status)',
        [
            'webhook_event_id' => $webhookEventId,
            'source' => 'xixapay',
            'target_url' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'last_error' => substr($throwable->getMessage(), 0, 255),
            'status' => 'pending',
        ]
    );
    app_logger()->error('XixaPay webhook processing failed.', [
        'event_key' => $eventKey,
        'error' => $throwable->getMessage(),
    ]);
    app(\GemData\Classes\Response::class)->json('error', 'Webhook could not be processed.', [], [], [], 422);
}
