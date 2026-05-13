<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

$requiredExtensions = ['json', 'pdo_mysql'];
$dvaEnabled = false;
$privateConfigCandidates = [];
$documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');

if ($documentRoot !== '') {
    $privateConfigCandidates[] = dirname($documentRoot) . '/gemdata-config.php';
}
$privateConfigCandidates[] = dirname(__DIR__) . '/gemdata-config.php';
$privateConfigCandidates = array_values(array_unique($privateConfigCandidates));

$privateConfigExists = false;
foreach ($privateConfigCandidates as $candidate) {
    if (is_file($candidate)) {
        $privateConfigExists = true;
        $privateConfigPath = $candidate;
        break;
    }
}

echo "==== GEMDATA BOOTSTRAP PROBE ====\n";
echo 'Timestamp: ' . date('c') . "\n";
echo 'PHP Version: ' . PHP_VERSION . "\n";
echo 'PHP_VERSION_ID: ' . PHP_VERSION_ID . "\n";
echo 'Meets PHP 8.0+: ' . (PHP_VERSION_ID >= 80000 ? 'YES' : 'NO') . "\n";
echo "\n";

echo "Required Extensions:\n";
foreach ($requiredExtensions as $extension) {
    echo '- ' . $extension . ': ' . (extension_loaded($extension) ? 'YES' : 'NO') . "\n";
}
echo '- curl: ' . (extension_loaded('curl') ? 'YES' : 'NO') . " (required when dedicated accounts are enabled)\n";
echo "\n";

echo "Key Files:\n";
foreach ([
    'includes/bootstrap.php' => __DIR__ . '/includes/bootstrap.php',
    'includes/config.php' => __DIR__ . '/includes/config.php',
    'classes/Database.php' => __DIR__ . '/classes/Database.php',
] as $label => $path) {
    echo '- ' . $label . ': exists=' . (is_file($path) ? 'YES' : 'NO') . ', readable=' . (is_readable($path) ? 'YES' : 'NO') . "\n";
}
echo "\n";

echo "Private Config Candidates:\n";
foreach ($privateConfigCandidates as $candidate) {
    echo '- ' . $candidate . ': ' . (is_file($candidate) ? 'FOUND' : 'missing') . "\n";
}
if ($privateConfigExists) {
    echo 'Using detected path: ' . $privateConfigPath . "\n";
}
echo "\n";

echo 'Storage logs directory writable: ' . ((is_dir(__DIR__ . '/storage/logs') && is_writable(__DIR__ . '/storage/logs')) ? 'YES' : 'NO') . "\n";
echo 'Dedicated accounts enabled in probe: ' . ($dvaEnabled ? 'YES' : 'NO') . "\n";
echo "\n";

echo "Use this probe before restoring full bootstrap when diagnosing shared-hosting 500 errors.\n";
