<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Alaress\Application\Kernel;
use Config\ModuleLoader;

class Geosnapshot extends ModuleLoader
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel): void
    {
        $this->loadModule($kernel, \Schoolbox\Geosnapshot\Component\Dashboard\Module::class, "/geosnapshot/component/dashboard");
        $this->loadModule($kernel, \Schoolbox\Geosnapshot\Component\Homepage\Module::class, "/geosnapshot/component/homepage");
        $this->loadModule($kernel, \Schoolbox\Geosnapshot\Module::class, "/geosnapshot");
    }
}
