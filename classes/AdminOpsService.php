<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class AdminOpsService
{
    public function __construct(
        private Database $db,
        private ActivityLogger $logger,
        private ProviderManager $providerManager
    ) {
    }

    public function savedViews(int $adminId, string $pageKey): array
    {
        return $this->db->query(
            'SELECT * FROM admin_saved_views
             WHERE admin_id = :admin_id AND page_key = :page_key
             ORDER BY is_default DESC, name ASC',
            ['admin_id' => $adminId, 'page_key' => $pageKey]
        );
    }

    public function defaultSavedView(int $adminId, string $pageKey): ?array
    {
        return $this->db->first(
            'SELECT * FROM admin_saved_views
             WHERE admin_id = :admin_id AND page_key = :page_key AND is_default = 1
             LIMIT 1',
            ['admin_id' => $adminId, 'page_key' => $pageKey]
        );
    }

    public function findSavedView(int $viewId, int $adminId, string $pageKey): ?array
    {
        return $this->db->first(
            'SELECT * FROM admin_saved_views
             WHERE id = :id AND admin_id = :admin_id AND page_key = :page_key
             LIMIT 1',
            ['id' => $viewId, 'admin_id' => $adminId, 'page_key' => $pageKey]
        );
    }

    public function saveView(int $adminId, string $pageKey, string $name, array $filters, bool $isDefault = false): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('View name is required.');
        }

        if ($isDefault) {
            $this->db->execute(
                'UPDATE admin_saved_views SET is_default = 0 WHERE admin_id = :admin_id AND page_key = :page_key',
                ['admin_id' => $adminId, 'page_key' => $pageKey]
            );
        }

        $this->db->execute(
            'INSERT INTO admin_saved_views (admin_id, page_key, name, filters_json, is_default)
             VALUES (:admin_id, :page_key, :name, :filters_json, :is_default)',
            [
                'admin_id' => $adminId,
                'page_key' => $pageKey,
                'name' => $name,
                'filters_json' => json_encode($filters),
                'is_default' => $isDefault ? 1 : 0,
            ]
        );
        $viewId = $this->db->lastInsertId();
        $this->logger->log('admin', $adminId, 'admin_saved_view_created', 'Created saved admin view.', [
            'page_key' => $pageKey,
            'view_id' => $viewId,
            'name' => $name,
        ]);

        return $viewId;
    }

    public function deleteView(int $viewId, array $admin, bool $canManageAll): void
    {
        $view = $this->db->first('SELECT * FROM admin_saved_views WHERE id = :id LIMIT 1', ['id' => $viewId]);
        if (!$view) {
            throw new RuntimeException('Saved view not found.');
        }
        if ((int) $view['admin_id'] !== (int) $admin['id'] && !$canManageAll) {
            throw new RuntimeException('You are not allowed to delete this saved view.');
        }

        $this->db->execute('DELETE FROM admin_saved_views WHERE id = :id', ['id' => $viewId]);
        $this->logger->log('admin', (int) $admin['id'], 'admin_saved_view_deleted', 'Deleted saved admin view.', [
            'view_id' => $viewId,
            'page_key' => $view['page_key'],
        ]);
    }

    public function applyBulkUserAction(array $admin, string $action, array $userIds, array $payload = [], bool $authorized = false): array
    {
        if (!$authorized) {
            throw new RuntimeException('You are not allowed to run bulk user actions.');
        }
        $userIds = $this->normalizeIds($userIds);
        if ($userIds === []) {
            throw new RuntimeException('Select at least one user.');
        }

        $allowed = ['activate', 'deactivate', 'set_tier'];
        if (!in_array($action, $allowed, true)) {
            throw new RuntimeException('Unsupported bulk user action.');
        }

        $affected = 0;
        if ($action === 'set_tier') {
            $tier = strtoupper((string) ($payload['tier'] ?? ''));
            if (!in_array($tier, ['USER', 'RESELLER', 'AGENT', 'API_RESELLER'], true)) {
                throw new RuntimeException('Invalid tier selected.');
            }
            foreach ($userIds as $userId) {
                $this->db->execute('UPDATE users SET tier = :tier WHERE id = :id', ['tier' => $tier, 'id' => $userId]);
                $affected++;
            }
            $this->logger->log('admin', (int) $admin['id'], 'users_bulk_tier_changed', 'Bulk changed user tier.', ['user_ids' => $userIds, 'tier' => $tier]);
            return ['affected' => $affected, 'skipped' => 0];
        }

        $status = $action === 'activate' ? 'active' : 'inactive';
        foreach ($userIds as $userId) {
            $this->db->execute('UPDATE users SET status = :status WHERE id = :id', ['status' => $status, 'id' => $userId]);
            $affected++;
        }
        $this->logger->log('admin', (int) $admin['id'], 'users_bulk_status_changed', 'Bulk changed user status.', ['user_ids' => $userIds, 'status' => $status]);

        return ['affected' => $affected, 'skipped' => 0];
    }

    public function applyBulkTransactionAction(array $admin, string $action, array $transactionIds, bool $authorized = false): array
    {
        if (!$authorized) {
            throw new RuntimeException('You are not allowed to run bulk transaction actions.');
        }
        $transactionIds = $this->normalizeIds($transactionIds);
        if ($transactionIds === []) {
            throw new RuntimeException('Select at least one transaction.');
        }
        if ($action !== 'acknowledge') {
            throw new RuntimeException('Unsupported bulk transaction action.');
        }

        $affected = 0;
        foreach ($transactionIds as $transactionId) {
            $alreadyAcknowledged = $this->db->first(
                'SELECT id FROM transaction_events WHERE transaction_id = :transaction_id AND event_type = :event_type LIMIT 1',
                ['transaction_id' => $transactionId, 'event_type' => 'transaction_reviewed']
            );
            if ($alreadyAcknowledged) {
                continue;
            }
            $this->db->execute(
                'INSERT INTO transaction_events (transaction_id, event_type, actor_type, actor_id, notes, meta_json)
                 VALUES (:transaction_id, :event_type, :actor_type, :actor_id, :notes, :meta_json)',
                [
                    'transaction_id' => $transactionId,
                    'event_type' => 'transaction_reviewed',
                    'actor_type' => 'admin',
                    'actor_id' => (int) $admin['id'],
                    'notes' => 'Admin acknowledged transaction during bulk review.',
                    'meta_json' => json_encode(['bulk' => true]),
                ]
            );
            $affected++;
        }

        $this->logger->log('admin', (int) $admin['id'], 'transactions_bulk_reviewed', 'Bulk reviewed transactions.', ['transaction_ids' => $transactionIds, 'affected' => $affected]);
        return ['affected' => $affected, 'skipped' => count($transactionIds) - $affected];
    }

    public function dashboardOpsSummary(): array
    {
        $pendingQueue = $this->db->query(
            'SELECT t.id, t.reference, t.created_at, t.amount, u.full_name, s.name AS service_name
             FROM transactions t
             INNER JOIN users u ON u.id = t.user_id
             INNER JOIN services s ON s.id = t.service_id
             WHERE t.status = "pending"
             ORDER BY t.id DESC LIMIT 5'
        );
        $recentFailures = $this->db->query(
            'SELECT t.id, t.reference, t.created_at, t.amount, u.full_name, s.name AS service_name, t.provider_code
             FROM transactions t
             INNER JOIN users u ON u.id = t.user_id
             INNER JOIN services s ON s.id = t.service_id
             WHERE t.status = "failed"
             ORDER BY t.id DESC LIMIT 5'
        );

        $providerHealth = array_map(static function (array $provider): array {
            return [
                'id' => (int) ($provider['id'] ?? 0),
                'name' => (string) ($provider['provider_name'] ?? $provider['provider'] ?? ''),
                'code' => (string) ($provider['provider_code'] ?? $provider['provider'] ?? ''),
                'status' => (string) ($provider['status'] ?? 'unknown'),
                'balance' => (float) ($provider['balance_amount'] ?? 0),
                'threshold' => (float) ($provider['threshold'] ?? 0),
                'is_low' => (bool) ($provider['is_low_balance'] ?? false),
                'sandbox' => !empty($provider['sandbox']),
            ];
        }, $this->providerManager->providerHealthSummary());

        return [
            'pending_queue' => $pendingQueue,
            'recent_failures' => $recentFailures,
            'provider_health' => $providerHealth,
            'provider_wallet_balances' => $this->providerWalletBalances(),
        ];
    }

    private function providerWalletBalances(): array
    {
        $rows = $this->db->query(
            'SELECT id, name, code, status, current_balance, balance_refreshed_at,
                    low_balance_threshold, circuit_breaker_status
             FROM provider_accounts
             WHERE status <> "archived"
             ORDER BY priority_order ASC, id ASC'
        );

        return array_map(static function (array $row): array {
            $balanceKnown = array_key_exists('current_balance', $row) && $row['current_balance'] !== null;
            $balance = $balanceKnown ? (float) $row['current_balance'] : null;
            $threshold = (float) ($row['low_balance_threshold'] ?? 0);

            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'code' => (string) ($row['code'] ?? ''),
                'status' => (string) ($row['status'] ?? 'unknown'),
                'balance_known' => $balanceKnown,
                'balance' => $balance,
                'balance_refreshed_at' => $row['balance_refreshed_at'] ?? null,
                'threshold' => $threshold,
                'is_low' => $balanceKnown && $balance !== null && $balance <= $threshold,
                'circuit_breaker_status' => (string) ($row['circuit_breaker_status'] ?? 'closed'),
            ];
        }, $rows);
    }

    private function normalizeIds(array $ids): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(static fn($id): int => (int) $id, $ids))));
        return array_values(array_filter($normalized, static fn(int $id): bool => $id > 0));
    }
}
