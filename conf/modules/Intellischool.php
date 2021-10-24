<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Schoolbox\Intellischool\Module;

class Intellischool extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $this->loadModule($kernel, Module::class, "/intellischool");
    }
}
