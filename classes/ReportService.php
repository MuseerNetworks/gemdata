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
}
