<?php
// minimal recovery deployment / emergency fallback
header('Content-Type: text/plain');
echo "==== GEMDATA RECOVERY DIAGNOSTIC ====\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Server API: " . PHP_SAPI . "\n";
echo "Current User: " . get_current_user() . "\n";
echo "Index Permissions: " . substr(sprintf('%o', fileperms(__FILE__)), -4) . "\n";

if (is_writable(__DIR__)) {
    echo "Directory is Writable: YES\n";
} else {
    echo "Directory is Writable: NO\n";
}

echo "\nIf you are seeing this text, PHP and LiteSpeed are working correctly.\n";
echo "The 500 Error was caused by permissions or .htaccess rules.\n";
