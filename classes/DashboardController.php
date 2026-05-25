<?php

declare(strict_types=1);

namespace GemData\Classes;

class DashboardController
{
    public function __construct(
        private Database $db,
        private Wallet $wallet,
        private FundingAccountProviderService $fundingAccounts,
        private ProviderPlanService $providerPlans,
        private UserRoleManager $roles
    ) {
    }

    public function dataFor(array $user): array
    {
        $userId = (int) $user['id'];
        $role = $this->roles->roleFor($user);
        $wallet = $this->wallet->ensure($userId);
        $fundingAccount = $this->fundingAccounts->primaryDisplayAccountForUser($userId);
        $fundingAccountRows = $this->fundingAccounts->displayAccountsForUser($userId);
        $services = $this->services();
        $recentTransactions = $this->recentTransactions($userId);

        return [
            'user' => $user,
            'role' => $role,
            'role_label' => $this->roles->label($role),
            'wallet' => $wallet,
            'funding_account' => $fundingAccount,
            'funding_accounts' => $fundingAccountRows,
            'funding_active_provider' => $this->fundingAccounts->activeProvider(),
            'funding_multi_provider' => $this->fundingAccounts->multiProviderFunding(),
            'services' => $services,
            'service_meta' => $this->serviceMeta(),
            'service_networks' => $this->serviceNetworks(),
            'data_plan_catalog' => $this->providerPlans->catalogForServiceSlug('data'),
            'recent_transactions' => $recentTransactions,
            'stats' => $this->stats($userId, $role),
            'upgrade' => $this->upgradeState($userId, $role),
            'kyc_status' => ($fundingAccount['status'] ?? '') === 'assigned' ? 'Verified' : 'Pending',
            'api' => $role === 'api' ? $this->apiSummary($userId) : null,
            'reseller' => in_array($role, ['reseller', 'api'], true) ? $this->resellerSummary($userId) : null,
        ];
    }

    private function services(): array
    {
        return $this->db->query(
            "SELECT * FROM services
             WHERE is_enabled = 1
             ORDER BY FIELD(slug, 'airtime', 'data', 'cable_tv', 'electricity', 'data_card', 'exam_pin', 'recharge_card', 'bulk_sms'), name"
        );
    }

    private function recentTransactions(int $userId): array
    {
        return $this->db->query(
            'SELECT t.*, s.name AS service_name FROM transactions t
             INNER JOIN services s ON s.id = t.service_id
             WHERE t.user_id = :user_id ORDER BY t.id DESC LIMIT 6',
            ['user_id' => $userId]
        );
    }

    private function serviceNetworks(): array
    {
        $rows = $this->db->safeQuery(
            'SELECT s.slug, sn.network_code, sn.network_name
             FROM service_networks sn
             INNER JOIN services s ON s.id = sn.service_id
             WHERE sn.is_enabled = 1
             ORDER BY s.slug, sn.network_name'
        );

        $networks = [];
        foreach ($rows as $row) {
            $networks[$row['slug']][] = $row;
        }

        return $networks;
    }

    private function stats(int $userId, string $role): array
    {
        $summary = $this->db->first(
            'SELECT COUNT(*) AS total_transactions,
                    COALESCE(SUM(amount), 0) AS total_spend,
                    COALESCE(SUM(commission_amount), 0) AS commission_total
             FROM transactions WHERE user_id = :user_id',
            ['user_id' => $userId]
        ) ?? [];

        $referrals = $this->db->safeFirst(
            'SELECT COUNT(*) AS total_referrals FROM users WHERE referred_by_user_id = :user_id',
            ['user_id' => $userId]
        ) ?? [];

        return [
            'transactions' => (int) ($summary['total_transactions'] ?? 0),
            'total_spend' => (float) ($summary['total_spend'] ?? 0),
            'commission_total' => (float) ($summary['commission_total'] ?? 0),
            'referrals' => (int) ($referrals['total_referrals'] ?? 0),
            'role' => $role,
        ];
    }

    private function resellerSummary(int $userId): array
    {
        $wallet = $this->db->safeFirst(
            'SELECT balance FROM commission_wallets WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $userId]
        ) ?? ['balance' => 0];

        $totals = $this->db->safeFirst(
            'SELECT
                COALESCE(SUM(CASE WHEN type = "credit" THEN amount ELSE 0 END), 0) AS total_earned,
                COALESCE(SUM(CASE WHEN type = "withdrawal" THEN amount ELSE 0 END), 0) AS total_withdrawn
             FROM commission_wallet_transactions
             WHERE user_id = :user_id',
            ['user_id' => $userId]
        ) ?? ['total_earned' => 0, 'total_withdrawn' => 0];

        $pendingWithdrawal = $this->db->safeFirst(
            'SELECT COALESCE(SUM(amount), 0) AS total
             FROM withdrawal_requests
             WHERE user_id = :user_id AND status = "pending"',
            ['user_id' => $userId]
        ) ?? ['total' => 0];

        $rates = $this->db->safeQuery(
            'SELECT
                s.slug,
                s.name,
                s.is_enabled,
                cu.rate_percent AS user_rate_percent,
                cd.rate_percent AS default_rate_percent,
                COALESCE(cu.rate_percent, cd.rate_percent, 0.00) AS rate_percent,
                CASE WHEN cu.id IS NOT NULL OR cd.id IS NOT NULL THEN 1 ELSE 0 END AS commission_enabled,
                CASE
                    WHEN cu.id IS NOT NULL THEN "User override"
                    WHEN cd.id IS NOT NULL THEN "Default"
                    ELSE "Not configured"
                END AS source_label
             FROM services s
             LEFT JOIN commissions cu ON cu.service_id = s.id AND cu.user_id = :user_id
             LEFT JOIN commissions cd ON cd.service_id = s.id AND cd.user_id IS NULL
             WHERE s.is_enabled = 1
             ORDER BY FIELD(s.slug, "airtime", "data", "cable_tv", "electricity", "exam_pin", "recharge_card", "bulk_sms", "data_card"), s.name',
            ['user_id' => $userId]
        );

        return [
            'commission_balance' => (float) ($wallet['balance'] ?? 0),
            'total_earned' => (float) ($totals['total_earned'] ?? 0),
            'total_withdrawn' => (float) ($totals['total_withdrawn'] ?? 0),
            'pending_withdrawal' => (float) ($pendingWithdrawal['total'] ?? 0),
            'rates' => $rates,
            'estimated_profit' => (float) ($wallet['balance'] ?? 0),
        ];
    }

