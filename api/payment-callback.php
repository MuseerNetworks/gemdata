<?php

declare(strict_types=1);

/**
 * This endpoint is disabled.
 * Mock payment callbacks are not supported in production.
 * XixaPay webhook traffic is received by api/xixapay.php in logging-only mode
 * until the live payload is confirmed.
 */

http_response_code(410);
header('Content-Type: application/json');
echo json_encode([
    'status'  => 'error',
    'message' => 'This endpoint is no longer available.',
]);
exit;
