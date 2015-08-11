<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\Daemon;


use Alaress\Application\Boot\BootstrapInterface;
use Alaress\Redis;
use Alaress\Web\Router\RouterFactory;
use Aura\Di;
use Autoloader;
use Database;
use Schoolbox\Config\ConfigMapper;
use Config\Modules\ModuleConfigInterface;

class Bootstrap implements BootstrapInterface {

	/** @var Kernel */
	protected $kernel;


	/**
	 * @param Kernel $kernel
	 */
	public function __construct($kernel) {
		$this->kernel = $kernel;
	}


	/**
	 * Load the core configuration file(s)
	 *
	 * @return void
	 */
	public function loadConfiguration() {
		require_once __DIR__ . '/../../../../www/conf/conf.php';
		require_once SITE_root . '/www/include/stdfuncs.php';
		require_once SITE_root . '/include/constants.php';
	}


	/**
	 * Set the include path
	 *
	 * @return void
	 */
	public function setIncludePath() {
		set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());
	}


	/**
	 * Register any autoloading required
	 *
	 * @return void
	 */
	public function registerAutoLoading() {
		require_once 'include/Autoloader.php';
		Autoloader::initialise();
	}


	/**
	 * Initialise debugging
	 *
	 * @return void
	 */
	public function initialiseDebugging() {
		// no debugging
	}


	/**
	 * Register any error handling required
	 *
	 * @return void
	 */
	public function registerErrorHandling() {
		set_error_handler(array($this->kernel, 'onError'));
	}


	/**
	 * Register any out-the-top exception handling required
	 *
	 * @return void
	 */
	public function registerExceptionHandling() {
		set_exception_handler(array($this->kernel, 'onException'));
	}


	/**
	 * Register any shutdown handling required
	 *
	 * @return void
	 */
	public function registerShutdownHandling() {
		register_shutdown_function(array($this->kernel, 'onShutdown'));
	}


	/**
	 * Initialise connections to databases, etc.
	 *
	 * @return void
	 */
	public function initialiseExternalConnections() {

		// MySQL connection configuration
		Database::getInstance()->connect(SQL_dsn, SQL_user, SQL_passwd, array('throw' => true));
		Database::getInstance()->setTimezone(ConfigMapper::getInstance()->get('date_timezone'));

		// Redis connection configuration
		Redis::getInstance()->connect(ConfigMapper::getInstance()->get('redis_url'));
	}


	/**
	 * Install any internal dependencies into the kernel (routing, etc.)
	 *
	 * @return void
	 */
	public function installDependencies() {
		// Do dependency injection stuff
		$this->kernel->setDependencyInjector(new Di\Container(new Di\Factory()));
		$di = $this->kernel->getDependencyInjector();

		// Configuration
		$di->set('config', $di->lazyNew('Schoolbox\\Config\\ConfigMapper'));

        // Set correct timezone
        date_default_timezone_set($di->get('config')->get('date_timezone'));

        // All modules relate to web requests, not daemons.
        // Therefore, no modules need to be set up.

//		require_once 'conf/modules/ModuleConfigInterface.php';
//
//		// Initialise module configuration
//		foreach(glob(SITE_root . '/conf/modules/*.php') as $routePath) {
//			require_once $routePath;
//			$start = strrpos($routePath, '/') + 1;
//			$length = strrpos($routePath, '.php') - $start;
//			$class = 'Config\\Modules\\' . substr($routePath, $start, $length);
//
//			if(class_exists($class, false)) {
//				/** @var ModuleConfigInterface $obj */
//				$obj = new $class();
//				$obj->configure($this->kernel);
//			}
//		}

	}

} 