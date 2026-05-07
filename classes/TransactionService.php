<?php

declare(strict_types=1);

namespace GemData\Classes;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TransactionService
{
    public function __construct(
        private Database $db,
        private Wallet $wallet,
        private Commission $commission,
        private NotificationService $notifications,
        private ProviderManager $providers,
        private PricingService $pricing,
        private FraudService $fraud,
        private ActivityLogger $logger
    ) {
    }

    public function serviceMap(): array
    {
        return [
            'airtime' => ['recipient' => 'phone', 'required' => ['network', 'phone', 'amount']],
            'data' => ['recipient' => 'phone', 'required' => ['network', 'plan', 'phone', 'amount']],
            'electricity' => ['recipient' => 'meter_number', 'required' => ['meter_type', 'meter_number', 'disco', 'amount']],
            'cable_tv' => ['recipient' => 'smartcard_number', 'required' => ['provider', 'smartcard_number', 'package', 'amount']],
            'exam_pin' => ['recipient' => 'exam_type', 'required' => ['exam_type', 'quantity', 'amount']],
            'recharge_card' => ['recipient' => 'network', 'required' => ['network', 'quantity', 'amount']],
            'data_card' => ['recipient' => 'network', 'required' => ['network', 'plan', 'quantity', 'amount']],
            'bulk_sms' => ['recipient' => 'sender', 'required' => ['sender', 'message', 'recipients', 'amount']],
        ];
    }

    public function validatePayload(string $serviceSlug, array $payload): array
    {
        $map = $this->serviceMap()[$serviceSlug] ?? null;
        if (!$map) {
            return ['service' => ['Unknown service selected.']];
        }

        $errors = [];
        foreach ($map['required'] as $field) {
            if (!isset($payload[$field]) || trim((string) $payload[$field]) === '') {
                $errors[$field][] = 'This field is required.';
            }
        }

        if (isset($payload['amount']) && (!is_numeric($payload['amount']) || (float) $payload['amount'] <= 0)) {
            $errors['amount'][] = 'Amount must be greater than zero.';
        }

        if ($serviceSlug === 'bulk_sms' && !empty($payload['recipients'])) {
            $recipients = array_filter(array_map('trim', explode(',', (string) $payload['recipients'])));
            if ($recipients === []) {
                $errors['recipients'][] = 'Provide at least one recipient.';
            }
        }

        return $errors;
    }

