<?php
/**
 * Configuration for the pastoral module.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\Pastoral\Module as PastoralModule;

class Pastoral extends ModuleLoader implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     *
     * @return void
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        if ($di->get('config')->get('pastoral_enabled')) {
            $this->loadModule($kernel, PastoralModule::class, "/pastoral");
        }
    }
}
