<?php

declare(strict_types=1);

function app_container(string $key = null)
{
    if (!isset($GLOBALS['__gemdata_container'])) {
        $GLOBALS['__gemdata_container'] = [];
    }
    if ($key === null) {
        return $GLOBALS['__gemdata_container'];
    }
    if (!array_key_exists($key, $GLOBALS['__gemdata_container'])) {
        throw new RuntimeException("Service {$key} is not registered.");
    }
    return $GLOBALS['__gemdata_container'][$key];
}

function register_service(string $key, $value): void
{
    $GLOBALS['__gemdata_container'][$key] = $value;
}

function app(string $key)
{
    return app_container($key);
}

function config(?string $path = null, $default = null)
{
    $config = app_container('config');
    if ($path === null) {
        return $config;
    }
    $segments = explode('.', $path);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function config_meta(string $key, $default = null)
{
    $meta = app_container('config')['__meta'] ?? [];
    return array_key_exists($key, $meta) ? $meta[$key] : $default;
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) config('app.base_url', ''), '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function app_origin(): string
{
    return rtrim((string) config('app.public_origin', ''), '/');
}

function absolute_url(string $path = ''): string
{
    return app_origin() . base_url($path);
}

function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csp_nonce(): string
{
    if (empty($GLOBALS['__gemdata_csp_nonce'])) {
        $GLOBALS['__gemdata_csp_nonce'] = bin2hex(random_bytes(16));
    }
    return $GLOBALS['__gemdata_csp_nonce'];
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_get(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function public_api_error_message(Throwable $throwable): string
{
    $message = strtolower($throwable->getMessage());
    if (str_contains($message, 'rate limit')) {
        return 'Rate limit exceeded. Try again later.';
    }
    if (str_contains($message, 'credential') || str_contains($message, 'api account') || str_contains($message, 'whitelist') || str_contains($message, 'authentication')) {
        return 'API authentication failed.';
    }
    if (str_contains($message, 'insufficient')) {
        return 'Insufficient wallet balance.';
    }
    if (str_contains($message, 'disabled') || str_contains($message, 'unavailable') || str_contains($message, 'outside the allowed')) {
        return $throwable->getMessage();
    }
    return 'Request could not be processed right now.';
}

function base32_encode_secret(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0, $length = strlen($bytes); $i < $length; $i++) {
        $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $encoded .= $alphabet[bindec($chunk)];
    }
    return $encoded;
}

function base32_decode_secret(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
    $bits = '';
    for ($i = 0, $length = strlen($secret); $i < $length; $i++) {
        $position = strpos($alphabet, $secret[$i]);
        if ($position !== false) {
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
    }
    $decoded = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $decoded .= chr(bindec($byte));
        }
    }
    return $decoded;
}

function totp_code(string $secret, ?int $timeSlice = null): string
{
    $timeSlice ??= (int) floor(time() / 30);
    $key = base32_decode_secret($secret);
    $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $timeSlice), $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $value = (((ord($hash[$offset]) & 0x7F) << 24) | ((ord($hash[$offset + 1]) & 0xFF) << 16) | ((ord($hash[$offset + 2]) & 0xFF) << 8) | (ord($hash[$offset + 3]) & 0xFF)) % 1000000;
    return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
}

function verify_totp_code(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (strlen($code) !== 6 || trim($secret) === '') {
        return false;
    }
    $current = (int) floor(time() / 30);
    for ($offset = -$window; $offset <= $window; $offset++) {
        if (hash_equals(totp_code($secret, $current + $offset), $code)) {
            return true;
        }
    }
    return false;
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function money($amount): string
{
    return 'NGN ' . number_format((float) $amount, 2);
}

function human_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    try {
        $timezone = new DateTimeZone((string) config('app.timezone', 'Africa/Lagos'));
        $date = (new DateTimeImmutable($value, $timezone))->setTimezone($timezone);
        $now = new DateTimeImmutable('now', $timezone);
        $diffSeconds = $now->getTimestamp() - $date->getTimestamp();

        if ($diffSeconds >= 0 && $diffSeconds < 60) {
            return 'Just now';
        }

        if ($diffSeconds >= 60 && $diffSeconds < 3600) {
            $minutes = (int) floor($diffSeconds / 60);
            return $minutes . ' min' . ($minutes === 1 ? '' : 's') . ' ago';
        }

        if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
            return 'Today, ' . $date->format('g:i A');
        }

        $yesterday = $now->sub(new DateInterval('P1D'));
        if ($date->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            return 'Yesterday, ' . $date->format('g:i A');
        }

        return $date->format('M j, Y g:i A');
    } catch (Throwable $throwable) {
        return (string) $value;
    }
}

function local_datetime(?string $value, string $format = 'M j, Y g:i A'): string
{
    if (!$value) {
        return '-';
    }

    try {
        $timezone = new DateTimeZone((string) config('app.timezone', 'Africa/Lagos'));
        return (new DateTimeImmutable($value, $timezone))->setTimezone($timezone)->format($format);
    } catch (Throwable) {
        return (string) $value;
    }
}