    private function apiSummary(int $userId): array
    {
        $apiUser = $this->db->safeFirst('SELECT * FROM api_users WHERE user_id = :user_id LIMIT 1', ['user_id' => $userId]);
        $apiUserId = (int) ($apiUser['id'] ?? 0);
        $keys = $apiUserId > 0
            ? $this->db->safeQuery('SELECT * FROM api_keys WHERE api_user_id = :api_user_id ORDER BY created_at DESC', ['api_user_id' => $apiUserId])
            : [];
        $usage = $this->db->safeFirst(
            'SELECT COUNT(*) AS total_requests,
                    SUM(CASE WHEN status = "successful" THEN 1 ELSE 0 END) AS successful,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed,
                    COALESCE(SUM(amount), 0) AS total_volume
             FROM transactions
             WHERE user_id = :user_id AND channel = "api"
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            ['user_id' => $userId]
        ) ?? [];

        return [
            'account' => $apiUser,
            'keys' => $keys,
            'usage' => $usage,
            'success_rate' => (int) ($usage['total_requests'] ?? 0) > 0
                ? round(((int) ($usage['successful'] ?? 0) / (int) $usage['total_requests']) * 100, 1)
                : 0,
        ];
    }

    private function upgradeState(int $userId, string $role): array
    {
        $latest = $this->db->safeFirst(
            'SELECT * FROM upgrade_requests WHERE user_id = :user_id ORDER BY id DESC LIMIT 1',
            ['user_id' => $userId]
        );

        return [
            'latest' => $latest,
            'target' => $this->roles->nextRole($role),
            'title' => match ($role) {
                'api' => 'API User Active',
                'reseller' => 'Request API Access',
                default => 'Upgrade to Reseller',
            },
            'text' => match ($role) {
                'api' => 'Your developer tools and integration center are ready.',
                'reseller' => 'Connect your website/app and automate transactions.',
                default => 'Unlock better pricing, higher limits, bulk tools & business benefits.',
            },
        ];
    }

    private function serviceMeta(): array
    {
        return [
            'airtime' => ['tag' => 'Instant top-up', 'summary' => 'Recharge any phone number.', 'description' => 'Top up any line instantly from your wallet.', 'expected' => 'Usually completes within seconds.', 'example' => 'Example: MTN, 08030000000, NGN 1000'],
            'data' => ['tag' => 'Bundle purchase', 'summary' => 'Buy mobile data bundles.', 'description' => 'Activate a mobile data plan with wallet payment.', 'expected' => 'Popular choice for repeat buyers and resellers.', 'example' => 'Example: Airtel, 2GB SME, 08030000000'],
            'electricity' => ['tag' => 'Meter token', 'summary' => 'Pay meter bills and tokens.', 'description' => 'Pay utility bills for prepaid or postpaid meters.', 'expected' => 'Confirm meter details before submitting.', 'example' => 'Example: Prepaid, 12345678901, NGN 5000'],
            'cable_tv' => ['tag' => 'Subscription', 'summary' => 'Renew TV subscriptions.', 'description' => 'Renew DStv, GOtv, or Startimes packages.', 'expected' => 'Great for quick package renewals.', 'example' => 'Example: GOtv, 1234567890, Max'],
            'exam_pin' => ['tag' => 'Education utility', 'summary' => 'Generate WAEC, NECO, JAMB PINs.', 'description' => 'Generate education PINs from one workspace.', 'expected' => 'PIN generation appears after successful processing.', 'example' => 'Example: WAEC, Qty 1'],
            'bulk_sms' => ['tag' => 'Messaging', 'summary' => 'Broadcast messages quickly.', 'description' => 'Send campaign or operational SMS in one flow.', 'expected' => 'Double-check recipients before sending.', 'example' => 'Example: GemData, 0803..., promo message'],
            'data_card' => ['tag' => 'Bulk data cards', 'summary' => 'Generate bulk data cards.', 'description' => 'Prepare bulk data card batches with plan control.', 'expected' => 'Designed for repeat, high-volume fulfilment.', 'example' => 'Example: MTN, 5GB, Qty 5'],
            'recharge_card' => ['tag' => 'Voucher generation', 'summary' => 'Print recharge voucher batches.', 'description' => 'Create recharge card batches for reseller use.', 'expected' => 'Useful for outlet and reseller batches.', 'example' => 'Example: Glo, Qty 10'],
        ];
    }
}
