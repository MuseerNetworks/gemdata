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

$mask = static function (string $value, int $left = 3, int $right = 3): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (strlen($value) <= $left + $right) {
        return str_repeat('*', strlen($value));
    }
    return substr($value, 0, $left) . '****' . substr($value, -$right);
};

$fundingReference = static function (): string {
    return 'KFW' . strtoupper(bin2hex(random_bytes(5)));
};

$idempotencyKeyFor = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        $value = bin2hex(random_bytes(16));
    }

    $key = 'katpay-funding:' . $value;
    return strlen($key) <= 120 ? $key : 'katpay-funding:' . hash('sha256', $value);
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
$transaction = is_array($data['transaction'] ?? null) ? $data['transaction'] : [];
$virtualAccount = is_array($data['virtual_account'] ?? null) ? $data['virtual_account'] : [];
$event = (string) ($decoded['event_type'] ?? $decoded['event'] ?? $decoded['type'] ?? 'katpay.unknown');
$orderNo = trim((string) ($transaction['order_no'] ?? ''));
$sessionId = trim((string) ($transaction['session_id'] ?? ''));
$payloadEventId = (string) ($decoded['event_id'] ?? $decoded['id'] ?? $data['event_id'] ?? $data['id'] ?? '');
$eventKey = trim((string) (
    $headerValue($headers, 'X-KatPay-Delivery-Id')
    ?: $headerValue($headers, 'X-Katpay-Delivery-Id')
    ?: ($payloadEventId !== '' ? $payloadEventId : ($orderNo !== '' ? $orderNo : $sessionId))
));
if ($eventKey === '') {
    $eventKey = 'payload:' . hash('sha256', $payload);
}
$eventKey = $event . ':' . $eventKey;

$safeHeaders = $redactArray($headers);
$safePayload = $redactArray($decoded);

$db = db();
$webhookEventId = null;
$providerReference = $orderNo !== '' ? $orderNo : ($sessionId !== '' ? $sessionId : $eventKey);
$idempotencyKey = $idempotencyKeyFor($providerReference);
$orderStatus = (string) ($transaction['order_status'] ?? '');
$currency = strtoupper(trim((string) ($transaction['currency'] ?? '')));
$amount = round((float) ($transaction['order_amount'] ?? 0), 2);
$accountNumber = trim((string) ($virtualAccount['account_number'] ?? ''));
$bankName = trim((string) ($virtualAccount['bank_name'] ?? ''));

