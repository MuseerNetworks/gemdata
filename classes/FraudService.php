<?php

declare(strict_types=1);

namespace GemData\Classes;

class FraudService
{
    public function __construct(private Database $db)
    {
    }

    public function fingerprint(int $userId, string $serviceSlug, string $recipient, float $amount): string
    {
        return sha1($userId . '|' . strtolower($serviceSlug) . '|' . strtolower($recipient) . '|' . number_format($amount, 2, '.', ''));
    }

    public function inspect(int $userId, string $serviceSlug, string $recipient, float $amount): array
    {
        $fingerprint = $this->fingerprint($userId, $serviceSlug, $recipient, $amount);
        $duplicate = $this->db->first(
            'SELECT id, reference FROM transactions
             WHERE user_id = :user_id AND recipient = :recipient AND amount = :amount
             AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             ORDER BY id DESC LIMIT 1',
            [
                'user_id' => $userId,
                'recipient' => $recipient,
                'amount' => $amount,
            ]
        );

        $rapid = $this->db->first(
            'SELECT COUNT(*) AS total
             FROM transactions
             WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)',
            ['user_id' => $userId]
        );

        $events = [];
        if ($duplicate) {
            $events[] = [
                'event_type' => 'duplicate_transaction_pattern',
                'risk_level' => 'medium',
                'fingerprint' => $fingerprint,
                'description' => 'Possible duplicate transaction detected close to a prior request.',
            ];
        }

        if ((int) ($rapid['total'] ?? 0) >= 4) {
            $events[] = [
                'event_type' => 'rapid_requests',
                'risk_level' => 'medium',
                'fingerprint' => sha1($userId . '|rapid|' . date('YmdHi')),
                'description' => 'Rapid request pattern detected for this user.',
            ];
        }

        return $events;
    }

    public function log(int $userId, ?int $transactionId, array $event): void
    {
        $this->db->safeExecute(
            'INSERT INTO fraud_events (user_id, transaction_id, event_type, risk_level, fingerprint, description)
             VALUES (:user_id, :transaction_id, :event_type, :risk_level, :fingerprint, :description)',
            [
                'user_id' => $userId,
                'transaction_id' => $transactionId,
                'event_type' => $event['event_type'],
                'risk_level' => $event['risk_level'],
                'fingerprint' => $event['fingerprint'],
                'description' => $event['description'],
            ]
        );
    }
}
