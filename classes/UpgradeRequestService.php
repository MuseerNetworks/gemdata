<?php

declare(strict_types=1);

namespace GemData\Classes;

use InvalidArgumentException;
use RuntimeException;

class UpgradeRequestService
{
    public function __construct(private Database $db, private ?UserRoleManager $roles = null)
    {
        $this->roles ??= new UserRoleManager();
    }

    public function request(int $userId, string $toType = 'reseller', array $payload = []): array
    {
        $user = $this->db->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        $fromType = $this->roles->roleFor($user);
        $validUpgrades = ['smart' => 'reseller', 'reseller' => 'api'];
        if (($validUpgrades[$fromType] ?? null) !== $toType) {
            throw new RuntimeException('Invalid upgrade path.');
        }

        $existing = $this->db->first(
            'SELECT id FROM upgrade_requests WHERE user_id = :uid AND to_type = :to_type AND status = :status LIMIT 1',
            ['uid' => $userId, 'to_type' => $toType, 'status' => 'pending']
        );
        if ($existing) {
            throw new RuntimeException('You already have a pending upgrade request.');
        }

        $payload = $this->validatePayload($toType, $payload, $user);
        $this->insertUpgradeRequest($userId, $fromType, $toType, 'pending', $payload);

        return $this->db->first(
            'SELECT * FROM upgrade_requests WHERE user_id = :uid ORDER BY id DESC LIMIT 1',
            ['uid' => $userId]
        ) ?? [];
    }

    public function upgradeSmartToReseller(int $userId, array $payload = []): array
    {
        $user = $this->db->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$user) {
            throw new RuntimeException('User not found.');
        }
        $fromType = $this->roles->roleFor($user);
        if ($fromType !== 'smart') {
            throw new RuntimeException('Only Smart Users can use the instant reseller upgrade.');
        }
        if (empty($payload['reseller_agreement'])) {
            throw new InvalidArgumentException('You must agree to the Reseller Terms and Conditions.');
        }

