<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Newic;

use Alaress\Application\Kernel;
use Config\Modules\ModuleConfigInterface;
use Custom\Newic\Module;

/**
 * Redirect a homepage to somewhere in canvas
 */
class Canvas implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        $module = new Module($di);
        $module->init('/homepage');
    }
}
