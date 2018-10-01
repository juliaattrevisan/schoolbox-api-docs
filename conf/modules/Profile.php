<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

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
    public function configure($kernel)
    {
        $this->loadModule($kernel, ProfileButtonModule::class, null);
    }
}
