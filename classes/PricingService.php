<?php

declare(strict_types=1);

namespace GemData\Classes;

class PricingService
{
    public function __construct(private Database $db, private ?SimpleCache $cache = null)
    {
    }

    private function networkMatchClause(): string
    {
        return '((network_code IS NULL AND :network_code_is_null = 1) OR network_code = :network_code_value)';
    }

    private function networkMatchParams(?string $networkCode): array
    {
        return [
            'network_code_is_null' => $networkCode === null ? 1 : 0,
            'network_code_value' => $networkCode,
        ];
    }

    public function normalizeNetwork(?string $network): ?string
    {
        $network = strtolower(trim((string) $network));
        if ($network === '') {
            return null;
        }

        return match ($network) {
            'mtn' => 'mtn',
            'airtel' => 'airtel',
            'glo' => 'glo',
            '9mobile', 'etisalat' => '9mobile',
            default => $network,
        };
    }

    public function resolveUserTier(array $user, bool $isApiUser = false): string
    {
        $tier = strtoupper((string) ($user['tier'] ?? 'USER'));
        if ($isApiUser && $tier === 'USER') {
            return 'API_RESELLER';
        }

        return in_array($tier, ['USER', 'RESELLER', 'AGENT', 'API_RESELLER'], true) ? $tier : 'USER';
    }

    public function resolve(int $userId, int $serviceId, ?string $network, float $requestedAmount, bool $isApiUser = false): array
    {
        $cacheKey = 'pricing:resolve:' . $userId . ':' . $serviceId . ':' . ($network ?? 'none') . ':' . ($isApiUser ? 'api' : 'web');
        if ($requestedAmount <= 0 && $this->cache) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $user = $this->db->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $userId]) ?? [];
        $tier = $this->resolveUserTier($user, $isApiUser);
        $networkCode = $this->normalizeNetwork($network);

        $custom = $this->db->first(
            'SELECT selling_price
             FROM user_custom_prices
             WHERE user_id = :user_id AND service_id = :service_id AND ' . $this->networkMatchClause() . '
             LIMIT 1',
            array_merge(
                ['user_id' => $userId, 'service_id' => $serviceId],
                $this->networkMatchParams($networkCode)
            )
        );
        if ($custom) {
            $selling = (float) $custom['selling_price'];
            $resolved = [
                'tier' => $tier,
                'network_code' => $networkCode,
                'selling_price' => $requestedAmount > 0 ? $requestedAmount : $selling,
                'cost_price' => $selling,
                'profit_amount' => max(0, ($requestedAmount > 0 ? $requestedAmount : $selling) - $selling),
                'pricing_source' => 'user_override',
            ];
            if ($requestedAmount <= 0 && $this->cache) {
                $this->cache->put($cacheKey, $resolved, 120);
            }
            return $resolved;
        }

        $price = $this->db->safeFirst(
            'SELECT *
             FROM service_prices
             WHERE service_id = :service_id AND tier = :tier AND ' . $this->networkMatchClause() . '
             LIMIT 1',
            array_merge(
                ['service_id' => $serviceId, 'tier' => $tier],
                $this->networkMatchParams($networkCode)
            )
        );
        if (!$price && $tier !== 'USER') {
            $price = $this->db->safeFirst(
                'SELECT *
                 FROM service_prices
                 WHERE service_id = :service_id AND tier = :tier AND network_code IS NULL
                 LIMIT 1',
                ['service_id' => $serviceId, 'tier' => 'USER']
            );
        }

        $configuredSelling = (float) ($price['selling_price'] ?? 0);
        $costPrice = (float) ($price['cost_price'] ?? 0);
        $sellingPrice = $requestedAmount > 0 ? $requestedAmount : $configuredSelling;
        $profitAmount = max(0, $sellingPrice - ($costPrice > 0 ? $costPrice : $sellingPrice));

        $resolved = [
            'tier' => $tier,
            'network_code' => $networkCode,
            'selling_price' => $sellingPrice,
            'cost_price' => $costPrice > 0 ? $costPrice : $sellingPrice,
            'profit_amount' => $profitAmount,
            'pricing_source' => $price ? 'tier' : 'legacy',
        ];
        if ($requestedAmount <= 0 && $this->cache) {
            $this->cache->put($cacheKey, $resolved, 120);
        }

        return $resolved;
    }

    public function tierPricesByService(int $serviceId): array
    {
        $cacheKey = 'pricing:tier-prices:' . $serviceId;
        $cached = $this->cache?->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $rows = $this->db->safeQuery(
            'SELECT * FROM service_prices WHERE service_id = :service_id ORDER BY network_code IS NULL DESC, network_code, tier',
            ['service_id' => $serviceId]
        );
        $this->cache?->put($cacheKey, $rows, 120);
        return $rows;
    }

    public function upsertTierPrice(int $serviceId, ?string $networkCode, string $tier, float $costPrice, float $sellingPrice): void
    {
        $networkCode = $this->normalizeNetwork($networkCode);
        $existing = $this->db->first(
            'SELECT id FROM service_prices WHERE service_id = :service_id AND tier = :tier AND ' . $this->networkMatchClause() . ' LIMIT 1',
            array_merge(
                ['service_id' => $serviceId, 'tier' => $tier],
                $this->networkMatchParams($networkCode)
            )
        );

        $profitMargin = $sellingPrice - $costPrice;
        if ($existing) {
            $this->db->execute(
                'UPDATE service_prices
                 SET cost_price = :cost_price, selling_price = :selling_price, profit_margin = :profit_margin
                 WHERE id = :id',
                [
                    'cost_price' => $costPrice,
                    'selling_price' => $sellingPrice,
                    'profit_margin' => $profitMargin,
                    'id' => $existing['id'],
                ]
            );
            $this->cache?->forget('pricing:tier-prices:' . $serviceId);
            return;
        }

        $this->db->execute(
            'INSERT INTO service_prices (service_id, network_code, tier, cost_price, selling_price, profit_margin)
             VALUES (:service_id, :network_code, :tier, :cost_price, :selling_price, :profit_margin)',
            [
                'service_id' => $serviceId,
                'network_code' => $networkCode,
                'tier' => $tier,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'profit_margin' => $profitMargin,
            ]
        );
        $this->cache?->forget('pricing:tier-prices:' . $serviceId);
    }

    public function upsertUserPrice(int $userId, int $serviceId, ?string $networkCode, float $sellingPrice): void
    {
        $networkCode = $this->normalizeNetwork($networkCode);
        $existing = $this->db->first(
            'SELECT id FROM user_custom_prices WHERE user_id = :user_id AND service_id = :service_id AND ' . $this->networkMatchClause() . ' LIMIT 1',
            array_merge(
                ['user_id' => $userId, 'service_id' => $serviceId],
                $this->networkMatchParams($networkCode)
            )
        );

        if ($existing) {
            $this->db->execute('UPDATE user_custom_prices SET selling_price = :selling_price WHERE id = :id', [
                'selling_price' => $sellingPrice,
                'id' => $existing['id'],
            ]);
            $this->cache?->forget('pricing:tier-prices:' . $serviceId);
            return;
        }

        $this->db->execute(
            'INSERT INTO user_custom_prices (user_id, service_id, network_code, selling_price)
             VALUES (:user_id, :service_id, :network_code, :selling_price)',
            [
                'user_id' => $userId,
                'service_id' => $serviceId,
                'network_code' => $networkCode,
                'selling_price' => $sellingPrice,
            ]
        );
        $this->cache?->forget('pricing:tier-prices:' . $serviceId);
    }
}
