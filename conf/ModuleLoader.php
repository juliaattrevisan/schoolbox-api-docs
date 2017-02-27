<?php
/**
 * @author      Slamian Dloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Config;

use Alaress\Application\Kernel;
use Alaress\Module\Module;

class ModuleLoader
{
    /**
     * @param string $className Name of module class to load
     * @param $path
     * @param Kernel $kernel
     * @return Module
     */
    protected function loadModule($kernel, $className, $path)
    {
        $di = $kernel->getDependencyInjector();

        $schoolCode = $di->get('config')->get('site_location');
        $customModuleClass = str_replace('\\Module', '\\Custom\\' . ucfirst($schoolCode) . '\\Module', $className);

        /** @var Module $module */

        if (class_exists($customModuleClass)) {
            $module = new $customModuleClass($di);
        } else {
            $module = new $className($di);
        }

        $module->init($path);
        return $module;
    }
}