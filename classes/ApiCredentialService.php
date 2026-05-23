<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class ApiCredentialService
{
    public function __construct(private Database $db)
    {
    }

    public function ensureActiveKey(int $apiUserId): ?array
    {
        if ($apiUserId <= 0) {
            return null;
        }

        $existing = $this->db->safeFirst(
            'SELECT id, api_key FROM api_keys WHERE api_user_id = :api_user_id AND status = "active" ORDER BY id DESC LIMIT 1',
            ['api_user_id' => $apiUserId]
        );

        if ($existing) {
            return [
                'api_key' => (string) $existing['api_key'],
                'secret' => null,
                'created' => false,
            ];
        }

        return $this->generateForApiUser($apiUserId, false);
    }

    public function generateForApiUser(int $apiUserId, bool $revokeExisting = true): array
    {
        if ($apiUserId <= 0) {
            throw new RuntimeException('API user account is missing.');
        }

        $apiUser = $this->db->safeFirst('SELECT id FROM api_users WHERE id = :id LIMIT 1', ['id' => $apiUserId]);
        if (!$apiUser) {
            throw new RuntimeException('API user account was not found.');
        }

        $apiKey = $this->newApiKey();
        $secret = bin2hex(random_bytes(24));

        if ($revokeExisting) {
            $this->db->safeExecute(
                'UPDATE api_keys SET status = "inactive" WHERE api_user_id = :api_user_id',
                ['api_user_id' => $apiUserId]
            );
        }

        $this->db->safeExecute(
            'INSERT INTO api_keys (api_user_id, api_key, secret_hash, status)
             VALUES (:api_user_id, :api_key, :secret_hash, "active")',
            [
                'api_user_id' => $apiUserId,
                'api_key' => $apiKey,
                'secret_hash' => password_hash($secret, PASSWORD_DEFAULT),
            ]
        );

        return [
            'api_key' => $apiKey,
            'secret' => $secret,
            'created' => true,
        ];
    }

    private function newApiKey(): string
    {
        do {
            $apiKey = 'gmd_live_' . bin2hex(random_bytes(12));
            $exists = $this->db->safeFirst('SELECT id FROM api_keys WHERE api_key = :api_key LIMIT 1', ['api_key' => $apiKey]);
        } while ($exists);

        return $apiKey;
    }
}
