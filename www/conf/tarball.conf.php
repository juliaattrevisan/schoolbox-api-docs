<?php

/************************************************
* This file contains most config options,	*
* Settings here will reflect through all 	*
* scripts.					*
*************************************************/

define("SITE_root", realpath( dirname(__FILE__) . "/../..") );
define("WEB_root", SITE_root . '/www');

define('TEMPLATE_body', 		'bodyTpl');
define('TEMPLATE_content', 		'contentTpl');

//MySQL config options

define("SQL_host", "localhost");
define("SQL_user", "root");
define("SQL_passwd", "xxxxxx");
define("SQL_db", "schoolbox");
define("SQL_dsn", "mysql:host=".SQL_host.";dbname=".SQL_db);

// sentry
define('SENTRY_TOKEN', null);
define('SENTRY_PROJECT', null);
define('ETHERPAD_HOST', 'http://localhost:9001');

// Set Instance ID from environment variable on CLI
if (php_sapi_name() == "cli") {
    $instanceIdFromEnv = getenv('INSTANCE_ID', true) ?: getenv('INSTANCE_ID');
    if ($instanceIdFromEnv) {
        define('INSTANCE_ID', $instanceIdFromEnv);
    }
}

//Instance ID
if (!defined('INSTANCE_ID')) {
    define('INSTANCE_ID', 'xxxxxxxx');
}
