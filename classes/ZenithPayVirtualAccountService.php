<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

/**
 * ZenithPay Virtual Account Service
 *
 * API: POST https://zenithpay.ng/api/dedicated_account/assign
 * Auth: Bearer token (form-urlencoded body — NOT JSON)
 * Required fields: bvn, account_name, first_name, last_name, email
 *
 * Response example:
 * {
 *   "status": true,
 *   "response_code": "00",
 *   "accountReference": "ZTSVA-01JXBCKMGSDD6FXDNTNB",
 *   "accountName": "GemData User",
 *   "customerEmail": "user@email.com",
 *   "bankName": "PALMPAY",
 *   "accountNumber": "1234567890",
 *   "accountStatus": "Enabled"
 * }
 */
class ZenithPayVirtualAccountService
{
    private const PROVIDER = 'zenithpay';

    public function __construct(
        private Database $db,
        private ActivityLogger $logger,
        private NotificationService $notifications
    ) {
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        $secretKey = trim((string) config('payments.zenithpay_secret_key', ''));
        return $secretKey !== '' && !$this->isPlaceholderSecret($secretKey);
    }

    public function shouldAutoAssign(): bool
    {
        return (bool) config('payments.zenithpay_auto_assign', false) && $this->isConfigured();
    }

    public function getForUser(int $userId): ?array
    {
        return $this->db->first(
            'SELECT * FROM user_funding_accounts WHERE user_id = :user_id AND provider = :provider LIMIT 1',
            ['user_id' => $userId, 'provider' => self::PROVIDER]
        );
    }

