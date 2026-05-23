<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class XixaPay
{
    private const PROVIDER = 'xixapay';

    public function __construct(
        private Database $db,
        private ActivityLogger $logger,
        private NotificationService $notifications
    ) {
    }

    public function isConfigured(): bool
    {
        return trim((string) config('payments.xixapay_api_key', '')) !== ''
            && trim((string) config('payments.xixapay_api_secret', '')) !== ''
            && trim((string) config('payments.xixapay_business_id', '')) !== '';
    }

    public function getForUser(int $userId): ?array
    {
        return $this->db->first(
            'SELECT * FROM user_funding_accounts WHERE user_id = :user_id AND provider = :provider LIMIT 1',
            ['user_id' => $userId, 'provider' => self::PROVIDER]
        );
    }

    public function ensureStaticAccountForUser(int $userId, string $idType, string $idNumber, bool $forceRetry = false): array
    {
        $user = $this->db->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$user) {
            throw new RuntimeException('User was not found for XixaPay account generation.');
        }

        $idType = strtolower(trim($idType));
        $idNumber = trim($idNumber);
        if (!in_array($idType, ['bvn', 'nin'], true)) {
            throw new RuntimeException('Select a valid ID type: BVN or NIN.');
        }
        if ($idNumber === '' || !ctype_digit($idNumber) || strlen($idNumber) < 10 || strlen($idNumber) > 11) {
            throw new RuntimeException('Enter a valid BVN or NIN number.');
        }

        $existing = $this->getForUser($userId);
        if ($existing && ($existing['status'] ?? '') === 'assigned' && !$forceRetry) {
            return $existing;
        }

        if (!$this->isConfigured()) {
            return $this->upsertAccountRow($userId, [
                'status' => 'failed',
                'last_error_message' => 'XixaPay is not configured yet.',
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($forceRetry) {
            $this->logger->log('user', $userId, 'xixapay_account_retry_requested', 'User requested XixaPay account retry.');
        }

        try {
            $response = $this->request([
                'email' => (string) $user['email'],
                'name' => (string) $user['full_name'],
                'phoneNumber' => (string) $user['phone'],
                'bankCode' => $this->bankCodes(),
                'businessId' => trim((string) config('payments.xixapay_business_id', '')),
                'accountType' => 'static',
                'id_type' => $idType,
                'id_number' => $idNumber,
            ]);
        } catch (RuntimeException $exception) {
            $failed = $this->upsertAccountRow($userId, [
                'status' => 'failed',
                'last_error_message' => $exception->getMessage(),
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logger->log('user', $userId, 'xixapay_account_failed', $exception->getMessage());
            return $failed;
        }

        $account = $this->firstBankAccount($response);
        if ($this->isSuccessful($response) && $account !== null && !empty($account['accountNumber'])) {
            $stored = $this->upsertAccountRow($userId, [
                'account_reference' => $account['Reserved_Account_Id'] ?? null,
                'dedicated_account_number' => $account['accountNumber'] ?? null,
                'account_name' => $account['accountName'] ?? null,
                'bank_name' => $account['bankName'] ?? null,
                'bank_slug' => $this->slug((string) ($account['bankName'] ?? '')),
                'status' => 'assigned',
                'last_error_message' => null,
                'requested_at' => date('Y-m-d H:i:s'),
                'assigned_at' => date('Y-m-d H:i:s'),
                'meta_json' => json_encode($response),
            ]);

            $this->logger->log('user', $userId, 'xixapay_account_assigned', 'XixaPay virtual account assigned.', [
                'account_number' => $stored['dedicated_account_number'] ?? null,
                'bank_name' => $stored['bank_name'] ?? null,
            ]);

            if (($existing['status'] ?? '') !== 'assigned') {
                $this->notifications->create(
                    $userId,
                    'XixaPay transfer account ready',
                    'Your XixaPay virtual account is ready for wallet funding.',
                    'success'
                );
            }

            return $stored;
        }

        $message = (string) ($response['message'] ?? 'XixaPay account generation failed.');
        $this->logger->log('user', $userId, 'xixapay_account_failed', $message, ['response' => $response]);

        return $this->upsertAccountRow($userId, [
            'status' => 'failed',
            'last_error_message' => $message,
            'requested_at' => date('Y-m-d H:i:s'),
            'meta_json' => json_encode($response),
        ]);
    }

    private function upsertAccountRow(int $userId, array $fields): array
    {
        $allowed = [
            'provider',
            'account_reference',
            'dedicated_account_id',
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

        $payload = array_intersect_key($fields, array_flip($allowed));
        $payload['user_id'] = $userId;
        $payload['provider'] = self::PROVIDER;

        $columns = array_keys($payload);
        $insertSql = implode(', ', $columns);
        $insertVals = implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns));
        $updates = implode(', ', array_map(
            static fn(string $column): string => $column . ' = VALUES(' . $column . ')',
            array_filter($columns, static fn(string $column): bool => $column !== 'user_id')
        ));

        $this->db->execute(
            "INSERT INTO user_funding_accounts ({$insertSql}) VALUES ({$insertVals}) ON DUPLICATE KEY UPDATE {$updates}",
            $payload
        );

        return $this->getForUser($userId) ?? [];
    }

    private function request(array $payload): array
    {
        $baseUrl = rtrim((string) config('payments.xixapay_base_url', 'https://api.xixapay.com'), '/');
        $apiKey = trim((string) config('payments.xixapay_api_key', ''));
        $apiSecret = trim((string) config('payments.xixapay_api_secret', ''));

        if ($apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('XixaPay API credentials are not configured.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for XixaPay integration.');
        }

        $handle = curl_init($baseUrl . '/api/v1/createVirtualAccount');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiSecret,
                'Content-Type: application/json',
                'Accept: application/json',
                'api-key: ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($handle);
        $curlError = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException('XixaPay request failed: ' . $curlError);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('XixaPay returned an invalid response: ' . substr($body, 0, 200));
        }

        if ($statusCode >= 400) {
            throw new RuntimeException((string) ($decoded['message'] ?? 'XixaPay request failed.'));
        }

        $decoded['_http_status'] = $statusCode;
        return $decoded;
    }

    private function bankCodes(): array
    {
        $codes = (array) config('payments.xixapay_bank_codes', ['20867']);
        $normalized = array_values(array_filter(array_map(
            static fn($code): string => trim((string) $code),
            $codes
        )));

        return $normalized !== [] ? $normalized : ['20867'];
    }

    private function firstBankAccount(array $response): ?array
    {
        $accounts = $response['bankAccounts'] ?? [];
        if (!is_array($accounts)) {
            return null;
        }

        foreach ($accounts as $account) {
            if (is_array($account)) {
                return $account;
            }
        }

        return null;
    }

    private function isSuccessful(array $response): bool
    {
        return strtolower((string) ($response['status'] ?? '')) === 'success'
            || (bool) ($response['success'] ?? false) === true;
    }

    private function slug(string $value): ?string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));
        return $slug !== '' ? $slug : null;
    }
}
