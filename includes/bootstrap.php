<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/logo.php';

$config = require __DIR__ . '/config.php';
$GLOBALS['__gemdata_container'] = ['config' => $config];
$environment = (string) config('app.environment', 'local');
require_once __DIR__ . '/../classes/AppLogger.php';
require_once __DIR__ . '/../classes/SimpleCache.php';

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
    'secure' => (bool) config('session.secure_cookie', is_https_request()) && is_https_request(),
    'httponly' => (bool) config('session.http_only', true),
    'samesite' => (string) config('session.same_site', 'Lax'),
]);
session_name((string) config('session.cookie_name', 'PHPSESSID'));
session_start();

require_once __DIR__ . '/security_headers.php';
emit_security_headers();

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
use GemData\Classes\ApiCredentialService;
use GemData\Classes\AppLogger;
use GemData\Classes\Commission;
use GemData\Classes\CommissionWallet;
use GemData\Classes\Database;
use GemData\Classes\DashboardController;
use GemData\Classes\FraudService;
use GemData\Classes\FundingAccountProviderService;
use GemData\Classes\KatPayVirtualAccountService;
use GemData\Classes\MailService;
use GemData\Classes\MaintenanceService;
use GemData\Classes\MockVtuProvider;
use GemData\Classes\NotificationService;
use GemData\Classes\PaystackDedicatedAccountService;
use GemData\Classes\PaymentGatewayService;
use GemData\Classes\XixaPay;
use GemData\Classes\PricingService;
use GemData\Classes\ProviderPlanService;
use GemData\Classes\ProviderManager;
use GemData\Classes\ProviderRouter;
use GemData\Classes\RateLimiter;
use GemData\Classes\ReportService;
use GemData\Classes\Response;
use GemData\Classes\RoleMiddleware;
use GemData\Classes\SessionAuth;
use GemData\Classes\SettingsService;
use GemData\Classes\SimpleCache;
use GemData\Classes\TransactionService;
use GemData\Classes\UserSecurityService;
use GemData\Classes\UserRoleManager;
use GemData\Classes\UpgradeRequestService;
use GemData\Classes\Validator;
use GemData\Classes\Wallet;

