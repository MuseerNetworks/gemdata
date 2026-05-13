<?php

declare(strict_types=1);

/**
 * GemData security headers — called early in bootstrap after session start.
 * Safe for cPanel shared hosting / LiteSpeed environments.
 */
function emit_security_headers(): void
{
    if (headers_sent() || PHP_SAPI === 'cli') {
        return;
    }

    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');

    // Prevent MIME-type sniffing
    header('X-Content-Type-Options: nosniff');

    // XSS filter (legacy browsers)
    header('X-XSS-Protection: 1; mode=block');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Permissions policy — disable sensitive APIs not needed by VTU platform
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // Content Security Policy — permissive enough for Tailwind CDN, Chart.js CDN, and inline scripts
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com",
        "img-src 'self' data: https:",
        "font-src 'self' https:",
        "connect-src 'self'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
    ]);
    header('Content-Security-Policy: ' . $csp);
}
