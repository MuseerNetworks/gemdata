<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Allow only GET or POST
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET or POST.'
    ]);
    exit;
}

// Authenticate session
$user = require_mobile_user();

$roles = app(\GemData\Classes\UserRoleManager::class);
$upgradeSvc = app(\GemData\Classes\UpgradeRequestService::class);

$role = $roles->roleFor($user);
$targetRole = $roles->nextRole($role);
$latest = $upgradeSvc->latestForUser((int) $user['id']);

if ($method === 'GET') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'current_role' => $role,
            'target_role' => $targetRole,
            'latest_request' => $latest ? [
                'id' => (int) $latest['id'],
                'status' => (string) $latest['status'],
                'from_type' => (string) $latest['from_type'],
                'to_type' => (string) $latest['to_type'],
                'admin_note' => $latest['admin_note'] ? (string) $latest['admin_note'] : null,
                'created_at' => (string) $latest['created_at']
            ] : null
        ]
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Handle POST request (Submit upgrade request)
$input = $_POST;
if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: [];
}

try {
    if ($targetRole === null) {
        throw new RuntimeException('Your account already has the highest tier access.');
    }

    if ($role === 'smart' && $targetRole === 'reseller') {
        $upgradeSvc->upgradeSmartToReseller((int) $user['id'], [
            'business_name' => $input['business_name'] ?? null,
            'phone' => $input['phone'] ?? null,
            'reseller_agreement' => $input['reseller_agreement'] ?? null,
        ]);
        
        // Refresh session user details
        $updatedUser = db()->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => (int) $user['id']]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Your account has been upgraded to Reseller.',
            'data' => [
                'current_role' => 'reseller',
                'target_role' => 'api'
            ]
        ]);
    } else {
        $upgradeSvc->request((int) $user['id'], $targetRole, [
            'business_name' => $input['business_name'] ?? null,
            'phone' => $input['phone'] ?? null,
            'reason' => $input['reason'] ?? null,
            'website_url' => $input['website_url'] ?? null,
            'api_agreement' => $input['api_agreement'] ?? null,
        ]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Your API access request has been submitted for admin review.'
        ]);
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
