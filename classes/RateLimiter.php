<?php

declare(strict_types=1);

namespace GemData\Classes;

class RateLimiter
{
    public function __construct(private Database $db, private int $limitPerMinute = 60)
    {
    }

    public function check(int $apiKeyId): void
    {
        $window = date('YmdHi');
        $record = $this->db->first(
            'SELECT * FROM api_rate_limits WHERE api_key_id = :api_key_id AND window_key = :window_key LIMIT 1',
            ['api_key_id' => $apiKeyId, 'window_key' => $window]
        );

        if (!$record) {
            $this->db->execute(
                'INSERT INTO api_rate_limits (api_key_id, window_key, request_count) VALUES (:api_key_id, :window_key, 1)',
                ['api_key_id' => $apiKeyId, 'window_key' => $window]
            );
            return;
        }

        if ((int) $record['request_count'] >= $this->limitPerMinute) {
            throw new \RuntimeException('Rate limit exceeded. Try again in the next minute window.');
        }

        $this->db->execute('UPDATE api_rate_limits SET request_count = request_count + 1 WHERE id = :id', ['id' => $record['id']]);
    }
}
