<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function run_test(string $name, callable $test) {
    echo "Running test: {$name}... ";
    try {
        $test();
        echo "\033[32mPASSED\033[0m\n";
    } catch (Throwable $e) {
        echo "\033[31mFAILED\033[0m\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
}

// -------------------------------------------------------------
// TEST 1: Database Connection & Basic Schema Audit
// -------------------------------------------------------------
run_test("Database Schema & Connection Verification", function() {
    $db = db();
    $res = $db->first("SELECT 1");
    if (!$res) {
        throw new Exception("Database connection returned empty result.");
    }
    
    // Check key tables exist
    $tables = ['users', 'wallets', 'wallet_transactions', 'user_funding_accounts', 'wallet_funding_requests', 'webhook_events'];
    foreach ($tables as $table) {
        $check = $db->first("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table", ['table' => $table]);
        if (!$check) {
            throw new Exception("Required database table '{$table}' is missing.");
        }
    }
});

// -------------------------------------------------------------
// TEST 2: Wallet Credit Idempotency & Locking
// -------------------------------------------------------------
run_test("Wallet Crediting Idempotency & Transaction Safety", function() {
    $db = db();
    
    // Create a temporary test user
    $email = 'qa_temp_test_' . bin2hex(random_bytes(4)) . '@example.com';
    $phone = '080' . sprintf('%08d', mt_rand(0, 99999999));
    $db->execute("INSERT INTO users (full_name, email, phone, password_hash, transaction_pin_hash) VALUES ('QA Temp User', :email, :phone, 'hash', 'pin_hash')", ['email' => $email, 'phone' => $phone]);
    $userId = (int) $db->lastInsertId();
    
    // Initialize wallet
    $wallet = app(\GemData\Classes\Wallet::class);
    $wallet->ensure($userId);
    
    // Fetch initial balance
    $walletData = $db->first("SELECT balance FROM wallets WHERE user_id = :user_id", ['user_id' => $userId]);
    $initialBalance = (float) ($walletData['balance'] ?? 0);
    
    // Test crediting
    $amount = 1500.00;
    $idempotencyKey = 'qa_idemp_key_' . bin2hex(random_bytes(8));
    
    $record1 = $wallet->credit(
        $userId,
        $amount,
        'QA Test credit 1',
        'system',
        [],
        'funding',
        null,
        $idempotencyKey,
        'manual'
    );
    
    $walletData2 = $db->first("SELECT balance FROM wallets WHERE user_id = :user_id", ['user_id' => $userId]);
    $newBalance = (float) ($walletData2['balance'] ?? 0);
    
    if (abs($newBalance - ($initialBalance + $amount)) > 0.001) {
        throw new Exception("Balance did not increment correctly. Expected: " . ($initialBalance + $amount) . ", Got: " . $newBalance);
    }
    
    // Attempt duplicate credit with same idempotency key
    try {
        $wallet->credit(
            $userId,
            $amount,
            'QA Test duplicate credit',
            'system',
            [],
            'funding',
            null,
            $idempotencyKey,
            'manual'
        );
        throw new Exception("Duplicate credit succeeded but should have failed/been blocked by idempotency.");
    } catch (Throwable $e) {
        // Exception is expected since the database unique constraint on idempotency key should trigger
        // OR the credit logic should block it.
    }
    
    // Verify balance remains unchanged
    $walletData3 = $db->first("SELECT balance FROM wallets WHERE user_id = :user_id", ['user_id' => $userId]);
    $finalBalance = (float) ($walletData3['balance'] ?? 0);
    if (abs($finalBalance - $newBalance) > 0.001) {
        throw new Exception("Balance changed after duplicate credit attempt! Expected: " . $newBalance . ", Got: " . $finalBalance);
    }
    
    // Clean up
    $db->execute("DELETE FROM wallet_transactions WHERE user_id = :user_id", ['user_id' => $userId]);
    $db->execute("DELETE FROM wallets WHERE user_id = :user_id", ['user_id' => $userId]);
    $db->execute("DELETE FROM users WHERE id = :id", ['id' => $userId]);
});

// -------------------------------------------------------------
// TEST 3: KatPay Webhook Signature & Callback Verification
// -------------------------------------------------------------
run_test("KatPay Webhook Callback Signature & Processing", function() {
    $db = db();
    
    // Make sure we have a katpay secret key configured in container config
    $GLOBALS['__gemdata_container']['config']['payments']['katpay_secret_key'] = 'mock_secret_key';
    $secretKey = 'mock_secret_key';
    
    // Create a temporary test user mapped to a dedicated virtual account
    $email = 'qa_katpay_test_' . bin2hex(random_bytes(4)) . '@example.com';
    $phone = '080' . sprintf('%08d', mt_rand(0, 99999999));
    $db->execute("INSERT INTO users (full_name, email, phone, password_hash, transaction_pin_hash) VALUES ('QA KatPay User', :email, :phone, 'hash', 'pin_hash')", ['email' => $email, 'phone' => $phone]);
    $userId = (int) $db->lastInsertId();
    
    $wallet = app(\GemData\Classes\Wallet::class);
    $wallet->ensure($userId);
    
    $accountNumber = '99' . sprintf('%08d', mt_rand(0, 99999999));
    $db->execute(
        "INSERT INTO user_funding_accounts (user_id, provider, dedicated_account_number, bank_name, status) 
         VALUES (:user_id, 'katpay', :account_number, 'Mock Bank', 'active')",
        ['user_id' => $userId, 'account_number' => $accountNumber]
    );
    
    // Prepare Webhook payload
    $orderNo = 'ORD_' . bin2hex(random_bytes(4));
    $payloadData = [
        'event' => 'virtual_account.payment_received',
        'event_id' => 'evt_' . bin2hex(random_bytes(6)),
        'data' => [
            'transaction' => [
                'order_no' => $orderNo,
                'order_status' => '1',
                'currency' => 'NGN',
                'order_amount' => 5000.00
            ],
            'virtual_account' => [
                'account_number' => $accountNumber,
                'bank_name' => 'Mock Bank'
            ]
        ]
    ];
    $payloadJson = json_encode($payloadData);
    
    // Generate valid signature headers
    $timestamp = (string) time();
    $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payloadJson, $secretKey);
    
    // Prepare variables for subprocess execution
    $simulate = function(string $payload, string $sig, string $time) {
        $code = "<?php
            require_once __DIR__ . '/../includes/bootstrap.php';
            \$GLOBALS['__gemdata_container']['config']['payments']['katpay_secret_key'] = 'mock_secret_key';
            \$GLOBALS['__mock_php_input'] = " . var_export($payload, true) . ";
            \$_SERVER['REQUEST_METHOD'] = 'POST';
            \$_SERVER['HTTP_X_KATPAY_SIGNATURE'] = " . var_export($sig, true) . ";
            \$_SERVER['HTTP_X_KATPAY_TIMESTAMP'] = " . var_export($time, true) . ";
            require 'api/katpay.php';
        ";
        file_put_contents('scratch/temp_webhook_test.php', $code);
        $cmd = 'C:\xampp\php\php.exe scratch/temp_webhook_test.php 2>&1';
        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        @unlink('scratch/temp_webhook_test.php');
        return [
            'exit' => $exit,
            'body' => implode("\n", $output)
        ];
    };

    echo "\nSimulating local POST execution of api/katpay.php...\n";
    $res = $simulate($payloadJson, $expectedSignature, $timestamp);
    echo "Webhook response: " . $res['body'] . "\n";
    
    $respDecoded = json_decode($res['body'], true);
    if (($respDecoded['status'] ?? '') !== 'success') {
        throw new Exception("Webhook response status was not 'success'. Response: " . $res['body']);
    }
    
    // Verify wallet was credited
    $walletData = $db->first("SELECT balance FROM wallets WHERE user_id = :user_id", ['user_id' => $userId]);
    $balanceAfter = (float) ($walletData['balance'] ?? 0);
    if (abs($balanceAfter - 5000.00) > 0.001) {
        throw new Exception("User's wallet was not credited. Balance: " . $balanceAfter);
    }
    
    // -------------------------------------------------------------
    // Test Webhook Idempotency (sending duplicate callback)
    // -------------------------------------------------------------
    echo "Sending duplicate webhook callback to test idempotency...\n";
    $res2 = $simulate($payloadJson, $expectedSignature, $timestamp);
    echo "Duplicate Webhook response: " . $res2['body'] . "\n";
    
    // Balance should still be 5000.00 (not 10000.00)
    $walletData2 = $db->first("SELECT balance FROM wallets WHERE user_id = :user_id", ['user_id' => $userId]);
    $balanceFinal = (float) ($walletData2['balance'] ?? 0);
    if (abs($balanceFinal - 5000.00) > 0.001) {
        throw new Exception("Idempotency check failed! Wallet was credited twice. Balance: " . $balanceFinal);
    }
    
    // Clean up
    $db->execute("DELETE FROM wallet_transactions WHERE user_id = :user_id", ['user_id' => $userId]);
    $db->execute("DELETE FROM wallets WHERE user_id = :user_id", ['user_id' => $userId]);
    $db->execute("DELETE FROM user_funding_accounts WHERE user_id = :user_id", ['user_id' => $userId]);
    $db->execute("DELETE FROM wallet_funding_requests WHERE user_id = :user_id", ['user_id' => $userId]);
    $db->execute("DELETE FROM webhook_events WHERE event_key LIKE :event_key", ['event_key' => 'virtual_account.payment_received:%']);
    $db->execute("DELETE FROM users WHERE id = :id", ['id' => $userId]);
});

echo "\n\033[32mALL QA REGRESSION TESTS COMPLETED SUCCESSFULLY!\033[0m\n";
