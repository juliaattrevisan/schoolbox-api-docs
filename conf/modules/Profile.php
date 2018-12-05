<?php
declare(strict_types=1);

namespace Config\Profile;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\Profile\Button\Module as ProfileButtonModule;

class Menu extends ModuleLoader
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
