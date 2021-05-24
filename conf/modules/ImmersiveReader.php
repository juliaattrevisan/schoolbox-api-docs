<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Schoolbox\ImmersiveReader\Module as ImmersiveReaderModule;

class ImmersiveReader extends ModuleLoader
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'immersive-reader';
        $this->loadModule($kernel, ImmersiveReaderModule::class, "/{$namespace}");
    }
}
