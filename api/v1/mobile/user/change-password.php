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

// Authenticate session
$user = require_mobile_user();

// Retrieve input data
$input = $_POST;
if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: [];
}

$currentPassword = (string) ($input['current_password'] ?? '');
$newPassword = (string) ($input['new_password'] ?? '');
$passwordConfirmation = (string) ($input['password_confirmation'] ?? '');

$errors = [];

// Fetch latest user details with password hash
$db = db();
$securityUser = $db->first('SELECT password_hash FROM users WHERE id = :id LIMIT 1', ['id' => (int) $user['id']]);
$passwordHash = (string) ($securityUser['password_hash'] ?? '');

if ($currentPassword === '' || !password_verify($currentPassword, $passwordHash)) {
    $errors['current_password'][] = 'Current password is incorrect.';
}

$passwordStrong = static function (string $password): bool {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $password) === 1;
};

if (!$passwordStrong($newPassword)) {
    $errors['new_password'][] = 'Password must include uppercase, lowercase, number, and a symbol.';
}

if ($newPassword !== $passwordConfirmation) {
    $errors['password_confirmation'][] = 'Passwords do not match.';
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

try {
    $db->execute(
        'UPDATE users SET password_hash = :password_hash WHERE id = :id',
        [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => (int) $user['id']
        ]
    );

    app(\GemData\Classes\ActivityLogger::class)->log('user', (int) $user['id'], 'user_password_changed_mobile', 'User changed password via mobile shell.');

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update password. Please try again later.'
    ]);
}
