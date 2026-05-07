<?php

declare(strict_types=1);

namespace GemData\Classes;

class ApiHandler
{
    public function __construct(
        private Database $db,
        private ApiAuth $auth,
        private TransactionService $transactions
    ) {
    }

    public function handlePurchase(string $serviceSlug): array
    {
        $apiUser = $this->auth->authenticate();
        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload) || $payload === []) {
            $payload = $_POST;
        }
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $payload['idempotency_key'] = $headers['X-Idempotency-Key'] ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ($payload['idempotency_key'] ?? '');
        return $this->transactions->purchase($serviceSlug, (int) $apiUser['user_id'], $payload, 'api', true);
    }

    public function balance(): array
    {
        $apiUser = $this->auth->authenticate();
        $wallet = $this->db->first('SELECT balance FROM wallets WHERE user_id = :user_id LIMIT 1', ['user_id' => $apiUser['user_id']]);
        return [
            'balance' => (float) ($wallet['balance'] ?? 0),
            'currency' => config('app.currency', 'NGN'),
        ];
    }

    public function transactions(): array
    {
        $apiUser = $this->auth->authenticate();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($_GET['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;
        $conditions = ['t.user_id = :user_id'];
        $params = ['user_id' => $apiUser['user_id']];

        if (!empty($_GET['status'])) {
            $conditions[] = 't.status = :status';
            $params['status'] = $_GET['status'];
        }
        if (!empty($_GET['service'])) {
            $conditions[] = 's.slug = :service';
            $params['service'] = $_GET['service'];
        }
        if (!empty($_GET['date_from'])) {
            $conditions[] = 'DATE(t.created_at) >= :date_from';
            $params['date_from'] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $conditions[] = 'DATE(t.created_at) <= :date_to';
            $params['date_to'] = $_GET['date_to'];
        }

        $where = implode(' AND ', $conditions);
        $rows = $this->db->query(
            "SELECT t.reference, t.provider_reference, t.status, t.amount, t.commission_amount, t.recipient, t.created_at, s.slug AS service_slug, s.name AS service_name
             FROM transactions t
             INNER JOIN services s ON s.id = t.service_id
             WHERE {$where}
             ORDER BY t.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return [
            'transactions' => $rows,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
}
