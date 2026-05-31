<?php

declare(strict_types=1);

namespace GemData\Classes;

class AppLogger
{
    public function log(string $level, string $message, array $context = []): void
    {
        error_log($this->renderLine($level, $message, $context));
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
            $sanitized[$key] = $this->sanitizeValue((string) $key, $value);
        }

        return $sanitized;
    }

    public function sanitizeProviderMeta(array $context, int $maxStringLength = 4000): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue((string) $key, $value, max(100, $maxStringLength));
        }

        return $sanitized;
    }

    public function writeToFile(string $file, string $level, string $message, array $context = []): void
    {
        if (!(bool) config('app.provider_log_to_file', true)) {
            return;
        }

        $directory = dirname($file);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        if (!is_writable($directory) && (!is_file($file) || !is_writable($file))) {
            return;
        }

        @file_put_contents($file, $this->renderLine($level, $message, $context) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function renderLine(string $level, string $message, array $context = []): string
    {
        return trim(sprintf(
            '[GemData][%s][%s] %s %s',
            strtoupper($level),
            $this->requestId(),
            $this->redact($message),
            $this->formatContext($context)
        ));
    }

    private function formatContext(array $context): string
    {
        $context = $this->sanitizeContext($context);
        $context['_path'] = (string) ($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? 'cli');
        $context['_sapi'] = PHP_SAPI;

        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '{}' : $encoded;
    }

    private function sanitizeValue(string $key, mixed $value, int $maxStringLength = 4000): mixed
    {
        $normalizedKey = strtolower($key);
        if ($this->isSecretKey($normalizedKey)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitizeValue((string) $childKey, $childValue, $maxStringLength);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return substr($this->redact($value), 0, $maxStringLength);
        }

        if (is_int($value) || is_float($value)) {
            $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
            if (strlen($digits) >= 10) {
                return substr($this->redact((string) $value), 0, $maxStringLength);
            }
        }

        return $value;
    }

    private function redact(string $value): string
    {
        $patterns = [
            '/sk_(live|test)_[A-Za-z0-9]+/i',
            '/pk_(live|test)_[A-Za-z0-9]+/i',
            '/(bearer\s+)[A-Za-z0-9_\-\.=]+/i',
            '/(secret|password|pin|token|api[_-]?key|webhook[_-]?secret|authorization)\s*[:=]\s*([^\s,"\']+)/i',
            '/\b(0\d{2})\d{4,8}(\d{4})\b/',
            '/\b(234\d{3})\d{3,7}(\d{4})\b/',
            '/\b(\d{3})\d{3,8}(\d{4})\b/',
        ];
        $replacements = [
            'sk_$1_[REDACTED]',
            'pk_$1_[REDACTED]',
            '$1[REDACTED]',
            '$1=[REDACTED]',
            '$1****$2',
            '$1****$2',
            '$1****$2',
        ];

        $redacted = $value;
        foreach ($patterns as $index => $pattern) {
            $redacted = preg_replace($pattern, $replacements[$index], $redacted) ?? $redacted;
        }

        return $redacted;
    }

    private function isSecretKey(string $key): bool
    {
        foreach (['secret', 'password', 'pin', 'token', 'api_key', 'apikey', 'api-key', 'signature', 'authorization'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
