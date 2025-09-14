<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

// Set up class autoloader, but not via conf/core/require:
// that would load from instances.ini, which may not exist.
require __DIR__ . '/../../vendor/autoload.php';

// Bypass load of config from instances.ini for unit tests
define('INSTANCE_ID', getenv('INSTANCE_ID'));
define('INSTANCE_ID_LIST', [INSTANCE_ID]);
define('SQL_host', '');
define('SQL_user', '');
define('SQL_db', '');
define('SQL_passwd', '');
define('SQL_dsn', '');
define('SQL_host_read', '');
define('SQL_user_read', '');
define('SQL_db_read', '');
define('SQL_passwd_read', '');
define('SQL_dsn_read', '');
define('TEMPLATE_body', 'bodyTpl');
define('TEMPLATE_content', 'contentTpl');

// Init Sentry before almost anything else
Alaress\Sentry\Config::init('', 0, 0, 0, '', '');

// Global functions and constants
require_once __DIR__ . '/../../www/include/stdfuncs.php';

// Set up DI container in mappers: this is unfortunately necessary in some tests
Schoolbox\Mapper::di((new Aura\Di\ContainerBuilder())->newInstance());
