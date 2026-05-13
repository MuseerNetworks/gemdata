<?php

declare(strict_types=1);

/**
 * This endpoint is disabled.
 * Mock payment callbacks are not supported in production.
 * Wallet funding is handled exclusively via bank transfer webhooks
 * from Paystack (api/webhook.php) and ZenithPay (api/zenithpay-webhook.php).
 */

http_response_code(410);
header('Content-Type: application/json');
echo json_encode([
    'status'  => 'error',
    'message' => 'This endpoint is no longer available.',
]);
exit;
