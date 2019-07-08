<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use \Schoolbox\Greenhouse\Module as GreenhouseModule;

class Greenhouse implements ModuleConfigInterface
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'greenhouse';

        $greenhouseModule = new GreenhouseModule($kernel->getDependencyInjector());
        $greenhouseModule->init("/$namespace");
    }
}
