<?php

/************************************************
* This file contains most config options,       *
* Settings here will reflect through all        *
* scripts.                                      *
*************************************************/

define("SITE_root", "/usr/share/schoolbox");
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

//Instance ID
if (!defined('INSTANCE_ID')) {
    define('INSTANCE_ID', 'xxxxxxxx');
}

?>