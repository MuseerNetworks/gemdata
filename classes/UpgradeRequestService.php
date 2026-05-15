<?php

declare(strict_types=1);

namespace GemData\Classes;

/**
 * UpgradeRequestService
 *
 * Handles user tier upgrade requests (click-to-request, no docs).
 * Flow: smart user clicks upgrade → admin sees request → approves/rejects
 * On approval: user_type and tier are updated automatically.
 */
class UpgradeRequestService
{
    public function __construct(private Database $db)
    {
    }

    // ── User submits upgrade request ──────────────────────────────

    public function request(int $userId, string $toType = 'reseller'): array
    {
        $user = $this->db->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$user) {
            throw new \RuntimeException('User not found.');
        }

        $fromType = $user['user_type'] ?? 'smart';

        if ($fromType === $toType) {
            throw new \RuntimeException('You are already a ' . $toType . ' account.');
        }

        // Only smart users can upgrade to reseller; resellers can request api
        $validUpgrades = ['smart' => 'reseller', 'reseller' => 'api'];
        if (!isset($validUpgrades[$fromType]) || $validUpgrades[$fromType] !== $toType) {
            throw new \RuntimeException('Invalid upgrade path.');
        }

        // Block if there's already a pending request
        $existing = $this->db->first(
            'SELECT id FROM upgrade_requests WHERE user_id = :uid AND status = :s LIMIT 1',
            ['uid' => $userId, 's' => 'pending']
        );
        if ($existing) {
            throw new \RuntimeException('You already have a pending upgrade request.');
        }

        $this->db->execute(
            'INSERT INTO upgrade_requests (user_id, from_type, to_type, status)
             VALUES (:uid, :from, :to, :s)',
            ['uid' => $userId, 'from' => $fromType, 'to' => $toType, 's' => 'pending']
        );

        return $this->db->first(
            'SELECT * FROM upgrade_requests WHERE user_id = :uid ORDER BY id DESC LIMIT 1',
            ['uid' => $userId]
        ) ?? [];
    }

    // ── Admin approves upgrade ────────────────────────────────────

    public function approve(int $requestId, int $adminId, string $note = ''): void
    {
        $req = $this->getOrFail($requestId);
        if ($req['status'] !== 'pending') {
            throw new \RuntimeException('Request is no longer pending.');
        }

        $toType = $req['to_type'];
        $userId = (int) $req['user_id'];

        // Map user_type → tier for pricing engine
        $newTier = match ($toType) {
            'reseller' => 'RESELLER',
            'api'      => 'API_RESELLER',
            default    => 'USER',
        };

        $this->db->execute(
            'UPDATE users SET user_type = :utype, tier = :tier WHERE id = :id',
            ['utype' => $toType, 'tier' => $newTier, 'id' => $userId]
        );

        // Ensure commission wallet exists for new resellers
        if ($toType === 'reseller') {
            $this->db->execute(
                'INSERT IGNORE INTO commission_wallets (user_id, balance) VALUES (:uid, 0.00)',
                ['uid' => $userId]
            );
        }

        $this->db->execute(
            'UPDATE upgrade_requests
                SET status = :s, admin_note = :note, reviewed_by_admin_id = :admin, reviewed_at = NOW()
              WHERE id = :id',
            ['s' => 'approved', 'note' => $note, 'admin' => $adminId, 'id' => $requestId]
        );
    }

    // ── Admin rejects upgrade ─────────────────────────────────────

    public function reject(int $requestId, int $adminId, string $reason): void
    {
        $req = $this->getOrFail($requestId);
        if ($req['status'] !== 'pending') {
            throw new \RuntimeException('Request is no longer pending.');
        }
        $this->db->execute(
            'UPDATE upgrade_requests
                SET status = :s, admin_note = :note, reviewed_by_admin_id = :admin, reviewed_at = NOW()
              WHERE id = :id',
            ['s' => 'rejected', 'note' => $reason, 'admin' => $adminId, 'id' => $requestId]
        );
    }

    // ── List queries ──────────────────────────────────────────────

    public function listPending(): array
    {
        return $this->db->safeQuery(
            'SELECT ur.*, u.full_name, u.email, u.phone, u.user_type AS current_type
               FROM upgrade_requests ur
               JOIN users u ON u.id = ur.user_id
              WHERE ur.status = :s
              ORDER BY ur.created_at ASC',
            ['s' => 'pending']
        );
    }

    public function listAll(int $limit = 50, int $offset = 0): array
    {
        return $this->db->safeQuery(
            'SELECT ur.*, u.full_name, u.email
               FROM upgrade_requests ur
               JOIN users u ON u.id = ur.user_id
              ORDER BY ur.created_at DESC
              LIMIT :limit OFFSET :offset',
            ['limit' => $limit, 'offset' => $offset]
        );
    }

    public function latestForUser(int $userId): ?array
    {
        return $this->db->first(
            'SELECT * FROM upgrade_requests WHERE user_id = :uid ORDER BY id DESC LIMIT 1',
            ['uid' => $userId]
        ) ?: null;
    }

    // ── Internal helpers ──────────────────────────────────────────

    private function getOrFail(int $requestId): array
    {
        $req = $this->db->first(
            'SELECT * FROM upgrade_requests WHERE id = :id LIMIT 1',
            ['id' => $requestId]
        );
        if (!$req) {
            throw new \RuntimeException('Upgrade request not found.');
        }
        return $req;
    }
}
