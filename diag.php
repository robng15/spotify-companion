<?php
echo '<pre>';

$httpdocs  = __DIR__;
$vhost_root = dirname(__DIR__);
$db_path   = $vhost_root . '/spotify-companion.db';
$log_path  = $vhost_root . '/callback.log';

echo "=== Paths ===\n";
echo "httpdocs:    $httpdocs\n";
echo "vhost root:  $vhost_root\n";
echo "DB path:     $db_path\n\n";

echo "=== Permissions ===\n";
echo "httpdocs writable:   " . (is_writable($httpdocs)   ? 'YES' : 'NO') . "\n";
echo "vhost root writable: " . (is_writable($vhost_root) ? 'YES' : 'NO') . "\n\n";

echo "=== Files ===\n";
echo "DB exists:     " . (file_exists($db_path) ? 'YES — ' . number_format(filesize($db_path)) . ' bytes' : 'NO') . "\n";
echo "Log exists:    " . (file_exists($log_path) ? 'YES' : 'NO') . "\n\n";

// Test write to vhost root
$test = $vhost_root . '/write-test.tmp';
$ok   = @file_put_contents($test, 'ok');
echo "Write test to vhost root: " . ($ok !== false ? 'OK' : 'FAILED') . "\n";
if ($ok !== false) @unlink($test);

// Show log if it exists
if (file_exists($log_path)) {
    echo "\n=== callback.log ===\n";
    echo htmlspecialchars(file_get_contents($log_path));
}

echo '</pre>';
