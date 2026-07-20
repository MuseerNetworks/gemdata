<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Allow only GET method
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ]);
    exit;
}

// Authenticate session
$user = require_mobile_user();

$reference = trim((string) ($_GET['reference'] ?? ''));
if ($reference === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Transaction reference is required.'
    ]);
    exit;
}

$db = db();

try {
    $tx = $db->first(
        'SELECT 
            t.reference, 
            t.provider_reference,
            s.name AS service_name, 
            s.slug AS service_slug, 
            t.recipient, 
            t.amount, 
            t.status, 
            t.customer_name,
            t.payload_json,
            t.response_json,
            t.created_at,
            t.processed_at,
            t.failure_code
         FROM transactions t 
         LEFT JOIN services s ON t.service_id = s.id 
         WHERE t.user_id = :user_id AND t.reference = :reference
         LIMIT 1',
        [
            'user_id' => (int) $user['id'],
            'reference' => $reference
        ]
    );

    if (!$tx) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Transaction not found.'
        ]);
        exit;
    }

    // Try parsing pin/token details if any from provider response
    $token = null;
    $pinList = [];
    
    $responsePayload = json_decode((string) $tx['response_json'], true) ?: [];
    $requestPayload = json_decode((string) $tx['payload_json'], true) ?: [];

    // Parse tokens for electricity or PINs for WAEC/NECO
    if (($tx['service_slug'] ?? '') === 'electricity') {
        $token = $responsePayload['token'] ?? $responsePayload['pin'] ?? $responsePayload['main_token'] ?? null;
    } elseif (($tx['service_slug'] ?? '') === 'exam_pin') {
        $pinList = $responsePayload['pins'] ?? $responsePayload['pin_code'] ?? $responsePayload['pin'] ?? [];
        if (is_string($pinList)) {
            $pinList = [$pinList];
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'reference' => (string) ($tx['reference'] ?? ''),
            'provider_reference' => (string) ($tx['provider_reference'] ?? ''),
            'service' => (string) ($tx['service_name'] ?? $tx['service_slug'] ?? 'VTU Purchase'),
            'service_slug' => (string) ($tx['service_slug'] ?? ''),
            'recipient' => (string) ($tx['recipient'] ?? ''),
            'customer_name' => $tx['customer_name'] ? (string) $tx['customer_name'] : null,
            'amount' => (float) ($tx['amount'] ?? 0),
            'status' => (string) ($tx['status'] ?? 'pending'),
            'created_at' => (string) ($tx['created_at'] ?? ''),
            'processed_at' => $tx['processed_at'] ? (string) $tx['processed_at'] : null,
            'failure_code' => $tx['failure_code'] ? (string) $tx['failure_code'] : null,
            'token' => $token,
            'pin_list' => $pinList
        ]
      ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to retrieve transaction details.'
    ]);
}
