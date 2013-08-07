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
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());

/* Load the Autoloader */
require_once dirname(__FILE__) . '/Autoloader.php';
Autoloader::initialise();

//
// Set up the debug bar, and add listeners for various parts of the debug bar
// (database calls, XHProf)
// Should go in a DI container instead of a static class.
if ($is_debug_enabled = Debug\DebugBar::isEnabled()) {
    Debug\DebugBar::init();
}

//
// XHProf on the debug bar.
if ($is_debug_enabled && extension_loaded('xhprof')) {
    include_once '/usr/share/php5-xhprof/xhprof_lib/utils/xhprof_lib.php';
    include_once '/usr/share/php5-xhprof/xhprof_lib/utils/xhprof_runs.php';
    Debug\DebugBar::getInstance()->addCollector(new Debug\DataCollector\XhprofCollector());
}

/* Configure Database connection */
$database = Database::getInstance();
$database->connect(SQL_dsn, SQL_user, SQL_passwd, array('debug' => $is_debug_enabled));
if ($is_debug_enabled) {
    $debug_bar = Debug\DebugBar::getInstance();
    $debug_bar->addCollector($database->getCollector());
}

/* Set default time zone */
date_default_timezone_set(Cfg::get('date_timezone'));