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
$secretKey = trim((string) config('payments.katpay_secret_key', ''));

$headerValue = static function (array $headers, string $name): string {
    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === strtolower($name)) {
            return trim((string) $value);
        }
    }
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$serverKey] ?? ''));
};

$redactArray = static function (array $value) use (&$redactArray): array {
    $redacted = [];
    foreach ($value as $key => $item) {
        if (preg_match('/authorization|secret|api.?key|token|signature|bvn|nin|email|phone|account.?number/i', (string) $key)) {
            $redacted[$key] = '[redacted]';
            continue;
        }
        $redacted[$key] = is_array($item) ? $redactArray($item) : $item;
    }
    return $redacted;
};

if ($secretKey === '') {
    app_logger()->warning('KatPay webhook rejected because KatPay secret key is not configured.');
    app(\GemData\Classes\Response::class)->json('error', 'Webhook is not configured.', [], [], [], 503);
}

$providedSignature = $headerValue($headers, 'X-KatPay-Signature') ?: $headerValue($headers, 'X-Katpay-Signature');
$timestamp = $headerValue($headers, 'X-KatPay-Timestamp') ?: $headerValue($headers, 'X-Katpay-Timestamp');

if ($providedSignature === '' || $timestamp === '' || !ctype_digit($timestamp)) {
    app_logger()->warning('KatPay webhook rejected because signature headers are incomplete.', ['remote_ip' => client_ip()]);
    app(\GemData\Classes\Response::class)->json('error', 'Invalid webhook signature.', [], [], [], 401);
}

$timestampInt = (int) $timestamp;
if (abs(time() - $timestampInt) > 300) {
    app_logger()->warning('KatPay webhook rejected because timestamp is outside tolerance.', ['remote_ip' => client_ip()]);
    app(\GemData\Classes\Response::class)->json('error', 'Expired webhook signature.', [], [], [], 401);
}

$expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secretKey);
$normalizedSignature = str_starts_with($providedSignature, 'sha256=')
    ? substr($providedSignature, 7)
    : $providedSignature;

if (!hash_equals($expectedSignature, $normalizedSignature)) {
    app_logger()->warning('KatPay webhook rejected because signature validation failed.', ['remote_ip' => client_ip()]);
    app(\GemData\Classes\Response::class)->json('error', 'Invalid webhook signature.', [], [], [], 401);
}

$decoded = json_decode($payload, true);
if (!is_array($decoded)) {
    app(\GemData\Classes\Response::class)->json('error', 'Invalid JSON payload.', [], [], [], 422);
}

$data = is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
$event = (string) ($decoded['event'] ?? $decoded['type'] ?? 'katpay.unknown');
$eventKey = trim((string) (
    $headerValue($headers, 'X-KatPay-Delivery-Id')
    ?: $headerValue($headers, 'X-Katpay-Delivery-Id')
    ?: ($decoded['event_id'] ?? $decoded['id'] ?? $data['event_id'] ?? $data['id'] ?? $data['reference'] ?? $data['transaction_reference'] ?? '')
));
if ($eventKey === '') {
    $eventKey = 'payload:' . hash('sha256', $payload);
}
$eventKey = $event . ':' . $eventKey;

$safeHeaders = $redactArray($headers);
$safePayload = $redactArray($decoded);

$db = db();
try {
    $db->execute(
        'INSERT INTO webhook_events (source, event_key, signature, payload_json, processing_status, processed_at)
         VALUES (:source, :event_key, :signature, :payload_json, :processing_status, NOW())',
        [
            'source' => 'katpay',
            'event_key' => $eventKey,
            'signature' => hash('sha256', $normalizedSignature),
            'payload_json' => json_encode($safePayload),
            'processing_status' => 'processed',
        ]
    );
} catch (Throwable $throwable) {
    $existing = $db->first(
        'SELECT id, processing_status FROM webhook_events WHERE source = :source AND event_key = :event_key LIMIT 1',
        ['source' => 'katpay', 'event_key' => $eventKey]
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

app_logger()->writeToFile($logDir . '/katpay-webhook.log', 'info', 'KatPay webhook logged without wallet crediting.', [
    'event_key' => $eventKey,
    'event' => $event,
    'headers' => $safeHeaders,
    'payload_bytes' => strlen($payload),
    'reference_hint' => (string) ($data['reference'] ?? $data['transaction_reference'] ?? $data['id'] ?? ''),
]);

app(\GemData\Classes\Response::class)->json('success', 'KatPay webhook logged. Wallet crediting is disabled.', [
    'event_key' => $eventKey,
    'event' => $event,
]);
