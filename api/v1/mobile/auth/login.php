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

// Check rate limit for authentication (max 5 requests per minute per IP)
check_mobile_rate_limit('login', 5, 60);

// Retrieve input data (supporting both form-url-encoded and JSON body requests)
$input = $_POST;
if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: [];
}

$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required.'
    ]);
    exit;
}

if (auth()->loginUser($email, $password)) {
    $user = user();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Successfully authenticated.',
        'data' => [
            'user' => [
                'id' => (int) $user['id'],
                'email' => (string) $user['email'],
                'full_name' => (string) $user['full_name'],
                'balance' => (float) $user['balance']
            ]
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email or password.'
    ]);
}
