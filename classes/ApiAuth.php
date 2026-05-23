<?php

declare(strict_types=1);

namespace GemData\Classes;

class ApiAuth
{
    public function __construct(private Database $db, private RateLimiter $rateLimiter)
    {
    }

    public function authenticate(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headerKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
        $headerSecret = $headers['X-API-SECRET'] ?? $_SERVER['HTTP_X_API_SECRET'] ?? '';
        $apiKey = trim((string) $headerKey);
        $apiSecret = (string) $headerSecret;

        if ($apiKey === '' || $apiSecret === '') {
            $this->throttleRejectedAttempt(null);
            $this->logRequest([], 'rejected', 'Missing API credentials.');
            throw new \RuntimeException('API authentication failed.');
        }

        $this->throttleRejectedAttempt($apiKey);
        $record = $this->db->first(
            'SELECT ak.*, au.id AS api_user_id, au.user_id, au.status AS api_user_status, u.full_name, u.email, u.status AS user_status
             FROM api_keys ak
             INNER JOIN api_users au ON au.id = ak.api_user_id
             INNER JOIN users u ON u.id = au.user_id
             WHERE ak.api_key = :api_key LIMIT 1',
            ['api_key' => $apiKey]
        );

        if (!$record || $record['status'] !== 'active' || $record['api_user_status'] !== 'active' || $record['user_status'] !== 'active') {
            $this->logRequest([], 'rejected', 'API account is inactive or missing.');
            throw new \RuntimeException('API authentication failed.');
        }

        if (!password_verify($apiSecret, $record['secret_hash'])) {
            $this->logRequest($record, 'rejected', 'Invalid API credentials.');
            throw new \RuntimeException('API authentication failed.');
        }

        $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($this->db->tableExists('api_ip_whitelists')) {
            $activeWhitelist = $this->db->first(
                'SELECT COUNT(*) AS total FROM api_ip_whitelists WHERE api_user_id = :api_user_id AND status = "active"',
                ['api_user_id' => (int) $record['api_user_id']]
            );
            if ((int) ($activeWhitelist['total'] ?? 0) > 0) {
                $allowed = $this->db->first(
                    'SELECT id FROM api_ip_whitelists WHERE api_user_id = :api_user_id AND ip_address = :ip_address AND status = "active" LIMIT 1',
                    ['api_user_id' => (int) $record['api_user_id'], 'ip_address' => $ipAddress]
                );
                if (!$allowed) {
                    $this->logRequest($record, 'rejected', 'IP address is not whitelisted.');
                    throw new \RuntimeException('API authentication failed.');
                }
            }
        }

        $this->rateLimiter->check((int) $record['id']);
        $this->db->execute('UPDATE api_keys SET last_used_at = NOW() WHERE id = :id', ['id' => $record['id']]);
        $this->logRequest($record, 'accepted');
        $this->recordUsage($record);

        return $record;
    }

    private function throttleRejectedAttempt(?string $apiKey): void
    {
        if (!$this->db->tableExists('api_request_logs')) {
            return;
        }
        $row = $this->db->safeFirst(
            'SELECT COUNT(*) AS total
             FROM api_request_logs
             WHERE request_status = "rejected"
               AND ip_address = :ip_address
               AND endpoint = :endpoint
               AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)',
            [
                'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            ]
        );
        if ((int) ($row['total'] ?? 0) >= 20) {
            throw new \RuntimeException('API authentication failed.');
        }
    }

    private function logRequest(array $record, string $status, ?string $error = null): void
    {
        if (!$this->db->tableExists('api_request_logs')) {
            return;
        }
        $this->db->safeExecute(
            'INSERT INTO api_request_logs (api_user_id, api_key_id, user_id, method, endpoint, ip_address, request_status, error_message)
             VALUES (:api_user_id, :api_key_id, :user_id, :method, :endpoint, :ip_address, :request_status, :error_message)',
            [
                'api_user_id' => isset($record['api_user_id']) ? (int) $record['api_user_id'] : null,
                'api_key_id' => isset($record['id']) ? (int) $record['id'] : null,
                'user_id' => isset($record['user_id']) ? (int) $record['user_id'] : null,
                'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'request_status' => $status,
                'error_message' => $error,
            ]
        );
    }

    private function recordUsage(array $record): void
    {
        if (!$this->db->tableExists('api_usage_records')) {
            return;
        }
        $this->db->safeExecute(
            'INSERT INTO api_usage_records (api_user_id, usage_date, request_count)
             VALUES (:api_user_id, CURDATE(), 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1, updated_at = NOW()',
            ['api_user_id' => (int) ($record['api_user_id'] ?? 0)]
        );
    }
}
