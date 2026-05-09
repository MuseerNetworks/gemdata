<?php

declare(strict_types=1);

namespace GemData\Classes;

class AppLogger
{
    public function log(string $level, string $message, array $context = []): void
    {
        $requestId = $this->requestId();
        $line = sprintf(
            '[GemData][%s][%s] %s %s',
            strtoupper($level),
            $requestId,
            $this->redact($message),
            $this->formatContext($context)
        );

        error_log(trim($line));
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function requestId(): string
    {
        if (!isset($GLOBALS['__gemdata_request_id'])) {
            $GLOBALS['__gemdata_request_id'] = bin2hex(random_bytes(8));
        }

        return (string) $GLOBALS['__gemdata_request_id'];
    }

    public function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if ($this->isSecretKey($normalizedKey)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = $this->redact($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function formatContext(array $context): string
    {
        $context = $this->sanitizeContext($context);
        $context['_path'] = (string) ($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? 'cli');
        $context['_sapi'] = PHP_SAPI;

        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '{}' : $encoded;
    }

    private function redact(string $value): string
    {
        $patterns = [
            '/sk_(live|test)_[A-Za-z0-9]+/i',
            '/pk_(live|test)_[A-Za-z0-9]+/i',
            '/(bearer\s+)[A-Za-z0-9_\-\.=]+/i',
            '/(secret|password|token|api[_-]?key|webhook[_-]?secret)\s*[:=]\s*([^\s,"\']+)/i',
        ];

        $redacted = $value;
        foreach ($patterns as $pattern) {
            $redacted = preg_replace($pattern, '$1[REDACTED]', $redacted) ?? $redacted;
        }

        return $redacted;
    }

    private function isSecretKey(string $key): bool
    {
        foreach (['secret', 'password', 'token', 'api_key', 'apikey', 'signature', 'authorization'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
