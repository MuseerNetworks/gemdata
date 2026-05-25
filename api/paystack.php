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
$secretKey = trim((string) config('payments.paystack_secret_key', ''));

$headerValue = static function (array $headers, string $name): string {
    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === strtolower($name)) {
            return trim((string) $value);
        }
    }
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$serverKey] ?? ''));
};

if ($secretKey === '') {
    app_logger()->warning('Paystack webhook rejected because Paystack secret key is not configured.');
    app(\GemData\Classes\Response::class)->json('error', 'Webhook is not configured.', [], [], [], 503);
}

$providedSignature = $headerValue($headers, 'X-Paystack-Signature');
$expectedSignature = hash_hmac('sha512', $payload, $secretKey);
if ($providedSignature === '' || !hash_equals($expectedSignature, $providedSignature)) {
    app_logger()->warning('Paystack webhook rejected because signature validation failed.', ['remote_ip' => client_ip()]);
    app(\GemData\Classes\Response::class)->json('error', 'Invalid webhook signature.', [], [], [], 401);
}

$decoded = json_decode($payload, true);
if (!is_array($decoded)) {
    app(\GemData\Classes\Response::class)->json('error', 'Invalid JSON payload.', [], [], [], 422);
}

$event = (string) ($decoded['event'] ?? 'paystack.unknown');
$data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
$eventKey = trim((string) ($data['reference'] ?? $data['id'] ?? $decoded['event_id'] ?? ''));
if ($eventKey === '') {
    $eventKey = 'payload:' . hash('sha256', $payload);
}
$eventKey = $event . ':' . $eventKey;

$db = db();
try {
    $db->execute(
        'INSERT INTO webhook_events (source, event_key, signature, payload_json, processing_status)
         VALUES (:source, :event_key, :signature, :payload_json, :processing_status)',
        [
            'source' => 'paystack',
            'event_key' => $eventKey,
            'signature' => hash('sha256', $providedSignature),
            'payload_json' => $payload,
            'processing_status' => 'processed',
        ]
    );
} catch (Throwable $throwable) {
    $existing = $db->first(
        'SELECT id, processing_status FROM webhook_events WHERE source = :source AND event_key = :event_key LIMIT 1',
        ['source' => 'paystack', 'event_key' => $eventKey]
    );
    if ($existing) {
        app(\GemData\Classes\Response::class)->json('success', 'Duplicate webhook ignored.', [
            'event_key' => $eventKey,
            'processing_status' => $existing['processing_status'],
        ]);
    }
    throw $throwable;
}

$safeHeaders = $headers;
foreach ($safeHeaders as $key => $value) {
    if (preg_match('/secret|signature|authorization|token/i', (string) $key)) {
        $safeHeaders[$key] = '[redacted]';
    }
}

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

app_logger()->writeToFile($logDir . '/paystack-webhook.log', 'info', 'Paystack webhook logged without wallet crediting.', [
    'event_key' => $eventKey,
    'event' => $event,
    'headers' => $safeHeaders,
    'payload_bytes' => strlen($payload),
]);

app(\GemData\Classes\Response::class)->json('success', 'Paystack webhook logged. Wallet crediting is disabled.', [
    'event_key' => $eventKey,
    'event' => $event,
]);
