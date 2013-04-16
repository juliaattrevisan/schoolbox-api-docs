<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

/* Load Configuration Files */
require_once dirname(__FILE__) . '/../www/conf/conf.php';

/* Stdfuncs */
require_once(WEB_root.'/include/stdfuncs.php');
require_once('constants.php');

// register the shutdown_safely() function in stdfuncs as the termination script
register_shutdown_function('shutdown_safely');

/* Set include path */
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . SITE_root . '/include/PEAR' . PATH_SEPARATOR . get_include_path());

/* Load the Autoloader */
require_once dirname(__FILE__) . '/Autoloader.php';
$autoloader = Autoloader::initialise();

/* Configure Database connection */
$database = Database::getInstance();
$database->connect(SQL_dsn, SQL_user, SQL_passwd);

/* Set default time zone */
date_default_timezone_set(Cfg::get('date_timezone'));