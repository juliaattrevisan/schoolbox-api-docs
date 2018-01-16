<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Application\Boot;


use Alaress\Application\Kernel;

interface BootstrapInterface {

	/**
	 * @param Kernel $kernel
	 */
	public function __construct($kernel);


	/* Alaress\Application will seek to execute these methods IN THIS ORDER */

	/**
	 * Load the core configuration file(s)
	 *
	 * @return void
	 */
	public function loadConfiguration();


	/**
	 * Set the include path
	 *
	 * @return void
	 */
	public function setIncludePath();


	/**
	 * Register any autoloading required
	 *
	 * @return void
	 */
	public function registerAutoLoading();


	/**
	 * Initialise debugging
	 *
	 * @return void
	 */
	public function initialiseDebugging();


	/**
	 * Register any error handling required
	 *
	 * @return void
	 */
	public function registerErrorHandling();


	/**
	 * Register any out-the-top exception handling required
	 *
	 * @return void
	 */
	public function registerExceptionHandling();


	/**
	 * Register any shutdown handling required
	 *
	 * @return void
	 */
	public function registerShutdownHandling();


	/**
	 * Initialise connections to databases, etc.
	 *
	 * @return void
	 */
	public function initialiseExternalConnections();


	/**
	 * Install any internal dependencies into the kernel (routing, etc.)
	 *
	 * @return void
	 */
	public function installDependencies();


    /**
     * Run any middleware code which may manipulate or reject requests before
     * they enter the kernel.
     *
     * @return void
     */
    public function runMiddleware();
}
