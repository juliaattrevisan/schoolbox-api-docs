<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Alaress\Application\Kernel;
use Config\ModuleLoader;

class Weather extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel): void
    {
        $this->loadModule($kernel, \Schoolbox\Weather\Component\Dashboard\Module::class, "/weather/component/dashboard");
        $this->loadModule($kernel, \Schoolbox\Weather\Module::class, "/weather");
    }
}
