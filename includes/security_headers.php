<?php

declare(strict_types=1);

/**
 * GemData security headers - called early in bootstrap after session start.
 * Safe for cPanel shared hosting / LiteSpeed environments.
 */
function emit_security_headers(): void
{
    if (headers_sent() || PHP_SAPI === 'cli') {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    $nonce = csp_nonce();
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'nonce-{$nonce}' https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
        "style-src 'self' 'nonce-{$nonce}' https://cdn.tailwindcss.com",
        "img-src 'self' data: https:",
        "font-src 'self' https:",
        "connect-src 'self'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
    ]);
    header('Content-Security-Policy: ' . $csp);

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    header_remove('X-Powered-By');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}
