<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Allow only GET or POST
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Authenticate session
$user = require_mobile_user();
$userId = (int) $user['id'];

$db = db();
$commWallet = new \GemData\Classes\CommissionWallet($db);
$withdrawSvc = new \GemData\Classes\WithdrawalService($db, $commWallet);

if ($method === 'GET') {
    $balance = (float) $commWallet->balance($userId);
    $minimum = (float) $withdrawSvc->minimumAmount();
    $historyRaw = $withdrawSvc->listByUser($userId, 30);
    
    $history = [];
    foreach ($historyRaw as $row) {
        $history[] = [
            'id' => (int) $row['id'],
            'amount' => (float) $row['amount'],
            'bank_name' => (string) $row['bank_name'],
            'account_number' => (string) $row['account_number'],
            'account_name' => (string) $row['account_name'],
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at']
        ];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'balance' => $balance,
            'minimum_amount' => $minimum,
            'history' => $history
        ]
    ]);
    exit;
}

// POST: Submit withdrawal request
$input = $_POST;
if (empty($input)) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: [];
}

$amount = (float) ($input['amount'] ?? 0);
$bankName = trim((string) ($input['bank_name'] ?? ''));
$acctNo = trim((string) ($input['account_number'] ?? ''));
$acctName = trim((string) ($input['account_name'] ?? ''));

try {
    if ($bankName === '' || $acctNo === '' || $acctName === '') {
        throw new InvalidArgumentException('All bank details are required.');
    }
    
    $withdrawSvc->request($userId, $amount, $bankName, $acctNo, $acctName);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Commission withdrawal request submitted successfully.'
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