try {
    bootstrap_runtime_preflight();
    $appLogger = new AppLogger();
    register_service(AppLogger::class, $appLogger);
    $cache = new SimpleCache((string) config('app.cache_dir', dirname(__DIR__) . '/storage/cache'));
    register_service(SimpleCache::class, $cache);

    enforce_production_safety($environment);

    $database = new Database(config('db'), $appLogger);
    $validator = new Validator();
    $response = new Response();
    $activityLogger = new ActivityLogger($database, $appLogger);
    $mailService = new MailService(config('mail', []), $appLogger);
    $settings = new SettingsService($database);
    $maintenance = new MaintenanceService($settings, $response);
    $adminService = new AdminService($database, $activityLogger);
    $userSecurity = new UserSecurityService($database, $activityLogger);
    $auth = new SessionAuth($database, $activityLogger);
    $wallet = new Wallet($database);
    $notifications = new NotificationService($database);
    $payments = new PaymentGatewayService($database, $wallet, $notifications, $activityLogger);
    $paystackDva = new PaystackDedicatedAccountService($database, $activityLogger, $notifications);
    $katPay = new KatPayVirtualAccountService($database, $activityLogger, $notifications);
    $commissionWallet = new CommissionWallet($database);
    $commission = new Commission($database, $commissionWallet);
    $xixaPay = new XixaPay($database, $activityLogger, $notifications);
    $userRoles = new UserRoleManager();
    $roleMiddleware = new RoleMiddleware($userRoles);
    $mockProvider = new MockVtuProvider();
    $pricing = new PricingService($database, $cache);
    $providerPlans = new ProviderPlanService($database, $pricing, $cache);
    $fraud = new FraudService($database);
    $providerRouter = new ProviderRouter($database, $providerPlans, $pricing);
    $providerManager = new ProviderManager($database, $mockProvider, $appLogger, $cache, $providerPlans, $providerRouter);
    $fundingProviders = new FundingAccountProviderService($database, $settings, $katPay, $paystackDva);
    $reportService = new ReportService($database);
    $adminOps = new AdminOpsService($database, $activityLogger, $providerManager);
    $transactionService = new TransactionService($database, $wallet, $commission, $notifications, $providerManager, $pricing, $providerPlans, $fraud, $activityLogger);
    $rateLimiter = new RateLimiter($database, (int) config('app.rate_limit_per_minute', 60));
    $apiCredentials = new ApiCredentialService($database);
    $apiAuth = new ApiAuth($database, $rateLimiter);
    $apiHandler = new ApiHandler($database, $apiAuth, $transactionService);

    register_service(Database::class, $database);
    register_service(Validator::class, $validator);
    register_service(Response::class, $response);
    register_service(ActivityLogger::class, $activityLogger);
    register_service(MailService::class, $mailService);
    register_service(MaintenanceService::class, $maintenance);
    register_service(SettingsService::class, $settings);
    register_service(AdminService::class, $adminService);
    register_service(AdminOpsService::class, $adminOps);
    register_service(UserSecurityService::class, $userSecurity);
    register_service(SessionAuth::class, $auth);
    register_service(Wallet::class, $wallet);
    register_service(NotificationService::class, $notifications);
    register_service(PaymentGatewayService::class, $payments);
    register_service(PaystackDedicatedAccountService::class, $paystackDva);
    register_service(KatPayVirtualAccountService::class, $katPay);
    register_service(FundingAccountProviderService::class, $fundingProviders);
    register_service(Commission::class, $commission);
    register_service(CommissionWallet::class, $commissionWallet);
    register_service(XixaPay::class, $xixaPay);
    register_service(UserRoleManager::class, $userRoles);
    register_service(RoleMiddleware::class, $roleMiddleware);
    register_service(PricingService::class, $pricing);
    register_service(ProviderPlanService::class, $providerPlans);
    register_service(ProviderRouter::class, $providerRouter);
    register_service(FraudService::class, $fraud);
    register_service(ProviderManager::class, $providerManager);
    register_service(ReportService::class, $reportService);
    register_service(TransactionService::class, $transactionService);
    register_service(RateLimiter::class, $rateLimiter);
    register_service(ApiCredentialService::class, $apiCredentials);
    register_service(ApiAuth::class, $apiAuth);
    register_service(ApiHandler::class, $apiHandler);
    register_service(DashboardController::class, new DashboardController($database, $wallet, $fundingProviders, $providerPlans, $userRoles));
    register_service(UpgradeRequestService::class, new UpgradeRequestService($database, $userRoles));

    $maintenance->enforce();
} catch (Throwable $exception) {
    $logger = $GLOBALS['__gemdata_container'][AppLogger::class] ?? null;
    if ($logger instanceof AppLogger) {
        $logger->error('Bootstrap failed.', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
    bootstrap_emergency_log($exception);

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

function enforce_production_safety(string $environment): void
{
    if ($environment !== 'production') {
        return;
    }

    if (!config_meta('private_override_loaded', false)) {
        throw new RuntimeException('Production config file is missing.');
    }

    if (config_meta('local_override_loaded', false)) {
        throw new RuntimeException('Local override must not load in production.');
    }

    $dbHost = (string) config('db.host', '');
    $dbName = (string) config('db.dbname', '');
    $dbUser = (string) config('db.username', '');
    $dbPassword = (string) config('db.password', '');
    $gateway = (string) config('payments.default_gateway', '');
    $webhookSecret = trim((string) config('webhooks.shared_secret', ''));

    if ($dbName === 'gemdata_api' || $dbUser === 'root' || $dbPassword === '' || $dbHost === '127.0.0.1') {
        throw new RuntimeException('Production database configuration is unsafe.');
    }

    if ($gateway === 'mock') {
        throw new RuntimeException('Mock payment gateway cannot run in production.');
    }

    if ($webhookSecret === '') {
        throw new RuntimeException('Webhook secret is required in production.');
    }

    if ((bool) config('mail.debug_display_reset_links', false)) {
        throw new RuntimeException('Reset-link debug output must be disabled in production.');
    }
}

function bootstrap_runtime_preflight(): void
{
    if (PHP_VERSION_ID < 80000) {
        throw new RuntimeException('PHP 8.0 or newer is required.');
    }

    $required = array_values(array_unique(array_filter(array_map(
        static fn($extension): string => strtolower(trim((string) $extension)),
        (array) config('app.required_extensions', ['json', 'pdo_mysql'])
    ))));

    if (
        (
            trim((string) config('payments.xixapay_api_key', '')) !== ''
            || trim((string) config('payments.xixapay_api_secret', '')) !== ''
            || trim((string) config('payments.paystack_secret_key', '')) !== ''
            || trim((string) config('payments.katpay_secret_key', '')) !== ''
            || trim((string) config('payments.katpay_api_key', '')) !== ''
        )
        && !in_array('curl', $required, true)
    ) {
        $required[] = 'curl';
    }

    foreach ((array) config('providers', []) as $providerConfig) {
        if (!is_array($providerConfig)) {
            continue;
        }

        if (!empty($providerConfig['enabled']) && strtolower((string) ($providerConfig['driver'] ?? '')) === 'albani' && !in_array('curl', $required, true)) {
            $required[] = 'curl';
        }
    }

    foreach ($required as $extension) {
        if ($extension === '') {
            continue;
        }

        if (!extension_loaded($extension)) {
            throw new RuntimeException(sprintf('Required PHP extension missing: %s.', $extension));
        }
    }
}

function bootstrap_emergency_log(Throwable $exception): void
{
    $line = sprintf(
        '[GemData bootstrap][%s] %s in %s:%d',
        date('c'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );

    error_log($line);

    if (!(bool) config('app.bootstrap_log_to_file', true)) {
        return;
    }

    $logFile = trim((string) config('app.bootstrap_log_file', dirname(__DIR__) . '/storage/logs/bootstrap.log'));
    if ($logFile === '') {
        return;
    }

    $directory = dirname($logFile);
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return;
    }

    if (!is_writable($directory) && (!is_file($logFile) || !is_writable($logFile))) {
        return;
    }

    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
