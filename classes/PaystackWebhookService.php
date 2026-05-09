<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class PaystackWebhookService
{
    public function __construct(
        private Database $db,
        private PaymentGatewayService $payments,
        private ActivityLogger $activity,
        private AppLogger $logger
    ) {
    }

    public function handle(string $payload, string $signature): array
    {
        $secret = trim((string) config('payments.paystack_secret_key', ''));
        if ($secret === '') {
            throw new RuntimeException('Paystack webhook handling is not configured.');
        }

        $expected = hash_hmac('sha512', $payload, $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid Paystack signature.');
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid webhook payload.');
        }

        $event = (string) ($decoded['event'] ?? 'unknown');
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $eventKey = (string) ($data['id'] ?? $data['reference'] ?? sha1($payload));

        $existing = $this->db->first(
            'SELECT * FROM webhook_events WHERE source = :source AND event_key = :event_key LIMIT 1',
            ['source' => 'paystack', 'event_key' => $eventKey]
        );
        if ($existing) {
            $this->db->execute(
                'UPDATE webhook_events SET processing_status = :processing_status, processed_at = NOW() WHERE id = :id',
                ['processing_status' => 'duplicate', 'id' => $existing['id']]
            );

            return ['duplicate' => true, 'event' => $event, 'event_key' => $eventKey];
        }

        $this->db->execute(
            'INSERT INTO webhook_events (source, event_key, signature, payload_json, processing_status)
             VALUES (:source, :event_key, :signature, :payload_json, :processing_status)',
            [
                'source' => 'paystack',
                'event_key' => $eventKey,
                'signature' => '[REDACTED]',
                'payload_json' => $payload,
                'processing_status' => 'pending',
            ]
        );
        $webhookId = $this->db->lastInsertId();

        try {
            $result = $this->payments->reconcileIncomingFunding([
                'event' => $event,
                'event_key' => $eventKey,
                'reference' => (string) ($data['reference'] ?? ''),
                'provider_reference' => (string) ($data['reference'] ?? $eventKey),
                'amount' => isset($data['amount']) ? round(((float) $data['amount']) / 100, 2) : 0.0,
                'currency' => (string) ($data['currency'] ?? 'NGN'),
                'paid_at' => (string) ($data['paid_at'] ?? date('Y-m-d H:i:s')),
                'customer_email' => strtolower(trim((string) (($data['customer']['email'] ?? '') ?: ($data['customer_email'] ?? '')))),
                'account_number' => (string) (($data['authorization']['receiver_bank_account_number'] ?? '') ?: ($data['dedicated_account']['account_number'] ?? '') ?: ($data['metadata']['dedicated_account_number'] ?? '')),
                'meta_json' => json_encode($decoded),
            ]);

            $this->db->execute(
                'UPDATE webhook_events
                 SET processing_status = :processing_status, linked_transaction_id = :linked_transaction_id, processed_at = NOW()
                 WHERE id = :id',
                [
                    'processing_status' => !empty($result['credited']) ? 'processed' : 'failed',
                    'linked_transaction_id' => $result['linked_transaction_id'] ?? null,
                    'id' => $webhookId,
                ]
            );

            $this->activity->log('system', 0, 'paystack_webhook_processed', 'Processed Paystack webhook event.', [
                'event' => $event,
                'event_key' => $eventKey,
                'credited' => !empty($result['credited']),
            ]);

            return $result + ['duplicate' => false, 'event' => $event, 'event_key' => $eventKey];
        } catch (\Throwable $throwable) {
            $this->db->execute(
                'UPDATE webhook_events SET processing_status = :processing_status, processed_at = NOW() WHERE id = :id',
                ['processing_status' => 'failed', 'id' => $webhookId]
            );
            $this->logger->warning('Paystack webhook processing failed.', [
                'event' => $event,
                'event_key' => $eventKey,
                'error' => $throwable->getMessage(),
            ]);
            throw $throwable;
        }
    }
}
