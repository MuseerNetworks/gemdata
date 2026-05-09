<?php

declare(strict_types=1);

namespace GemData\Classes;

class Response
{
    public function json(string $status, string $message, array $data = [], array $errors = [], array $meta = [], int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        $meta['request_id'] = $meta['request_id'] ?? app(\GemData\Classes\AppLogger::class)->requestId();
        $payload = [
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        echo json_encode($payload, JSON_PRETTY_PRINT);
        exit;
    }
}
