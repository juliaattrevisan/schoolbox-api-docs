<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Application;
use Alaress\Application\Boot\BootstrapInterface;
use Aura\Di;
use Exception;
use RuntimeException;

/**
 * Class Kernel
 * Also known as a FrontController or Mediator.
 *
 * @package Alaress\Application
 */
abstract class Kernel {

	/** @var BootstrapInterface */
	protected $boot;

	/** @var Di\Container */
	protected $di;

	/** @var callable */
	protected $shutdown;

	/**
	 * Load the boot loader, returning success or failure
	 *
	 * @return bool
	 */
	public function boot() {
		// if it's not a bootstrap, don't bother trying
		if(!$this->boot instanceof BootstrapInterface) {
			return false;
		}

        // run the launch sequence
        $this->boot->loadConfiguration();
        $this->boot->setIncludePath();
        $this->boot->registerAutoLoading();
        $this->boot->initialiseDebugging();
        $this->boot->registerErrorHandling();
        $this->boot->registerExceptionHandling();
        $this->boot->registerShutdownHandling();
        $this->boot->initialiseExternalConnections();
        $this->boot->installDependencies();

		return true;
	}


	/**
	 * @param Di\Container $di
	 * @throws RuntimeException
	 */
	public function setDependencyInjector($di) {
		// only allow once
		if(is_null($this->di)) {
			$this->di = $di;
		}
		else {
			throw new RuntimeException("Dependency injector has already been set, cannot set again");
		}
	}


	/**
	 * @return Di\Container
	 */
	public function getDependencyInjector() {
		return $this->di;
	}


	/**
	 * Go. Do stuff.
	 *
	 * @return void
	 */
	public abstract function run();


	/**
	 * @param callable $callable
	 */
	public function registerCustomShutdown($callable) {
		if(is_callable($callable)) {
			$this->shutdown = $callable;
		}
	}

}