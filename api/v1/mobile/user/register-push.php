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

// Retrieve input data
$input = $_POST;
if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: [];
}

$pushToken = trim((string) ($input['push_token'] ?? ''));
$platform = trim((string) ($input['platform'] ?? ''));
$deviceId = trim((string) ($input['device_id'] ?? ''));

if ($pushToken === '' || $platform === '' || $deviceId === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'push_token, platform, and device_id are required fields.'
    ]);
    exit;
}

// Verify authorization header bearer token
$token = get_bearer_token();
if (!$token) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized session.'
    ]);
    exit;
}

$db = db();

// Validate token in database
$deviceToken = $db->first('SELECT * FROM mobile_device_tokens WHERE token_hash = :hash LIMIT 1', [
    'hash' => hash('sha256', $token)
]);

if (!$deviceToken || strtotime($deviceToken['expires_at']) < time()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please log in again.'
    ]);
    exit;
}

$userId = (int) $deviceToken['user_id'];

// Save or update push token mapping
try {
    $db->query(
        "INSERT INTO mobile_push_tokens (user_id, device_id, push_token, platform) 
         VALUES (:user_id, :device_id, :push_token, :platform) 
         ON DUPLICATE KEY UPDATE push_token = :push_token_update, platform = :platform_update",
        [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'push_token' => $pushToken,
            'platform' => $platform,
            'push_token_update' => $pushToken,
            'platform_update' => $platform
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Push token registered successfully.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error registering token: ' . $e->getMessage()
    ]);
}
