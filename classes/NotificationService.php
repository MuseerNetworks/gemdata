<?php

declare(strict_types=1);

namespace GemData\Classes;

class NotificationService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Create a notification for a single user.
     */
    public function create(int $userId, string $title, string $message, string $type = 'info'): void
    {
        $this->db->execute(
            'INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, :title, :message, :type)',
            ['user_id' => $userId, 'title' => $title, 'message' => $message, 'type' => $type]
        );
    }

    /**
     * Create a notification for ALL users.
     */
    public function createForAllUsers(string $title, string $message, string $type = 'info', ?int $adminId = null): int
    {
        $users = $this->db->query('SELECT id FROM users WHERE status = :status', ['status' => 'active']);
        $count = 0;
        foreach ($users as $user) {
            $this->create((int) $user['id'], $title, $message, $type);
            $count++;
        }
        return $count;
    }

    /**
     * Create a notification for users of a specific tier/role.
     */
    public function createForUsersByTier(string $tier, string $title, string $message, string $type = 'info'): int
    {
        $users = $this->db->query(
            'SELECT id FROM users WHERE status = :status AND tier = :tier',
            ['status' => 'active', 'tier' => strtoupper($tier)]
        );
        $count = 0;
        foreach ($users as $user) {
            $this->create((int) $user['id'], $title, $message, $type);
            $count++;
        }
        return $count;
    }

    /**
     * Get notifications for a user with limit.
     */
    public function getForUser(int $userId, int $limit = 50): array
    {
        return $this->db->query(
            'SELECT * FROM notifications WHERE user_id = :user_id ORDER BY id DESC LIMIT ' . max(1, min($limit, 200)),
            ['user_id' => $userId]
        );
    }

    /**
     * Count unread notifications.
     */
    public function unreadCount(int $userId): int
    {
        $row = $this->db->first('SELECT COUNT(*) AS total FROM notifications WHERE user_id = :user_id AND is_read = 0', ['user_id' => $userId]);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(int $notificationId, int $userId): void
    {
        $this->db->execute(
            'UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id',
            ['id' => $notificationId, 'user_id' => $userId]
        );
    }

    /**
     * Mark ALL notifications as read for a user.
     */
    public function markAllAsRead(int $userId): void
    {
        $this->db->execute(
            'UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0',
            ['user_id' => $userId]
        );
    }

    /**
     * Delete a specific notification.
     */
    public function deleteNotification(int $notificationId, int $userId): void
    {
        $this->db->execute(
            'DELETE FROM notifications WHERE id = :id AND user_id = :user_id',
            ['id' => $notificationId, 'user_id' => $userId]
        );
    }
}
