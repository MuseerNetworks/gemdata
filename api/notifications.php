<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

// API authentication — require valid API key
$apiHandler = app(\GemData\Classes\ApiHandler::class);
$apiAuth = app(\GemData\Classes\ApiAuth::class);

try {
    $apiKey = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? ''));
    $apiSecret = trim((string) ($_SERVER['HTTP_X_API_SECRET'] ?? $_GET['api_secret'] ?? ''));

    if ($apiKey === '' || $apiSecret === '') {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'API authentication required.']);
        exit;
    }

    $authResult = $apiAuth->authenticate($apiKey, $apiSecret);
    if (!$authResult) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Invalid API credentials.']);
        exit;
    }

    $userId = (int) $authResult['user_id'];
    $notifications = app(\GemData\Classes\NotificationService::class);

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET') {
        $limit = max(1, min((int) ($_GET['limit'] ?? 20), 100));
        $rows = $notifications->getForUser($userId, $limit);
        $unread = $notifications->unreadCount($userId);

        echo json_encode([
            'status' => true,
            'data' => [
                'unread_count' => $unread,
                'notifications' => array_map(static function (array $row): array {
                    return [
                        'id' => (int) $row['id'],
                        'title' => $row['title'],
                        'message' => $row['message'],
                        'type' => $row['type'],
                        'is_read' => (bool) $row['is_read'],
                        'created_at' => $row['created_at'],
                    ];
                }, $rows),
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = (string) ($input['action'] ?? '');

        if ($action === 'mark_read') {
            $notifId = (int) ($input['notification_id'] ?? 0);
            if ($notifId > 0) {
                $notifications->markAsRead($notifId, $userId);
            }
            echo json_encode(['status' => true, 'message' => 'Notification marked as read.']);
            exit;
        }

        if ($action === 'mark_all_read') {
            $notifications->markAllAsRead($userId);
            echo json_encode(['status' => true, 'message' => 'All notifications marked as read.']);
            exit;
        }

        if ($action === 'delete') {
            $notifId = (int) ($input['notification_id'] ?? 0);
            if ($notifId > 0) {
                $notifications->deleteNotification($notifId, $userId);
            }
            echo json_encode(['status' => true, 'message' => 'Notification deleted.']);
            exit;
        }

        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid action. Supported: mark_read, mark_all_read, delete.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed.']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Internal server error.']);
}
