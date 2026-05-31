<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token)) {
    app(\GemData\Classes\Response::class)->json('error', 'Invalid CSRF token.', [], ['csrf_token' => ['Invalid CSRF token.']], [], 419);
}

$serviceSlug = trim($_POST['service_slug'] ?? '');
$payload = $_POST;

if ($serviceSlug === 'cable_tv' && empty($payload['provider']) && !empty($payload['plan'])) {
    $payload['provider'] = $payload['plan'];
}

try {
    $db = db();
    $settingsUrl = base_url('user/settings.php#security');
    if (!$db->columnExists('users', 'transaction_pin_hash')) {
        app(\GemData\Classes\Response::class)->json('error', 'Wallet PIN protection is not configured.', [], ['security_pin' => ['Wallet PIN protection is required.']], [], 503);
    }

    $pinRow = $db->first('SELECT transaction_pin_hash FROM users WHERE id = :id LIMIT 1', ['id' => $user['id']]);
    $pinHash = (string) ($pinRow['transaction_pin_hash'] ?? '');
    if ($pinHash === '') {
        app(\GemData\Classes\Response::class)->json(
            'error',
            'Set your Wallet PIN before making purchases.',
            [],
            ['security_pin' => ['Set your Wallet PIN before making purchases.']],
            ['redirect_url' => $settingsUrl],
            403
        );
    }

    $pin = trim((string) ($payload['security_pin'] ?? $payload['wallet_pin'] ?? ''));
    if ($pin === '') {
        app(\GemData\Classes\Response::class)->json('error', 'Wallet PIN is required.', [], ['security_pin' => ['Wallet PIN is required.']], [], 422);
    }
    if ($serviceSlug === 'cable_tv' && array_key_exists('cable_validation_status', $payload)) {
        $validationStatus = trim((string) ($payload['cable_validation_status'] ?? ''));
        $confirmedFallback = (string) ($payload['cable_iuc_confirmed'] ?? '') === '1';
        if ($validationStatus !== 'verified' && !$confirmedFallback) {
            app(\GemData\Classes\Response::class)->json('error', 'Please verify or confirm your smartcard/IUC number before payment.', [], ['smartcard_number' => ['Confirm your smartcard/IUC number before payment.']], [], 422);
        }
    }
    if ($serviceSlug === 'electricity' && array_key_exists('electricity_validation_status', $payload)) {
        $validationStatus = trim((string) ($payload['electricity_validation_status'] ?? ''));
        $confirmedFallback = (string) ($payload['electricity_meter_confirmed'] ?? '') === '1';
        if ($validationStatus !== 'verified' && !$confirmedFallback) {
            app(\GemData\Classes\Response::class)->json('error', 'Please verify or confirm your meter number before payment.', [], ['meter_number' => ['Confirm your meter number before payment.']], [], 422);
        }
    }
    if (!password_verify($pin, $pinHash)) {
        app(\GemData\Classes\Response::class)->json('error', 'Invalid wallet PIN.', [], ['security_pin' => ['Invalid wallet PIN.']], [], 422);
    }

    $result = app(\GemData\Classes\TransactionService::class)->purchase($serviceSlug, (int) $user['id'], $payload, 'web', false);
    if ($serviceSlug === 'cable_tv' && array_key_exists('cable_validation_status', $payload) && (($payload['cable_validation_status'] ?? '') !== 'verified')) {
        app(\GemData\Classes\ActivityLogger::class)->log('user', (int) $user['id'], 'cable_purchase_without_live_validation', 'Cable TV purchase submitted after unavailable verification fallback.', [
            'provider' => (string) ($payload['provider'] ?? ''),
            'smartcard_last4' => substr(preg_replace('/\D+/', '', (string) ($payload['smartcard_number'] ?? '')) ?? '', -4),
            'reference' => (string) ($result['reference'] ?? ''),
        ]);
    }
    if ($serviceSlug === 'electricity' && array_key_exists('electricity_validation_status', $payload) && (($payload['electricity_validation_status'] ?? '') !== 'verified')) {
        app(\GemData\Classes\ActivityLogger::class)->log('user', (int) $user['id'], 'electricity_purchase_without_live_validation', 'Electricity purchase submitted after unavailable verification fallback.', [
            'provider' => (string) ($payload['disco'] ?? ''),
            'meter_last4' => substr(preg_replace('/\D+/', '', (string) ($payload['meter_number'] ?? '')) ?? '', -4),
            'reference' => (string) ($result['reference'] ?? ''),
        ]);
    }
    $walletBalance = app(\GemData\Classes\Wallet::class)->balance((int) $user['id']);
    app(\GemData\Classes\Response::class)->json('success', 'Transaction accepted and queued for processing.', [
        'transaction' => $result,
        'wallet_balance' => $walletBalance,
        'wallet_balance_formatted' => money($walletBalance),
    ]);
} catch (InvalidArgumentException $exception) {
    app(\GemData\Classes\Response::class)->json('error', 'Validation failed.', [], json_decode((string) $exception->getMessage(), true) ?: [], [], 422);
} catch (Throwable $throwable) {
    $logger = app(\GemData\Classes\AppLogger::class);
    $redactDiagnosticString = static function (string $value): string {
        $value = preg_replace('/\b(\d{3})\d{5,10}(\d{2})\b/', '$1[REDACTED]$2', $value) ?? $value;
        $value = preg_replace('/(pin|password|secret|token|api[_-]?key|authorization)\s*[:=]\s*([^\s,"\']+)/i', '$1=[REDACTED]', $value) ?? $value;

        return substr($value, 0, 500);
    };

    $projectRoot = dirname(__DIR__);
    $safeTrace = [];
    foreach (array_slice($throwable->getTrace(), 0, 3) as $frame) {
        $frameFile = isset($frame['file']) ? str_replace('\\', '/', (string) $frame['file']) : '';
        $root = str_replace('\\', '/', $projectRoot);
        if ($frameFile !== '' && str_starts_with($frameFile, $root)) {
            $frameFile = ltrim(substr($frameFile, strlen($root)), '/');
        } elseif ($frameFile !== '') {
            $frameFile = basename($frameFile);
        }

        $safeTrace[] = [
            'file' => $frameFile,
            'line' => isset($frame['line']) ? (int) $frame['line'] : null,
            'class' => (string) ($frame['class'] ?? ''),
            'function' => (string) ($frame['function'] ?? ''),
        ];
    }

    $logger->writeToFile((string) config('app.provider_log_file', dirname(__DIR__) . '/storage/logs/provider.log'), 'error', 'Purchase submit failed before transaction queue.', [
        'action' => 'purchase_submit',
        'exception_class' => get_class($throwable),
        'exception_message' => $redactDiagnosticString($throwable->getMessage()),
        'user_id' => (int) ($user['id'] ?? 0),
        'service_slug' => $serviceSlug,
        'network_slug' => $redactDiagnosticString((string) ($payload['network'] ?? $payload['provider'] ?? $payload['disco'] ?? '')),
        'amount' => isset($payload['amount']) ? $redactDiagnosticString((string) $payload['amount']) : null,
        'plan_id' => isset($payload['plan_id']) || isset($payload['plan'])
            ? $redactDiagnosticString((string) ($payload['plan_id'] ?? $payload['plan'] ?? ''))
            : null,
        'has_transaction_pin_hash' => isset($pinHash) && trim((string) $pinHash) !== '',
        'security_pin_present' => trim((string) ($payload['security_pin'] ?? $payload['wallet_pin'] ?? '')) !== '',
        'request_reference' => $redactDiagnosticString((string) ($payload['reference'] ?? $payload['request_id'] ?? $payload['idempotency_key'] ?? '')),
        'trace' => $safeTrace,
    ]);

    $rawMessage = strtolower($throwable->getMessage());
    $message = str_contains($rawMessage, 'insufficient')
        ? 'Insufficient wallet balance. Please fund your wallet and try again.'
        : (str_contains($rawMessage, 'disabled') || str_contains($rawMessage, 'unavailable') || str_contains($rawMessage, 'outside the allowed') || str_contains($rawMessage, 'configured')
            ? $throwable->getMessage()
            : 'Transaction could not be processed right now. Please try again or contact support.');
    app(\GemData\Classes\Response::class)->json('error', $message, [], [], [], 400);
}
