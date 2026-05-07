<?php

declare(strict_types=1);

namespace GemData\Classes;

class NotificationService
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $userId, string $title, string $message, string $type = 'info'): void
    {
        $this->db->execute(
            'INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, :title, :message, :type)',
            ['user_id' => $userId, 'title' => $title, 'message' => $message, 'type' => $type]
        );
    }

    public function unreadCount(int $userId): int
    {
        $row = $this->db->first('SELECT COUNT(*) AS total FROM notifications WHERE user_id = :user_id AND is_read = 0', ['user_id' => $userId]);
        return (int) ($row['total'] ?? 0);
    }
}
