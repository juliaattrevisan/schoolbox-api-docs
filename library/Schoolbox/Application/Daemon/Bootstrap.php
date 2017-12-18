<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\Daemon;

use Alaress\Redis;
use Autoloader;
use Database;
use React\Stomp;
use Schoolbox\Application\AbstractBootstrap;
use Schoolbox\Config\ConfigMapper;


/**
 * Class Bootstrap
 * @package Schoolbox\Application\Daemon
 * @method Kernel getKernel()
 */
class Bootstrap extends AbstractBootstrap {

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
		set_error_handler(array($this->getKernel(), 'onError'));
	}


	/**
	 * Register any out-the-top exception handling required
	 *
	 * @return void
	 */
	public function registerExceptionHandling() {
		set_exception_handler(array($this->getKernel(), 'onException'));
	}


	/**
	 * Register any shutdown handling required
	 *
	 * @return void
	 */
	public function registerShutdownHandling() {
		register_shutdown_function(array($this->getKernel(), 'onShutdown'));
	}


	/**
	 * Initialise connections to databases, etc.
	 *
	 * @return void
	 */
	public function initialiseExternalConnections() {

		// MySQL connection configuration
		Database::getInstance()->connect(SQL_dsn, SQL_user, SQL_passwd, array('throw' => true));
	}

    /**
     * Install any internal dependencies into the kernel (routing, etc.)

     * @throws \Aura\Di\Exception
     * @return void
     */
    public function installDependencies()
    {
        parent::installDependencies();

        $di = $this->getKernel()->getDependencyInjector();

        $di->set('stomp.factory', $di->lazyNew(
            Stomp\Factory::class, [
            'loop' => $di->lazyValue('react.loop')
        ]));

        $di->set('stomp', $di->lazy(function () use ($di) {
            $conf = $di->get('config');

            return $di->get('stomp.factory')->createClient(
                [
                    'host'     => $conf->get('amqp_host'),
                    'port'     => $conf->get('amqp_stomp_port'),
                    'vhost'    => $conf->get('amqp_vhost'),
                    'login'    => $conf->get('amqp_user'),
                    'passcode' => $conf->get('amqp_pass'),
                ]
            );
        }));

        $di->values['logger'] = $this->getKernel()->logger();
    }

}
