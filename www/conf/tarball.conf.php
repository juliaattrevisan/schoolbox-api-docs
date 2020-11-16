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
// No longer in use, but cannot be removed till all schoolbox instances + datasets have updated to 20.1.6
// This is because the update script that moves this config to the DB reads the value from here.
// eg, consider upgrade from 20.1.5 to X, the X codebase must continue to have this variable here
//     so long as any instance still hasn't run the 20.1.6 migration.
/** @deprecated 20.1.6 */
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