    /**
     * Assign or retrieve a ZenithPay virtual account for a user.
     * BVN is required by ZenithPay to create the account.
     */
    public function ensureForUser(int $userId, string $bvn, bool $forceRetry = false): array
    {
        $user = $this->db->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        $bvn = trim($bvn);
        if ($bvn === '' || strlen($bvn) !== 11 || !ctype_digit($bvn)) {
            throw new RuntimeException('A valid 11-digit BVN is required to create a ZenithPay account.');
        }

        // Return existing assigned account unless forcing retry
        $existing = $this->getForUser($userId);
        if ($existing && $existing['status'] === 'assigned' && !$forceRetry) {
            return $existing;
        }

        if (!$this->isConfigured()) {
            return $this->upsertAccountRow($userId, [
                'status'             => 'failed',
                'last_error_message' => 'ZenithPay is not configured. Set ZENITHPAY_SECRET_KEY so payments.zenithpay_secret_key resolves.',
                'requested_at'       => date('Y-m-d H:i:s'),
            ]);
        }

        if ($forceRetry) {
            $this->logger->log('user', $userId, 'zenithpay_account_retry_requested', 'User requested ZenithPay account retry.');
        }

        $name      = $this->splitName((string) $user['full_name']);
        $accountName = $name['first_name'] . ' ' . $name['last_name'];

        try {
            $response = $this->request([
                'bvn'          => $bvn,
                'account_name' => $accountName,
                'first_name'   => $name['first_name'],
                'last_name'    => $name['last_name'],
                'email'        => (string) $user['email'],
            ]);
        } catch (RuntimeException $e) {
            $failed = $this->upsertAccountRow($userId, [
                'bvn'                => $bvn,
                'status'             => 'failed',
                'last_error_message' => $e->getMessage(),
                'requested_at'       => date('Y-m-d H:i:s'),
            ]);

            $this->logger->log('user', $userId, 'zenithpay_account_failed', $e->getMessage());
            return $failed;
        }

        // Parse response
        $success       = (bool) ($response['status'] ?? false);
        $responseCode  = (string) ($response['response_code'] ?? '');
        $accountNumber = (string) ($response['accountNumber'] ?? '');
        $bankName      = (string) ($response['bankName'] ?? '');
        $accountRef    = (string) ($response['accountReference'] ?? '');
        $storedName    = (string) ($response['accountName'] ?? $accountName);

        if ($success && $responseCode === '00' && $accountNumber !== '') {
            $stored = $this->upsertAccountRow($userId, [
                'bvn'                      => $bvn,
                'account_reference'        => $accountRef,
                'dedicated_account_number' => $accountNumber,
                'account_name'             => $storedName,
                'bank_name'                => $bankName,
                'bank_slug'                => strtolower(str_replace(' ', '-', $bankName)),
                'status'                   => 'assigned',
                'last_error_message'       => null,
                'requested_at'             => date('Y-m-d H:i:s'),
                'assigned_at'              => date('Y-m-d H:i:s'),
                'meta_json'                => json_encode($response),
            ]);

            $this->logger->log('user', $userId, 'zenithpay_account_assigned', 'ZenithPay virtual account assigned.', [
                'account_number' => $accountNumber,
                'bank_name'      => $bankName,
            ]);

            $previous = $existing;
            if (($previous['status'] ?? '') !== 'assigned') {
                $this->notifications->create(
                    $userId,
                    'ZenithPay transfer account ready',
                    "Your ZenithPay virtual account ({$bankName}: {$accountNumber}) is ready for wallet funding.",
                    'success'
                );
            }

            return $stored;
        }

        // Non-success response
        $errorMsg = (string) ($response['message'] ?? 'ZenithPay account assignment failed.');
        $pending  = $this->upsertAccountRow($userId, [
            'bvn'                => $bvn,
            'account_reference'  => $accountRef ?: null,
            'status'             => 'failed',
            'last_error_message' => $errorMsg,
            'requested_at'       => date('Y-m-d H:i:s'),
            'meta_json'          => json_encode($response),
        ]);

        $this->logger->log('user', $userId, 'zenithpay_account_failed', $errorMsg, ['response' => $response]);
        return $pending;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function upsertAccountRow(int $userId, array $fields): array
    {
        $allowed = [
            'provider',
            'bvn',
            'account_reference',
            'dedicated_account_number',
            'account_name',
            'bank_name',
            'bank_slug',
            'status',
            'last_error_message',
            'requested_at',
            'assigned_at',
            'meta_json',
        ];

        $payload             = array_intersect_key($fields, array_flip($allowed));
        $payload['user_id']  = $userId;
        $payload['provider'] = self::PROVIDER;

        $columns     = array_keys($payload);
        $insertSql   = implode(', ', $columns);
        $insertVals  = implode(', ', array_map(static fn(string $c): string => ':' . $c, $columns));
        $updates     = implode(', ', array_map(
            static fn(string $c): string => $c . ' = VALUES(' . $c . ')',
            array_filter($columns, static fn(string $c): bool => $c !== 'user_id')
        ));

        $this->db->execute(
            "INSERT INTO user_funding_accounts ({$insertSql}) VALUES ({$insertVals})
             ON DUPLICATE KEY UPDATE {$updates}",
            $payload
        );

        return $this->getForUser($userId) ?? [];
    }

    private function request(array $formData): array
    {
        $baseUrl   = rtrim((string) config('payments.zenithpay_base_url', 'https://zenithpay.ng'), '/');
        $secretKey = trim((string) config('payments.zenithpay_secret_key', ''));

        if ($secretKey === '' || $this->isPlaceholderSecret($secretKey)) {
            throw new RuntimeException('ZenithPay secret key is not configured. Set ZENITHPAY_SECRET_KEY to your live ZenithPay token.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for ZenithPay integration.');
        }

        $url     = $baseUrl . '/api/dedicated_account/assign';
        $handle  = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            // ZenithPay uses form-urlencoded (NOT JSON)
            CURLOPT_POSTFIELDS     => http_build_query($formData),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $body       = curl_exec($handle);
        $curlError  = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException('ZenithPay request failed: ' . $curlError);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('ZenithPay returned an invalid response: ' . substr($body, 0, 200));
        }

        if ($statusCode >= 400) {
            $message = (string) ($decoded['message'] ?? 'ZenithPay request failed (HTTP ' . $statusCode . ').');
            throw new RuntimeException($message);
        }

        return $decoded;
    }

    private function splitName(string $fullName): array
    {
        $parts     = preg_split('/\s+/', trim($fullName)) ?: [];
        $firstName = $parts[0] ?? 'GemData';
        $lastName  = count($parts) > 1 ? trim(implode(' ', array_slice($parts, 1))) : 'User';

        return ['first_name' => $firstName, 'last_name' => $lastName];
    }

    private function isPlaceholderSecret(string $secretKey): bool
    {
        $normalized = strtolower(trim($secretKey));
        if ($normalized === '') {
            return true;
        }

        foreach ([
            'your-zenithpay-live-token',
            'replace_with_your_zenithpay_live_token',
            'replace-with-your-zenithpay-live-token',
            'replace_with_your_zenithpay_token',
            'replace-with-your-zenithpay-token',
            'changeme',
        ] as $placeholder) {
            if ($normalized === $placeholder) {
                return true;
            }
        }

        return str_contains($normalized, 'replace_with') || str_contains($normalized, 'your-zenithpay');
    }
}
