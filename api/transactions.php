<?php
require_once __DIR__ . '/../includes/bootstrap.php';
try {
    $data = app(\GemData\Classes\ApiHandler::class)->transactions();
    $meta = ['page' => $data['page'], 'per_page' => $data['per_page']];
    unset($data['page'], $data['per_page']);
    app(\GemData\Classes\Response::class)->json('success', 'Transactions fetched successfully', $data, [], $meta);
} catch (Throwable $e) {
    app(\GemData\Classes\Response::class)->json('error', public_api_error_message($e), [], [], [], 400);
}
