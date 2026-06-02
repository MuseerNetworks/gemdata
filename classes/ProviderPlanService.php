<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class ProviderPlanService
{
    public function __construct(
        private Database $db,
        private PricingService $pricing,
        private ?SimpleCache $cache = null,
        private ?SettingsService $settings = null
    ) {
    }

    public function catalogForServiceSlug(string $serviceSlug): array
    {
        $showInactiveProviderPlans = $this->showInactiveProviderPlansForTesting();
        $cacheKey = 'provider-plan-catalog:' . strtolower($serviceSlug) . ':' . ($showInactiveProviderPlans ? 'testing' : 'strict');
        $cached = $this->cache?->get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $rows = $this->db->query(
            'SELECT psp.service_id, psp.network_code, psp.local_plan_code, psp.local_plan_name, psp.amount
             FROM provider_service_plans psp
             INNER JOIN services s ON s.id = psp.service_id
             INNER JOIN provider_accounts pa ON pa.id = psp.provider_account_id
             WHERE s.slug = :slug
               AND psp.is_enabled = 1
               AND s.is_enabled = 1
               AND (:show_inactive_provider_plans = 1 OR pa.status = "active")
             ORDER BY psp.network_code, psp.amount ASC, psp.local_plan_name ASC',
            [
                'slug' => $serviceSlug,
                'show_inactive_provider_plans' => $showInactiveProviderPlans ? 1 : 0,
            ]
        );

        $enabledNetworksByService = $this->enabledNetworksByService($rows);
        $catalog = [];
        $seen = [];
        foreach ($rows as $row) {
            $serviceId = (int) $row['service_id'];
            $normalizedNetwork = $this->pricing->normalizeNetwork((string) ($row['network_code'] ?? ''));
            if (!$this->networkIsVisibleForCatalog($serviceId, $normalizedNetwork, $enabledNetworksByService)) {
                continue;
            }

            $key = implode(':', [
                (string) $serviceId,
                (string) ($normalizedNetwork ?? ''),
                strtolower((string) $row['local_plan_code']),
            ]);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $catalog[] = [
                'service_id' => $serviceId,
                'network_code' => (string) ($normalizedNetwork ?? ''),
                'local_plan_code' => (string) $row['local_plan_code'],
                'local_plan_name' => (string) $row['local_plan_name'],
                'amount' => (float) ($row['amount'] ?? 0),
            ];
        }

        if ($catalog !== []) {
            $this->cache?->put($cacheKey, $catalog, 120);
        }
        return $catalog;
    }

    public function mappingsForAdmin(): array
    {
        return $this->db->query(
            'SELECT psp.*, pa.name AS provider_name, pa.code AS provider_code, s.name AS service_name, s.slug AS service_slug
             FROM provider_service_plans psp
             INNER JOIN provider_accounts pa ON pa.id = psp.provider_account_id
             INNER JOIN services s ON s.id = psp.service_id
             ORDER BY pa.priority_order ASC, s.name ASC, psp.network_code ASC, psp.amount ASC, psp.local_plan_name ASC'
        );
    }

    public function latestMappingsForAdmin(int $limit = 5): array
    {
        return $this->db->query(
            'SELECT psp.*, pa.name AS provider_name, pa.code AS provider_code, s.name AS service_name, s.slug AS service_slug
             FROM provider_service_plans psp
             INNER JOIN provider_accounts pa ON pa.id = psp.provider_account_id
             INNER JOIN services s ON s.id = psp.service_id
             ORDER BY psp.id DESC
             LIMIT ' . max(1, min(25, $limit))
        );
    }

    public function assertDataPlanAvailable(int $serviceId, ?string $networkCode, string $localPlanCode): void
    {
        $normalizedNetwork = $this->pricing->normalizeNetwork($networkCode);
        $planCode = $this->normalizePlanCode($localPlanCode);
        if ($planCode === '') {
            throw new RuntimeException('Select a valid data plan.');
        }

        $row = $this->db->first(
            'SELECT psp.id
             FROM provider_service_plans psp
             INNER JOIN provider_accounts pa ON pa.id = psp.provider_account_id
             WHERE psp.service_id = :service_id
               AND psp.is_enabled = 1
               AND pa.status = "active"
               AND psp.local_plan_code = :local_plan_code
               AND psp.network_code <=> :network_code
             LIMIT 1',
            [
                'service_id' => $serviceId,
                'local_plan_code' => $planCode,
                'network_code' => $normalizedNetwork,
            ]
        );

        if (!$row) {
            throw new RuntimeException('The selected data plan is not configured for any active provider yet.');
        }
    }

    public function resolveForProvider(int $providerAccountId, int $serviceId, ?string $networkCode, string $localPlanCode): ?array
    {
        return $this->db->first(
            'SELECT *
             FROM provider_service_plans
             WHERE provider_account_id = :provider_account_id
               AND service_id = :service_id
               AND is_enabled = 1
               AND local_plan_code = :local_plan_code
               AND network_code <=> :network_code
             LIMIT 1',
            [
                'provider_account_id' => $providerAccountId,
                'service_id' => $serviceId,
                'local_plan_code' => $this->normalizePlanCode($localPlanCode),
                'network_code' => $this->pricing->normalizeNetwork($networkCode),
            ]
        );
    }

    public function upsertMapping(array $payload): void
    {
        $providerAccountId = (int) ($payload['provider_account_id'] ?? 0);
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $networkCode = $this->pricing->normalizeNetwork((string) ($payload['network_code'] ?? ''));
        $localPlanCode = $this->normalizePlanCode((string) ($payload['local_plan_code'] ?? ''));
        $localPlanName = trim((string) ($payload['local_plan_name'] ?? ''));
        $providerPlanId = trim((string) ($payload['provider_plan_id'] ?? ''));
        $providerPlanName = trim((string) ($payload['provider_plan_name'] ?? ''));
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $isEnabled = !empty($payload['is_enabled']) ? 1 : 0;

        if ($providerAccountId <= 0 || $serviceId <= 0) {
            throw new RuntimeException('Provider and service are required for plan mapping.');
        }

        if ($localPlanCode === '' || $localPlanName === '' || $providerPlanId === '') {
            throw new RuntimeException('Local plan code, local plan name, and provider plan ID are required.');
        }

        $existing = $this->db->first(
            'SELECT id
             FROM provider_service_plans
             WHERE provider_account_id = :provider_account_id
               AND service_id = :service_id
               AND local_plan_code = :local_plan_code
               AND network_code <=> :network_code
             LIMIT 1',
            [
                'provider_account_id' => $providerAccountId,
                'service_id' => $serviceId,
                'local_plan_code' => $localPlanCode,
                'network_code' => $networkCode,
            ]
        );

        $params = [
            'provider_account_id' => $providerAccountId,
            'service_id' => $serviceId,
            'network_code' => $networkCode,
            'local_plan_code' => $localPlanCode,
            'local_plan_name' => $localPlanName,
            'provider_plan_id' => $providerPlanId,
            'provider_plan_name' => $providerPlanName,
            'amount' => $amount,
            'is_enabled' => $isEnabled,
        ];

        if ($existing) {
            $updateParams = [
                'local_plan_name' => $params['local_plan_name'],
                'provider_plan_id' => $params['provider_plan_id'],
                'provider_plan_name' => $params['provider_plan_name'],
                'amount' => $params['amount'],
                'is_enabled' => $params['is_enabled'],
                'id' => $existing['id'],
            ];
            $this->db->execute(
                'UPDATE provider_service_plans
                 SET local_plan_name = :local_plan_name,
                     provider_plan_id = :provider_plan_id,
                     provider_plan_name = :provider_plan_name,
                     amount = :amount,
                     is_enabled = :is_enabled
                 WHERE id = :id',
                $updateParams
            );
        } else {
            $this->db->execute(
                'INSERT INTO provider_service_plans (
                    provider_account_id, service_id, network_code, local_plan_code, local_plan_name,
                    provider_plan_id, provider_plan_name, amount, is_enabled
                 ) VALUES (
                    :provider_account_id, :service_id, :network_code, :local_plan_code, :local_plan_name,
                    :provider_plan_id, :provider_plan_name, :amount, :is_enabled
                 )',
                $params
            );
        }

        $this->flushCaches();
    }

    public function invalidate(): void
    {
        $this->flushCaches();
    }

    private function normalizePlanCode(string $planCode): string
    {
        $planCode = strtoupper(trim($planCode));
        return preg_replace('/[^A-Z0-9:_\.-]/', '', $planCode) ?? '';
    }

    private function showInactiveProviderPlansForTesting(): bool
    {
        $default = filter_var(
            \config('feature_flags.show_inactive_provider_plans_for_testing', false),
            FILTER_VALIDATE_BOOLEAN
        );

        return $this->settings?->bool('show_inactive_provider_plans_for_testing', $default) ?? $default;
    }

    private function enabledNetworksByService(array $planRows): array
    {
        $serviceIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['service_id'] ?? 0),
            $planRows
        ))));

        if ($serviceIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($serviceIds as $index => $serviceId) {
            $key = 'service_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $serviceId;
        }

        $rows = $this->db->query(
            'SELECT service_id, network_code, is_enabled
             FROM service_networks
             WHERE service_id IN (' . implode(',', $placeholders) . ')',
            $params
        );

        $networks = [];
        foreach ($rows as $row) {
            $serviceId = (int) $row['service_id'];
            $networkCode = $this->pricing->normalizeNetwork((string) ($row['network_code'] ?? ''));
            if ($networkCode === null) {
                continue;
            }

            $networks[$serviceId] ??= [
                'has_rows' => true,
                'enabled' => [],
            ];

            if (!empty($row['is_enabled'])) {
                $networks[$serviceId]['enabled'][$networkCode] = true;
            }
        }

        return $networks;
    }

    private function networkIsVisibleForCatalog(int $serviceId, ?string $networkCode, array $enabledNetworksByService): bool
    {
        if (empty($enabledNetworksByService[$serviceId]['has_rows'])) {
            return true;
        }

        if ($networkCode === null || $networkCode === '') {
            return false;
        }

        return !empty($enabledNetworksByService[$serviceId]['enabled'][$networkCode]);
    }

    private function flushCaches(): void
    {
        foreach (['airtime', 'data', 'electricity', 'cable_tv', 'exam_pin', 'recharge_card', 'data_card', 'bulk_sms'] as $slug) {
            $this->cache?->forget('provider-plan-catalog:' . $slug);
            $this->cache?->forget('provider-plan-catalog:' . $slug . ':strict');
            $this->cache?->forget('provider-plan-catalog:' . $slug . ':testing');
        }
    }
}
