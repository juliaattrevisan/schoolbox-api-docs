<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


/* Load Configuration Files */
require_once __DIR__ . '/../../www/conf/conf.php';
require_once __DIR__ . '/../../include/constants.php';

require_once WEB_root . '/include/stdfuncs.php';

/* Set include path */
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());

/* Load the Autoloader */
require_once __DIR__ . '/../../include/Autoloader.php';
Autoloader::initialise();

// need DB connection in the main daemon processes
$database = Database::getInstance();
$database->connect(SQL_dsn, SQL_user, SQL_passwd);

/* Set default time zone */
date_default_timezone_set(Cfg::get('date_timezone'));
