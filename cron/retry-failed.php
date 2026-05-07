<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$settings = app(\GemData\Classes\SettingsService::class);
if (!$settings->bool('auto_retry_enabled', false)) {
    echo "Auto retry disabled.\n";
    exit(0);
}

$service = app(\GemData\Classes\TransactionService::class);
$processed = $service->processPendingTransactions(20);

$rows = db()->query(
    'SELECT id
     FROM transactions
     WHERE status = "failed" AND is_retryable = 1 AND retry_count < max_retry_count
     ORDER BY id ASC
     LIMIT 20'
);

$queued = 0;
foreach ($rows as $row) {
    try {
        $service->retryTransaction((int) $row['id'], 0);
        $service->processPendingTransaction((int) $row['id']);
        $queued++;
    } catch (Throwable $throwable) {
        // Continue to next transaction; details are already captured through transaction events when retries run.
    }
}

echo "Processed pending: {$processed}\n";
echo "Queued retries: {$queued}\n";
