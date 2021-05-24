<?php

declare(strict_types=1);

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\Profile\Button\Module as ProfileButtonModule;

class Profile extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel): void
    {
        $this->loadModule($kernel, ProfileButtonModule::class, null);
    }
}
