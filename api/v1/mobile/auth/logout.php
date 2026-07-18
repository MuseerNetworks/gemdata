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

auth()->logoutUser();

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Successfully logged out.'
]);
