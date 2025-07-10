<?php
/**
 * Simple healthcheck endpoint that verifies essential configuration files exist
 * Does not load the Schoolbox kernel for performance and reliability
 */

header('Content-Type: application/json');

$requiredFiles = [
    '/etc/schoolbox/instances.ini',
    '/etc/schoolbox/config.ini',
    '/etc/schoolbox/instance-registry.json'
];

$status = 'healthy';
$checks = [];
$allFilesExist = true;

foreach ($requiredFiles as $file) {
    $exists = file_exists($file);
    $checks[$file] = [
        'exists' => $exists,
        'readable' => $exists ? is_readable($file) : false
    ];
    
    if (!$exists) {
        $allFilesExist = false;
        $status = 'unhealthy';
    }
}

$response = [
    'status' => $status,
    'timestamp' => date('c'),
    'checks' => $checks
];

http_response_code($allFilesExist ? 200 : 503);
echo json_encode($response, JSON_PRETTY_PRINT);