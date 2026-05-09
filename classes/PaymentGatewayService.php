<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class PaymentGatewayService
{
    public function __construct(
        private Database $db,
        private Wallet $wallet,
        private NotificationService $notifications,
        private ActivityLogger $logger
    ) {
    }

    public function isProductionBankTransferOnly(): bool
    {
        return config('app.environment', 'local') === 'production';
    }

    public function createFundingRequest(int $userId, float $amount, string $provider = 'mock_paystack'): array
    {
        if ($this->isProductionBankTransferOnly()) {
            throw new RuntimeException('Production wallet funding is handled through your dedicated transfer account.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Enter a valid funding amount.');
        }

        $idempotencyKey = trim((string) ($_POST['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = 'fund:' . bin2hex(random_bytes(16));
        }

        $existing = $this->db->first(
            'SELECT * FROM wallet_funding_requests WHERE user_id = :user_id AND idempotency_key = :idempotency_key LIMIT 1',
            ['user_id' => $userId, 'idempotency_key' => $idempotencyKey]
        );
        if ($existing) {
            if (($existing['status'] ?? '') === 'initiated') {
                $sessionToken = bin2hex(random_bytes(24));
                $this->db->execute(
                    'UPDATE wallet_funding_requests SET callback_session_token_hash = :callback_session_token_hash WHERE id = :id',
                    [
                        'callback_session_token_hash' => password_hash($sessionToken, PASSWORD_DEFAULT),
                        'id' => $existing['id'],
                    ]
                );
                $existing['callback_session_token'] = $sessionToken;
            }
            return $existing;
        }

        $reference = 'GFW' . strtoupper(bin2hex(random_bytes(5)));
        $token = bin2hex(random_bytes(24));
        $sessionToken = bin2hex(random_bytes(24));

        $this->db->execute(
            'INSERT INTO wallet_funding_requests
                (user_id, reference, provider, amount, currency, status, callback_token_hash, idempotency_key, callback_session_token_hash, meta_json)
             VALUES
                (:user_id, :reference, :provider, :amount, :currency, :status, :callback_token_hash, :idempotency_key, :callback_session_token_hash, :meta_json)',
            [
                'user_id' => $userId,
                'reference' => $reference,
                'provider' => $provider,
                'amount' => $amount,
                'currency' => (string) config('app.currency', 'NGN'),
                'status' => 'initiated',
                'callback_token_hash' => password_hash($token, PASSWORD_DEFAULT),
                'idempotency_key' => $idempotencyKey,
                'callback_session_token_hash' => password_hash($sessionToken, PASSWORD_DEFAULT),
                'meta_json' => json_encode(['environment' => config('app.environment', 'local')]),
            ]
        );

        $request = $this->db->first('SELECT * FROM wallet_funding_requests WHERE reference = :reference', ['reference' => $reference]);
        if (!$request) {
            throw new RuntimeException('Could not create the funding request.');
        }

        $this->logger->log('user', $userId, 'wallet_funding_initiated', 'User initiated wallet funding.', [
            'reference' => $reference,
            'provider' => $provider,
            'amount' => $amount,
        ]);

        $request['callback_token'] = $token;
        $request['callback_session_token'] = $sessionToken;
        return $request;
    }

    public function userFundingRequests(int $userId, int $limit = 6): array
    {
        return $this->db->query(
            'SELECT * FROM wallet_funding_requests
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT ' . max(1, (int) $limit),
            ['user_id' => $userId]
        );
    }

    public function findUserFundingRequest(int $userId, string $reference): ?array
    {
        return $this->db->first(
            'SELECT * FROM wallet_funding_requests WHERE user_id = :user_id AND reference = :reference',
            ['user_id' => $userId, 'reference' => $reference]
        );
    }

    public function verifyMockCallback(string $reference, string $token, string $status, ?string $providerReference = null): array
    {
        $normalizedStatus = strtolower(trim($status));
        if (!in_array($normalizedStatus, ['success', 'failed'], true)) {
            throw new RuntimeException('Unsupported funding callback status.');
        }

        $providerReference ??= 'MOCK-' . strtoupper(bin2hex(random_bytes(4)));

        $this->db->beginTransaction();
        try {
            $request = $this->db->first('SELECT * FROM wallet_funding_requests WHERE reference = :reference FOR UPDATE', ['reference' => $reference]);
            if (!$request) {
                throw new RuntimeException('Funding request was not found.');
            }

            if (
                !password_verify($token, (string) $request['callback_token_hash'])
                && !password_verify($token, (string) ($request['callback_session_token_hash'] ?? ''))
            ) {
                throw new RuntimeException('Invalid funding verification token.');
            }

            if (in_array($request['status'], ['credited', 'failed'], true)) {
                $this->db->commit();
                return $request;
            }

            if ($normalizedStatus === 'success') {
                $this->db->execute(
                    'UPDATE wallet_funding_requests
                     SET status = :status, provider_reference = :provider_reference, verified_at = NOW(), credited_at = NOW()
                     WHERE id = :id AND status = :expected_status',
                    [
                        'status' => 'credited',
                        'provider_reference' => $providerReference,
                        'expected_status' => 'initiated',
                        'id' => $request['id'],
                    ]
                );

                $this->wallet->credit(
                    (int) $request['user_id'],
                    (float) $request['amount'],
                    'Wallet funding confirmed',
                    'web',
                    [
                        'provider' => $request['provider'],
                        'funding_reference' => $request['reference'],
                        'provider_reference' => $providerReference,
                    ]
                    ,
                    'funding',
                    (int) $request['id'],
                    'wallet-funding:' . ((string) ($request['idempotency_key'] ?? $request['reference']))
                );

                $this->notifications->create(
                    (int) $request['user_id'],
                    'Wallet funded',
                    'Your wallet funding was confirmed and credited successfully.',
                    'success'
                );
            } else {
                $this->db->execute(
                    'UPDATE wallet_funding_requests
                     SET status = :status, provider_reference = :provider_reference, verified_at = NOW()
                     WHERE id = :id AND status = :expected_status',
                    [
                        'status' => 'failed',
                        'provider_reference' => $providerReference,
                        'expected_status' => 'initiated',
                        'id' => $request['id'],
                    ]
                );

                $this->notifications->create(
                    (int) $request['user_id'],
                    'Funding attempt failed',
                    'Your wallet funding attempt was not confirmed by the payment provider.',
                    'warning'
                );
            }

            $this->logger->log('system', 0, 'wallet_funding_callback_processed', 'Processed wallet funding callback.', [
                'reference' => $request['reference'],
                'status' => $normalizedStatus,
                'provider_reference' => $providerReference,
            ]);

            $this->db->commit();
        } catch (\Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }

        return $this->db->first('SELECT * FROM wallet_funding_requests WHERE id = :id', ['id' => $request['id']]) ?? $request;
    }

    public function reconcileIncomingFunding(array $payload): array
    {
        $providerReference = trim((string) ($payload['provider_reference'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $accountNumber = trim((string) ($payload['account_number'] ?? ''));
        $email = strtolower(trim((string) ($payload['customer_email'] ?? '')));
        $amount = round((float) ($payload['amount'] ?? 0), 2);

        if ($providerReference === '' || $amount <= 0) {
            throw new RuntimeException('Incoming funding payload is incomplete.');
        }

        $this->db->beginTransaction();
        try {
            $request = null;
            if ($reference !== '') {
                $request = $this->db->first(
                    'SELECT * FROM wallet_funding_requests WHERE reference = :reference LIMIT 1',
                    ['reference' => $reference]
                );
            }

            if (!$request && $providerReference !== '') {
                $request = $this->db->first(
                    'SELECT * FROM wallet_funding_requests WHERE provider_reference = :provider_reference LIMIT 1',
                    ['provider_reference' => $providerReference]
                );
            }

            if (!$request) {
                $account = null;
                if ($accountNumber !== '') {
                    $account = $this->db->first(
                        'SELECT * FROM user_funding_accounts WHERE dedicated_account_number = :account_number LIMIT 1',
                        ['account_number' => $accountNumber]
                    );
                }

                if (!$account && $email !== '') {
                    $account = $this->db->first(
                        'SELECT ufa.* FROM user_funding_accounts ufa
                         INNER JOIN users u ON u.id = ufa.user_id
                         WHERE LOWER(u.email) = :email
                         LIMIT 1',
                        ['email' => $email]
                    );
                }

                if (!$account) {
                    throw new RuntimeException('Could not match funding webhook to a user funding account.');
                }

                $reference = $reference !== '' ? $reference : 'WFH' . strtoupper(bin2hex(random_bytes(5)));
                $this->db->execute(
                    'INSERT INTO wallet_funding_requests
                        (user_id, reference, provider, provider_reference, amount, currency, status, callback_token_hash, verified_at, credited_at, meta_json)
                     VALUES
                        (:user_id, :reference, :provider, :provider_reference, :amount, :currency, :status, :callback_token_hash, NOW(), NOW(), :meta_json)',
                    [
                        'user_id' => $account['user_id'],
                        'reference' => $reference,
                        'provider' => 'paystack_bank_transfer',
                        'provider_reference' => $providerReference,
                        'amount' => $amount,
                        'currency' => (string) ($payload['currency'] ?? config('app.currency', 'NGN')),
                        'status' => 'initiated',
                        'callback_token_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                        'meta_json' => (string) ($payload['meta_json'] ?? '{}'),
                    ]
                );
                $request = $this->db->first('SELECT * FROM wallet_funding_requests WHERE reference = :reference LIMIT 1', ['reference' => $reference]);
            }

            if (!$request) {
                throw new RuntimeException('Funding request could not be loaded.');
            }

            if (($request['status'] ?? '') === 'credited') {
                $this->db->commit();
                return ['credited' => false, 'reference' => $request['reference'], 'linked_transaction_id' => null];
            }

            $this->db->execute(
                'UPDATE wallet_funding_requests
                 SET provider_reference = :provider_reference, status = :status, verified_at = NOW(), credited_at = NOW(), meta_json = :meta_json
                 WHERE id = :id',
                [
                    'provider_reference' => $providerReference,
                    'status' => 'credited',
                    'meta_json' => (string) ($payload['meta_json'] ?? '{}'),
                    'id' => $request['id'],
                ]
            );

            $walletRecord = $this->wallet->credit(
                (int) $request['user_id'],
                (float) $request['amount'],
                'Wallet funding confirmed via bank transfer',
                'system',
                [
                    'provider' => $request['provider'],
                    'funding_reference' => $request['reference'],
                    'provider_reference' => $providerReference,
                    'event_key' => $payload['event_key'] ?? null,
                ],
                'funding',
                (int) $request['id'],
                'wallet-funding:' . ($providerReference !== '' ? $providerReference : (string) $request['reference']),
                'paystack_bank_transfer'
            );

            $this->notifications->create(
                (int) $request['user_id'],
                'Wallet funded',
                'Your wallet has been credited after Paystack bank transfer verification.',
                'success'
            );

            $this->logger->log('system', 0, 'wallet_funding_webhook_credited', 'Credited wallet from Paystack webhook.', [
                'reference' => $request['reference'],
                'provider_reference' => $providerReference,
                'wallet_transaction_id' => $walletRecord['id'] ?? null,
            ]);

            $this->db->commit();

            return [
                'credited' => true,
                'reference' => $request['reference'],
                'linked_transaction_id' => null,
            ];
        } catch (\Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function reconcileFundingCredits(int $limit = 20): array
    {
        $rows = $this->db->query(
            'SELECT *
             FROM wallet_funding_requests
             WHERE status = "credited"
             ORDER BY id ASC
             LIMIT ' . max(1, (int) $limit)
        );

        $repaired = 0;
        foreach ($rows as $row) {
            $existingWalletTx = $this->db->first(
                'SELECT id FROM wallet_transactions
                 WHERE source_type = :source_type AND source_id = :source_id
                 LIMIT 1',
                [
                    'source_type' => 'funding',
                    'source_id' => $row['id'],
                ]
            );
            if ($existingWalletTx) {
                continue;
            }

            try {
                $this->wallet->credit(
                    (int) $row['user_id'],
                    (float) $row['amount'],
                    'Funding credit repaired during reconciliation',
                    'system',
                    [
                        'provider' => $row['provider'],
                        'funding_reference' => $row['reference'],
                        'provider_reference' => $row['provider_reference'],
                    ],
                    'funding',
                    (int) $row['id'],
                    'wallet-funding-repair:' . ($row['provider_reference'] ?: $row['reference']),
                    'funding_repair'
                );
                $repaired++;
            } catch (\Throwable $throwable) {
                $this->logger->log('system', 0, 'wallet_funding_repair_failed', 'Funding credit repair failed.', [
                    'reference' => $row['reference'],
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        return ['repaired' => $repaired];
    }
}
