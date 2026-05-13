<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

/**
 * ZenithPay Webhook Service
 *
 * ZenithPay sends POST notifications to your Webhook URL when a transfer
 * is received into any virtual account. Security is IP-based:
 * only requests from ZenithPay's listed public IPs are accepted.
 *
 * Set in your ZenithPay Dashboard → Settings → Webhook URL:
 *   https://gemdata.com.ng/api/zenithpay-webhook.php
 */
class ZenithPayWebhookService
{
    // ZenithPay public IPs (from Dashboard → Settings → Zenithpay Public IP).
    // Add/update these from your dashboard — they may expand over time.
    private const ZENITHPAY_IPS = [
        // Populate from your ZenithPay dashboard → "Zenithpay Public IP" list
        // Example format: '41.58.x.x', '102.89.x.x'
    ];

    public function __construct(
        private Database          $db,
        private PaymentGatewayService $payments,
        private ActivityLogger    $activity,
        private AppLogger         $logger
    ) {
    }

    /**
     * Main entry point — call from api/zenithpay-webhook.php
     */
    public function handle(string $payload, string $remoteIp): array
    {
        // ── 1. IP Allowlist ──────────────────────────────────────────────────
        $configIps = (array) config('payments.zenithpay_allowed_ips', []);
        $allowedIps = array_filter(array_merge(self::ZENITHPAY_IPS, $configIps));

        if (!empty($allowedIps) && !in_array($remoteIp, $allowedIps, true)) {
            $this->logger->warning('ZenithPay webhook blocked — IP not in allowlist.', [
                'remote_ip'   => $remoteIp,
                'allowed_ips' => $allowedIps,
            ]);
            throw new RuntimeException('Webhook request from unauthorised IP: ' . $remoteIp);
        }

        // ── 2. Parse payload ─────────────────────────────────────────────────
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('ZenithPay webhook: invalid JSON payload.');
        }

        // ZenithPay may wrap in event+data or send flat
        $event     = (string) ($decoded['event'] ?? $decoded['type'] ?? 'transfer');
        $data      = is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
        $eventKey  = (string) ($data['reference'] ?? $data['accountReference'] ?? $data['transactionRef'] ?? sha1($payload));

        // ── 3. Deduplicate ───────────────────────────────────────────────────
        $existing = $this->db->first(
            'SELECT * FROM webhook_events WHERE source = :source AND event_key = :event_key LIMIT 1',
            ['source' => 'zenithpay', 'event_key' => $eventKey]
        );
        if ($existing) {
            $this->db->execute(
                'UPDATE webhook_events SET processing_status = :s, processed_at = NOW() WHERE id = :id',
                ['s' => 'duplicate', 'id' => $existing['id']]
            );
            return ['duplicate' => true, 'event' => $event, 'event_key' => $eventKey];
        }

        // ── 4. Store webhook event ───────────────────────────────────────────
        $this->db->execute(
            'INSERT INTO webhook_events (source, event_key, signature, payload_json, processing_status)
             VALUES (:source, :event_key, :signature, :payload_json, :processing_status)',
            [
                'source'             => 'zenithpay',
                'event_key'          => $eventKey,
                'signature'          => '[IP:' . $remoteIp . ']',
                'payload_json'       => $payload,
                'processing_status'  => 'pending',
            ]
        );
        $webhookId = $this->db->lastInsertId();

        // ── 5. Reconcile funding ─────────────────────────────────────────────
        try {
            // Extract amount (ZenithPay sends in Naira, not kobo — unlike Paystack)
            $rawAmount = (float) ($data['amount'] ?? $data['transactionAmount'] ?? 0);

            // Detect if amount is in kobo (> 100k) or Naira — ZenithPay typically sends Naira
            $amount = $rawAmount > 100000 ? round($rawAmount / 100, 2) : $rawAmount;

            $result = $this->payments->reconcileIncomingFunding([
                'event'              => $event,
                'event_key'          => $eventKey,
                'reference'          => $eventKey,
                'provider_reference' => $eventKey,
                'amount'             => $amount,
                'currency'           => (string) ($data['currency'] ?? 'NGN'),
                'paid_at'            => (string) ($data['createdOn'] ?? $data['transactionDate'] ?? date('Y-m-d H:i:s')),
                'customer_email'     => strtolower(trim((string) (
                    $data['customerEmail'] ?? $data['email'] ?? $data['customer']['email'] ?? ''
                ))),
                'account_number'     => (string) (
                    $data['accountNumber'] ?? $data['account_number'] ?? $data['virtualAccount'] ?? ''
                ),
                'meta_json'          => json_encode($decoded),
            ]);

            $this->db->execute(
                'UPDATE webhook_events
                 SET processing_status = :s, linked_transaction_id = :tx, processed_at = NOW()
                 WHERE id = :id',
                [
                    's'  => !empty($result['credited']) ? 'processed' : 'failed',
                    'tx' => $result['linked_transaction_id'] ?? null,
                    'id' => $webhookId,
                ]
            );

            $this->activity->log('system', 0, 'zenithpay_webhook_processed', 'ZenithPay webhook processed.', [
                'event'    => $event,
                'key'      => $eventKey,
                'credited' => !empty($result['credited']),
                'ip'       => $remoteIp,
            ]);

            return $result + ['duplicate' => false, 'event' => $event, 'event_key' => $eventKey];

        } catch (\Throwable $throwable) {
            $this->db->execute(
                'UPDATE webhook_events SET processing_status = :s, processed_at = NOW() WHERE id = :id',
                ['s' => 'failed', 'id' => $webhookId]
            );
            $this->logger->warning('ZenithPay webhook processing failed.', [
                'event' => $event,
                'key'   => $eventKey,
                'error' => $throwable->getMessage(),
                'ip'    => $remoteIp,
            ]);
            throw $throwable;
        }
    }
}
