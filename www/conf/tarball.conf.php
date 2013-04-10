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



//module config options
define('HELPDESK_enable', false);
define('HELPDESK_location', '/var/www/helpdesk');
define('HELPDESK_db', 'helpdesk');


?>