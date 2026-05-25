<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class PaystackDedicatedAccountService
{
    private const PROVIDER = 'paystack';

    public function __construct(
        private Database $db,
        private ActivityLogger $logger,
        private NotificationService $notifications
    ) {
    }

    public function isConfigured(): bool
    {
        return trim((string) config('payments.paystack_secret_key', '')) !== ''
            && trim((string) config('payments.paystack_preferred_bank', '')) !== '';
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
            throw new RuntimeException('User was not found for Paystack account generation.');
        }

        $existing = $this->getForUser($userId);
        if ($existing && ($existing['status'] ?? '') === 'assigned' && !$forceRetry) {
            return $existing;
        }

        if (!$this->isConfigured()) {
            $failed = $this->upsertAccountRow($userId, [
                'status' => 'failed',
                'last_error_message' => 'Paystack dedicated account is not configured yet.',
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logFailure($userId, 'Paystack dedicated account is not configured yet.', $adminId);
            return $failed;
        }

        if (!function_exists('curl_init')) {
            $failed = $this->upsertAccountRow($userId, [
                'status' => 'failed',
                'last_error_message' => 'PHP cURL extension is required for Paystack integration.',
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logFailure($userId, 'PHP cURL extension is required for Paystack integration.', $adminId);
            return $failed;
        }

        $pending = $this->upsertAccountRow($userId, [
            'status' => 'pending',
            'last_error_message' => null,
            'requested_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $response = $this->requestAssign($this->payloadForUser($user));
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
        if ($account !== null && $this->accountNumber($account) !== '') {
            $stored = $this->storeAssignedAccount($userId, $response, $account);
            $this->logger->log($adminId !== null ? 'admin' : 'user', $adminId ?? $userId, 'paystack_account_assigned', 'Paystack funding account assigned.', [
                'user_id' => $userId,
                'bank_name' => $stored['bank_name'] ?? null,
            ]);
            $this->notifications->create($userId, 'Funding account ready', 'Your funding account is ready for wallet funding.', 'success');
            return $stored;
        }

        $pending = $this->upsertAccountRow($userId, [
            'status' => 'pending',
            'last_error_message' => null,
            'requested_at' => date('Y-m-d H:i:s'),
            'meta_json' => json_encode($this->redactResponse($response)),
        ]);

        $this->logger->log($adminId !== null ? 'admin' : 'user', $adminId ?? $userId, 'paystack_account_requested', 'Paystack funding account assignment requested.', [
            'user_id' => $userId,
            'message' => (string) ($response['message'] ?? 'Assignment in progress.'),
        ]);

        return $pending;
    }

    private function assertCompatibleSchema(): void
    {
        foreach ([
            'provider',
            'paystack_customer_id',
            'paystack_customer_code',
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

        throw new RuntimeException('Funding account storage must have a unique key on user_id and provider before Paystack can be enabled.');
    }

    private function payloadForUser(array $user): array
    {
        [$firstName, $lastName] = $this->splitName((string) ($user['full_name'] ?? ''));

        return [
            'email' => (string) $user['email'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $this->normalizePhone((string) ($user['phone'] ?? '')),
            'preferred_bank' => trim((string) config('payments.paystack_preferred_bank', '')),
            'country' => 'NG',
        ];
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
        $firstName = $parts[0] ?? 'GemData';
        $lastName = trim(implode(' ', array_slice($parts, 1)));

        return [$firstName, $lastName !== '' ? $lastName : $firstName];
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, '+')) {
            return '+' . preg_replace('/\D+/', '', substr($phone, 1));
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '234')) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '+234' . substr($digits, 1);
        }

        return $digits !== '' ? '+234' . $digits : $phone;
    }

    private function requestAssign(array $payload): array
    {
        $baseUrl = rtrim((string) config('payments.paystack_base_url', 'https://api.paystack.co'), '/');
        $secretKey = trim((string) config('payments.paystack_secret_key', ''));

        if ($secretKey === '') {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        $handle = curl_init($baseUrl . '/dedicated_account/assign');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
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
            throw new RuntimeException('Paystack request failed: ' . $curlError);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Paystack returned an invalid response.');
        }

        if ($statusCode >= 400 || (isset($decoded['status']) && (bool) $decoded['status'] !== true)) {
            throw new RuntimeException((string) ($decoded['message'] ?? 'Paystack request failed.'));
        }

        $decoded['_http_status'] = $statusCode;
        return $decoded;
    }

    private function storeAssignedAccount(int $userId, array $response, array $account): array
    {
        $customer = is_array($account['customer'] ?? null) ? $account['customer'] : (is_array($response['data']['customer'] ?? null) ? $response['data']['customer'] : []);
        $bank = is_array($account['bank'] ?? null) ? $account['bank'] : [];

        return $this->upsertAccountRow($userId, [
            'paystack_customer_id' => $customer['id'] ?? null,
            'paystack_customer_code' => $customer['customer_code'] ?? null,
            'dedicated_account_id' => $account['id'] ?? null,
            'account_reference' => isset($account['id']) ? (string) $account['id'] : null,
            'dedicated_account_number' => $this->accountNumber($account),
            'account_name' => $account['account_name'] ?? null,
            'bank_name' => $bank['name'] ?? null,
            'bank_slug' => $bank['slug'] ?? null,
            'status' => 'assigned',
            'last_error_message' => null,
            'requested_at' => date('Y-m-d H:i:s'),
            'assigned_at' => date('Y-m-d H:i:s'),
            'meta_json' => json_encode($this->redactResponse($response)),
        ]);
    }

    private function extractAccount(array $response): ?array
    {
        $data = $response['data'] ?? null;
        return is_array($data) && isset($data['account_number']) ? $data : null;
    }

    private function accountNumber(array $account): string
    {
        return trim((string) ($account['account_number'] ?? $account['accountNumber'] ?? ''));
    }

    private function upsertAccountRow(int $userId, array $fields): array
    {
        $allowed = [
            'provider',
            'account_reference',
            'paystack_customer_id',
            'paystack_customer_code',
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
            if (in_array($normalizedKey, [
                'authorization',
                'secret',
                'api_key',
                'apikey',
                'bvn',
                'nin',
                'id_number',
                'email',
                'phone',
                'phone_number',
                'customer_email',
                'customer_phone_number',
                'account_number',
                'accountnumber',
            ], true)) {
                $redacted[$key] = '[redacted]';
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redactResponse($value) : $value;
        }

        return $redacted;
    }

    private function logFailure(int $userId, string $message, ?int $adminId): void
    {
        $this->logger->log($adminId !== null ? 'admin' : 'user', $adminId ?? $userId, 'paystack_account_failed', 'Paystack funding account generation failed.', [
            'user_id' => $userId,
            'error' => $message,
        ]);
    }
}
