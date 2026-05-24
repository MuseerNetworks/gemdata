<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;
use Throwable;

class UserSecurityService
{
    public function __construct(
        private Database $db,
        private ActivityLogger $logger
    ) {
    }

    public function createPasswordReset(int $userId, int $adminId): array
    {
        $user = $this->db->first('SELECT id, email FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        $token = bin2hex(random_bytes(24));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);

        $this->db->execute(
            'UPDATE user_password_reset_tokens
             SET used_at = NOW()
             WHERE user_id = :user_id AND used_at IS NULL',
            ['user_id' => $userId]
        );

        $this->db->execute(
            'INSERT INTO user_password_reset_tokens (user_id, token_hash, expires_at, created_by_admin_id)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR), :created_by_admin_id)',
            [
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'created_by_admin_id' => $adminId,
            ]
        );

        $resetId = $this->db->lastInsertId();
        $reset = $this->db->first(
            'SELECT expires_at FROM user_password_reset_tokens WHERE id = :id LIMIT 1',
            ['id' => $resetId]
        );
        $expiresAt = (string) ($reset['expires_at'] ?? '');

        $this->logger->log('admin', $adminId, 'user_password_reset_link_created', 'Created user password reset link.', [
            'user_id' => $userId,
            'reset_id' => $resetId,
        ]);

        return [
            'id' => $resetId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'email' => $user['email'],
        ];
    }

    public function createSelfServicePasswordResetByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        $user = $this->db->first(
            'SELECT id, email, status FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        if (!$user || ($user['status'] ?? 'inactive') !== 'active') {
            return null;
        }

        $token = bin2hex(random_bytes(24));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);

        $this->db->execute(
            'UPDATE user_password_reset_tokens
             SET used_at = NOW()
             WHERE user_id = :user_id AND used_at IS NULL',
            ['user_id' => $user['id']]
        );

        $this->db->execute(
            'INSERT INTO user_password_reset_tokens (user_id, token_hash, expires_at, created_by_admin_id)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR), NULL)',
            [
                'user_id' => $user['id'],
                'token_hash' => $tokenHash,
            ]
        );

        $resetId = $this->db->lastInsertId();
        $reset = $this->db->first(
            'SELECT expires_at FROM user_password_reset_tokens WHERE id = :id LIMIT 1',
            ['id' => $resetId]
        );
        $expiresAt = (string) ($reset['expires_at'] ?? '');

        $this->logger->log('user', (int) $user['id'], 'user_password_reset_requested', 'User requested a self-service password reset link.', [
            'reset_id' => $resetId,
            'email' => $email,
        ]);

        return [
            'id' => $resetId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'email' => $user['email'],
        ];
    }

    public function validatePasswordReset(int $resetId, string $token): ?array
    {
        $reset = $this->db->first(
            'SELECT prt.*, u.email, u.full_name
             FROM user_password_reset_tokens prt
             INNER JOIN users u ON u.id = prt.user_id
             WHERE prt.id = :id AND prt.used_at IS NULL AND prt.expires_at >= NOW()
             LIMIT 1',
            ['id' => $resetId]
        );
        if (!$reset) {
            return null;
        }

        return password_verify($token, $reset['token_hash']) ? $reset : null;
    }

    public function consumePasswordReset(int $resetId, string $token, string $newPassword): void
    {
        $reset = $this->validatePasswordReset($resetId, $token);
        if (!$reset) {
            throw new RuntimeException('This reset link is invalid or expired.');
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'UPDATE users SET password_hash = :password_hash WHERE id = :id',
                ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $reset['user_id']]
            );
            $this->db->execute(
                'UPDATE user_password_reset_tokens SET used_at = NOW() WHERE id = :id',
                ['id' => $resetId]
            );
            $this->db->commit();
        } catch (Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }

        $this->logger->log('user', (int) $reset['user_id'], 'user_password_reset_completed', 'User completed password reset.', [
            'reset_id' => $resetId,
        ]);
    }
}
