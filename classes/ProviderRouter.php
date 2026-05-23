<?php

declare(strict_types=1);

namespace GemData\Classes;

class ProviderRouter
{
    public function __construct(
        private Database $db,
        private ProviderPlanService $planService,
        private PricingService $pricing
    ) {
    }

    public function route(string $serviceSlug, array $payload, array $providers): array
    {
        $setting = $this->routingSetting($serviceSlug);
        $providers = $this->filterPlanAwareProviders($providers, $payload);
        $providers = $this->filterBalanceAwareProviders($providers, (float) ($payload['amount'] ?? 0));
        $providers = $this->filterSuccessThresholdProviders($providers, (float) $setting['minimum_success_rate']);

        $mode = (string) $setting['routing_mode'];
        if ($mode === 'manual') {
            $providers = $this->manualOrder($providers, (int) ($setting['manual_provider_account_id'] ?? 0), !empty($setting['fallback_enabled']));
        } elseif ($mode === 'cheapest') {
            $providers = $this->cheapestOrder($providers, $payload);
        } elseif ($mode === 'cheapest_health') {
            $providers = $this->cheapestHealthOrder($providers, $payload, $setting);
        } else {
            usort($providers, static fn(array $a, array $b): int => [(int) $a['priority_order'], (int) $a['id']] <=> [(int) $b['priority_order'], (int) $b['id']]);
        }

        return [
            'providers' => array_values($providers),
            'setting' => $setting,
        ];
    }

    public function routingSetting(string $serviceSlug): array
    {
        $fallback = [
            'service_slug' => $serviceSlug,
            'routing_mode' => 'priority',
            'manual_provider_account_id' => null,
            'fallback_enabled' => 1,
            'minimum_success_rate' => 80.0,
            'health_weight' => 30.0,
            'cost_weight' => 70.0,
        ];

        if (!$this->db->tableExists('routing_settings')) {
            return $fallback;
        }

        $specific = $this->db->first('SELECT * FROM routing_settings WHERE service_slug = :service_slug LIMIT 1', ['service_slug' => $serviceSlug]);
        if ($specific) {
            return array_merge($fallback, $specific);
        }

        $global = $this->db->first('SELECT * FROM routing_settings WHERE service_slug IS NULL LIMIT 1');
        return $global ? array_merge($fallback, $global, ['service_slug' => $serviceSlug]) : $fallback;
    }

    public function allRoutingSettings(): array
    {
        if (!$this->db->tableExists('routing_settings')) {
            return [];
        }

        return $this->db->query('SELECT rs.*, pa.name AS provider_name FROM routing_settings rs LEFT JOIN provider_accounts pa ON pa.id = rs.manual_provider_account_id ORDER BY COALESCE(rs.service_slug, "__global__") ASC');
    }

