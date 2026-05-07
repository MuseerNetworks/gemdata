<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$service = app(\GemData\Classes\TransactionService::class);
$processed = $service->processPendingTransactions(20);

echo "Processed pending transactions: {$processed}\n";
