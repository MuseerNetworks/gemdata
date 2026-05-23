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
        $limit = $this->limitPerMinute;
        if ($this->db->columnExists('api_users', 'rate_limit_per_minute')) {
            $limitRow = $this->db->first(
                'SELECT au.rate_limit_per_minute
                 FROM api_keys ak
                 INNER JOIN api_users au ON au.id = ak.api_user_id
                 WHERE ak.id = :api_key_id LIMIT 1',
                ['api_key_id' => $apiKeyId]
            );
            $limit = max(1, (int) ($limitRow['rate_limit_per_minute'] ?? $limit));
        }
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $this->db->execute(
                'INSERT IGNORE INTO api_rate_limits (api_key_id, window_key, request_count) VALUES (:api_key_id, :window_key, 0)',
                ['api_key_id' => $apiKeyId, 'window_key' => $window]
            );

            $record = $this->db->first(
                'SELECT * FROM api_rate_limits WHERE api_key_id = :api_key_id AND window_key = :window_key LIMIT 1 FOR UPDATE',
                ['api_key_id' => $apiKeyId, 'window_key' => $window]
            );
            if (!$record) {
                throw new \RuntimeException('Rate limit state could not be created.');
            }

            if ((int) $record['request_count'] >= $limit) {
                throw new \RuntimeException('Rate limit exceeded. Try again in the next minute window.');
            }

            $this->db->execute('UPDATE api_rate_limits SET request_count = request_count + 1 WHERE id = :id', ['id' => $record['id']]);
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $throwable) {
            if ($ownsTransaction) {
                $this->db->rollBack();
            }
            throw $throwable;
        }
    }
}
