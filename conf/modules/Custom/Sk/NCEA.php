<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Sk;

use Alaress\Application\Kernel;
use Custom\Sk\NCEA\Module as NCEAModule;
use Config\Modules\ModuleConfigInterface;

class NCEA implements ModuleConfigInterface
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

        $module = new NCEAModule($di);
        $module->init('/modules/custom/stk/ncea');
    }
}
