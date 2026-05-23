<?php
/**
 * GemData Logo System — v2 (Real PNG, cache-busted)
 * Uses the REAL uploaded brand image. Version string forces browser cache refresh.
 */

// Increment this when brand assets change — busts all browser caches
define('GEMDATA_BRAND_VERSION', '20260522a');

function gemdata_logo(
    string $variant = 'full',
    string $theme   = 'auto',
    string $class   = '',
    string $alt     = 'GemData'
): string {
    $v       = GEMDATA_BRAND_VERSION;
    $iconSrc = base_url('assets/brand/icon.png') . '?v=' . $v;
    $cls     = $class ? ' ' . htmlspecialchars($class, ENT_QUOTES) : '';

    if ($variant === 'icon') {
        return '<img src="' . e($iconSrc) . '"'
             . ' alt="' . e($alt) . '"'
             . ' class="gemdata-brand-icon' . $cls . '"'
             . ' width="40" height="40"'
             . ' loading="eager"'
             . ' decoding="async"'
             . ' onerror="this.style.display=\'none\'"'
             . '>';
    }

    if ($variant === 'wordmark') {
        return '<span class="gemdata-wordmark' . $cls . '">'
             . '<strong>GemData</strong>'
             . '<small>Trust In Data</small>'
             . '</span>';
    }

    // Full brand block: icon image + text
    return '<span class="gemdata-logo-full' . $cls . '">'
         . '<img src="' . e($iconSrc) . '"'
         . '     alt="' . e($alt) . '"'
         . '     class="gemdata-brand-icon"'
         . '     width="40" height="40"'
         . '     loading="eager"'
         . '     decoding="async"'
         . '     onerror="this.style.display=\'none\'">'
         . '<span class="gemdata-logo-text">'
         . '<span class="gemdata-logo-name">GemData</span>'
         . '<span class="gemdata-logo-tag">Trust In Data</span>'
         . '</span>'
         . '</span>';
}
