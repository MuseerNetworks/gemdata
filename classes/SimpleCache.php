<?php

declare(strict_types=1);

namespace GemData\Classes;

class SimpleCache
{
    public function __construct(private string $directory)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return $default;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return $default;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return $default;
        }

        if ((int) ($payload['expires_at'] ?? 0) < time()) {
            @unlink($path);
            return $default;
        }

        return $payload['value'] ?? $default;
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        if ($ttlSeconds < 1) {
            return;
        }

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        $payload = [
            'expires_at' => time() + $ttlSeconds,
            'value' => $value,
        ];

        @file_put_contents($this->path($key), json_encode($payload, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public function forget(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . sha1($key) . '.json';
    }
}
