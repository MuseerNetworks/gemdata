<?php

declare(strict_types=1);

namespace GemData\Classes;

use RuntimeException;

class ProviderPlanCatalogService
{
    public const COLUMNS = [
        'service_slug',
        'network_code',
        'local_plan_code',
        'local_plan_name',
        'validity_label',
        'provider_plan_id',
        'provider_plan_name',
        'amount',
        'provider_cost_price',
        'is_enabled',
    ];

    private const LEGACY_COLUMNS = [
        'service_slug',
        'network_code',
        'local_plan_code',
        'local_plan_name',
        'provider_plan_id',
        'provider_plan_name',
        'amount',
        'is_enabled',
    ];

    private const COST_COLUMNS = [
        'service_slug',
        'network_code',
        'local_plan_code',
        'local_plan_name',
        'provider_plan_id',
        'provider_plan_name',
        'amount',
        'provider_cost_price',
        'is_enabled',
    ];

    public const UNSUPPORTED_SYNC_MESSAGE = 'This provider does not currently support automatic plan synchronization. Please use CSV Import, Bulk Paste Import, or Manual Add.';

    public function __construct(
        private Database $db,
        private PricingService $pricing,
        private ProviderPlanService $providerPlans,
        private AppLogger $logger
    ) {
    }

    public function supportsSync(array $provider): bool
    {
        return $this->adapterFor($provider)->supportsSync($provider);
    }

    public function syncPlans(int $providerId, int $serviceId): array
    {
        $provider = $this->provider($providerId);
        $service = $this->service($serviceId);
        $adapter = $this->adapterFor($provider);

        if (!$adapter->supportsSync($provider)) {
            return [
                'supported' => false,
                'message' => self::UNSUPPORTED_SYNC_MESSAGE,
                'rows' => [],
                'errors' => [],
            ];
        }

        $rows = $adapter->syncPlans($provider, $service);
        $preview = $this->previewRows('sync', $provider, $service, $rows);
        $preview['supported'] = true;
        $preview['message'] = count($preview['rows']) . ' provider plan(s) fetched for review.';

        return $preview;
    }

    public function previewCsvUpload(array $file, int $providerId, int $serviceId): array
    {
        $provider = $this->provider($providerId);
        $service = $this->service($serviceId);

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [
                'supported' => true,
                'message' => '',
                'rows' => [],
                'errors' => ['Upload a CSV file before previewing.'],
            ];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return [
                'supported' => true,
                'message' => '',
                'rows' => [],
                'errors' => ['The uploaded CSV file could not be read.'],
            ];
        }

