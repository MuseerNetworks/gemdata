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

$user = require_mobile_user();

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Session is active.',
    'data' => [
        'user' => [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'full_name' => (string) $user['full_name'],
            'balance' => (float) $user['balance']
        ]
    ]
]);
