<?php
// Temporary diagnostic — DELETE THIS FILE after use
echo '<pre>';
echo 'PHP version: ' . PHP_VERSION . "\n";
echo 'PDO drivers: ' . implode(', ', PDO::getAvailableDrivers()) . "\n";
echo 'cURL loaded: ' . (function_exists('curl_init') ? 'YES' : 'NO') . "\n";

$config = __DIR__ . '/config.php';
echo 'config.php exists: ' . (file_exists($config) ? 'YES' : 'NO - CREATE THIS FILE') . "\n";

$db_dir  = dirname(__DIR__);
$db_path = $db_dir . '/spotify-companion.db';
echo 'DB directory: ' . $db_dir . "\n";
echo 'DB dir writable: ' . (is_writable($db_dir) ? 'YES' : 'NO - permissions issue') . "\n";
echo 'DB file exists: ' . (file_exists($db_path) ? 'YES' : 'not yet') . "\n";
echo '</pre>';