        return $this->previewRows('csv', $provider, $service, $this->rowsFromCsvFile($tmpName));
    }

    public function previewBulkText(string $text, int $providerId, int $serviceId): array
    {
        $provider = $this->provider($providerId);
        $service = $this->service($serviceId);
        $rows = $this->rowsFromDelimitedText($text);

        return $this->previewRows('bulk', $provider, $service, $rows);
    }

    public function previewManual(array $payload): array
    {
        $provider = $this->provider((int) ($payload['provider_account_id'] ?? 0));
        $service = $this->service((int) ($payload['service_id'] ?? 0));

        return $this->previewRows('manual', $provider, $service, [[
            'service_slug' => (string) ($payload['service_slug'] ?? $service['slug']),
            'network_code' => (string) ($payload['network_code'] ?? ''),
            'local_plan_code' => (string) ($payload['local_plan_code'] ?? ''),
            'local_plan_name' => (string) ($payload['local_plan_name'] ?? ''),
            'validity_label' => (string) ($payload['validity_label'] ?? ''),
            'provider_plan_id' => (string) ($payload['provider_plan_id'] ?? ''),
            'provider_plan_name' => (string) ($payload['provider_plan_name'] ?? ''),
            'amount' => (string) ($payload['amount'] ?? ''),
            'provider_cost_price' => (string) ($payload['provider_cost_price'] ?? ''),
            'is_enabled' => !empty($payload['is_enabled']) ? 1 : 0,
        ]]);
    }

    public function saveManual(array $payload): array
    {
        $preview = $this->previewManual($payload);
        $plan = $preview['rows'][0] ?? null;
        if (!$plan) {
            throw new RuntimeException('Manual plan row could not be prepared for saving.');
        }

        $this->saveNormalizedPlan($plan);

        return [
            'saved' => 1,
            'skipped' => 0,
            'errors' => [],
        ];
    }

    public function previewRows(string $source, array $provider, array $defaultService, array $rows): array
    {
        $services = $this->servicesBySlug();
        $preview = [];
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                $errors[] = 'Row ' . ($index + 1) . ' is not a valid plan row.';
                continue;
            }

            $serviceSlug = strtolower(trim((string) ($row['service_slug'] ?? $defaultService['slug'] ?? '')));
            $service = $services[$serviceSlug] ?? null;
            if (!$service) {
                $errors[] = 'Row ' . ($index + 1) . ': service_slug is invalid or missing.';
                continue;
            }

            $providerPlanId = trim((string) ($row['provider_plan_id'] ?? ''));
            $providerPlanName = trim((string) ($row['provider_plan_name'] ?? ''));
            $localPlanName = trim((string) ($row['local_plan_name'] ?? $providerPlanName));
            $validityLabel = $this->normalizeValidityLabel((string) ($row['validity_label'] ?? ''));
            $amount = $this->normalizeAmount($row['amount'] ?? null);
            $providerCostPrice = $this->normalizeOptionalAmount($row['provider_cost_price'] ?? null);
            $networkCode = $this->normalizeNetworkCode((string) ($row['network_code'] ?? ''));
            $localPlanCode = trim((string) ($row['local_plan_code'] ?? ''));
            if ($localPlanCode === '') {
                $localPlanCode = $this->generateLocalPlanCode($serviceSlug, $networkCode, $providerPlanId, $localPlanName);
            } else {
                $localPlanCode = $this->normalizeLocalPlanCode($localPlanCode);
            }

            $rowErrors = [];
            if ($providerPlanId === '') {
                $rowErrors[] = 'provider_plan_id is required.';
            }
            if ($localPlanName === '') {
                $rowErrors[] = 'local_plan_name is required.';
            }
            if ($amount === null) {
                $rowErrors[] = 'amount must be a valid number.';
            }
            if ($providerCostPrice === false) {
                $rowErrors[] = 'provider_cost_price must be a valid number or blank.';
            }

            $preview[] = [
                'source' => $source,
                'provider_account_id' => (int) $provider['id'],
                'provider_code' => (string) $provider['code'],
                'provider_name' => (string) $provider['name'],
                'service_id' => (int) $service['id'],
                'service_slug' => (string) $service['slug'],
                'service_name' => (string) $service['name'],
                'network_code' => $networkCode,
                'local_plan_code' => $localPlanCode,
                'local_plan_name' => $localPlanName,
                'validity_label' => $validityLabel,
                'provider_plan_id' => $providerPlanId,
                'provider_plan_name' => $providerPlanName,
                'amount' => $amount ?? 0.0,
                'provider_cost_price' => $providerCostPrice === false ? null : $providerCostPrice,
                'is_enabled' => $this->truthy($row['is_enabled'] ?? 0) ? 1 : 0,
                'selected' => $rowErrors === [],
                'errors' => $rowErrors,
            ];
        }

        return [
            'supported' => true,
            'message' => count($preview) . ' plan row(s) ready for review.',
            'rows' => $preview,
            'errors' => $errors,
        ];
    }

    public function publishRows(array $payload): array
    {
        $rows = $this->postedRows($payload);
        $saved = 0;
        $skipped = 0;
        $errors = [];
        $selected = 0;

        if ($rows === []) {
            return [
                'saved' => 0,
                'skipped' => 0,
                'errors' => ['No plan rows were submitted for saving.'],
            ];
        }

        foreach ($rows as $index => $row) {
            if (!$this->truthy($row['selected'] ?? 0)) {
                $skipped++;
                continue;
            }
            $selected++;

            try {
                $provider = $this->provider((int) ($row['provider_account_id'] ?? 0));
                $service = $this->service((int) ($row['service_id'] ?? 0));
                $preview = $this->previewRows('publish', $provider, $service, [$row]);
                $plan = $preview['rows'][0] ?? null;
                if (!$plan) {
                    throw new RuntimeException('Invalid row.');
                }

                $this->saveNormalizedPlan($plan + [
                    'is_enabled' => $this->truthy($row['is_enabled'] ?? 0) ? 1 : 0,
                ]);
                $saved++;
            } catch (\Throwable $throwable) {
                $errors[] = 'Row ' . ($index + 1) . ': ' . $throwable->getMessage();
            }
        }

        if ($selected === 0) {
            $errors[] = 'Select at least one plan row before saving.';
        } elseif ($saved === 0 && $errors === []) {
            $errors[] = 'No plan rows were saved.';
        }

        return [
            'saved' => $saved,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    public function deleteMapping(int $mappingId): void
    {
        $this->db->execute('DELETE FROM provider_service_plans WHERE id = :id', ['id' => $mappingId]);
        $this->providerPlans->invalidate();
    }

    private function saveNormalizedPlan(array $plan): void
    {
        $errors = $plan['errors'] ?? [];
        if (is_array($errors) && $errors !== []) {
            throw new RuntimeException(implode(' ', array_map('strval', $errors)));
        }

        $this->providerPlans->upsertMapping([
            'provider_account_id' => (int) ($plan['provider_account_id'] ?? 0),
            'service_id' => (int) ($plan['service_id'] ?? 0),
            'network_code' => $plan['network_code'] ?? null,
            'local_plan_code' => (string) ($plan['local_plan_code'] ?? ''),
            'local_plan_name' => (string) ($plan['local_plan_name'] ?? ''),
            'validity_label' => (string) ($plan['validity_label'] ?? ''),
            'provider_plan_id' => (string) ($plan['provider_plan_id'] ?? ''),
            'provider_plan_name' => (string) ($plan['provider_plan_name'] ?? ''),
            'amount' => (float) ($plan['amount'] ?? 0),
            'provider_cost_price' => $plan['provider_cost_price'] ?? null,
            'is_enabled' => !empty($plan['is_enabled']) ? 1 : 0,
        ]);
    }

    private function adapterFor(array $provider): ProviderPlanCatalogAdapterInterface
    {
        $driver = strtolower((string) ($provider['driver'] ?? ''));
        if (in_array($driver, ['vtung', 'nigerianvtu'], true)) {
            return new VtuNgPlanCatalogAdapter($this->logger);
        }
        if ($driver === 'cheapdatahub') {
            return new CheapDataHubPlanCatalogAdapter($this->logger);
        }

        return new UnsupportedProviderPlanCatalogAdapter();
    }

    private function provider(int $providerId): array
    {
        $provider = $this->db->first('SELECT * FROM provider_accounts WHERE id = :id LIMIT 1', ['id' => $providerId]);
        if (!$provider) {
            throw new RuntimeException('Provider not found.');
        }
        if (!RealProviderRegistry::isAllowedDriver((string) ($provider['driver'] ?? '')) || (string) ($provider['status'] ?? '') === 'archived') {
            throw new RuntimeException('This provider is archived or is not registered as a real production provider.');
        }

        return $provider;
    }

    private function service(int $serviceId): array
    {
        $service = $this->db->first('SELECT * FROM services WHERE id = :id LIMIT 1', ['id' => $serviceId]);
        if (!$service) {
            throw new RuntimeException('Service not found.');
        }

        return $service;
    }

    private function servicesBySlug(): array
    {
        $services = [];
        foreach ($this->db->query('SELECT id, slug, name FROM services ORDER BY name') as $service) {
            $services[(string) $service['slug']] = $service;
        }

        return $services;
    }

    private function rowsFromCsvFile(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return [];
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $this->tabularRowsToAssoc($rows);
    }

    private function rowsFromDelimitedText(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\R+/', trim($text)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $delimiter = str_contains($line, "\t") ? "\t" : (str_contains($line, '|') ? '|' : ',');
            $rows[] = str_getcsv($line, $delimiter);
        }

        return $this->tabularRowsToAssoc($rows);
    }

    private function tabularRowsToAssoc(array $rows): array
    {
        $assoc = [];
        $header = null;
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $row = array_map(static fn($value): string => trim((string) $value), $row);
            if ($row === [] || implode('', $row) === '') {
                continue;
            }

            if ($index === 0) {
                $maybeHeader = array_map(static fn($value): string => strtolower(trim($value)), $row);
                if (in_array('provider_plan_id', $maybeHeader, true) || in_array('service_slug', $maybeHeader, true)) {
                    $header = $maybeHeader;
                    continue;
                }
            }

            $columns = $header ?: match (count($row)) {
                count(self::LEGACY_COLUMNS) => self::LEGACY_COLUMNS,
                count(self::COST_COLUMNS) => self::COST_COLUMNS,
                default => self::COLUMNS,
            };
            $item = [];
            foreach ($columns as $columnIndex => $columnName) {
                if (!in_array($columnName, self::COLUMNS, true)) {
                    continue;
                }
                $item[$columnName] = $row[$columnIndex] ?? '';
            }
            $assoc[] = $item;
        }

        return $assoc;
    }

    private function postedRows(array $payload): array
    {
        $posted = is_array($payload['plans'] ?? null) ? $payload['plans'] : [];
        return array_values(array_filter($posted, 'is_array'));
    }

    private function normalizeNetworkCode(string $network): ?string
    {
        $network = strtolower(trim($network));
        if ($network === '') {
            return null;
        }

        $network = preg_replace('/\s+/', '', $network) ?? $network;
        return match ($network) {
            'mtn' => 'mtn',
            'airtel' => 'airtel',
            'glo' => 'glo',
            '9mobile', '9mob', 'etisalat' => '9mobile',
            default => preg_replace('/[^a-z0-9_-]/', '', $network) ?: null,
        };
    }

    private function normalizeAmount(mixed $amount): ?float
    {
        $amount = str_replace([',', 'NGN', '₦', ' '], '', (string) $amount);
        if ($amount === '' || !is_numeric($amount)) {
            return null;
        }

        $value = round((float) $amount, 2);
        return $value > 0 ? $value : null;
    }

    private function normalizeOptionalAmount(mixed $amount): float|false|null
    {
        $amount = str_replace([',', 'NGN', '₦', 'â‚¦', ' '], '', (string) $amount);
        if ($amount === '') {
            return null;
        }
        if (!is_numeric($amount)) {
            return false;
        }

        $value = round((float) $amount, 2);
        return $value >= 0 ? $value : false;
    }

    private function normalizeValidityLabel(string $validityLabel): string
    {
        $validityLabel = trim(preg_replace('/\s+/', ' ', $validityLabel) ?? $validityLabel);
        return substr($validityLabel, 0, 80);
    }

    private function generateLocalPlanCode(string $serviceSlug, ?string $networkCode, string $providerPlanId, string $localPlanName): string
    {
        $seed = implode('_', array_filter([$serviceSlug, $networkCode, $providerPlanId, $localPlanName]));
        return $this->normalizeLocalPlanCode($seed);
    }

    private function normalizeLocalPlanCode(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9:_\.-]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return substr($value !== '' ? $value : 'PLAN_' . bin2hex(random_bytes(3)), 0, 120);
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on', 'enabled', 'publish', 'published'], true);
    }
}
