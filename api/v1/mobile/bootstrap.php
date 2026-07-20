<?php

declare(strict_types=1);

// Disable direct HTML output and redirect-based behaviors
define('GEMDATA_MOBILE_API', true);

require_once __DIR__ . '/../../../includes/bootstrap.php';

// Set strict JSON and security headers
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    $allowedOrigins = [
        'http://localhost',
        'https://localhost',
        'capacitor://localhost'
    ];
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($requestOrigin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: " . $requestOrigin);
    } else {
        header("Access-Control-Allow-Origin: http://localhost");
    }

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

    // Re-initialize session with SameSite=None to allow cross-origin cookie transmission in WebViews
    if (session_status() === PHP_SESSION_ACTIVE) {
        $sessionId = session_id();
        session_write_close();
        
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);
        
        if ($sessionId !== '') {
            session_id($sessionId);
        }
        session_start();
    }
}

// Handle OPTIONS preflight requests
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Performance monitoring start time
$GLOBALS['__mobile_api_start_time'] = microtime(true);

// Structured logging helper
register_shutdown_function(static function (): void {
    $startTime = $GLOBALS['__mobile_api_start_time'] ?? microtime(true);
    $durationMs = round((microtime(true) - $startTime) * 1000, 2);
    
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
    $statusCode = http_response_code();

    // Sanitize input payload logging (scrub sensitive parameters)
    $scrubbedParams = $_REQUEST;
    $sensitiveKeys = ['password', 'password_hash', 'transaction_pin', 'transaction_pin_hash', 'pin', 'cookie'];
    array_walk_recursive($scrubbedParams, static function (&$val, $key) use ($sensitiveKeys): void {
        if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
            $val = '********';
        }
    });

    $logPayload = [
        'uri' => $requestUri,
        'method' => $requestMethod,
        'status' => $statusCode,
        'duration_ms' => $durationMs,
        'ip' => client_ip(),
        'user_agent' => current_user_agent(),
        'payload' => $scrubbedParams
    ];

    $appLogger = app(\GemData\Classes\AppLogger::class);
    if ($statusCode >= 400) {
        $appLogger->error("Mobile API Error - Code {$statusCode} on {$requestMethod} {$requestUri}", $logPayload);
    } else {
        $appLogger->info("Mobile API Request - {$requestMethod} {$requestUri}", $logPayload);
    }
});

// Rate limiting utility for mobile app endpoints
function check_mobile_rate_limit(string $action, int $maxAttempts = 10, int $decaySeconds = 60): void
{
    $ip = client_ip();
    $cache = app(\GemData\Classes\SimpleCache::class);
    $key = "rate_limit:mobile:" . sha1($action . ':' . $ip);
    
    $attempts = (int) $cache->get($key, 0);
    if ($attempts >= $maxAttempts) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please try again in a minute.'
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    $cache->put($key, $attempts + 1, $decaySeconds);
}

// Authentication validator helper for protected endpoints
function require_mobile_user(): array
{
    $user = user();
    if (!$user || session_timed_out()) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            auth()->logoutUser();
        }
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Your session has expired. Please sign in again.'
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    $_SESSION['last_activity_at'] = time();
    return $user;
}

// Strict JSON error output handler for unhandled exceptions
set_exception_handler(static function (Throwable $e): void {
    $statusCode = 500;
    if ($e instanceof InvalidArgumentException) {
        $statusCode = 422;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $statusCode === 500 ? 'An internal server error occurred.' : $e->getMessage(),
        'errors' => $statusCode === 422 ? (json_decode($e->getMessage(), true) ?: []) : []
    ], JSON_UNESCAPED_SLASHES);
    exit;
});

// Extract Authorization Bearer token from request headers
function get_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    if (!$header && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    }
    if ($header && preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

// Authenticate session via device refresh token if cookie session is not set or active
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['user_id'])) {
    $bearer = get_bearer_token();
    if ($bearer !== null) {
        try {
            $tokenHash = hash('sha256', $bearer);
            $tokenRow = db()->first(
                'SELECT * FROM mobile_device_tokens WHERE token_hash = :hash AND expires_at > NOW() LIMIT 1',
                ['hash' => $tokenHash]
            );
            if ($tokenRow) {
                $_SESSION['user_id'] = (int) $tokenRow['user_id'];
                $_SESSION['last_activity_at'] = time();
                $_SESSION['mobile_device_id'] = (string) $tokenRow['device_id'];
            }
        } catch (Throwable $tokenEx) {
            // mobile_device_tokens table may not exist yet — skip token auth, fall back to session
            // Run the SQL migration (database/migrations/mobile_tables.sql) to enable persistent token auth
            error_log('[GemData Mobile] Bearer token lookup failed (table may not exist): ' . $tokenEx->getMessage());
        }
    }
}

