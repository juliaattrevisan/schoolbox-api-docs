<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Schoolbox;

class Application extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $this->loadModule($kernel, Schoolbox\Application\Login\Module::class, '/login');
        $this->loadModule($kernel, Schoolbox\Application\Feedback\Module::class, '/feedback');
        $this->loadModule($kernel, Schoolbox\Application\Policy\Module::class, '/policy');
    }
}
