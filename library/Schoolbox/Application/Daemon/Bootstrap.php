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
use Schoolbox\Application\AbstractBootstrap;
use Schoolbox\Config\ConfigMapper;
use Config\Modules\ModuleConfigInterface;
use Schoolbox\MapperFactory;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Notif\SilentNotif;
use Schoolbox\Notif\Notif;

class Bootstrap extends AbstractBootstrap {

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
		require_once SITE_root . '/library/constants.php';
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
		require_once 'library/Autoloader.php';
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

		// Redis connection configuration
		Redis::getInstance()->connect(ConfigMapper::getInstance()->get('redis_url'), null, INSTANCE_ID);
	}

}
