<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Allow only POST method
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Optional auth recovery
$userId = 'guest';
try {
    $user = require_mobile_user();
    if ($user && isset($user['id'])) {
        $userId = (string) $user['id'];
    }
} catch (Throwable $e) {
    // Guest context
}

// Retrieve telemetry
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true) ?: [];

if (!empty($data)) {
    $data['user_id'] = $userId;
    $data['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $logDir = 'C:/xampp/htdocs/gemdata/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/mobile_errors.log';
    $logEntry = '[' . date('Y-m-d H:i:s') . '] ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Telemetry received.']);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Empty payload.']);
}
