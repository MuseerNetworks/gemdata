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

// Authenticate session
$user = require_mobile_user();

$service = trim((string) ($_GET['service'] ?? ''));
if ($service === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Service query parameter is required.'
    ]);
    exit;
}

$catalog = [];
try {
    $catalog = app(\GemData\Classes\ProviderPlanService::class)->catalogForServiceSlug($service);
} catch (Throwable $e) {
    // Return empty list if catalog fetch fails
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'data' => $catalog
], JSON_UNESCAPED_SLASHES);
