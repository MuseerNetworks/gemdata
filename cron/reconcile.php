<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$service = app(\GemData\Classes\TransactionService::class);
$funding = app(\GemData\Classes\PaymentGatewayService::class);
$summary = $service->reconcileTransactions(20);
$fundingSummary = $funding->reconcileFundingCredits(20);

echo 'Recovered locks: ' . (int) $summary['recovered_locks'] . PHP_EOL;
echo 'Timed out: ' . (int) $summary['timed_out'] . PHP_EOL;
echo 'Refunded: ' . (int) $summary['refunded'] . PHP_EOL;
echo 'Funding repairs: ' . (int) $fundingSummary['repaired'] . PHP_EOL;
