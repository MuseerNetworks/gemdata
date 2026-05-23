<?php

declare(strict_types=1);

namespace GemData\Classes;

class MaintenanceService
{
    public function __construct(
        private SettingsService $settings,
        private Response $response
    ) {
    }

    public function enforce(): void
    {
        if (PHP_SAPI === 'cli' || !$this->settings->bool('maintenance_mode', false)) {
            return;
        }

        $path = (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['REQUEST_URI'] ?? '');
        if ($this->isAllowedPath($path)) {
            return;
        }

        $message = $this->settings->get('maintenance_message', 'GemData is under scheduled maintenance. Please try again shortly.') ?? 'GemData is under scheduled maintenance. Please try again shortly.';
        http_response_code(503);

        if (str_contains($path, '/api/')) {
            $this->response->json('error', $message, [], [], ['maintenance' => true], 503);
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Maintenance</title></head><body style="font-family:Arial,sans-serif;background:#0f172a;color:#fff;padding:48px;"><main style="max-width:720px;margin:0 auto;background:#111827;padding:32px;border-radius:16px;"><h1>Maintenance mode enabled</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></main></body></html>';
        exit;
    }

    private function isAllowedPath(string $path): bool
    {
        $allowedFragments = [
            '/admin/login.php',
            '/admin/register.php',
            '/admin/change-password.php',
            '/api/xixapay.php',
            '/offline.html',
        ];

        foreach ($allowedFragments as $fragment) {
            if (str_contains($path, $fragment)) {
                return true;
            }
        }

        return str_contains($path, '/admin/');
    }
}
