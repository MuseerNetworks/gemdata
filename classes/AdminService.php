<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;
use Throwable;

class AdminService
{
    public function __construct(
        private Database $db,
        private ActivityLogger $logger
    ) {
    }

    public function adminCount(): int
    {
        return (int) ($this->db->first('SELECT COUNT(*) AS total FROM admins')['total'] ?? 0);
    }

    public function canBootstrap(): bool
    {
        return $this->adminCount() === 0;
    }

    public function roles(): array
    {
        return $this->db->query('SELECT * FROM admin_roles ORDER BY id');
    }

    public function permissionsForAdmin(int $adminId): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['permission_key'],
            $this->db->query(
                'SELECT p.permission_key
                 FROM admins a
                 INNER JOIN admin_roles r ON r.id = a.role_id
                 INNER JOIN role_permissions rp ON rp.role_id = r.id
                 INNER JOIN admin_permissions p ON p.id = rp.permission_id
                 WHERE a.id = :admin_id
                 ORDER BY p.permission_key',
                ['admin_id' => $adminId]
            )
        );
    }

    public function hasPermission(?array $admin, string $permission): bool
    {
        if (!$admin) {
            return false;
        }

        return in_array($permission, $this->permissionsForAdmin((int) $admin['id']), true);
    }

    public function bootstrap(string $fullName, string $email, string $password): int
    {
        if (!$this->canBootstrap()) {
            throw new RuntimeException('Bootstrap admin creation is no longer available.');
        }

        $roleId = (int) ($this->db->first('SELECT id FROM admin_roles WHERE slug = :slug LIMIT 1', ['slug' => 'super_admin'])['id'] ?? 0);
        if ($roleId === 0) {
            throw new RuntimeException('Super Admin role is not configured.');
        }

        $this->db->execute(
            'INSERT INTO admins (full_name, email, password_hash, role_id, force_password_change, is_active)
             VALUES (:full_name, :email, :password_hash, :role_id, 0, 1)',
            [
                'full_name' => trim($fullName),
                'email' => strtolower(trim($email)),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role_id' => $roleId,
            ]
        );
        $adminId = $this->db->lastInsertId();
        $this->logger->log('admin', $adminId, 'admin_bootstrap_created', 'Bootstrap admin account created.', ['email' => strtolower(trim($email))]);
        return $adminId;
    }

    public function assignRole(int $adminId, int $roleId, int $performedBy): void
    {
        $this->db->execute('UPDATE admins SET role_id = :role_id WHERE id = :id', ['role_id' => $roleId, 'id' => $adminId]);
        $this->logger->log('admin', $performedBy, 'admin_role_updated', 'Updated admin role assignment.', ['target_admin_id' => $adminId, 'role_id' => $roleId]);
    }

    public function createInvite(int $createdByAdminId, string $email, int $roleId, string $ipAddress): array
    {
        $token = bin2hex(random_bytes(24));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $this->db->execute(
            'INSERT INTO admin_invites (email, role_id, token_hash, expires_at, created_by_admin_id, invite_ip)
             VALUES (:email, :role_id, :token_hash, :expires_at, :created_by_admin_id, :invite_ip)',
            [
                'email' => strtolower(trim($email)),
                'role_id' => $roleId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'created_by_admin_id' => $createdByAdminId,
                'invite_ip' => $ipAddress,
            ]
        );

        $inviteId = $this->db->lastInsertId();
        $this->logger->log('admin', $createdByAdminId, 'admin_invite_created', 'Created admin invite.', ['invite_id' => $inviteId, 'email' => strtolower(trim($email))]);

        return [
            'id' => $inviteId,
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function pendingInvites(): array
    {
        return $this->db->query(
            'SELECT ai.*, ar.name AS role_name, creator.full_name AS created_by_name
             FROM admin_invites ai
             INNER JOIN admin_roles ar ON ar.id = ai.role_id
             INNER JOIN admins creator ON creator.id = ai.created_by_admin_id
             ORDER BY ai.id DESC'
        );
    }

    public function acceptInvite(string $token, string $fullName, string $email, string $password, string $ipAddress): int
    {
        $email = strtolower(trim($email));
        $invites = $this->db->query(
            'SELECT * FROM admin_invites
             WHERE email = :email AND used_at IS NULL AND expires_at >= NOW()
             ORDER BY id DESC',
            ['email' => $email]
        );

        $invite = null;
        foreach ($invites as $candidate) {
            if (password_verify($token, $candidate['token_hash'])) {
                $invite = $candidate;
                break;
            }
        }

        if (!$invite) {
            throw new RuntimeException('Invite link is invalid or expired.');
        }

        $existingAdmin = $this->db->first('SELECT id FROM admins WHERE email = :email LIMIT 1', ['email' => $email]);
        if ($existingAdmin) {
            throw new RuntimeException('An admin with this email already exists.');
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'INSERT INTO admins (full_name, email, password_hash, role_id, force_password_change, is_active, invited_by_admin_id)
                 VALUES (:full_name, :email, :password_hash, :role_id, 0, 1, :invited_by_admin_id)',
                [
                    'full_name' => trim($fullName),
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role_id' => $invite['role_id'],
                    'invited_by_admin_id' => $invite['created_by_admin_id'],
                ]
            );
            $adminId = $this->db->lastInsertId();
            $this->db->execute('UPDATE admin_invites SET used_at = NOW() WHERE id = :id', ['id' => $invite['id']]);
            $this->db->commit();
        } catch (Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }

        $this->logger->log('admin', $adminId, 'admin_invite_accepted', 'Accepted admin invite.', ['invite_id' => $invite['id'], 'ip_address' => $ipAddress]);
        return $adminId;
    }

    public function listAdmins(): array
    {
        return $this->db->query(
            'SELECT a.*, ar.slug AS role_slug, ar.name AS role_name, inviter.full_name AS invited_by_name
             FROM admins a
             LEFT JOIN admin_roles ar ON ar.id = a.role_id
             LEFT JOIN admins inviter ON inviter.id = a.invited_by_admin_id
             ORDER BY a.id DESC'
        );
    }
}
