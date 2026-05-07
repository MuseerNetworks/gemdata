<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$payload = file_get_contents('php://input') ?: '{}';
$source = trim((string) ($_GET['source'] ?? $_POST['source'] ?? 'generic'));
$eventKey = trim((string) ($_SERVER['HTTP_X_EVENT_KEY'] ?? $_POST['event_key'] ?? ''));
$signature = trim((string) ($_SERVER['HTTP_X_SIGNATURE'] ?? ''));
$allowedSources = (array) config('webhooks.allowed_sources', ['generic']);
$sharedSecret = (string) config('webhooks.shared_secret', '');

if ($sharedSecret === '') {
    app(\GemData\Classes\Response::class)->json('error', 'Webhook handling is not configured.', [], [], [], 503);
}

if (!in_array($source, $allowedSources, true)) {
    app(\GemData\Classes\Response::class)->json('error', 'Webhook source is not allowed.', [], [], [], 403);
}

$expectedSignature = hash_hmac('sha256', $payload, $sharedSecret);
if ($signature === '' || !hash_equals($expectedSignature, $signature)) {
    app(\GemData\Classes\Response::class)->json('error', 'Invalid webhook signature.', [], [], [], 401);
}

$existing = null;
if ($eventKey !== '') {
    $existing = db()->first(
        'SELECT id FROM webhook_events WHERE source = :source AND event_key = :event_key LIMIT 1',
        ['source' => $source, 'event_key' => $eventKey]
    );
}

if ($existing) {
    db()->execute('UPDATE webhook_events SET processing_status = :processing_status, processed_at = NOW() WHERE id = :id', [
        'processing_status' => 'duplicate',
        'id' => $existing['id'],
    ]);
    app(\GemData\Classes\Response::class)->json('success', 'Duplicate webhook ignored.', ['source' => $source, 'duplicate' => true]);
}

db()->execute(
    'INSERT INTO webhook_events (source, event_key, signature, payload_json, processing_status)
     VALUES (:source, :event_key, :signature, :payload_json, :processing_status)',
    [
        'source' => $source,
        'event_key' => $eventKey !== '' ? $eventKey : null,
        'signature' => $signature !== '' ? $signature : null,
        'payload_json' => $payload,
        'processing_status' => 'pending',
    ]
);

app(\GemData\Classes\ActivityLogger::class)->log('system', 0, 'webhook_received', 'Webhook received and logged.', ['source' => $source, 'event_key' => $eventKey]);
app(\GemData\Classes\Response::class)->json('success', 'Webhook received.', ['source' => $source, 'logged' => true]);
