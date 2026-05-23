<?php

declare(strict_types=1);

namespace GemData\Classes;

use DateInterval;
use DateTimeImmutable;

class ReportService
{
    public function __construct(private Database $db)
    {
    }

    public function overview(): array
    {
        return [
            'total_users' => (int) ($this->db->first('SELECT COUNT(*) AS total FROM users')['total'] ?? 0),
            'system_wallet_balance' => (float) ($this->db->first('SELECT COALESCE(SUM(balance), 0) AS total FROM wallets')['total'] ?? 0),
            'today_transactions' => (int) ($this->db->first('SELECT COUNT(*) AS total FROM transactions WHERE DATE(created_at) = CURDATE()')['total'] ?? 0),
            'success_transactions' => (int) ($this->db->first('SELECT COUNT(*) AS total FROM transactions WHERE status = "successful"')['total'] ?? 0),
            'failed_transactions' => (int) ($this->db->first('SELECT COUNT(*) AS total FROM transactions WHERE status = "failed"')['total'] ?? 0),
            'revenue' => (float) ($this->db->first('SELECT COALESCE(SUM(selling_price), 0) AS total FROM transactions WHERE status = "successful"')['total'] ?? 0),
            'profit' => (float) ($this->db->first('SELECT COALESCE(SUM(profit_amount), 0) AS total FROM transactions WHERE status = "successful"')['total'] ?? 0),
        ];
    }

    public function dailySeries(int $days = 7): array
    {
        $days = max(1, $days);
        $cutoff = (new DateTimeImmutable('today'))
            ->sub(new DateInterval('P' . $days . 'D'))
            ->format('Y-m-d H:i:s');

        return $this->db->query(
            'SELECT DATE(created_at) AS period, COUNT(*) AS total_transactions,
                    COALESCE(SUM(CASE WHEN status = "successful" THEN selling_price ELSE 0 END), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN status = "successful" THEN profit_amount ELSE 0 END), 0) AS profit
             FROM transactions
             WHERE created_at >= :cutoff
             GROUP BY DATE(created_at)
             ORDER BY DATE(created_at) ASC',
            ['cutoff' => $cutoff]
        );
    }

    public function topUsers(int $limit = 5): array
    {
        return $this->db->query(
            "SELECT u.id, u.full_name, u.email, u.tier,
                    COUNT(t.id) AS transaction_count,
                    COALESCE(SUM(CASE WHEN t.status = 'successful' THEN t.selling_price ELSE 0 END), 0) AS revenue
             FROM users u
             LEFT JOIN transactions t ON t.user_id = u.id
             GROUP BY u.id
             ORDER BY revenue DESC, transaction_count DESC
             LIMIT {$limit}"
        );
    }

    public function monthlySummary(): array
    {
        return $this->db->query(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS period,
                    COUNT(*) AS total_transactions,
                    COALESCE(SUM(CASE WHEN status = "successful" THEN selling_price ELSE 0 END), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN status = "successful" THEN profit_amount ELSE 0 END), 0) AS profit
             FROM transactions
             GROUP BY DATE_FORMAT(created_at, "%Y-%m")
             ORDER BY DATE_FORMAT(created_at, "%Y-%m") DESC
             LIMIT 12'
        );
    }

    public function providerPerformance(): array
    {
        return $this->db->query(
            'SELECT provider_code,
                    COUNT(*) AS total_transactions,
                    COALESCE(SUM(CASE WHEN status = "successful" THEN 1 ELSE 0 END), 0) AS successful_transactions,
                    COALESCE(SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END), 0) AS failed_transactions,
                    COALESCE(SUM(CASE WHEN status = "successful" THEN profit_amount ELSE 0 END), 0) AS profit
             FROM transactions
             GROUP BY provider_code
             ORDER BY total_transactions DESC'
        );
    }

    public function activity(int $limit = 25): array
    {
        return $this->db->query(
            "SELECT * FROM activity_logs ORDER BY id DESC LIMIT {$limit}"
        );
    }

    public function topServices(int $limit = 10): array
    {
        return $this->db->query(
            "SELECT s.name, COUNT(t.id) AS total_transactions,
                    COALESCE(SUM(CASE WHEN t.status = 'successful' THEN t.selling_price ELSE 0 END), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN t.status = 'successful' THEN t.profit_amount ELSE 0 END), 0) AS profit
             FROM services s
             LEFT JOIN transactions t ON t.service_id = s.id
             GROUP BY s.id
             ORDER BY total_transactions DESC, revenue DESC
             LIMIT {$limit}"
        );
    }

    public function failedBreakdown(): array
    {
        return $this->db->query(
            "SELECT s.name, COALESCE(t.failure_code, 'unknown') AS failure_code, COUNT(*) AS total
             FROM transactions t
             INNER JOIN services s ON s.id = t.service_id
             WHERE t.status = 'failed'
             GROUP BY s.id, t.failure_code
             ORDER BY total DESC
             LIMIT 25"
        );
    }

    public function refundReport(): array
    {
        return $this->db->query(
            "SELECT DATE(created_at) AS period, COUNT(*) AS refunds, COALESCE(SUM(amount), 0) AS amount
             FROM wallet_transactions
             WHERE type = 'refund'
             GROUP BY DATE(created_at)
             ORDER BY DATE(created_at) DESC
             LIMIT 30"
        );
    }

    public function userGrowth(): array
    {
        return $this->db->query(
            "SELECT DATE(created_at) AS period, COUNT(*) AS users
             FROM users
             GROUP BY DATE(created_at)
             ORDER BY DATE(created_at) DESC
             LIMIT 30"
        );
    }

    public function apiUsage(): array
    {
        if (!$this->db->tableExists('api_usage_records')) {
            return [];
        }
        return $this->db->query(
            "SELECT aur.*, u.full_name
             FROM api_usage_records aur
             INNER JOIN api_users au ON au.id = aur.api_user_id
             INNER JOIN users u ON u.id = au.user_id
             ORDER BY aur.usage_date DESC, aur.request_count DESC
             LIMIT 30"
        );
    }

    public function providerResponseTimes(): array
    {
        if (!$this->db->tableExists('provider_transaction_attempts')) {
            return [];
        }
        return $this->db->query(
            "SELECT provider_code,
                    COUNT(*) AS attempts,
                    COALESCE(AVG(response_time_ms), 0) AS avg_response_ms,
                    COALESCE(SUM(CASE WHEN status = 'successful' THEN 1 ELSE 0 END), 0) AS successful_attempts,
                    COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) AS failed_attempts
             FROM provider_transaction_attempts
             GROUP BY provider_code
             ORDER BY avg_response_ms DESC
             LIMIT 20"
        );
    }

    public function queueReadiness(): array
    {
        $pending = (int) ($this->db->first("SELECT COUNT(*) AS total FROM transactions WHERE status = 'pending'")['total'] ?? 0);
        $stale = (int) ($this->db->first("SELECT COUNT(*) AS total FROM transactions WHERE status = 'pending' AND created_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)")['total'] ?? 0);
        $deadLetters = $this->db->tableExists('webhook_dead_letters')
            ? (int) ($this->db->first("SELECT COUNT(*) AS total FROM webhook_dead_letters WHERE status IN ('pending','retrying','dead')")['total'] ?? 0)
            : 0;

        return [
            'pending_transactions' => $pending,
            'stale_pending' => $stale,
            'webhook_dead_letters' => $deadLetters,
        ];
    }
}
