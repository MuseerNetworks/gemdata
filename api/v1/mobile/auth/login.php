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

    // Generate secure refresh token for persistent mobile app sessions
    $refreshToken = bin2hex(random_bytes(32));
    $tokenHash    = hash('sha256', $refreshToken);
    $deviceId     = trim((string) ($input['device_id'] ?? 'unknown_device'));
    $expiresAt    = date('Y-m-d H:i:s', time() + 30 * 86400); // 30 days

    try {
        $db = db();
        // Delete any existing token for this user+device before inserting new one
        $db->execute(
            'DELETE FROM mobile_device_tokens WHERE user_id = :user_id AND device_id = :device_id',
            ['user_id' => (int) $user['id'], 'device_id' => $deviceId]
        );
        // Store new refresh token
        $db->execute(
            'INSERT INTO mobile_device_tokens (user_id, device_id, token_hash, expires_at)
             VALUES (:user_id, :device_id, :token_hash, :expires_at)',
            [
                'user_id'    => (int) $user['id'],
                'device_id'  => $deviceId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ]
        );
    } catch (Throwable $tokenEx) {
        // mobile_device_tokens table may not exist yet.
        // Login still succeeds — run the SQL migration to enable persistent token auth.
        error_log('[GemData Mobile] Token storage failed: ' . $tokenEx->getMessage());
    }

    $walletBalance = 0.0;
    try {
        $walletRow = db()->safeFirst('SELECT balance FROM wallets WHERE user_id = :id LIMIT 1', ['id' => (int) $user['id']]);
        $walletBalance = (float) ($walletRow['balance'] ?? $user['balance'] ?? 0);
    } catch (Throwable $e) {
        $walletBalance = (float) ($user['balance'] ?? 0);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Successfully authenticated.',
        'data'    => [
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'        => (int) $user['id'],
                'email'     => (string) $user['email'],
                'full_name' => (string) $user['full_name'],
                'balance'   => $walletBalance
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
