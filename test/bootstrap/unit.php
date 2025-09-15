<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

// Set up class autoloader, but not via conf/core/require:
// that would load from instances.ini, which may not exist.
require __DIR__ . '/../../vendor/autoload.php';

// Load site_root consts here
define('SITE_root', dirname(__DIR__, 2));
define('WEB_root', SITE_root . '/www');

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

// Load default values for Sentry consts
define('SENTRY_TOKEN', '');
define('SENTRY_PROJECT', '');
define('SENTRY_DSN', '');
define('SENTRY_ERROR_SAMPLE_RATE', 0);
define('SENTRY_TRACE_SAMPLE_RATE', 0);
define('SENTRY_PROFILE_SAMPLE_RATE', 0);

// Init Sentry before almost anything else
Alaress\Sentry\Config::init(
    SENTRY_DSN,
    SENTRY_ERROR_SAMPLE_RATE,
    SENTRY_TRACE_SAMPLE_RATE,
    SENTRY_PROFILE_SAMPLE_RATE,
    SENTRY_TOKEN,
    SENTRY_PROJECT,
);

// Global functions and constants
require_once __DIR__ . '/../../www/include/stdfuncs.php';

// Set up DI container in mappers: this is unfortunately necessary in some tests
Schoolbox\Mapper::di((new Aura\Di\ContainerBuilder())->newInstance());