try {
    $existingEvent = $db->first(
        'SELECT id, processing_status FROM webhook_events WHERE source = :source AND event_key = :event_key LIMIT 1',
        ['source' => 'katpay', 'event_key' => $eventKey]
    );
    if ($existingEvent) {
        app(\GemData\Classes\Response::class)->json('success', 'Duplicate webhook ignored.', [
            'event_key' => $eventKey,
            'processing_status' => $existingEvent['processing_status'],
        ]);
    }

    $db->beginTransaction();

    $db->execute(
        'INSERT INTO webhook_events (source, event_key, signature, payload_json, processing_status)
         VALUES (:source, :event_key, :signature, :payload_json, :processing_status)',
        [
            'source' => 'katpay',
            'event_key' => $eventKey,
            'signature' => hash('sha256', $normalizedSignature),
            'payload_json' => json_encode($safePayload),
            'processing_status' => 'pending',
        ]
    );
    $webhookEventId = $db->lastInsertId();

    if ($event !== 'virtual_account.payment_received' || $orderStatus !== '1' || $currency !== 'NGN' || $amount <= 0 || $accountNumber === '') {
        $db->execute(
            'UPDATE webhook_events SET processing_status = :status, processed_at = NOW() WHERE id = :id',
            ['status' => 'processed', 'id' => $webhookEventId]
        );
        $db->commit();

        app_logger()->writeToFile(dirname(__DIR__) . '/storage/logs/katpay-webhook.log', 'info', 'KatPay webhook received without wallet crediting.', [
            'event_key' => $eventKey,
            'event' => $event,
            'order_status' => $orderStatus,
            'currency' => $currency,
            'amount' => $amount,
            'account_hint' => $mask($accountNumber),
            'reason' => 'not_a_successful_ngn_virtual_account_payment',
        ]);

        app(\GemData\Classes\Response::class)->json('success', 'KatPay webhook received. Wallet crediting was not required.', [
            'event_key' => $eventKey,
            'credited' => false,
        ]);
    }

    $fundingAccount = $db->first(
        'SELECT * FROM user_funding_accounts
         WHERE provider = :provider AND dedicated_account_number = :account_number
         LIMIT 1 FOR UPDATE',
        ['provider' => 'katpay', 'account_number' => $accountNumber]
    );

    if (!$fundingAccount) {
        $db->execute(
            'UPDATE webhook_events SET processing_status = :status, processed_at = NOW() WHERE id = :id',
            ['status' => 'failed', 'id' => $webhookEventId]
        );
        $db->commit();

        app_logger()->warning('KatPay webhook could not be matched to a funding account.', [
            'event_key' => $eventKey,
            'provider_reference' => $providerReference,
            'account_hint' => $mask($accountNumber),
            'bank_name' => $bankName,
        ]);

        app(\GemData\Classes\Response::class)->json('success', 'KatPay webhook logged but not credited because the funding account was not found.', [
            'event_key' => $eventKey,
            'credited' => false,
        ]);
    }

    $fundingRequest = $db->first(
        'SELECT * FROM wallet_funding_requests
         WHERE user_id = :user_id AND idempotency_key = :idempotency_key
         LIMIT 1 FOR UPDATE',
        [
            'user_id' => (int) $fundingAccount['user_id'],
            'idempotency_key' => $idempotencyKey,
        ]
    );

    if (!$fundingRequest) {
        $requestReference = $fundingReference();
        $db->execute(
            'INSERT INTO wallet_funding_requests
                (user_id, reference, provider, provider_reference, amount, currency, status, callback_token_hash, idempotency_key, verified_at, meta_json)
             VALUES
                (:user_id, :reference, :provider, :provider_reference, :amount, :currency, :status, :callback_token_hash, :idempotency_key, NOW(), :meta_json)',
            [
                'user_id' => (int) $fundingAccount['user_id'],
                'reference' => $requestReference,
                'provider' => 'katpay',
                'provider_reference' => $providerReference,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'initiated',
                'callback_token_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                'idempotency_key' => $idempotencyKey,
                'meta_json' => json_encode([
                    'provider' => 'katpay',
                    'event_key' => $eventKey,
                    'order_no' => $orderNo,
                    'session_id' => $sessionId !== '' ? $sessionId : null,
                    'bank_name' => $bankName !== '' ? $bankName : null,
                    'account_hint' => $mask($accountNumber),
                ]),
            ]
        );
        $fundingRequest = $db->first(
            'SELECT * FROM wallet_funding_requests WHERE reference = :reference LIMIT 1 FOR UPDATE',
            ['reference' => $requestReference]
        );
    }

    if (!$fundingRequest) {
        throw new RuntimeException('KatPay funding request could not be loaded.');
    }

    if (($fundingRequest['status'] ?? '') === 'credited') {
        $db->execute(
            'UPDATE webhook_events SET processing_status = :status, processed_at = NOW() WHERE id = :id',
            ['status' => 'processed', 'id' => $webhookEventId]
        );
        $db->commit();

        app(\GemData\Classes\Response::class)->json('success', 'Duplicate KatPay funding webhook ignored.', [
            'event_key' => $eventKey,
            'credited' => false,
        ]);
    }

    $db->execute(
        'UPDATE wallet_funding_requests
         SET provider_reference = :provider_reference, amount = :amount, currency = :currency, status = :status, verified_at = NOW(), credited_at = NOW(), meta_json = :meta_json
         WHERE id = :id',
        [
            'provider_reference' => $providerReference,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'credited',
            'meta_json' => json_encode([
                'provider' => 'katpay',
                'event_key' => $eventKey,
                'order_no' => $orderNo,
                'session_id' => $sessionId !== '' ? $sessionId : null,
                'bank_name' => $bankName !== '' ? $bankName : null,
                'account_hint' => $mask($accountNumber),
            ]),
            'id' => (int) $fundingRequest['id'],
        ]
    );

    $walletRecord = app(\GemData\Classes\Wallet::class)->credit(
        (int) $fundingAccount['user_id'],
        $amount,
        'Wallet funding confirmed via KatPay transfer',
        'system',
        [
            'provider' => 'katpay',
            'funding_reference' => (string) $fundingRequest['reference'],
            'provider_reference' => $providerReference,
            'event_key' => $eventKey,
            'bank_name' => $bankName !== '' ? $bankName : null,
        ],
        'funding',
        (int) $fundingRequest['id'],
        $idempotencyKey,
        'katpay'
    );

    app(\GemData\Classes\NotificationService::class)->create(
        (int) $fundingAccount['user_id'],
        'Wallet funded',
        'Your wallet has been credited after bank transfer verification.',
        'success'
    );

    $db->execute(
        'UPDATE webhook_events SET processing_status = :status, processed_at = NOW() WHERE id = :id',
        ['status' => 'processed', 'id' => $webhookEventId]
    );

    $db->commit();
} catch (Throwable $throwable) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    if ($webhookEventId !== null) {
        $db->safeExecute(
            'UPDATE webhook_events SET processing_status = :status, processed_at = NOW() WHERE id = :id',
            ['status' => 'failed', 'id' => $webhookEventId]
        );
    }

    app_logger()->error('KatPay webhook processing failed.', [
        'event_key' => $eventKey ?? null,
        'provider_reference' => $providerReference ?? null,
        'error' => $throwable->getMessage(),
    ]);

    throw $throwable;
}

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

app_logger()->writeToFile($logDir . '/katpay-webhook.log', 'info', 'KatPay webhook processed.', [
    'event_key' => $eventKey,
    'event' => $event,
    'headers' => $safeHeaders,
    'payload_bytes' => strlen($payload),
    'provider_reference' => $providerReference,
    'wallet_transaction_id' => $walletRecord['id'] ?? null,
]);

app(\GemData\Classes\Response::class)->json('success', 'KatPay webhook processed.', [
    'event_key' => $eventKey,
    'event' => $event,
    'credited' => true,
]);
