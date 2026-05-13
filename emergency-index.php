<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

$paths = [
    'index.php' => __DIR__ . '/index.php',
    'bootstrap.php' => __DIR__ . '/includes/bootstrap.php',
    'config.php' => __DIR__ . '/includes/config.php',
    'database.php' => __DIR__ . '/classes/Database.php',
    'private_config_guess' => dirname(__DIR__) . '/gemdata-config.php',
];

echo "==== GEMDATA EMERGENCY ENTRYPOINT ====\n";
echo 'Timestamp: ' . date('c') . "\n";
echo 'PHP Version: ' . PHP_VERSION . "\n";
echo 'PHP SAPI: ' . PHP_SAPI . "\n";
echo 'Document Root: ' . (string) ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "\n";
echo 'Current Working Directory: ' . getcwd() . "\n";
echo 'Script File: ' . __FILE__ . "\n";
echo 'Request URI: ' . (string) ($_SERVER['REQUEST_URI'] ?? '/') . "\n";
echo "\n";

foreach ($paths as $label => $path) {
    echo $label . ': ' . $path . "\n";
    echo '  exists=' . (is_file($path) ? 'YES' : 'NO') . "\n";
    echo '  readable=' . (is_readable($path) ? 'YES' : 'NO') . "\n";
}

echo "\nIf this file loads but index.php does not, the problem is above normal app bootstrap.\n";
