<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

// Load environment for Codeception tests.

use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Url\Factory;

require_once __DIR__ . '/../../conf/core/require.php';

$config = new ConfigLoader();
return [
    'SQL_HOST'   => SQL_host,
    'SQL_DB'     => SQL_db,
    'SQL_USER'   => SQL_user,
    'SQL_PASSWD' => SQL_passwd,
    'BASE_URL'   => (new Factory($config))->buildFullyQualified()->__toString(),
];
