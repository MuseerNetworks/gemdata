<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_user();
$reference = strtoupper(trim((string) ($_GET['reference'] ?? '')));

if ($reference === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Receipt not found.';
    exit;
}

$transaction = db()->first(
    'SELECT t.*, s.name AS service_name, s.slug AS service_slug
     FROM transactions t
     INNER JOIN services s ON s.id = t.service_id
     WHERE t.reference = :reference AND t.user_id = :user_id
     LIMIT 1',
    ['reference' => $reference, 'user_id' => (int) $user['id']]
);

if (!$transaction) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Receipt not found.';
    exit;
}

$receipt = transaction_receipt_context($transaction);
$status = (string) $receipt['status'];
$filenameReference = preg_replace('/[^A-Z0-9_-]/', '', (string) $transaction['reference']) ?: 'receipt';
$filename = 'gemdata-receipt-' . $filenameReference . '.html';

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GemData Receipt <?= e((string) $transaction['reference']); ?></title>
    <style>
        :root {
            color-scheme: light;
            --border: #dbe3ef;
            --muted: #64748b;
            --text: #0f172a;
            --primary: #1b4dff;
            --surface: #f8fafc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #ffffff;
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }
        .receipt {
            width: min(100%, 560px);
            margin: 0 auto;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
        }
        .head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 14px;
            margin-bottom: 14px;
        }
        .brand {
            margin: 0;
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        h1 {
            margin: 4px 0 0;
            font-size: 22px;
            line-height: 1.2;
        }
        .status {
            border-radius: 999px;
            background: <?= $status === 'successful' ? '#ecfdf3' : ($status === 'failed' ? '#fef3f2' : '#fffbeb'); ?>;
            color: <?= $status === 'successful' ? '#027a48' : ($status === 'failed' ? '#b42318' : '#b54708'); ?>;
            font-size: 12px;
            font-weight: 800;
            padding: 6px 10px;
            white-space: nowrap;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .item {
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
            padding: 12px;
        }
        dt {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        dd {
            margin: 5px 0 0;
            color: var(--text);
            font-size: 14px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }
        .mono { font-family: Consolas, Monaco, monospace; }
        .amount { font-size: 18px; }
        @media (max-width: 520px) {
            body { padding: 12px; }
            .receipt { padding: 14px; border-radius: 12px; }
            .head { gap: 10px; }
            h1 { font-size: 18px; }
            .grid { grid-template-columns: 1fr; gap: 8px; }
            .item { padding: 10px; }
            dd { font-size: 13px; }
        }
        @media print {
            body { padding: 0; }
            .receipt { border-radius: 8px; }
        }
    </style>
</head>
<body>
    <main class="receipt">
        <div class="head">
            <div>
                <p class="brand">GemData</p>
                <h1>Transaction Receipt</h1>
            </div>
            <span class="status"><?= e(ucfirst($status)); ?></span>
        </div>
        <dl class="grid">
            <div class="item">
                <dt>Reference</dt>
                <dd class="mono"><?= e((string) $transaction['reference']); ?></dd>
            </div>
            <div class="item">
                <dt>Date / Time</dt>
                <dd><?= e((string) $receipt['display_time']); ?></dd>
            </div>
            <div class="item">
                <dt>Service</dt>
                <dd><?= e((string) $transaction['service_name']); ?></dd>
            </div>
            <div class="item">
                <dt>Plan / Package</dt>
                <dd><?= e((string) $receipt['plan_name']); ?></dd>
            </div>
            <?php if ($receipt['validity_label'] !== ''): ?>
            <div class="item">
                <dt>Validity</dt>
                <dd><?= e((string) $receipt['validity_label']); ?></dd>
            </div>
            <?php endif; ?>
            <div class="item">
                <dt>Recipient</dt>
                <dd><?= e((string) $receipt['recipient']); ?></dd>
            </div>
            <div class="item">
                <dt>Amount</dt>
                <dd class="mono amount"><?= e(money((float) $transaction['amount'])); ?></dd>
            </div>
            <div class="item">
                <dt>Status</dt>
                <dd><?= e(ucfirst($status)); ?></dd>
            </div>
        </dl>
    </main>
</body>
</html>
