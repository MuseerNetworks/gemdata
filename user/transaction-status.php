<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$reference = strtoupper(trim((string) ($_GET['reference'] ?? $_POST['reference'] ?? '')));
$response = app(\GemData\Classes\Response::class);

if ($reference === '') {
    $response->json('error', 'Transaction reference is required.', [], ['reference' => ['Transaction reference is required.']], [], 422);
}

$transaction = db()->first(
    'SELECT t.id, t.reference, t.provider_code, t.status, t.failure_code, t.amount, t.recipient, t.created_at, t.processed_at,
            s.name AS service_name, s.slug AS service_slug
     FROM transactions t
     INNER JOIN services s ON s.id = t.service_id
     WHERE t.reference = :reference AND t.user_id = :user_id
     LIMIT 1',
    ['reference' => $reference, 'user_id' => (int) $user['id']]
);

if (!$transaction) {
    $response->json('error', 'Transaction was not found.', [], ['reference' => ['Transaction was not found.']], [], 404);
}

$recentRows = db()->query(
    'SELECT t.reference, t.provider_code, t.status, t.failure_code, t.amount, t.recipient, t.created_at, t.processed_at,
            s.name AS service_name, s.slug AS service_slug
     FROM transactions t
     INNER JOIN services s ON s.id = t.service_id
     WHERE t.user_id = :user_id
     ORDER BY t.id DESC
     LIMIT 5',
    ['user_id' => (int) $user['id']]
);

$status = strtolower((string) ($transaction['status'] ?? 'pending'));
$message = match ($status) {
    'successful' => 'Transaction successful.',
    'failed' => 'Transaction failed. If your wallet was debited, GemData will refund according to the transaction result.',
    'refunded' => 'Transaction refunded.',
    'reversed' => 'Transaction reversed.',
    default => 'Transaction is processing with the provider.',
};

$safeTransaction = static function (array $row): array {
    $status = strtolower((string) ($row['status'] ?? 'pending'));
    return [
        'reference' => (string) ($row['reference'] ?? ''),
        'service' => (string) ($row['service_name'] ?? 'Transaction'),
        'service_slug' => (string) ($row['service_slug'] ?? ''),
        'status' => $status,
        'provider_code' => (string) ($row['provider_code'] ?? ''),
        'failure_code' => (string) ($row['failure_code'] ?? ''),
        'recipient' => (string) ($row['recipient'] ?? ''),
        'amount' => (float) ($row['amount'] ?? 0),
        'amount_formatted' => money((float) ($row['amount'] ?? 0)),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'created_at_display' => local_datetime((string) ($row['created_at'] ?? ''), 'M j, Y g:i A'),
        'created_at_full' => local_datetime((string) ($row['created_at'] ?? ''), 'M j, Y g:i A'),
        'receipt_url' => $status === 'successful' ? base_url('user/receipt.php?reference=' . rawurlencode((string) ($row['reference'] ?? ''))) : null,
    ];
};

$hasPending = false;
foreach ($recentRows as $row) {
    if (strtolower((string) ($row['status'] ?? '')) === 'pending') {
        $hasPending = true;
        break;
    }
}

$response->json('success', $message, [
    'transaction' => $safeTransaction($transaction),
    'recent_transactions' => array_map($safeTransaction, $recentRows),
    'has_pending' => $hasPending,
]);
