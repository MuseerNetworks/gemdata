<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/config.php';
$GLOBALS['__gemdata_container'] = ['config' => $config];
$environment = (string) config('app.environment', 'local');

error_reporting(E_ALL);
if ($environment === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
}

date_default_timezone_set((string) config('app.timezone', 'Africa/Lagos'));

if (
    config('app.environment') === 'production'
    && (bool) config('app.force_https_in_production', true)
    && !is_https_request()
    && PHP_SAPI !== 'cli'
) {
    $target = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? base_url());
    header('Location: ' . $target, true, 302);
    exit;
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => is_https_request(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

spl_autoload_register(function (string $class): void {
    $prefix = 'GemData\\Classes\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../classes/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use GemData\Classes\ApiAuth;
use GemData\Classes\ApiHandler;
use GemData\Classes\ActivityLogger;
use GemData\Classes\AdminService;
use GemData\Classes\AdminOpsService;
use GemData\Classes\Commission;
use GemData\Classes\Database;
use GemData\Classes\FraudService;
use GemData\Classes\MockVtuProvider;
use GemData\Classes\NotificationService;
use GemData\Classes\PaystackDedicatedAccountService;
use GemData\Classes\PaymentGatewayService;
use GemData\Classes\PricingService;
use GemData\Classes\ProviderManager;
use GemData\Classes\RateLimiter;
use GemData\Classes\ReportService;
use GemData\Classes\Response;
use GemData\Classes\SessionAuth;
use GemData\Classes\SettingsService;
use GemData\Classes\TransactionService;
use GemData\Classes\UserSecurityService;
use GemData\Classes\Validator;
use GemData\Classes\Wallet;

try {
    $database = new Database(config('db'));
    $validator = new Validator();
    $response = new Response();
    $activityLogger = new ActivityLogger($database);
    $settings = new SettingsService($database);
    $adminService = new AdminService($database, $activityLogger);
    $userSecurity = new UserSecurityService($database, $activityLogger);
    $auth = new SessionAuth($database, $activityLogger);
    $wallet = new Wallet($database);
    $notifications = new NotificationService($database);
    $dedicatedAccounts = new PaystackDedicatedAccountService($database, $activityLogger, $notifications);
    $payments = new PaymentGatewayService($database, $wallet, $notifications, $activityLogger);
    $commission = new Commission($database);
    $mockProvider = new MockVtuProvider();
    $pricing = new PricingService($database);
    $fraud = new FraudService($database);
    $providerManager = new ProviderManager($database, $mockProvider);
    $reportService = new ReportService($database);
    $adminOps = new AdminOpsService($database, $activityLogger, $providerManager);
    $transactionService = new TransactionService($database, $wallet, $commission, $notifications, $providerManager, $pricing, $fraud, $activityLogger);
    $rateLimiter = new RateLimiter($database, (int) config('app.rate_limit_per_minute', 60));
    $apiAuth = new ApiAuth($database, $rateLimiter);
    $apiHandler = new ApiHandler($database, $apiAuth, $transactionService);

    register_service(Database::class, $database);
    register_service(Validator::class, $validator);
    register_service(Response::class, $response);
    register_service(ActivityLogger::class, $activityLogger);
    register_service(SettingsService::class, $settings);
    register_service(AdminService::class, $adminService);
    register_service(AdminOpsService::class, $adminOps);
    register_service(UserSecurityService::class, $userSecurity);
    register_service(SessionAuth::class, $auth);
    register_service(Wallet::class, $wallet);
    register_service(NotificationService::class, $notifications);
    register_service(PaystackDedicatedAccountService::class, $dedicatedAccounts);
    register_service(PaymentGatewayService::class, $payments);
    register_service(Commission::class, $commission);
    register_service(PricingService::class, $pricing);
    register_service(FraudService::class, $fraud);
    register_service(ProviderManager::class, $providerManager);
    register_service(ReportService::class, $reportService);
    register_service(TransactionService::class, $transactionService);
    register_service(RateLimiter::class, $rateLimiter);
    register_service(ApiAuth::class, $apiAuth);
    register_service(ApiHandler::class, $apiHandler);
} catch (Throwable $exception) {
    error_log(sprintf(
        '[GemData bootstrap] %s in %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));

    if (!headers_sent()) {
        http_response_code(503);
    }

    $requestPath = (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['REQUEST_URI'] ?? '');
    $isApiRequest = str_contains($requestPath, '/api/');

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "GemData is temporarily unavailable because the database connection failed.\n");
        exit(1);
    }

    if (!headers_sent()) {
        if ($isApiRequest) {
            header('Content-Type: application/json; charset=UTF-8');
        } else {
            header('Content-Type: text/html; charset=UTF-8');
        }
    }

    if ($isApiRequest) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Service temporarily unavailable.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GemData Unavailable</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 640px; margin: 10vh auto; padding: 32px; background: #ffffff; border-radius: 16px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08); }
        h1 { margin-top: 0; font-size: 2rem; }
        p { line-height: 1.6; }
    </style>
</head>
<body>
    <main>
        <h1>Service temporarily unavailable</h1>
        <p>GemData cannot connect to the database right now. Please try again shortly.</p>
    </main>
</body>
</html>
HTML;
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api_auth.php';
require_once __DIR__ . '/view.php';
