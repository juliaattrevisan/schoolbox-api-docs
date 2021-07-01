<?php

/**
 * help.schoolbox.com.au
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\help;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Custom\Help\Hero\Module as HeroModule;

class Hero extends ModuleLoader
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $this->loadModule($kernel, HeroModule::class, '/hero');
    }
}
