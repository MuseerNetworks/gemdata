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

$db = db();

try {
    // Retrieve all transactions for the user
    $transactions = $db->query(
        'SELECT 
            t.reference, 
            s.name AS service_name, 
            s.slug AS service_slug, 
            t.recipient, 
            t.amount, 
            t.status, 
            t.created_at 
         FROM transactions t 
         LEFT JOIN services s ON t.service_id = s.id 
         WHERE t.user_id = :user_id 
         ORDER BY t.created_at DESC',
        ['user_id' => (int) $user['id']]
    );

    $mapped = array_map(static fn(array $tx): array => [
        'reference' => (string) ($tx['reference'] ?? ''),
        'service' => (string) ($tx['service_name'] ?? $tx['service_slug'] ?? 'VTU Purchase'),
        'service_slug' => (string) ($tx['service_slug'] ?? ''),
        'recipient' => (string) ($tx['recipient'] ?? ''),
        'amount' => (float) ($tx['amount'] ?? 0),
        'status' => (string) ($tx['status'] ?? 'pending'),
        'created_at' => (string) ($tx['created_at'] ?? '')
    ], $transactions);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $mapped
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to retrieve transaction history.'
    ]);
}