        $clean = [
            'business_name' => trim((string) ($payload['business_name'] ?? ($user['full_name'] ?? 'GemData Reseller'))),
            'phone' => trim((string) ($payload['phone'] ?? ($user['phone'] ?? ''))),
            'reason' => 'Instant reseller agreement accepted.',
            'website_url' => null,
            'agreement_type' => 'reseller_terms',
            'agreement_ip' => $this->requestIp(),
        ];

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'UPDATE users
                    SET user_type = :user_type,
                        tier = :tier
                  WHERE id = :id',
                ['user_type' => 'reseller', 'tier' => 'RESELLER', 'id' => $userId]
            );

            $requestId = $this->insertUpgradeRequest($userId, 'smart', 'reseller', 'approved', $clean);
            $request = $this->db->first('SELECT * FROM upgrade_requests WHERE id = :id LIMIT 1', ['id' => $requestId]) ?? $clean;
            $this->ensureResellerProfile($userId, $request);
            $this->db->safeExecute(
                'INSERT IGNORE INTO commission_wallets (user_id, balance) VALUES (:uid, 0.00)',
                ['uid' => $userId]
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return $this->latestForUser($userId) ?? [];
    }

    public function approve(int $requestId, int $adminId, string $note = ''): void
    {
        $req = $this->getPendingOrFail($requestId);
        $toType = (string) $req['to_type'];
        $userId = (int) $req['user_id'];
        $newTier = match ($toType) {
            'reseller' => 'RESELLER',
            'api' => 'API_RESELLER',
            default => 'USER',
        };

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'UPDATE users
                    SET user_type = :user_type,
                        tier = :tier,
                        is_api_user = CASE WHEN :is_api = 1 THEN 1 ELSE is_api_user END
                  WHERE id = :id',
                [
                    'user_type' => $toType,
                    'tier' => $newTier,
                    'is_api' => $toType === 'api' ? 1 : 0,
                    'id' => $userId,
                ]
            );

            if (in_array($toType, ['reseller', 'api'], true)) {
                $this->ensureResellerProfile($userId, $req);
                $this->db->safeExecute(
                    'INSERT IGNORE INTO commission_wallets (user_id, balance) VALUES (:uid, 0.00)',
                    ['uid' => $userId]
                );
            }

            if ($toType === 'api') {
                $this->ensureApiProfile($userId, $req);
                $apiUserId = $this->ensureApiUser($userId);
                app(ApiCredentialService::class)->ensureActiveKey($apiUserId);
            }

            $this->db->execute(
                'UPDATE upgrade_requests
                    SET status = :status,
                        admin_note = :note,
                        reviewed_by_admin_id = :admin_id,
                        reviewed_at = NOW(),
                        updated_at = NOW()
                  WHERE id = :id',
                ['status' => 'approved', 'note' => $note, 'admin_id' => $adminId, 'id' => $requestId]
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function reject(int $requestId, int $adminId, string $reason): void
    {
        $this->completeReview($requestId, $adminId, 'rejected', $reason);
    }

    public function requestMoreInfo(int $requestId, int $adminId, string $note): void
    {
        $this->completeReview($requestId, $adminId, 'needs_info', $note);
    }

    public function listPending(): array
    {
        return $this->db->safeQuery(
            'SELECT ur.*, u.full_name, u.email, u.phone AS user_phone, u.user_type AS current_type
               FROM upgrade_requests ur
               JOIN users u ON u.id = ur.user_id
              WHERE ur.status = :status
              ORDER BY ur.created_at ASC',
            ['status' => 'pending']
        );
    }

    public function listAll(int $limit = 50, int $offset = 0): array
    {
        return $this->db->safeQuery(
            'SELECT ur.*, u.full_name, u.email, u.phone AS user_phone, u.user_type AS current_type
               FROM upgrade_requests ur
               JOIN users u ON u.id = ur.user_id
              ORDER BY ur.created_at DESC
              LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
    }

    public function latestForUser(int $userId): ?array
    {
        return $this->db->first(
            'SELECT * FROM upgrade_requests WHERE user_id = :uid ORDER BY id DESC LIMIT 1',
            ['uid' => $userId]
        ) ?: null;
    }

    private function validatePayload(string $toType, array $payload, array $user): array
    {
        $clean = [
            'business_name' => null,
            'phone' => null,
            'reason' => null,
            'website_url' => null,
        ];

        if ($toType === 'reseller') {
            $clean['business_name'] = trim((string) ($payload['business_name'] ?? ''));
            $clean['phone'] = trim((string) ($payload['phone'] ?? ($user['phone'] ?? '')));
            $clean['reason'] = trim((string) ($payload['reason'] ?? ''));

            if ($clean['business_name'] === '' || $clean['phone'] === '' || $clean['reason'] === '') {
                throw new InvalidArgumentException('Business name, phone number, and reason are required.');
            }
        }

        if ($toType === 'api') {
            $clean['website_url'] = trim((string) ($payload['website_url'] ?? ''));
            if ($clean['website_url'] === '' || !filter_var($clean['website_url'], FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Enter a valid website or app URL.');
            }
            if (empty($payload['api_agreement'])) {
                throw new InvalidArgumentException('You must agree to the API User Agreement.');
            }
            $clean['agreement_type'] = 'api_user_agreement';
            $clean['agreement_ip'] = $this->requestIp();
        }

        return $clean;
    }

    private function insertUpgradeRequest(int $userId, string $fromType, string $toType, string $status, array $payload): int
    {
        $columns = [
            'user_id' => ':uid',
            'from_type' => ':from_type',
            'to_type' => ':to_type',
            'status' => ':status',
            'created_at' => 'NOW()',
            'updated_at' => 'NOW()',
        ];
        $params = [
            'uid' => $userId,
            'from_type' => $fromType,
            'to_type' => $toType,
            'status' => $status,
        ];

        foreach (['business_name', 'phone', 'reason', 'website_url'] as $optionalColumn) {
            if ($this->db->columnExists('upgrade_requests', $optionalColumn)) {
                $columns[$optionalColumn] = ':' . $optionalColumn;
                $params[$optionalColumn] = $payload[$optionalColumn] ?? null;
            }
        }

        if ($this->db->columnExists('upgrade_requests', 'agreement_accepted_at')) {
            $columns['agreement_accepted_at'] = 'NOW()';
        }
        if ($this->db->columnExists('upgrade_requests', 'agreement_type')) {
            $columns['agreement_type'] = ':agreement_type';
            $params['agreement_type'] = $payload['agreement_type'] ?? null;
        }
        if ($this->db->columnExists('upgrade_requests', 'agreement_ip')) {
            $columns['agreement_ip'] = ':agreement_ip';
            $params['agreement_ip'] = $payload['agreement_ip'] ?? null;
        }

        $this->db->execute(
            'INSERT INTO upgrade_requests (' . implode(', ', array_keys($columns)) . ')
             VALUES (' . implode(', ', array_values($columns)) . ')',
            $params
        );

        return $this->db->lastInsertId();
    }

    private function requestIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private function completeReview(int $requestId, int $adminId, string $status, string $note): void
    {
        if (trim($note) === '') {
            throw new InvalidArgumentException('Please provide a review note.');
        }
        $this->getPendingOrFail($requestId);
        $this->db->execute(
            'UPDATE upgrade_requests
                SET status = :status,
                    admin_note = :note,
                    reviewed_by_admin_id = :admin_id,
                    reviewed_at = NOW(),
                    updated_at = NOW()
              WHERE id = :id',
            ['status' => $status, 'note' => $note, 'admin_id' => $adminId, 'id' => $requestId]
        );
    }

    private function getPendingOrFail(int $requestId): array
    {
        $req = $this->getOrFail($requestId);
        if (($req['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Request is no longer pending.');
        }
        return $req;
    }

    private function getOrFail(int $requestId): array
    {
        $req = $this->db->first('SELECT * FROM upgrade_requests WHERE id = :id LIMIT 1', ['id' => $requestId]);
        if (!$req) {
            throw new RuntimeException('Upgrade request not found.');
        }
        return $req;
    }

    private function ensureResellerProfile(int $userId, array $request): void
    {
        $this->db->safeExecute(
            'INSERT INTO reseller_profiles (user_id, business_name, phone, status, created_at, updated_at)
             VALUES (:uid, :business_name, :phone, :status, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                business_name = COALESCE(NULLIF(VALUES(business_name), ""), business_name),
                phone = COALESCE(NULLIF(VALUES(phone), ""), phone),
                status = VALUES(status),
                updated_at = NOW()',
            [
                'uid' => $userId,
                'business_name' => $request['business_name'] ?? '',
                'phone' => $request['phone'] ?? '',
                'status' => 'active',
            ]
        );
    }

    private function ensureApiProfile(int $userId, array $request): void
    {
        $this->db->safeExecute(
            'INSERT INTO api_user_profiles (user_id, website_url, status, created_at, updated_at)
             VALUES (:uid, :website_url, :status, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                website_url = COALESCE(NULLIF(VALUES(website_url), ""), website_url),
                status = VALUES(status),
                updated_at = NOW()',
            [
                'uid' => $userId,
                'website_url' => $request['website_url'] ?? '',
                'status' => 'active',
            ]
        );
    }

    private function ensureApiUser(int $userId): int
    {
        $existing = $this->db->safeFirst('SELECT id FROM api_users WHERE user_id = :uid LIMIT 1', ['uid' => $userId]);
        if ($existing) {
            $this->db->safeExecute('UPDATE api_users SET status = :status WHERE id = :id', ['status' => 'active', 'id' => $existing['id']]);
            return (int) $existing['id'];
        }

        $this->db->safeExecute(
            'INSERT INTO api_users (user_id, status, created_at, updated_at)
             VALUES (:uid, :status, NOW(), NOW())',
            ['uid' => $userId, 'status' => 'active']
        );

        return $this->db->lastInsertId();
    }
}
