<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use Aura\Di;
use Alaress\DebugBar\Instance as DebugBar;
use Alaress\DebugBar\DataCollector\XhprofCollector;

/* Load Configuration Files */
require_once dirname(__FILE__) . '/../www/conf/conf.php';

/* Stdfuncs */
require_once(WEB_root.'/include/stdfuncs.php');
require_once('constants.php');

// register the shutdown_safely() function in stdfuncs as the termination script
register_shutdown_function('shutdown_safely');
set_exception_handler('shutdown_safely');

/* Set include path */
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());

/* Load the Autoloader */
require_once dirname(__FILE__) . '/Autoloader.php';
Autoloader::initialise();

// Do dependency injection stuff
// How do I get this container elsewhere without using a global?
$di = new Di\Container(new Di\Factory());
$di->set(
    'router',
    function() {
        $factory = new Aura\Router\RouterFactory();
        return $factory->newInstance();
    }
);

// Set up a web factory and its request/responses
$di->params['Aura\\Web\\WebFactory'] = array('globals' => $GLOBALS);
$di->set('webFactory', $di->lazyNew('Aura\\Web\\WebFactory'));

// inject request and response into controller
// (I may need to mangle requests in pre-processing though)
$di->setters['Schoolbox\\Web\\BaseController'] = array(
    'setRequest' => $di->lazy(function() use($di) {
        return $di->get('webFactory')->newRequest();
    }),
    'setResponse' => $di->lazy(function() use($di) {
        return $di->get('webFactory')->newResponse();
    }),
);

//// inject Twig environment
//$loader = new Twig_Loader_Filesystem($templatePaths);
//$twig = new Twig_Environment($loader);
//$twig->addExtension(new Twig_Extension_Debug());
//$di->set('templateEngine');

//
// Set up the debug bar, and add listeners for various parts of the debug bar
// (database calls, XHProf)
// Should go in a DI container instead of a static class.
if ($is_debug_enabled = DebugBar::isEnabled()) {
    DebugBar::init();
}

//
// XHProf on the debug bar.
if ($is_debug_enabled && Config::get('debug_xhprof') && extension_loaded('xhprof')) {
    include_once '/usr/share/php5-xhprof/xhprof_lib/utils/xhprof_lib.php';
    include_once '/usr/share/php5-xhprof/xhprof_lib/utils/xhprof_runs.php';
    DebugBar::getInstance()->addCollector(new XhprofCollector());
}

/* Configure Database connection */
$database = Database::getInstance();
$database->connect(SQL_dsn, SQL_user, SQL_passwd, array('debug' => $is_debug_enabled));
if ($is_debug_enabled) {
    $debug_bar = Alaress\DebugBar\Instance::getInstance();
    $debug_bar->addCollector($database->getCollector());

    $deprecated_database = DeprecatedDatabase::getInstance();
    $deprecated_database->installCollector();
    $debug_bar->addCollector($deprecated_database->getCollector());
}

// Redis connection configuration
$redis = Alaress\Redis::getInstance()->connect(Config::get('redis_url'));

/* Set default time zone */
date_default_timezone_set(Cfg::get('date_timezone'));
