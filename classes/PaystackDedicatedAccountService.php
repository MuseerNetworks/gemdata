<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class PaystackDedicatedAccountService
{
    public function __construct(
        private Database $db,
        private ActivityLogger $logger,
        private NotificationService $notifications
    ) {
    }

    public function shouldAutoAssign(): bool
    {
        return (bool) config('payments.auto_assign_dedicated_account', false) && $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return trim((string) config('payments.paystack_secret_key', '')) !== '';
    }

    public function getForUser(int $userId): ?array
    {
        return $this->db->first(
            'SELECT * FROM user_funding_accounts WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );
    }

    public function ensureForUser(int $userId, bool $forceRetry = false): array
    {
        $user = $this->db->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$user) {
            throw new RuntimeException('User was not found for dedicated account assignment.');
        }

        if ($forceRetry) {
            $this->logger->log('user', $userId, 'paystack_dedicated_account_retry_requested', 'User requested another dedicated account sync attempt.');
        }

        $existing = $this->getForUser($userId);
        if ($existing && $existing['status'] === 'assigned' && !$forceRetry) {
            return $existing;
        }

        if (!$this->isConfigured()) {
            return $this->upsertAccountRow($userId, [
                'provider' => 'paystack',
                'status' => 'failed',
                'last_error_message' => 'Paystack dedicated account assignment is not configured yet.',
                'requested_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $customer = $this->ensureCustomer($user);
        $synced = $this->syncExistingDedicatedAccount($userId, $customer);
        if ($synced && $synced['status'] === 'assigned') {
            return $synced;
        }

        try {
            $response = $this->request('POST', '/dedicated_account', array_filter([
                'customer' => $customer['customer_code'],
                'preferred_bank' => trim((string) config('payments.dva_preferred_bank', '')) ?: null,
                'first_name' => $customer['first_name'] ?: null,
                'last_name' => $customer['last_name'] ?: null,
                'phone' => $customer['phone'] ?: null,
            ], static fn($value): bool => $value !== null && $value !== ''));
        } catch (RuntimeException $exception) {
            $syncedAfterFailure = $this->syncExistingDedicatedAccount($userId, $customer);
            if ($syncedAfterFailure && $syncedAfterFailure['status'] === 'assigned') {
                return $syncedAfterFailure;
            }

            $failed = $this->upsertAccountRow($userId, [
                'provider' => 'paystack',
                'paystack_customer_id' => $customer['id'],
                'paystack_customer_code' => $customer['customer_code'],
                'status' => 'failed',
                'last_error_message' => $exception->getMessage(),
                'requested_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logger->log('user', $userId, 'paystack_dedicated_account_failed', $exception->getMessage(), [
                'customer_code' => $customer['customer_code'],
            ]);

            return $failed;
        }

        $status = (bool) ($response['status'] ?? false);
        $message = (string) ($response['message'] ?? 'Unable to assign dedicated account right now.');
        $data = $response['data'] ?? null;

        if ($status && is_array($data) && !empty($data['account_number'])) {
            return $this->storeAssignedAccount($userId, $customer, $data, $response, 'paystack_dedicated_account_assigned', 'Dedicated account assigned successfully.');
        }

        $pending = $this->upsertAccountRow($userId, [
            'provider' => 'paystack',
            'paystack_customer_id' => $customer['id'],
            'paystack_customer_code' => $customer['customer_code'],
            'status' => $status ? 'pending' : 'failed',
            'last_error_message' => $status ? null : $message,
            'requested_at' => date('Y-m-d H:i:s'),
            'meta_json' => json_encode($response),
        ]);

        $this->logger->log('user', $userId, $status ? 'paystack_dedicated_account_pending' : 'paystack_dedicated_account_failed', $message, [
            'customer_code' => $customer['customer_code'],
            'response' => $response,
        ]);

        if (!$status) {
            $this->notifications->create(
                $userId,
                'Dedicated account pending attention',
                'We created your account successfully, but your transfer account could not be assigned yet. You can retry from the wallet funding page later.',
                'warning'
            );
        }

        $refreshed = $this->syncExistingDedicatedAccount($userId, $customer);
        return $refreshed ?? $pending;
    }

    private function ensureCustomer(array $user): array
    {
        $existing = $this->getForUser((int) $user['id']);
        if (!empty($existing['paystack_customer_code'])) {
            $customer = $this->request('GET', '/customer/' . rawurlencode((string) $existing['paystack_customer_code']), null, false);
            if (($customer['status'] ?? false) && is_array($customer['data'] ?? null)) {
                return $this->normalizeCustomer($customer['data']);
            }
        }

        $fetched = $this->request('GET', '/customer/' . rawurlencode((string) $user['email']), null, false);
        if (($fetched['status'] ?? false) && is_array($fetched['data'] ?? null)) {
            return $this->normalizeCustomer($fetched['data']);
        }

        $name = $this->splitName((string) $user['full_name']);
        $created = $this->request('POST', '/customer', [
            'email' => (string) $user['email'],
            'first_name' => $name['first_name'],
            'last_name' => $name['last_name'],
            'phone' => (string) $user['phone'],
            'metadata' => [
                'gemdata_user_id' => (int) $user['id'],
            ],
        ]);

        if (!(bool) ($created['status'] ?? false) || !is_array($created['data'] ?? null)) {
            throw new RuntimeException((string) ($created['message'] ?? 'Unable to create Paystack customer.'));
        }

        return $this->normalizeCustomer($created['data']);
    }

    private function syncExistingDedicatedAccount(int $userId, array $customer): ?array
    {
        if (empty($customer['id']) && empty($customer['customer_code'])) {
            return null;
        }

        $query = '/dedicated_account?customer=' . rawurlencode((string) ($customer['id'] ?: $customer['customer_code']));
        $response = $this->request('GET', $query);
        $accounts = $response['data'] ?? [];
        if (!(bool) ($response['status'] ?? false) || !is_array($accounts) || $accounts === []) {
            return null;
        }

        foreach ($accounts as $account) {
            if (!is_array($account)) {
                continue;
            }
            if (!empty($account['account_number']) && !empty($account['assigned'])) {
                return $this->storeAssignedAccount($userId, $customer, $account, $response, 'paystack_dedicated_account_synced', 'Dedicated account synced from Paystack.');
            }
        }

        return null;
    }

    private function storeAssignedAccount(int $userId, array $customer, array $account, array $response, string $action, string $description): array
    {
        $previous = $this->getForUser($userId);
        $stored = $this->upsertAccountRow($userId, [
            'provider' => 'paystack',
            'paystack_customer_id' => $customer['id'],
            'paystack_customer_code' => $customer['customer_code'],
            'dedicated_account_id' => $account['id'] ?? null,
            'dedicated_account_number' => $account['account_number'] ?? null,
            'account_name' => $account['account_name'] ?? null,
            'bank_name' => $account['bank']['name'] ?? null,
            'bank_slug' => $account['bank']['slug'] ?? null,
            'status' => 'assigned',
            'last_error_message' => null,
            'requested_at' => date('Y-m-d H:i:s'),
            'assigned_at' => date('Y-m-d H:i:s'),
            'meta_json' => json_encode($response),
        ]);

        $this->logger->log('user', $userId, $action, $description, [
            'customer_code' => $customer['customer_code'],
            'account_number' => $stored['dedicated_account_number'],
            'bank_name' => $stored['bank_name'],
        ]);

        if (($previous['status'] ?? '') !== 'assigned') {
            $this->notifications->create(
                $userId,
                'Dedicated transfer account ready',
                'Your Paystack transfer account is now available for wallet funding.',
                'success'
            );
        }

        return $stored;
    }

    private function upsertAccountRow(int $userId, array $fields): array
    {
        $allowed = [
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
        ];

        $payload = array_intersect_key($fields, array_flip($allowed));
        $payload['user_id'] = $userId;

        $columns = array_keys($payload);
        $insertSql = implode(', ', $columns);
        $insertValues = implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns));
        $updates = implode(', ', array_map(static fn(string $column): string => $column . ' = VALUES(' . $column . ')', array_filter($columns, static fn(string $column): bool => $column !== 'user_id')));

        $this->db->execute(
            "INSERT INTO user_funding_accounts ({$insertSql}) VALUES ({$insertValues}) ON DUPLICATE KEY UPDATE {$updates}",
            $payload
        );

        return $this->getForUser($userId) ?? [];
    }

    private function normalizeCustomer(array $data): array
    {
        return [
            'id' => (int) ($data['id'] ?? 0),
            'customer_code' => (string) ($data['customer_code'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name' => (string) ($data['last_name'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
        ];
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $firstName = $parts[0] ?? 'GemData';
        $lastName = count($parts) > 1 ? trim(implode(' ', array_slice($parts, 1))) : 'User';

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    private function request(string $method, string $path, ?array $payload = null, bool $throwOnHttpError = true): array
    {
        $baseUrl = rtrim((string) config('payments.paystack_base_url', 'https://api.paystack.co'), '/');
        $secretKey = trim((string) config('payments.paystack_secret_key', ''));
        if ($secretKey === '') {
            throw new RuntimeException('Paystack secret key is missing.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Paystack integration.');
        }

        $url = $baseUrl . $path;
        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ]);

        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $body = curl_exec($handle);
        $error = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException('Paystack request failed: ' . $error);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Paystack returned an invalid response.');
        }

        if ($statusCode >= 400 && $throwOnHttpError) {
            $message = (string) ($decoded['message'] ?? 'Paystack request failed.');
            throw new RuntimeException($message);
        }

        $decoded['_http_status'] = $statusCode;
        return $decoded;
    }
}
