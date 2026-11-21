<?php
header('Content-Type: application/json');
$checks = [
    'php_version' => PHP_VERSION,
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'json' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring'),
    'openssl' => extension_loaded('openssl'),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];
$allOk = $checks['pdo_mysql'] && $checks['json'] && $checks['mbstring'] && $checks['openssl'];
echo json_encode([
    'status' => $allOk ? 'READY_FOR_SENSOR_PLATFORM' : 'MISSING_EXTENSIONS',
    'checks' => $checks
], JSON_PRETTY_PRINT);
