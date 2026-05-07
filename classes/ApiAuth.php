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
        $apiKey = $headerKey !== '' ? $headerKey : ($_POST['api_key'] ?? '');
        $apiSecret = $headerSecret !== '' ? $headerSecret : ($_POST['api_secret'] ?? '');

        if ($apiKey === '' || $apiSecret === '') {
            throw new \RuntimeException('API credentials are required.');
        }

        $record = $this->db->first(
            'SELECT ak.*, au.user_id, au.status AS api_user_status, u.full_name, u.email, u.status AS user_status
             FROM api_keys ak
             INNER JOIN api_users au ON au.id = ak.api_user_id
             INNER JOIN users u ON u.id = au.user_id
             WHERE ak.api_key = :api_key LIMIT 1',
            ['api_key' => $apiKey]
        );

        if (!$record || $record['status'] !== 'active' || $record['api_user_status'] !== 'active' || $record['user_status'] !== 'active') {
            throw new \RuntimeException('API account is inactive or missing.');
        }

        if (!password_verify($apiSecret, $record['secret_hash'])) {
            throw new \RuntimeException('Invalid API credentials.');
        }

        $this->rateLimiter->check((int) $record['id']);
        $this->db->execute('UPDATE api_keys SET last_used_at = NOW() WHERE id = :id', ['id' => $record['id']]);

        return $record;
    }
}
