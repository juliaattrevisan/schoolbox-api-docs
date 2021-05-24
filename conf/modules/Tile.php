<?php

/**
 * Configuration for the tile component layout.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Tile\Module;

class Tile implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     *
     * @return void
     */
    public function configure($kernel)
    {
        $module = new Module($kernel->getDependencyInjector());
        $module->init('/tile');
    }
}
