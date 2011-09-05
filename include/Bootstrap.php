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

/* Load HTML Purifier */
require_once dirname(__FILE__) . '/HTMLPurifier/HTMLPurifier.auto.php';

/* Load the Autoloader */
require_once dirname(__FILE__) . '/Autoloader.php';
$autoloader = Autoloader::initialise();

/* Configure Database connection */
$database = Database::getInstance();
$database->connect(SQL_dsn, SQL_user, SQL_passwd);

/* Set default time zone */
date_default_timezone_set(Cfg::get('date_timezone'));