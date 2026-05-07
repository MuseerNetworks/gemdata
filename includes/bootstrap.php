<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/config.php';
$GLOBALS['__gemdata_container'] = ['config' => $config];

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

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api_auth.php';
require_once __DIR__ . '/view.php';
