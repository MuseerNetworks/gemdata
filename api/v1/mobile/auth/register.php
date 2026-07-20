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

// Retrieve inputs
$input = $_POST;
if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: [];
}

$fullName = trim((string) ($input['full_name'] ?? ''));
$email = strtolower(trim((string) ($input['email'] ?? '')));
$phone = trim((string) ($input['phone'] ?? ''));
$password = (string) ($input['password'] ?? '');
$walletPin = trim((string) ($input['wallet_pin'] ?? ''));

// Validate fields
$errors = [];

if (strlen($fullName) < 3) {
    $errors['full_name'][] = 'Full name must be at least 3 characters.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'][] = 'Please enter a valid email address.';
}
if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
    $errors['phone'][] = 'Please enter a valid phone number (10-15 digits).';
}
if (strlen($password) < 8) {
    $errors['password'][] = 'Password must be at least 8 characters long.';
}
if (!preg_match('/^\d{4,6}$/', $walletPin)) {
    $errors['wallet_pin'][] = 'Wallet PIN must be between 4 and 6 digits.';
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

$db = db();

// Check uniqueness of Email and Phone
$existingEmail = $db->first('SELECT id FROM users WHERE email = :email LIMIT 1', ['email' => $email]);
if ($existingEmail) {
    $errors['email'][] = 'This email address is already registered.';
}

$existingPhone = $db->first('SELECT id FROM users WHERE phone = :phone LIMIT 1', ['phone' => $phone]);
if ($existingPhone) {
    $errors['phone'][] = 'This phone number is already registered.';
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

// Proceed to insert new user
$db->beginTransaction();
try {
    $db->execute(
        'INSERT INTO users (full_name, email, phone, password_hash, transaction_pin_hash, status) 
         VALUES (:full_name, :email, :phone, :password_hash, :transaction_pin_hash, "active")',
        [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'transaction_pin_hash' => password_hash($walletPin, PASSWORD_DEFAULT),
        ]
    );
    
    $userId = $db->lastInsertId();
    
    // Ensure user wallet is initialized
    app(\GemData\Classes\Wallet::class)->ensure($userId);
    
    $db->commit();
    
    // Log Activity
    app(\GemData\Classes\ActivityLogger::class)->log('user', $userId, 'user_registered_mobile', 'User registered successfully via mobile shell.');

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! You can now sign in.'
    ]);
} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to create your account. Please try again later.'
    ]);
}
