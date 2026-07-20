<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Allow only POST method
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

$user = require_mobile_user();

// Retrieve input data
$input = $_POST;
if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: [];
}

$serviceSlug = trim((string) ($input['service_slug'] ?? ''));
if (!in_array($serviceSlug, ['cable_tv', 'electricity'], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Unsupported verification request.'
    ]);
    exit;
}

$errors = [];

if ($serviceSlug === 'electricity') {
    $provider = strtolower(trim((string) ($input['disco'] ?? $input['provider'] ?? '')));
    $meterType = strtolower(trim((string) ($input['meter_type'] ?? '')));
    $meterNumber = preg_replace('/\D+/', '', (string) ($input['meter_number'] ?? '')) ?? '';

    if ($provider === '') {
        $errors['disco'][] = 'Select a Disco provider.';
    }
    if (!in_array($meterType, ['prepaid', 'postpaid'], true)) {
        $errors['meter_type'][] = 'Select a valid meter type.';
    }
    if ($meterNumber === '') {
        $errors['meter_number'][] = 'Enter the meter number.';
    }

    if ($errors !== []) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $errors
        ]);
        exit;
    }

    // Log verification event
    app(\GemData\Classes\ActivityLogger::class)->log('user', (int) $user['id'], 'electricity_verification_unavailable', 'Electricity verification fallback shown.', [
        'provider' => $provider,
        'meter_type' => $meterType,
        'meter_last4' => substr($meterNumber, -4),
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Verification temporarily unavailable. Please confirm your meter number before payment.',
        'data' => [
            'validation_status' => 'unavailable',
            'customer_name' => null
        ]
    ]);
    exit;
}

// Cable TV Verification
$provider = strtolower(trim((string) ($input['provider'] ?? '')));
$smartcardNumber = preg_replace('/\D+/', '', (string) ($input['smartcard_number'] ?? '')) ?? '';

if ($provider === '') {
    $errors['provider'][] = 'Select a provider.';
}
if ($smartcardNumber === '') {
    $errors['smartcard_number'][] = 'Enter the smartcard/IUC number.';
}

if ($errors !== []) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => $errors
    ]);
    exit;
}

// Log verification event
app(\GemData\Classes\ActivityLogger::class)->log('user', (int) $user['id'], 'cable_verification_unavailable', 'Cable TV verification fallback shown.', [
    'provider' => $provider,
    'smartcard_last4' => substr($smartcardNumber, -4),
]);

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Verification temporarily unavailable. Please confirm your smartcard/IUC number before payment.',
    'data' => [
        'validation_status' => 'unavailable',
        'customer_name' => null
    ]
]);