    public function upsertRoutingSetting(array $payload, ?int $adminId = null): void
    {
        if (!$this->db->tableExists('routing_settings')) {
            return;
        }

        $serviceSlug = trim((string) ($payload['service_slug'] ?? ''));
        $serviceSlug = $serviceSlug === '__global__' ? '' : $serviceSlug;
        $routingMode = strtolower(trim((string) ($payload['routing_mode'] ?? 'priority')));
        if (!in_array($routingMode, ['manual', 'priority', 'cheapest', 'cheapest_health'], true)) {
            $routingMode = 'priority';
        }

        $params = [
            'service_slug' => $serviceSlug === '' ? null : $serviceSlug,
            'routing_mode' => $routingMode,
            'manual_provider_account_id' => !empty($payload['manual_provider_account_id']) ? (int) $payload['manual_provider_account_id'] : null,
            'fallback_enabled' => !empty($payload['fallback_enabled']) ? 1 : 0,
            'minimum_success_rate' => max(0, min(100, (float) ($payload['minimum_success_rate'] ?? 80))),
            'health_weight' => max(0, min(100, (float) ($payload['health_weight'] ?? 30))),
            'cost_weight' => max(0, min(100, (float) ($payload['cost_weight'] ?? 70))),
            'updated_by_admin_id' => $adminId,
        ];

        $existing = $params['service_slug'] === null
            ? $this->db->first('SELECT id FROM routing_settings WHERE service_slug IS NULL LIMIT 1')
            : $this->db->first('SELECT id FROM routing_settings WHERE service_slug = :service_slug LIMIT 1', ['service_slug' => $params['service_slug']]);

        if ($existing) {
            $params['id'] = (int) $existing['id'];
            $this->db->execute(
                'UPDATE routing_settings
                 SET routing_mode = :routing_mode,
                     manual_provider_account_id = :manual_provider_account_id,
                     fallback_enabled = :fallback_enabled,
                     minimum_success_rate = :minimum_success_rate,
                     health_weight = :health_weight,
                     cost_weight = :cost_weight,
                     updated_by_admin_id = :updated_by_admin_id
                 WHERE id = :id',
                $params
            );
            return;
        }

        $this->db->execute(
            'INSERT INTO routing_settings (
                service_slug, routing_mode, manual_provider_account_id, fallback_enabled,
                minimum_success_rate, health_weight, cost_weight, updated_by_admin_id
             ) VALUES (
                :service_slug, :routing_mode, :manual_provider_account_id, :fallback_enabled,
                :minimum_success_rate, :health_weight, :cost_weight, :updated_by_admin_id
             )',
            $params
        );
    }

    private function manualOrder(array $providers, int $manualProviderId, bool $fallbackEnabled): array
    {
        if ($manualProviderId <= 0) {
            return $providers;
        }

        $manual = [];
        $fallbacks = [];
        foreach ($providers as $provider) {
            if ((int) $provider['id'] === $manualProviderId) {
                $manual[] = $provider;
            } elseif ($fallbackEnabled) {
                $fallbacks[] = $provider;
            }
        }

        usort($fallbacks, static fn(array $a, array $b): int => [(int) $a['priority_order'], (int) $a['id']] <=> [(int) $b['priority_order'], (int) $b['id']]);
        return array_merge($manual, $fallbacks);
    }

    private function cheapestOrder(array $providers, array $payload): array
    {
        usort($providers, function (array $a, array $b) use ($payload): int {
            return [$this->providerCost($a, $payload), (int) $a['priority_order'], (int) $a['id']]
                <=> [$this->providerCost($b, $payload), (int) $b['priority_order'], (int) $b['id']];
        });

        return $providers;
    }

    private function cheapestHealthOrder(array $providers, array $payload, array $setting): array
    {
        $costs = array_map(fn(array $provider): float => $this->providerCost($provider, $payload), $providers);
        $minCost = min($costs ?: [0]);
        $maxCost = max($costs ?: [0]);
        $costRange = max(1.0, $maxCost - $minCost);
        $healthWeight = (float) ($setting['health_weight'] ?? 30);
        $costWeight = (float) ($setting['cost_weight'] ?? 70);

        usort($providers, function (array $a, array $b) use ($payload, $minCost, $costRange, $healthWeight, $costWeight): int {
            $scoreA = $this->weightedScore($a, $payload, $minCost, $costRange, $healthWeight, $costWeight);
            $scoreB = $this->weightedScore($b, $payload, $minCost, $costRange, $healthWeight, $costWeight);
            return [$scoreB, (int) $a['priority_order'] * -1] <=> [$scoreA, (int) $b['priority_order'] * -1];
        });

        return $providers;
    }

    private function weightedScore(array $provider, array $payload, float $minCost, float $costRange, float $healthWeight, float $costWeight): float
    {
        $cost = $this->providerCost($provider, $payload);
        $costScore = max(0, 100 - ((($cost - $minCost) / $costRange) * 100));
        $healthScore = (float) ($provider['health_score'] ?? 100);
        $successRate = $this->successRate((int) $provider['id']);
        $responsePenalty = min(20.0, $this->averageResponseTimeMs((int) $provider['id']) / 1000);

        return (($costScore * $costWeight) + (((($healthScore + $successRate) / 2) - $responsePenalty) * $healthWeight)) / max(1, $costWeight + $healthWeight);
    }

    private function filterPlanAwareProviders(array $providers, array $payload): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $planCode = $this->payloadPlanCode($payload);
        if ($serviceId <= 0 || $planCode === '') {
            return $providers;
        }

        $networkCode = $this->pricing->normalizeNetwork((string) ($payload['network'] ?? $payload['provider'] ?? ''));
        $mapped = [];
        foreach ($providers as $provider) {
            $mapping = $this->planService->resolveForProvider((int) $provider['id'], $serviceId, $networkCode, $planCode);
            if ($mapping) {
                $provider['_route_plan_mapping'] = $mapping;
                $mapped[] = $provider;
            }
        }

        return $mapped === [] ? $providers : $mapped;
    }

    private function filterBalanceAwareProviders(array $providers, float $amount): array
    {
        if ($amount <= 0) {
            return $providers;
        }

        return array_values(array_filter($providers, static function (array $provider) use ($amount): bool {
            if (!array_key_exists('current_balance', $provider) || $provider['current_balance'] === null) {
                return true;
            }
            return (float) $provider['current_balance'] >= $amount;
        }));
    }

    private function filterSuccessThresholdProviders(array $providers, float $settingMinimum): array
    {
        $eligible = [];
        foreach ($providers as $provider) {
            $minimum = max($settingMinimum, (float) ($provider['minimum_success_rate'] ?? 0));
            $rate = $this->successRate((int) $provider['id']);
            if ($rate >= $minimum) {
                $eligible[] = $provider;
            }
        }

        return $eligible;
    }

    private function providerCost(array $provider, array $payload): float
    {
        if (isset($provider['_route_plan_mapping']['amount'])) {
            return (float) $provider['_route_plan_mapping']['amount'];
        }

        $serviceId = (int) ($payload['service_id'] ?? 0);
        $planCode = $this->payloadPlanCode($payload);
        if ($serviceId > 0 && $planCode !== '') {
            $networkCode = $this->pricing->normalizeNetwork((string) ($payload['network'] ?? $payload['provider'] ?? ''));
            $mapping = $this->planService->resolveForProvider((int) $provider['id'], $serviceId, $networkCode, $planCode);
            if ($mapping) {
                return (float) ($mapping['amount'] ?? 0);
            }
        }

        return (float) ($payload['amount'] ?? PHP_FLOAT_MAX);
    }

    private function payloadPlanCode(array $payload): string
    {
        return strtoupper(trim((string) ($payload['plan'] ?? $payload['package'] ?? $payload['exam_type'] ?? $payload['local_plan_code'] ?? '')));
    }

    private function successRate(int $providerId): float
    {
        $row = $this->db->safeFirst(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = "successful" THEN 1 ELSE 0 END), 0) AS successful
             FROM provider_transaction_attempts
             WHERE provider_account_id = :provider_id
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            ['provider_id' => $providerId]
        );

        $total = (int) ($row['total'] ?? 0);
        if ($total === 0) {
            return 100.0;
        }

        return round((((int) ($row['successful'] ?? 0)) / $total) * 100, 2);
    }

    private function averageResponseTimeMs(int $providerId): float
    {
        $row = $this->db->safeFirst(
            'SELECT AVG(response_time_ms) AS avg_ms
             FROM provider_transaction_attempts
             WHERE provider_account_id = :provider_id
               AND response_time_ms IS NOT NULL
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            ['provider_id' => $providerId]
        );

        return (float) ($row['avg_ms'] ?? 0);
    }
}
