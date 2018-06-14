<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use \Schoolbox\Service\Module as ServiceModule;

class Service implements ModuleConfigInterface
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'service';

        $greenhouseModule = new ServiceModule($kernel->getDependencyInjector());
        $greenhouseModule->init("/$namespace");
    }
}
