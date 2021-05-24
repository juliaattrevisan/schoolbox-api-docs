<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Help;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Custom\Help\Marketo\Component\Dashboard\Module as MarketoModule;

class Marketo extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'marketo';

        $this->loadModule($kernel, MarketoModule::class, "/modules/custom/help/{$namespace}");
    }
}
