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

// Ensure the user is logged in
$user = require_mobile_user();

// Delete all active mobile refresh tokens for this user
db()->execute('DELETE FROM mobile_device_tokens WHERE user_id = :user_id', [
    'user_id' => (int) $user['id']
]);

auth()->logoutUser();

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Successfully logged out from all devices.'
]);
