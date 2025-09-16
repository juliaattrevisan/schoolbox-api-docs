<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

// Load environment for Codeception tests.

use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Url\Factory;

require_once __DIR__ . '/../../conf/core/require.php';

// Load base URL from config if possible:
// otherwise, make an assumption based on the first hostname in instances.ini
$config = new ConfigLoader();
try {
    $baseUrl = (new Factory($config))->buildFullyQualified()->__toString();
} catch (\Alaress\Database\Exception $e) {
    $instances = parse_ini_file("/etc/schoolbox/instances.ini", true);
    $baseUrl   = 'https://' . ($instances[INSTANCE_ID]['hostnames'][0] ?? 'localhost');
}
return [
    'SQL_HOST'   => SQL_host,
    'SQL_DB'     => SQL_db,
    'SQL_USER'   => SQL_user,
    'SQL_PASSWD' => SQL_passwd,
    'BASE_URL'   => $baseUrl
];
