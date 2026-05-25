<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class KatPayVirtualAccountService
{
    private const PROVIDER = 'katpay';

    public function __construct(
        private Database $db,
        private ActivityLogger $logger,
        private NotificationService $notifications
    ) {
    }

    public function isConfigured(): bool
    {
        return (bool) config('payments.katpay_enabled', false)
            && trim((string) config('payments.katpay_api_key', '')) !== ''
            && trim((string) config('payments.katpay_secret_key', '')) !== ''
            && trim((string) config('payments.katpay_merchant_id', '')) !== ''
            && $this->bankCodes() !== [];
    }

    public function getForUser(int $userId): ?array
    {
        return $this->db->first(
            'SELECT * FROM user_funding_accounts WHERE user_id = :user_id AND provider = :provider LIMIT 1',
            ['user_id' => $userId, 'provider' => self::PROVIDER]
        );
    }

    public function ensureAccountForUser(int $userId, bool $forceRetry = false, ?int $adminId = null): array
    {
        $this->assertCompatibleSchema();

        $user = $this->db->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$user) {
            throw new RuntimeException('User was not found for KatPay account generation.');
        }

        $existing = $this->getForUser($userId);
        if ($existing && ($existing['status'] ?? '') === 'assigned' && !$forceRetry) {
            return $existing;
        }

        if (!$this->isConfigured()) {
            $failed = $this->upsertAccountRow($userId, [
                'status' => 'failed',
                'last_error_message' => 'KatPay funding account is not configured yet.',
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logFailure($userId, 'KatPay funding account is not configured yet.', $adminId);
            return $failed;
        }

        if (!function_exists('curl_init')) {
            $failed = $this->upsertAccountRow($userId, [
                'status' => 'failed',
                'last_error_message' => 'PHP cURL extension is required for KatPay integration.',
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logFailure($userId, 'PHP cURL extension is required for KatPay integration.', $adminId);
            return $failed;
        }

        $this->upsertAccountRow($userId, [
            'status' => 'pending',
            'last_error_message' => null,
            'requested_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $response = $this->request($this->payloadForUser($user));
        } catch (RuntimeException $exception) {
            $failed = $this->upsertAccountRow($userId, [
                'status' => 'failed',
                'last_error_message' => $exception->getMessage(),
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logFailure($userId, $exception->getMessage(), $adminId);
            return $failed;
        }

        $account = $this->extractAccount($response);
        $accountNumber = $account !== null ? $this->firstString($account, [
            'accountNumber',
            'account_number',
            'dedicated_account_number',
            'number',
        ]) : '';

        if ($accountNumber !== '') {
            $stored = $this->upsertAccountRow($userId, [
                'account_reference' => $this->accountReference($response, $account),
                'dedicated_account_number' => $accountNumber,
                'account_name' => $this->firstString($account, ['accountName', 'account_name', 'name']),
                'bank_name' => $this->firstString($account, ['bankName', 'bank_name', 'bank']),
                'bank_slug' => $this->slug($this->firstString($account, ['bankName', 'bank_name', 'bank'])),
                'status' => 'assigned',
                'last_error_message' => null,
                'requested_at' => date('Y-m-d H:i:s'),
                'assigned_at' => date('Y-m-d H:i:s'),
                'meta_json' => json_encode($this->redactResponse($response)),
            ]);

            $this->logger->log($adminId !== null ? 'admin' : 'user', $adminId ?? $userId, 'katpay_account_assigned', 'KatPay funding account assigned.', [
                'user_id' => $userId,
                'bank_name' => $stored['bank_name'] ?? null,
            ]);

            if (($existing['status'] ?? '') !== 'assigned') {
                $this->notifications->create($userId, 'Funding account ready', 'Your funding account is ready for wallet funding.', 'success');
            }

            return $stored;
        }

        $pending = $this->upsertAccountRow($userId, [
            'status' => 'pending',
            'last_error_message' => null,
            'requested_at' => date('Y-m-d H:i:s'),
            'meta_json' => json_encode($this->redactResponse($response)),
        ]);

        $this->logger->log($adminId !== null ? 'admin' : 'user', $adminId ?? $userId, 'katpay_account_requested', 'KatPay funding account request logged.', [
            'user_id' => $userId,
            'message' => (string) ($response['message'] ?? 'Funding account request is pending.'),
        ]);

        return $pending;
    }

    private function assertCompatibleSchema(): void
    {
        foreach ([
            'provider',
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
        ] as $column) {
            if (!$this->db->columnExists('user_funding_accounts', $column)) {
                throw new RuntimeException('Funding account storage is missing required column: ' . $column . '.');
            }
        }

        $indexes = $this->db->query('SHOW INDEX FROM user_funding_accounts');
        $columnsByIndex = [];
        foreach ($indexes as $index) {
            if ((int) ($index['Non_unique'] ?? 1) !== 0) {
                continue;
            }
            $columnsByIndex[(string) $index['Key_name']][] = (string) $index['Column_name'];
        }

        foreach ($columnsByIndex as $columns) {
            if ($columns === ['user_id', 'provider']) {
                return;
            }
        }

        throw new RuntimeException('Funding account storage must have a unique key on user_id and provider before KatPay can be enabled.');
    }

    private function payloadForUser(array $user): array
    {
        return [
            'email' => (string) ($user['email'] ?? ''),
            'name' => (string) ($user['full_name'] ?? ''),
            'phoneNumber' => (string) ($user['phone'] ?? ''),
            'bankCode' => $this->bankCodes(),
            'merchantID' => trim((string) config('payments.katpay_merchant_id', '')),
        ];
    }

    private function request(array $payload): array
    {
        $baseUrl = rtrim((string) config('payments.katpay_base_url', 'https://api.katpay.co/v1'), '/');
        $apiKey = trim((string) config('payments.katpay_api_key', ''));
        $secretKey = trim((string) config('payments.katpay_secret_key', ''));

        if ($apiKey === '' || $secretKey === '') {
            throw new RuntimeException('KatPay API credentials are not configured.');
        }

        $handle = curl_init($baseUrl . '/virtual-accounts');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
                'api-key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($handle);
        $curlError = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException('KatPay request failed: ' . $curlError);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('KatPay returned an invalid response.');
        }

        if ($statusCode >= 400 || $this->isFailedResponse($decoded)) {
            throw new RuntimeException((string) ($decoded['message'] ?? $decoded['error'] ?? 'KatPay request failed.'));
        }

        $decoded['_http_status'] = $statusCode;
        return $decoded;
    }

    private function bankCodes(): array
    {
        $codes = (array) config('payments.katpay_bank_codes', ['PALMPAY', 'OPAY']);
        return array_values(array_filter(array_map(
            static fn($code): string => trim((string) $code),
            $codes
        )));
    }

    private function extractAccount(array $response): ?array
    {
        foreach ([
            $response['data']['account'] ?? null,
            $response['data']['virtualAccount'] ?? null,
            $response['data']['virtual_account'] ?? null,
            $response['data'] ?? null,
            $response['account'] ?? null,
            $response,
        ] as $candidate) {
            if (is_array($candidate) && $this->firstString($candidate, ['accountNumber', 'account_number', 'dedicated_account_number', 'number']) !== '') {
                return $candidate;
            }
        }

        foreach ([
            $response['data']['accounts'] ?? null,
            $response['data']['bankAccounts'] ?? null,
            $response['accounts'] ?? null,
            $response['bankAccounts'] ?? null,
        ] as $accounts) {
            if (!is_array($accounts)) {
                continue;
            }
            foreach ($accounts as $account) {
                if (is_array($account) && $this->firstString($account, ['accountNumber', 'account_number', 'dedicated_account_number', 'number']) !== '') {
                    return $account;
                }
            }
        }

        return null;
    }

    private function accountReference(array $response, array $account): ?string
    {
        $reference = $this->firstString($account, ['account_reference', 'provider_reference', 'reference', 'id', 'accountId', 'account_id']);
        if ($reference !== '') {
            return $reference;
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $reference = $this->firstString($data, ['account_reference', 'provider_reference', 'reference', 'id', 'accountId', 'account_id']);
        return $reference !== '' ? $reference : null;
    }

    private function firstString(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function isFailedResponse(array $response): bool
    {
        if (isset($response['status']) && is_bool($response['status'])) {
            return $response['status'] === false;
        }

        $status = strtolower(trim((string) ($response['status'] ?? $response['code'] ?? '')));
        return in_array($status, ['error', 'failed', 'failure'], true);
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

    private function redactResponse(array $response): array
    {
        $redacted = [];
        foreach ($response as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (preg_match('/authorization|secret|api.?key|token|bvn|nin|id_number|email|phone|account.?number/i', $normalizedKey)) {
                $redacted[$key] = '[redacted]';
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redactResponse($value) : $value;
        }

        return $redacted;
    }

    private function slug(string $value): ?string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));
        return $slug !== '' ? $slug : null;
    }

    private function logFailure(int $userId, string $message, ?int $adminId): void
    {
        $this->logger->log($adminId !== null ? 'admin' : 'user', $adminId ?? $userId, 'katpay_account_failed', 'KatPay funding account generation failed.', [
            'user_id' => $userId,
            'error' => $message,
        ]);
    }
}
