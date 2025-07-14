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
    $readable = $exists ? is_readable($file) : false;
    $valid = false;
    $error = null;

    if ($exists && $readable) {
        $content = file_get_contents($file);
        if (str_ends_with($file, '.json')) {
            json_decode($content);
            $valid = json_last_error() === JSON_ERROR_NONE;
            if (!$valid) {
                $error = json_last_error_msg();
            }
        } elseif (str_ends_with($file, '.ini')) {
            $parsed = parse_ini_string($content);
            $valid = $parsed !== false;
            if (!$valid) {
                $error = 'Invalid INI format';
            }
        } else {
            $valid = true;
        }
    }

    $checks[$file] = [
        'exists' => $exists,
        'readable' => $readable,
        'valid' => $valid,
        'error' => $error
    ];

    if (!$exists || !$readable || !$valid) {
        $allFilesExist = false;
        $status = 'unhealthy';
    }
}

$response = [
    'status' => $status,
    'timestamp' => date('c'),
    'checks' => $checks,
];

http_response_code($allFilesExist ? 200 : 503);
echo json_encode($response);