    public function purchase(string $serviceSlug, int $userId, array $payload, string $channel = 'web', bool $isApiUser = false): array
    {
        $service = $this->db->first('SELECT * FROM services WHERE slug = :slug LIMIT 1', ['slug' => $serviceSlug]);
        if (!$service) {
            throw new RuntimeException('Service not found.');
        }
        if ((int) $service['is_enabled'] !== 1) {
            throw new RuntimeException('This service is currently disabled.');
        }

        $errors = $this->validatePayload($serviceSlug, $payload);
        if ($errors !== []) {
            throw new InvalidArgumentException(json_encode($errors));
        }

        $networkCode = $this->pricing->normalizeNetwork((string) ($payload['network'] ?? $payload['provider'] ?? ''));
        if ($networkCode !== null) {
            $network = $this->db->first(
                'SELECT sn.* FROM service_networks sn WHERE sn.service_id = :service_id AND sn.network_code = :network_code LIMIT 1',
                ['service_id' => $service['id'], 'network_code' => $networkCode]
            );
            if ($network && (int) $network['is_enabled'] !== 1) {
                throw new RuntimeException('This network is currently unavailable for the selected service.');
            }
        }

        $amount = round((float) $payload['amount'], 2);
        if ($amount < (float) $service['min_amount'] || $amount > (float) $service['max_amount']) {
            throw new RuntimeException('Amount is outside the allowed service range.');
        }

        $idempotencyKey = $this->normalizeIdempotencyKey((string) ($payload['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = $this->generateIdempotencyKey('txn');
        }

        $existing = $this->findByIdempotency($userId, $channel, $idempotencyKey);
        if ($existing) {
            return $this->responsePayload($existing);
        }

        $reference = strtoupper('GDT' . bin2hex(random_bytes(6)));
        $recipientKey = $this->serviceMap()[$serviceSlug]['recipient'];
        $recipient = (string) ($payload[$recipientKey] ?? $payload['phone'] ?? $serviceSlug);
        $pricing = $this->pricing->resolve($userId, (int) $service['id'], $networkCode, $amount, $isApiUser);
        $fraudEvents = $this->fraud->inspect($userId, $serviceSlug, $recipient, $amount);
        foreach ($fraudEvents as $fraudEvent) {
            if (($fraudEvent['event_type'] ?? '') === 'duplicate_transaction_pattern') {
                throw new RuntimeException('A similar transaction was submitted recently. Wait for the first request to complete before retrying.');
            }
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'INSERT INTO transactions (
                    user_id, service_id, reference, idempotency_key, channel, status, amount, selling_price, cost_price, profit_amount,
                    pricing_source, is_retryable, recipient, customer_name, payload_json, processing_started_at, processed_at, failure_code
                 ) VALUES (
                    :user_id, :service_id, :reference, :idempotency_key, :channel, :status, :amount, :selling_price, :cost_price, :profit_amount,
                    :pricing_source, :is_retryable, :recipient, :customer_name, :payload_json, NULL, NULL, NULL
                 )',
                [
                    'user_id' => $userId,
                    'service_id' => $service['id'],
                    'reference' => $reference,
                    'idempotency_key' => $idempotencyKey,
                    'channel' => $channel,
                    'status' => 'pending',
                    'amount' => $amount,
                    'selling_price' => $pricing['selling_price'],
                    'cost_price' => $pricing['cost_price'],
                    'profit_amount' => $pricing['profit_amount'],
                    'pricing_source' => $pricing['pricing_source'],
                    'is_retryable' => 1,
                    'recipient' => $recipient,
                    'customer_name' => $payload['customer_name'] ?? null,
                    'payload_json' => json_encode($payload),
                ]
            );
            $transactionId = $this->db->lastInsertId();

            $this->wallet->debit(
                $userId,
                $amount,
                "{$service['name']} purchase reserved ({$reference})",
                $channel,
                $payload,
                'purchase',
                $transactionId,
                'wallet-debit:' . $idempotencyKey
            );

            $this->event($transactionId, 'transaction_created', $channel === 'api' ? 'api' : 'user', $userId, 'Transaction accepted and queued for processing.', [
                'reference' => $reference,
                'pricing_source' => $pricing['pricing_source'],
                'idempotency_key' => $idempotencyKey,
            ]);
            $this->event($transactionId, 'transaction_funds_reserved', 'system', null, 'Wallet funds reserved for pending transaction.', ['amount' => $amount]);

            foreach ($fraudEvents as $fraudEvent) {
                $this->fraud->log($userId, $transactionId, $fraudEvent);
            }

            $this->db->commit();

            $this->logger->log($channel === 'api' ? 'api' : 'user', $userId, 'transaction_pending', "{$service['name']} transaction queued for processing.", ['reference' => $reference]);
            $this->notifications->create(
                $userId,
                $service['name'] . ' transaction',
                "Your {$service['name']} request for {$recipient} is pending confirmation.",
                'info'
            );

            $queued = $this->db->first(
                'SELECT t.*, s.name AS service_name
                 FROM transactions t
                 INNER JOIN services s ON s.id = t.service_id
                 WHERE t.id = :id
                 LIMIT 1',
                ['id' => $transactionId]
            );
            if (!$queued) {
                throw new RuntimeException('Transaction could not be queued.');
            }

            return $this->responsePayload($queued);
        } catch (Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function processPendingTransactions(int $limit = 20): int
    {
        $rows = $this->db->query(
            'SELECT id FROM transactions
             WHERE status = "pending" AND processing_started_at IS NULL
             ORDER BY id ASC
             LIMIT ' . max(1, (int) $limit)
        );

        $processed = 0;
        foreach ($rows as $row) {
            try {
                $result = $this->processPendingTransaction((int) $row['id']);
                if (!empty($result['processed'])) {
                    $processed++;
                }
            } catch (Throwable) {
                // Finalization failures are left recorded in transaction state/events for admin review.
            }
        }

        return $processed;
    }

    public function processPendingTransaction(int $transactionId): array
    {
        $transaction = null;
        $service = null;
        $payload = [];

        $this->db->beginTransaction();
        try {
            $transaction = $this->db->first('SELECT * FROM transactions WHERE id = :id FOR UPDATE', ['id' => $transactionId]);
            if (!$transaction) {
                throw new RuntimeException('Transaction not found.');
            }
            if (($transaction['status'] ?? '') !== 'pending' || !empty($transaction['processing_started_at'])) {
                $this->db->commit();
                return ['processed' => false, 'status' => $transaction['status'] ?? 'unknown'];
            }

            $service = $this->db->first('SELECT slug, name FROM services WHERE id = :id LIMIT 1', ['id' => $transaction['service_id']]);
            if (!$service) {
                throw new RuntimeException('Service record missing for transaction.');
            }

            $payload = json_decode_array($transaction['payload_json'] ?? '[]');
            $this->db->execute(
                'UPDATE transactions SET processing_started_at = NOW() WHERE id = :id',
                ['id' => $transactionId]
            );
            $this->event($transactionId, 'transaction_processing_started', 'system', null, 'Pending transaction picked for fulfillment.');
            $this->db->commit();
        } catch (Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }

        try {
            $providerResponse = $this->providers->purchase($service['slug'], array_merge($payload, [
                'recipient' => $transaction['recipient'],
                'amount' => (float) $transaction['amount'],
            ]));
        } catch (Throwable $throwable) {
            $providerResponse = [
                'status' => 'failed',
                'provider_reference' => null,
                'raw' => ['message' => $throwable->getMessage()],
                'attempts' => [],
            ];
        }

        $status = ($providerResponse['status'] ?? 'failed') === 'successful' ? 'successful' : 'failed';

        $this->db->beginTransaction();
        try {
            $locked = $this->db->first('SELECT * FROM transactions WHERE id = :id FOR UPDATE', ['id' => $transactionId]);
            if (!$locked) {
                throw new RuntimeException('Transaction disappeared before finalization.');
            }
            if (($locked['status'] ?? '') !== 'pending') {
                $this->db->commit();
                return ['processed' => false, 'status' => $locked['status'] ?? 'unknown'];
            }

            foreach ($providerResponse['attempts'] ?? [] as $attempt) {
                $this->event($transactionId, 'provider_attempt', 'provider', null, 'Provider attempt recorded.', $attempt);
            }

            $providerAccount = $providerResponse['provider_account'] ?? null;
            $commissionAmount = 0.0;
            if ($status === 'successful' && ($locked['channel'] ?? 'web') === 'api') {
                $rate = $this->commission->resolveRate((int) $locked['user_id'], (int) $locked['service_id']);
                $commissionAmount = round(((float) $locked['amount'] * $rate) / 100, 2);
                $this->commission->log((int) $locked['user_id'], $transactionId, (int) $locked['service_id'], $rate, (float) $locked['amount'], $commissionAmount);
            }

            if ($status !== 'successful') {
                $this->wallet->refund(
                    (int) $locked['user_id'],
                    (float) $locked['amount'],
                    "Refund for failed {$service['name']} transaction ({$locked['reference']})",
                    (string) $locked['channel'],
                    $providerResponse,
                    'refund',
                    $transactionId,
                    'wallet-refund:' . ($locked['idempotency_key'] ?? $locked['reference']),
                    'provider_failed'
                );
                $this->event($transactionId, 'transaction_refunded', 'system', null, 'Wallet refunded for failed transaction.', ['amount' => (float) $locked['amount']]);
            }

            $this->db->execute(
                'UPDATE transactions
                 SET provider_reference = :provider_reference, provider_account_id = :provider_account_id, provider_code = :provider_code,
                     status = :status, commission_amount = :commission_amount, response_json = :response_json,
                     processed_at = NOW(), failure_code = :failure_code
                 WHERE id = :id',
                [
                    'provider_reference' => $providerResponse['provider_reference'] ?? null,
                    'provider_account_id' => $providerAccount['id'] ?? null,
                    'provider_code' => $providerAccount['code'] ?? null,
                    'status' => $status,
                    'commission_amount' => $commissionAmount,
                    'response_json' => json_encode($providerResponse),
                    'failure_code' => $status === 'successful' ? null : 'provider_failed',
                    'id' => $transactionId,
                ]
            );

            $this->event($transactionId, 'transaction_' . $status, ($locked['channel'] ?? 'web') === 'api' ? 'api' : 'system', (int) $locked['user_id'], "{$service['name']} transaction {$status}.", [
                'provider_reference' => $providerResponse['provider_reference'] ?? null,
            ]);
            $this->db->commit();

            $this->logger->log(($locked['channel'] ?? 'web') === 'api' ? 'api' : 'user', (int) $locked['user_id'], 'transaction_' . $status, "{$service['name']} transaction {$status}", ['reference' => $locked['reference']]);
            $this->notifications->create(
                (int) $locked['user_id'],
                $service['name'] . ' transaction',
                $status === 'successful'
                    ? "Your {$service['name']} request for {$locked['recipient']} was successful."
                    : "Your {$service['name']} request for {$locked['recipient']} failed and your wallet was refunded.",
                $status === 'successful' ? 'success' : 'error'
            );

            return ['processed' => true, 'status' => $status, 'provider_reference' => $providerResponse['provider_reference'] ?? null];
        } catch (Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function retryTransaction(int $transactionId, int $adminId): array
    {
        $transaction = $this->db->first('SELECT * FROM transactions WHERE id = :id LIMIT 1', ['id' => $transactionId]);
        if (!$transaction) {
            throw new RuntimeException('Transaction not found.');
        }
        if (($transaction['status'] ?? '') === 'refunded') {
            throw new RuntimeException('Refunded transactions cannot be retried from the admin panel.');
        }
        $refundEvent = $this->db->first(
            'SELECT id FROM transaction_events WHERE transaction_id = :transaction_id AND event_type = :event_type LIMIT 1',
            ['transaction_id' => $transactionId, 'event_type' => 'transaction_refunded']
        );
        if ($refundEvent) {
            throw new RuntimeException('This failed transaction was already refunded. Manual retry is disabled to avoid free value delivery.');
        }
        if ((int) $transaction['retry_count'] >= (int) $transaction['max_retry_count'] || (int) $transaction['is_retryable'] !== 1) {
            throw new RuntimeException('This transaction is not retryable anymore.');
        }

        $this->db->beginTransaction();
        try {
            $locked = $this->db->first('SELECT * FROM transactions WHERE id = :id FOR UPDATE', ['id' => $transactionId]);
            if (!$locked) {
                throw new RuntimeException('Transaction not found.');
            }
            if (($locked['status'] ?? '') !== 'failed') {
                throw new RuntimeException('Only failed transactions can be queued for retry.');
            }

            $this->db->execute(
                'UPDATE transactions
                 SET status = :status, retry_count = retry_count + 1, processing_started_at = NULL, processed_at = NULL, failure_code = NULL
                 WHERE id = :id',
                [
                    'status' => 'pending',
                    'id' => $transactionId,
                ]
            );
            $this->event($transactionId, 'transaction_retry_queued', 'admin', $adminId, 'Admin queued failed transaction for retry.');
            $this->logger->log('admin', $adminId, 'transaction_retry_queued', 'Admin queued transaction retry.', ['transaction_id' => $transactionId]);
            $this->db->commit();
        } catch (Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }

        return ['status' => 'pending', 'provider_reference' => null];
    }

    public function overrideStatus(int $transactionId, string $status, string $reason, int $adminId): void
    {
        $transaction = $this->db->first('SELECT * FROM transactions WHERE id = :id LIMIT 1', ['id' => $transactionId]);
        if (!$transaction) {
            throw new RuntimeException('Transaction not found.');
        }
        $currentStatus = (string) $transaction['status'];
        $allowedTransitions = [
            'pending' => ['failed'],
            'failed' => [],
            'successful' => [],
            'refunded' => [],
        ];
        if (!in_array($status, $allowedTransitions[$currentStatus] ?? [], true)) {
            throw new RuntimeException('Manual override is limited to cancelling pending transactions into failed status.');
        }

        $this->db->beginTransaction();
        try {
            $locked = $this->db->first('SELECT * FROM transactions WHERE id = :id FOR UPDATE', ['id' => $transactionId]);
            if (!$locked) {
                throw new RuntimeException('Transaction not found.');
            }

            $this->db->execute(
                'UPDATE transactions
                 SET status = :status, override_status = 1, override_reason = :override_reason, overridden_by_admin_id = :admin_id, overridden_at = NOW(),
                     is_retryable = 0, processed_at = NOW(), failure_code = :failure_code
                 WHERE id = :id',
                [
                    'status' => $status,
                    'override_reason' => $reason,
                    'admin_id' => $adminId,
                    'failure_code' => 'admin_cancelled',
                    'id' => $transactionId,
                ]
            );

            $refundEvent = $this->db->first(
                'SELECT id FROM transaction_events WHERE transaction_id = :transaction_id AND event_type = :event_type LIMIT 1',
                ['transaction_id' => $transactionId, 'event_type' => 'transaction_refunded']
            );
            if (!$refundEvent) {
                $this->wallet->refund(
                    (int) $locked['user_id'],
                    (float) $locked['amount'],
                    'Admin cancellation refund for transaction ' . $locked['reference'],
                    'admin',
                    [
                        'admin_id' => $adminId,
                        'transaction_id' => $transactionId,
                    ],
                    'refund',
                    $transactionId,
                    'wallet-override-refund:' . ($locked['idempotency_key'] ?? $locked['reference']),
                    'admin_cancellation'
                );
                $this->event($transactionId, 'transaction_refunded', 'admin', $adminId, 'Admin issued wallet refund during override.', ['amount' => (float) $locked['amount']]);
            }

            $this->event($transactionId, 'transaction_override', 'admin', $adminId, 'Admin cancelled pending transaction.', ['status' => $status, 'reason' => $reason]);
            $this->logger->log('admin', $adminId, 'transaction_override', 'Admin cancelled pending transaction.', ['transaction_id' => $transactionId, 'status' => $status]);
            $this->db->commit();
        } catch (Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    private function responsePayload(array $transaction): array
    {
        return [
            'reference' => $transaction['reference'],
            'provider_reference' => $transaction['provider_reference'] ?? null,
            'status' => $transaction['status'],
            'amount' => (float) $transaction['amount'],
            'service' => $transaction['service_name'] ?? null,
            'recipient' => $transaction['recipient'] ?? null,
            'commission_amount' => (float) ($transaction['commission_amount'] ?? 0),
            'profit_amount' => (float) ($transaction['profit_amount'] ?? 0),
            'queued' => ($transaction['status'] ?? '') === 'pending',
        ];
    }

    private function findByIdempotency(int $userId, string $channel, string $idempotencyKey): ?array
    {
        if ($idempotencyKey === '') {
            return null;
        }

        return $this->db->first(
            'SELECT t.*, s.name AS service_name
             FROM transactions t
             INNER JOIN services s ON s.id = t.service_id
             WHERE t.user_id = :user_id AND t.channel = :channel AND t.idempotency_key = :idempotency_key
             LIMIT 1',
            [
                'user_id' => $userId,
                'channel' => $channel,
                'idempotency_key' => $idempotencyKey,
            ]
        );
    }

    private function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);
        return preg_replace('/[^a-zA-Z0-9:_-]/', '', $key) ?? '';
    }

    private function generateIdempotencyKey(string $prefix): string
    {
        return $prefix . ':' . bin2hex(random_bytes(16));
    }

    private function event(int $transactionId, string $type, string $actorType, ?int $actorId, string $notes, array $meta = []): void
    {
        $this->db->execute(
            'INSERT INTO transaction_events (transaction_id, event_type, actor_type, actor_id, notes, meta_json)
             VALUES (:transaction_id, :event_type, :actor_type, :actor_id, :notes, :meta_json)',
            [
                'transaction_id' => $transactionId,
                'event_type' => $type,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'notes' => $notes,
                'meta_json' => $meta === [] ? null : json_encode($meta),
            ]
        );
    }
}
