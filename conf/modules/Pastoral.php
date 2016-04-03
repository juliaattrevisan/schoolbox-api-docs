<?php
/**
 * Configuration for the pastoral module.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Pastoral\Module;

class Pastoral implements ModuleConfigInterface
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
            $pastoralModule = new \Schoolbox\Pastoral\Pastoral\Module($di);
            $pastoralModule->init('/pastoral');
        }
    }
}
