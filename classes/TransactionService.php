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
        private ProviderPlanService $providerPlans,
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

        if (isset($payload['phone']) && trim((string) $payload['phone']) !== '' && $this->normalizePhone((string) $payload['phone']) === '') {
            $errors['phone'][] = 'Provide a valid phone number.';
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
        if ((bool) config('security.email_verification_required_for_money_movement', true) && $this->db->columnExists('users', 'email_verified_at')) {
            $verification = $this->db->first('SELECT email_verified_at FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
            if (empty($verification['email_verified_at'])) {
                throw new RuntimeException('Verify your email address before making purchases.');
            }
        }

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

        if (isset($payload['phone'])) {
            $payload['phone'] = $this->normalizePhone((string) $payload['phone']);
        }

        if ($serviceSlug === 'data') {
            $payload['plan'] = strtoupper(trim((string) ($payload['plan'] ?? '')));
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

        $providerSelection = $this->providers->selectProviderForPurchase($serviceSlug, array_merge($payload, [
            'recipient' => $recipient,
            'amount' => $amount,
            'reference' => $reference,
            'service_id' => (int) $service['id'],
        ]));
        $selectedProvider = is_array($providerSelection['provider'] ?? null) ? $providerSelection['provider'] : null;
        if ($selectedProvider === null) {
            return $this->recordProviderSelectionFailure($service, $serviceSlug, $userId, $payload, $channel, $amount, $pricing, $reference, $idempotencyKey, $recipient, $providerSelection);
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'INSERT INTO transactions (
                    user_id, service_id, reference, idempotency_key, channel, status, amount, selling_price, cost_price, profit_amount,
                    pricing_source, is_retryable, recipient, customer_name, payload_json, provider_account_id, provider_code,
                    processing_started_at, processed_at, failure_code
                 ) VALUES (
                    :user_id, :service_id, :reference, :idempotency_key, :channel, :status, :amount, :selling_price, :cost_price, :profit_amount,
                    :pricing_source, :is_retryable, :recipient, :customer_name, :payload_json, :provider_account_id, :provider_code,
                    NULL, NULL, NULL
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
                    'provider_account_id' => (int) $selectedProvider['id'],
                    'provider_code' => (string) $selectedProvider['code'],
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
            $this->event($transactionId, 'transaction_provider_selected', 'system', null, 'Provider selected before transaction fulfillment.', [
                'provider_account_id' => (int) $selectedProvider['id'],
                'provider_code' => (string) $selectedProvider['code'],
                'routing_mode' => (string) ($providerSelection['setting']['routing_mode'] ?? 'priority'),
                'routing' => $providerSelection['diagnostics'] ?? [],
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
        $this->recoverStaleProcessingLocks();

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

    public function recoverStaleProcessingLocks(int $ageMinutes = 10): int
    {
        $rows = $this->db->query(
            'SELECT id, reference
             FROM transactions
             WHERE status = "pending"
               AND processing_started_at IS NOT NULL
               AND processed_at IS NULL
               AND (failure_code IS NULL OR failure_code <> "provider_pending")
               AND processing_started_at < DATE_SUB(NOW(), INTERVAL ' . max(1, $ageMinutes) . ' MINUTE)
             ORDER BY id ASC
             LIMIT 50'
        );

        $count = 0;
        foreach ($rows as $row) {
            $this->db->execute(
                'UPDATE transactions
                 SET processing_started_at = NULL, failure_code = :failure_code
                 WHERE id = :id',
                [
                    'failure_code' => 'processing_lock_recovered',
                    'id' => $row['id'],
                ]
            );
            $this->event((int) $row['id'], 'transaction_processing_recovered', 'system', null, 'Recovered stale transaction processing lock.');
            $count++;
        }

        return $count;
    }

    public function reconcileTransactions(int $limit = 20): array
    {
        $recoveredLocks = $this->recoverStaleProcessingLocks();
        $timedOut = 0;
        $refunded = 0;

        $rows = $this->db->query(
            'SELECT t.*, s.name AS service_name
             FROM transactions t
             INNER JOIN services s ON s.id = t.service_id
             WHERE t.status = "pending"
               AND t.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
             ORDER BY t.id ASC
             LIMIT ' . max(1, (int) $limit)
        );

        foreach ($rows as $row) {
            if (($row['provider_code'] ?? '') !== '' || ($row['provider_reference'] ?? '') !== '') {
                $provider = !empty($row['provider_account_id'])
                    ? $this->providers->getById((int) $row['provider_account_id'])
                    : null;

                if (!$provider && !empty($row['provider_code'])) {
                    $provider = $this->db->first('SELECT * FROM provider_accounts WHERE code = :code LIMIT 1', ['code' => $row['provider_code']]);
                }

                if ($provider) {
                    try {
                        $providerResponse = $this->providers->queryTransaction($provider, (string) ($row['provider_reference'] ?: $row['reference']));
                        $providerStatus = $this->normalizeProviderStatus((string) ($providerResponse['status'] ?? 'pending'));
                        if ($providerStatus === 'successful') {
                            $this->markTransactionSuccessfulFromReconciliation($row, $providerResponse);
                            $timedOut++;
                            continue;
                        }

                        if ($providerStatus === 'pending') {
                            $this->event((int) $row['id'], 'transaction_reconciliation_pending', 'system', null, 'Provider still reports this transaction as pending.', [
                                'provider_reference' => $providerResponse['provider_reference'] ?? null,
                            ]);
                            continue;
                        }
                    } catch (Throwable $throwable) {
                        $this->event((int) $row['id'], 'transaction_reconciliation_error', 'system', null, 'Provider reconciliation request failed.', [
                            'error' => $throwable->getMessage(),
                        ]);
                        continue;
                    }
                }
            }

            $hasRefund = $this->db->first(
                'SELECT id FROM transaction_events WHERE transaction_id = :transaction_id AND event_type = :event_type LIMIT 1',
                ['transaction_id' => $row['id'], 'event_type' => 'transaction_refunded']
            );
            if ($hasRefund) {
                continue;
            }

            $this->db->beginTransaction();
            try {
                $locked = $this->db->first('SELECT * FROM transactions WHERE id = :id FOR UPDATE', ['id' => $row['id']]);
                if (!$locked || ($locked['status'] ?? '') !== 'pending') {
                    $this->db->commit();
                    continue;
                }

                $this->wallet->refund(
                    (int) $locked['user_id'],
                    (float) $locked['amount'],
                    'Automatic timeout refund for transaction ' . $locked['reference'],
                    (string) $locked['channel'],
                    ['reason' => 'provider_timeout_reconciliation'],
                    'refund',
                    (int) $locked['id'],
                    'wallet-timeout-refund:' . ($locked['idempotency_key'] ?? $locked['reference']),
                    'provider_timeout'
                );

                $this->db->execute(
                    'UPDATE transactions
                     SET status = :status, processed_at = NOW(), failure_code = :failure_code, is_retryable = 0
                     WHERE id = :id',
                    [
                        'status' => 'failed',
                        'failure_code' => 'provider_timeout',
                        'id' => $locked['id'],
                    ]
                );
                $this->event((int) $locked['id'], 'transaction_refunded', 'system', null, 'Wallet refunded during timeout reconciliation.', ['amount' => (float) $locked['amount']]);
                $this->event((int) $locked['id'], 'transaction_reconciled_timeout', 'system', null, 'Transaction reconciled after timeout.');
                $this->db->commit();
                $timedOut++;
                $refunded++;
            } catch (Throwable $throwable) {
                $this->db->rollBack();
            }
        }

        return [
            'recovered_locks' => $recoveredLocks,
            'timed_out' => $timedOut,
            'refunded' => $refunded,
        ];
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
                'reference' => (string) $transaction['reference'],
                'idempotency_key' => (string) ($transaction['idempotency_key'] ?? ''),
                'transaction_id' => $transactionId,
                'service_id' => (int) $transaction['service_id'],
                '_assigned_provider_account_id' => (int) ($transaction['provider_account_id'] ?? 0),
            ]));
        } catch (Throwable $throwable) {
            $providerResponse = [
                'status' => 'failed',
                'provider_reference' => null,
                'raw' => ['message' => $throwable->getMessage()],
                'attempts' => [],
            ];
            if (!empty($transaction['provider_account_id'])) {
                $providerResponse['provider_account'] = [
                    'id' => (int) $transaction['provider_account_id'],
                    'code' => (string) ($transaction['provider_code'] ?? ''),
                ];
            }
        }

        $status = $this->normalizeProviderStatus((string) ($providerResponse['status'] ?? 'failed'));

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
            if ($status === 'successful') {
                // Resolve user type to determine commission eligibility
                $txUser = $this->db->first('SELECT user_type, tier, is_api_user FROM users WHERE id = :id LIMIT 1', ['id' => (int) $locked['user_id']]);
                $isReseller = in_array($txUser['user_type'] ?? '', ['reseller', 'api'], true)
                           || in_array($txUser['tier'] ?? '', ['RESELLER', 'API_RESELLER'], true)
                           || (int) ($txUser['is_api_user'] ?? 0) === 1;
                if ($isReseller) {
                    $rate = $this->commission->resolveRate((int) $locked['user_id'], (int) $locked['service_id']);
                    $commissionAmount = round(((float) $locked['amount'] * $rate) / 100, 2);
                    $this->commission->creditToWallet(
                        (int) $locked['user_id'],
                        $transactionId,
                        (int) $locked['service_id'],
                        $rate,
                        (float) $locked['amount'],
                        $commissionAmount,
                        $service['name'] ?? 'VTU Service'
                    );
                }
            }

            if ($status === 'failed') {
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
                     processing_started_at = :processing_started_at, processed_at = :processed_at, failure_code = :failure_code
                 WHERE id = :id',
                [
                    'provider_reference' => $providerResponse['provider_reference'] ?? null,
                    'provider_account_id' => $providerAccount['id'] ?? ($locked['provider_account_id'] ?? null),
                    'provider_code' => $providerAccount['code'] ?? ($locked['provider_code'] ?? null),
                    'status' => $status,
                    'commission_amount' => $commissionAmount,
                    'response_json' => json_encode($providerResponse),
                    'processing_started_at' => $status === 'pending' ? date('Y-m-d H:i:s') : null,
                    'processed_at' => $status === 'pending' ? null : date('Y-m-d H:i:s'),
                    'failure_code' => match ($status) {
                        'successful' => null,
                        'pending' => 'provider_pending',
                        default => 'provider_failed',
                    },
                    'id' => $transactionId,
                ]
            );

            $providerMessage = (string) ($providerResponse['raw']['message'] ?? '');
            $eventNote = "{$service['name']} transaction {$status}.";
            if ($status === 'failed' && $providerMessage !== '') {
                $eventNote = "{$service['name']} transaction failed: " . substr($providerMessage, 0, 180);
            }

            $this->event($transactionId, 'transaction_' . $status, ($locked['channel'] ?? 'web') === 'api' ? 'api' : 'system', (int) $locked['user_id'], $eventNote, [
                'provider_reference' => $providerResponse['provider_reference'] ?? null,
                'message' => $providerMessage !== '' ? $providerMessage : null,
            ]);
            $this->db->commit();

            $this->logger->log(($locked['channel'] ?? 'web') === 'api' ? 'api' : 'user', (int) $locked['user_id'], 'transaction_' . $status, "{$service['name']} transaction {$status}", ['reference' => $locked['reference']]);
            $this->notifications->create(
                (int) $locked['user_id'],
                $service['name'] . ' transaction',
                match ($status) {
                    'successful' => "Your {$service['name']} request for {$locked['recipient']} was successful.",
                    'pending' => "Your {$service['name']} request for {$locked['recipient']} is still awaiting provider confirmation.",
                    default => "Your {$service['name']} request for {$locked['recipient']} failed and your wallet was refunded.",
                },
                match ($status) {
                    'successful' => 'success',
                    'pending' => 'info',
                    default => 'error',
                }
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
                $refund = $this->wallet->refund(
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
                if ($this->db->tableExists('refund_logs')) {
                    $this->db->execute(
                        'INSERT IGNORE INTO refund_logs
                            (transaction_id, wallet_transaction_id, admin_id, user_id, amount, reason, status, idempotency_key)
                         VALUES
                            (:transaction_id, :wallet_transaction_id, :admin_id, :user_id, :amount, :reason, :status, :idempotency_key)',
                        [
                            'transaction_id' => $transactionId,
                            'wallet_transaction_id' => $refund['id'] ?? null,
                            'admin_id' => $adminId,
                            'user_id' => (int) $locked['user_id'],
                            'amount' => (float) $locked['amount'],
                            'reason' => $reason,
                            'status' => 'completed',
                            'idempotency_key' => 'wallet-override-refund:' . ($locked['idempotency_key'] ?? $locked['reference']),
                        ]
                    );
                }
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

    public function manualRefund(int $transactionId, string $reason, int $adminId): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A refund reason is required.');
        }

        $this->db->beginTransaction();
        try {
            $locked = $this->db->first('SELECT * FROM transactions WHERE id = :id FOR UPDATE', ['id' => $transactionId]);
            if (!$locked) {
                throw new RuntimeException('Transaction not found.');
            }

            $status = (string) ($locked['status'] ?? '');
            if (!in_array($status, ['pending', 'failed'], true)) {
                throw new RuntimeException('Manual refund is only available for failed or pending transactions.');
            }

            $refundEvent = $this->db->first(
                'SELECT id FROM transaction_events WHERE transaction_id = :transaction_id AND event_type = :event_type LIMIT 1',
                ['transaction_id' => $transactionId, 'event_type' => 'transaction_refunded']
            );
            if ($refundEvent) {
                throw new RuntimeException('This transaction has already been refunded.');
            }

            $refund = $this->wallet->refund(
                (int) $locked['user_id'],
                (float) $locked['amount'],
                'Manual admin refund for transaction ' . $locked['reference'],
                'admin',
                [
                    'admin_id' => $adminId,
                    'transaction_id' => $transactionId,
                    'reason' => $reason,
                ],
                'refund',
                $transactionId,
                'wallet-manual-refund:' . ($locked['idempotency_key'] ?? $locked['reference']),
                'manual_refund'
            );

            $this->db->execute(
                'UPDATE transactions
                 SET status = :status, override_status = 1, override_reason = :override_reason, overridden_by_admin_id = :admin_id, overridden_at = NOW(),
                     is_retryable = 0, processed_at = NOW(), failure_code = :failure_code
                 WHERE id = :id',
                [
                    'status' => 'refunded',
                    'override_reason' => $reason,
                    'admin_id' => $adminId,
                    'failure_code' => 'manual_refund',
                    'id' => $transactionId,
                ]
            );

            $this->event($transactionId, 'transaction_refunded', 'admin', $adminId, 'Admin issued manual wallet refund.', [
                'amount' => (float) $locked['amount'],
                'wallet_transaction_id' => $refund['id'] ?? null,
                'reason' => $reason,
            ]);
            if ($this->db->tableExists('refund_logs')) {
                $this->db->execute(
                    'INSERT IGNORE INTO refund_logs
                        (transaction_id, wallet_transaction_id, admin_id, user_id, amount, reason, status, idempotency_key)
                     VALUES
                        (:transaction_id, :wallet_transaction_id, :admin_id, :user_id, :amount, :reason, :status, :idempotency_key)',
                    [
                        'transaction_id' => $transactionId,
                        'wallet_transaction_id' => $refund['id'] ?? null,
                        'admin_id' => $adminId,
                        'user_id' => (int) $locked['user_id'],
                        'amount' => (float) $locked['amount'],
                        'reason' => $reason,
                        'status' => 'completed',
                        'idempotency_key' => 'wallet-manual-refund:' . ($locked['idempotency_key'] ?? $locked['reference']),
                    ]
                );
            }
            $this->event($transactionId, 'transaction_manual_refund', 'admin', $adminId, 'Manual refund completed from transaction command center.', [
                'reason' => $reason,
            ]);
            $this->logger->log('admin', $adminId, 'transaction_manual_refund', 'Admin manually refunded transaction.', [
                'transaction_id' => $transactionId,
                'amount' => (float) $locked['amount'],
            ]);

            $this->db->commit();
        } catch (Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }
    }

    public function forceSuccess(int $transactionId, string $reason, int $adminId): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A force-success reason is required.');
        }

        $this->db->beginTransaction();
        try {
            $locked = $this->db->first('SELECT * FROM transactions WHERE id = :id FOR UPDATE', ['id' => $transactionId]);
            if (!$locked) {
                throw new RuntimeException('Transaction not found.');
            }

            $status = (string) ($locked['status'] ?? '');
            if (!in_array($status, ['pending', 'failed'], true)) {
                throw new RuntimeException('Force success is only available for pending or failed transactions.');
            }

            $refundEvent = $this->db->first(
                'SELECT id FROM transaction_events WHERE transaction_id = :transaction_id AND event_type = :event_type LIMIT 1',
                ['transaction_id' => $transactionId, 'event_type' => 'transaction_refunded']
            );
            if ($refundEvent) {
                throw new RuntimeException('Refunded transactions cannot be force-marked successful.');
            }

            $this->db->execute(
                'UPDATE transactions
                 SET status = :status, override_status = 1, override_reason = :override_reason, overridden_by_admin_id = :admin_id, overridden_at = NOW(),
                     is_retryable = 0, processed_at = NOW(), failure_code = NULL
                 WHERE id = :id',
                [
                    'status' => 'successful',
                    'override_reason' => $reason,
                    'admin_id' => $adminId,
                    'id' => $transactionId,
                ]
            );

            $this->event($transactionId, 'transaction_force_success', 'admin', $adminId, 'Super Admin force-marked transaction successful. Wallet was not mutated.', [
                'reason' => $reason,
                'previous_status' => $status,
            ]);
            $this->event($transactionId, 'transaction_successful', 'admin', $adminId, 'Transaction marked successful by Super Admin override.', [
                'reason' => $reason,
                'wallet_mutation' => false,
            ]);
            $this->logger->log('admin', $adminId, 'transaction_force_success', 'Super Admin force-marked transaction successful.', [
                'transaction_id' => $transactionId,
                'previous_status' => $status,
            ]);

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

    private function recordProviderSelectionFailure(
        array $service,
        string $serviceSlug,
        int $userId,
        array $payload,
        string $channel,
        float $amount,
        array $pricing,
        string $reference,
        string $idempotencyKey,
        string $recipient,
        array $providerSelection
    ): array {
        $intendedProvider = $this->intendedProviderFromSelection($providerSelection);
        $diagnostics = $providerSelection['diagnostics'] ?? [];
        if (is_array($intendedProvider)) {
            $diagnostics['intended_provider_id'] = (int) $intendedProvider['id'];
            $diagnostics['intended_provider_code'] = (string) $intendedProvider['code'];
        }
        $diagnostics['wallet_mutation'] = false;
        $response = [
            'status' => 'failed',
            'provider_reference' => null,
            'raw' => [
                'message' => (string) ($providerSelection['message'] ?? 'No eligible provider found for ' . $serviceSlug . '.'),
                'routing' => $diagnostics,
            ],
            'attempts' => [],
        ];

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'INSERT INTO transactions (
                    user_id, service_id, reference, idempotency_key, channel, status, amount, selling_price, cost_price, profit_amount,
                    pricing_source, is_retryable, recipient, customer_name, payload_json, response_json, provider_account_id, provider_code,
                    processing_started_at, processed_at, failure_code
                 ) VALUES (
                    :user_id, :service_id, :reference, :idempotency_key, :channel, :status, :amount, :selling_price, :cost_price, :profit_amount,
                    :pricing_source, :is_retryable, :recipient, :customer_name, :payload_json, :response_json, :provider_account_id, :provider_code,
                    NULL, NOW(), :failure_code
                 )',
                [
                    'user_id' => $userId,
                    'service_id' => (int) $service['id'],
                    'reference' => $reference,
                    'idempotency_key' => $idempotencyKey,
                    'channel' => $channel,
                    'status' => 'failed',
                    'amount' => $amount,
                    'selling_price' => $pricing['selling_price'],
                    'cost_price' => $pricing['cost_price'],
                    'profit_amount' => $pricing['profit_amount'],
                    'pricing_source' => $pricing['pricing_source'],
                    'is_retryable' => 0,
                    'recipient' => $recipient,
                    'customer_name' => $payload['customer_name'] ?? null,
                    'payload_json' => json_encode($payload),
                    'response_json' => json_encode($response),
                    'provider_account_id' => is_array($intendedProvider) ? (int) $intendedProvider['id'] : null,
                    'provider_code' => is_array($intendedProvider) ? (string) $intendedProvider['code'] : null,
                    'failure_code' => 'provider_selection_failed',
                ]
            );
            $transactionId = $this->db->lastInsertId();
            $this->event($transactionId, 'transaction_provider_selection_failed', 'system', null, 'No eligible provider was available before wallet debit.', [
                'message' => $response['raw']['message'],
                'provider_account_id' => is_array($intendedProvider) ? (int) $intendedProvider['id'] : null,
                'provider_code' => is_array($intendedProvider) ? (string) $intendedProvider['code'] : null,
                'routing' => $diagnostics,
                'wallet_mutation' => false,
            ]);
            $this->db->commit();
        } catch (Throwable $throwable) {
            $this->db->rollBack();
            throw $throwable;
        }

        $row = $this->db->first(
            'SELECT t.*, s.name AS service_name
             FROM transactions t
             INNER JOIN services s ON s.id = t.service_id
             WHERE t.id = :id
             LIMIT 1',
            ['id' => $transactionId]
        );
        if (!$row) {
            throw new RuntimeException('Transaction could not be recorded.');
        }

        $this->logger->log($channel === 'api' ? 'api' : 'user', $userId, 'transaction_provider_selection_failed', "{$service['name']} transaction could not be routed.", ['reference' => $reference]);

        return $this->responsePayload($row);
    }

    private function intendedProviderFromSelection(array $providerSelection): ?array
    {
        $diagnostics = $providerSelection['diagnostics'] ?? [];
        $providerId = (int) ($diagnostics['intended_provider_id'] ?? 0);
        if ($providerId <= 0) {
            $providerId = (int) ($providerSelection['setting']['manual_provider_account_id'] ?? 0);
        }
        if ($providerId <= 0) {
            return null;
        }

        $provider = $this->providers->getById($providerId);
        if (!$provider || (string) ($provider['status'] ?? '') === 'archived') {
            return null;
        }

        return $provider;
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

    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '234') && strlen($normalized) === 13) {
            $normalized = '0' . substr($normalized, 3);
        }

        return strlen($normalized) >= 10 && strlen($normalized) <= 15 ? $normalized : '';
    }

    private function normalizeProviderStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'successful', 'success', 'completed' => 'successful',
            'pending', 'processing', 'queued', 'timeout' => 'pending',
            default => 'failed',
        };
    }

    private function markTransactionSuccessfulFromReconciliation(array $transaction, array $providerResponse): void
    {
        $service = $this->db->first('SELECT slug, name FROM services WHERE id = :id LIMIT 1', ['id' => $transaction['service_id']]);
        if (!$service) {
            return;
        }

        $this->db->beginTransaction();
        try {
            $locked = $this->db->first('SELECT * FROM transactions WHERE id = :id FOR UPDATE', ['id' => $transaction['id']]);
            if (!$locked || ($locked['status'] ?? '') !== 'pending') {
                $this->db->commit();
                return;
            }

            $commissionAmount = (float) ($locked['commission_amount'] ?? 0);
            if ($commissionAmount <= 0) {
                $reconUser = $this->db->first('SELECT user_type, tier, is_api_user FROM users WHERE id = :id LIMIT 1', ['id' => (int) $locked['user_id']]);
                $isReseller = in_array($reconUser['user_type'] ?? '', ['reseller', 'api'], true)
                           || in_array($reconUser['tier'] ?? '', ['RESELLER', 'API_RESELLER'], true)
                           || (int) ($reconUser['is_api_user'] ?? 0) === 1;
                if ($isReseller) {
                    $rate = $this->commission->resolveRate((int) $locked['user_id'], (int) $locked['service_id']);
                    $commissionAmount = round(((float) $locked['amount'] * $rate) / 100, 2);
                    $this->commission->creditToWallet(
                        (int) $locked['user_id'],
                        (int) $locked['id'],
                        (int) $locked['service_id'],
                        $rate,
                        (float) $locked['amount'],
                        $commissionAmount,
                        $service['name'] ?? 'VTU Service'
                    );
                }
            }

            $providerAccount = $providerResponse['provider_account'] ?? null;
            $this->db->execute(
                'UPDATE transactions
                 SET status = :status,
                     provider_reference = :provider_reference,
                     provider_account_id = :provider_account_id,
                     provider_code = :provider_code,
                     commission_amount = :commission_amount,
                     response_json = :response_json,
                     processing_started_at = NULL,
                     processed_at = NOW(),
                     failure_code = NULL
                 WHERE id = :id',
                [
                    'status' => 'successful',
                    'provider_reference' => $providerResponse['provider_reference'] ?? $locked['reference'],
                    'provider_account_id' => $providerAccount['id'] ?? ($locked['provider_account_id'] ?? null),
                    'provider_code' => $providerAccount['code'] ?? ($locked['provider_code'] ?? null),
                    'commission_amount' => $commissionAmount,
                    'response_json' => json_encode($providerResponse),
                    'id' => $locked['id'],
                ]
            );
            $this->event((int) $locked['id'], 'transaction_successful', 'system', null, 'Transaction marked successful during provider reconciliation.', [
                'provider_reference' => $providerResponse['provider_reference'] ?? null,
            ]);
            $this->db->commit();

            $this->notifications->create(
                (int) $locked['user_id'],
                $service['name'] . ' transaction',
                "Your {$service['name']} request for {$locked['recipient']} was confirmed successful after provider verification.",
                'success'
            );
        } catch (Throwable $throwable) {
            $this->db->rollBack();
        }
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
