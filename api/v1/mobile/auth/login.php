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

$db = db();
$checkUser = $db->first('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => $email]);
if ($checkUser && !empty($checkUser['security_lock_until']) && strtotime((string) $checkUser['security_lock_until']) > time()) {
    $diff = strtotime((string) $checkUser['security_lock_until']) - time();
    $minutes = max(1, (int) ceil($diff / 60));
    http_response_code(423); // 423 Locked
    echo json_encode([
        'success' => false,
        'message' => "This account is temporarily locked due to repeated failed login attempts. Try again in {$minutes} minute(s)."
    ]);
    exit;
}

if (auth()->loginUser($email, $password)) {
    $user = user();
    
    // Generate secure refresh token for mobile app auto-login/token storage
    $refreshToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $refreshToken);
    $deviceId = trim((string) ($input['device_id'] ?? 'unknown_device'));
    $expiresAt = date('Y-m-d H:i:s', time() + 30 * 86400); // Valid for 30 days
    
    $db = db();
    // Delete any old session token for this user and device
    $db->execute('DELETE FROM mobile_device_tokens WHERE user_id = :user_id AND device_id = :device_id', [
        'user_id' => (int) $user['id'],
        'device_id' => $deviceId
    ]);
    
    // Store new token
    $db->execute(
        'INSERT INTO mobile_device_tokens (user_id, device_id, token_hash, expires_at) 
         VALUES (:user_id, :device_id, :token_hash, :expires_at)',
        [
            'user_id' => (int) $user['id'],
            'device_id' => $deviceId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt
        ]
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Successfully authenticated.',
        'data' => [
            'refresh_token' => $refreshToken,
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
