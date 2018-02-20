<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\BasicRequest;

class Kernel extends \Alaress\Application\Kernel
{

    /** @var bool */
    protected $debugMode;


    /**
    * Initialise
    */
    public function __construct()
    {
        $this->boot = new Bootstrap($this);
        $this->boot();
    }

    /**
     * Load the boot loader, returning success or failure
     *
     * @return bool
     */
    public function boot() {
        // if it's not a bootstrap, don't bother trying
        if(!$this->boot instanceof Bootstrap) {
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
        $this->boot->runMiddleware();

        return true;
    }

    /**
    * Execute the main script
    */
    public function run()
    {
    }
}
