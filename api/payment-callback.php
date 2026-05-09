<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$service = app(\GemData\Classes\PaymentGatewayService::class);
$reference = trim((string) ($_GET['reference'] ?? $_POST['reference'] ?? ''));
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$status = trim((string) ($_GET['status'] ?? $_POST['status'] ?? 'success'));
$providerReference = trim((string) ($_GET['provider_reference'] ?? $_POST['provider_reference'] ?? ''));

try {
    if ($service->isProductionBankTransferOnly()) {
        throw new RuntimeException('Mock payment callback is disabled in production.');
    }

    if ($reference === '' || $token === '') {
        throw new RuntimeException('Missing callback reference or token.');
    }

    $request = $service->verifyMockCallback($reference, $token, $status, $providerReference !== '' ? $providerReference : null);
    unset($_SESSION['wallet_funding_session_tokens'][$request['reference']]);
    flash('success', 'Wallet funding reference ' . $request['reference'] . ' has been processed with status ' . $request['status'] . '.');
    redirect(base_url('user/fund-wallet.php?reference=' . urlencode($request['reference'])));
} catch (Throwable $throwable) {
    http_response_code(400);
    echo 'Funding callback failed: ' . e($throwable->getMessage());
}
