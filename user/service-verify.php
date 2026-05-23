<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token)) {
    app(\GemData\Classes\Response::class)->json('error', 'Invalid CSRF token.', [], ['csrf_token' => ['Invalid CSRF token.']], [], 419);
}

$serviceSlug = trim((string) ($_POST['service_slug'] ?? ''));
if (!in_array($serviceSlug, ['cable_tv', 'electricity'], true)) {
    app(\GemData\Classes\Response::class)->json('error', 'Unsupported verification request.', [], ['service_slug' => ['Unsupported service.']], [], 422);
}

if ($serviceSlug === 'electricity') {
    $provider = strtolower(trim((string) ($_POST['disco'] ?? $_POST['provider'] ?? '')));
    $meterType = strtolower(trim((string) ($_POST['meter_type'] ?? '')));
    $meterNumber = preg_replace('/\D+/', '', (string) ($_POST['meter_number'] ?? '')) ?? '';

    $errors = [];
    if ($provider === '') {
        $errors['disco'][] = 'Select a disco/provider.';
    }
    if (!in_array($meterType, ['prepaid', 'postpaid'], true)) {
        $errors['meter_type'][] = 'Select a valid meter type.';
    }
    if ($meterNumber === '') {
        $errors['meter_number'][] = 'Enter the meter number.';
    }
    if ($errors !== []) {
        app(\GemData\Classes\Response::class)->json('error', 'Validation failed.', [], $errors, [], 422);
    }

    app(\GemData\Classes\ActivityLogger::class)->log('user', (int) $user['id'], 'electricity_verification_unavailable', 'Electricity verification fallback shown.', [
        'provider' => $provider,
        'meter_type' => $meterType,
        'meter_last4' => substr($meterNumber, -4),
    ]);

    app(\GemData\Classes\Response::class)->json('success', 'Verification temporarily unavailable. Please confirm your meter number before payment.', [
        'validation_status' => 'unavailable',
    ]);
}

$provider = strtolower(trim((string) ($_POST['provider'] ?? '')));
$smartcardNumber = preg_replace('/\D+/', '', (string) ($_POST['smartcard_number'] ?? '')) ?? '';

$errors = [];
if ($provider === '') {
    $errors['provider'][] = 'Select a provider.';
}
if ($smartcardNumber === '') {
    $errors['smartcard_number'][] = 'Enter the smartcard/IUC number.';
}
if ($errors !== []) {
    app(\GemData\Classes\Response::class)->json('error', 'Validation failed.', [], $errors, [], 422);
}

app(\GemData\Classes\ActivityLogger::class)->log('user', (int) $user['id'], 'cable_verification_unavailable', 'Cable TV verification fallback shown.', [
    'provider' => $provider,
    'smartcard_last4' => substr($smartcardNumber, -4),
]);

app(\GemData\Classes\Response::class)->json('success', 'Verification temporarily unavailable. Please confirm your smartcard/IUC number before payment.', [
    'validation_status' => 'unavailable',
]);
