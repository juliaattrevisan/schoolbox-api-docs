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
use Alaress\Web\Controller;
use Alaress\Module\Module;
use Aura\Di;
use Exception;
use OutOfBoundsException;
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
	 * A list of callbacks to call after an uncaught exception has been raised.
	 * This includes ErrorException instances.
	 *
	 * @var callable[]
	 */
	protected $exceptionCallbacks = [];

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
        $this->boot->initialiseExternalConnections();
        $this->boot->initialiseDebugging();
        $this->boot->registerErrorHandling();
        $this->boot->registerExceptionHandling();
        $this->boot->registerShutdownHandling();
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
     * Convenience method to retrieve service from DI
     *
     * @param string $service
     * @throws RuntimeException if the DI has not been initialised
     * @throws Di\Exception\ServiceNotFound
     * @return mixed
     */
    public function get($service)
    {
        $di = $this->getDependencyInjector();

        if ($di instanceof Di\Container) {
            return $di->get($service);
        }

        throw new RuntimeException("Dependency injector has not been set, cannot retrieve service");
    }


    /**
     * Returns the named controller from DI, initialising and configuring as required
     *
     * @param string $name
     * @throws OutOfBoundsException if the named controller is not registered
     * @return Controller\Base
     */
    public function getController($name)
    {
        try {
            /** @var Module $module */
            $module = $this->get("module:$name");

            // initialise the controller into the DI
            $module->initControllerDependencies();
            $module->initControllers($this->getDependencyInjector());

            // remove this reference, we don't need it anymore
            unset($module);

            // obtain and return the controller from DI now that it's been registered
            return $this->get("controller:$name");

        } catch (\Exception $e) {
            throw new OutOfBoundsException("Could not load controller '$name': is the module registered?", 0, $e);
        }
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

	/**
	 * @param callable $callable
	 */
	public function registerExceptionCallback($callable) {
		if(is_callable($callable)) {
			$this->exceptionCallbacks[] = $callable;
		}
	}

	/**
	 * Calls all exception callbacks with the given exception.
	 *
	 * @param Exception $e
	 */
	public function callExceptionCallbacks($e)
	{
		foreach ($this->exceptionCallbacks as $callback) {
			call_user_func($callback, $e);
		}
	}
}
