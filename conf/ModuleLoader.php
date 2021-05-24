<?php

/**
 * @author      Slamian Dloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config;

use Alaress\Application\Kernel;
use Alaress\Module\Module;
use Config\Modules\ModuleConfigInterface;

abstract class ModuleLoader implements ModuleConfigInterface
{
    /**
     * @param string $className Name of module class to load
     * @param string $path
     * @param Kernel $kernel
     * @return Module
     */
    protected function loadModule($kernel, $className, $path)
    {
        /** @var Module $module */
        $module = new $className($kernel->getDependencyInjector());
        $module->init($path);
        return $module;
    }
}