function transaction_display_timestamp(array $transaction): string
{
    $processedAt = trim((string) ($transaction['processed_at'] ?? ''));
    if ($processedAt !== '' && $processedAt !== '0000-00-00 00:00:00') {
        return $processedAt;
    }

    return trim((string) ($transaction['created_at'] ?? ''));
}

function transaction_display_datetime(array $transaction, string $format = 'M j, Y g:i A'): string
{
    return local_datetime(transaction_display_timestamp($transaction), $format);
}

function transaction_receipt_context(array $transaction): array
{
    $payload = json_decode_array((string) ($transaction['payload_json'] ?? '{}'));
    $networkCode = (string) ($payload['network'] ?? $payload['provider'] ?? '');
    $planCode = (string) ($payload['local_plan_code'] ?? $payload['package'] ?? $payload['exam_type'] ?? $payload['plan'] ?? '');
    $mapping = null;

    if ((int) ($transaction['provider_account_id'] ?? 0) > 0 && $planCode !== '') {
        $mapping = app(\GemData\Classes\ProviderPlanService::class)->resolveForProvider(
            (int) $transaction['provider_account_id'],
            (int) $transaction['service_id'],
            $networkCode,
            $planCode
        );
    }

    $planName = trim((string) ($payload['local_plan_name'] ?? ''));
    if ($planName === '' && is_array($mapping)) {
        $planName = trim((string) ($mapping['local_plan_name'] ?? ''));
    }
    if ($planName === '') {
        $planName = trim($planCode);
    }

    $validityLabel = trim((string) ($payload['validity_label'] ?? ''));
    if ($validityLabel === '' && is_array($mapping)) {
        $validityLabel = trim((string) ($mapping['validity_label'] ?? ''));
    }

    return [
        'plan_name' => $planName !== '' ? $planName : 'N/A',
        'validity_label' => $validityLabel,
        'recipient' => (string) (($transaction['recipient'] ?? '') ?: ($transaction['customer_name'] ?? 'N/A')),
        'status' => strtolower((string) ($transaction['status'] ?? 'pending')),
        'display_time' => transaction_display_datetime($transaction, 'M j, Y g:i A'),
    ];
}

function client_ip(): string
{
    $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
    $trustedProxies = array_map('trim', (array) config('app.trusted_proxies', []));
    $allowForwarded = $trustedProxies !== [] && in_array($remoteAddr, $trustedProxies, true);

    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!$allowForwarded && in_array($key, ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'], true)) {
            continue;
        }
        $value = trim((string) ($_SERVER[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = array_map('trim', explode(',', $value));
            return $parts[0] ?? '127.0.0.1';
        }
        return $value;
    }

    return '127.0.0.1';
}

function fresh_idempotency_key(string $prefix = 'req'): string
{
    return $prefix . ':' . bin2hex(random_bytes(16));
}

function current_user_agent(): string
{
    return substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')), 0, 255);
}

function json_decode_array(?string $json): array
{
    if (!$json) {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function current_path_with_base(): string
{
    return (string) ($_SERVER['REQUEST_URI'] ?? base_url());
}

function app_logger(): \GemData\Classes\AppLogger
{
    return app(\GemData\Classes\AppLogger::class);
}

function query_except(array $keysToRemove = []): array
{
    $query = $_GET;
    foreach ($keysToRemove as $key) {
        unset($query[$key]);
    }

    return array_filter($query, static fn($value): bool => $value !== '' && $value !== null);
}

function pagination_meta(int $total, int $page, int $perPage): array
{
    $perPage = max(1, $perPage);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));

    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'offset' => ($page - 1) * $perPage,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages,
    ];
}

function render_pagination(array $meta, string $path, array $query = []): void
{
    if (($meta['total_pages'] ?? 1) <= 1) {
        return;
    }

    $current = (int) ($meta['page'] ?? 1);
    $totalPages = (int) ($meta['total_pages'] ?? 1);
    $start = max(1, $current - 2);
    $end = min($totalPages, $current + 2);
    ?>
    <nav class="pagination-shell" aria-label="Pagination">
        <?php
        $prevQuery = array_merge($query, ['page' => max(1, $current - 1)]);
        $nextQuery = array_merge($query, ['page' => min($totalPages, $current + 1)]);
        ?>
        <a class="pagination-link<?= !empty($meta['has_prev']) ? '' : ' is-disabled'; ?>" href="<?= !empty($meta['has_prev']) ? e(base_url($path . '?' . http_build_query($prevQuery))) : '#'; ?>">Previous</a>
        <div class="pagination-pages">
            <?php for ($page = $start; $page <= $end; $page++): ?>
                <a class="pagination-link<?= $page === $current ? ' is-active' : ''; ?>" href="<?= e(base_url($path . '?' . http_build_query(array_merge($query, ['page' => $page])))); ?>"><?= $page; ?></a>
            <?php endfor; ?>
        </div>
        <a class="pagination-link<?= !empty($meta['has_next']) ? '' : ' is-disabled'; ?>" href="<?= !empty($meta['has_next']) ? e(base_url($path . '?' . http_build_query($nextQuery))) : '#'; ?>">Next</a>
    </nav>
    <?php
}
