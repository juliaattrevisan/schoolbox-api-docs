<?php

/**
 * @author      Slamian Dloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config;

use Config\Modules\ModuleConfigInterface;
use Psr\Container\ContainerInterface;

abstract class ModuleLoader implements ModuleConfigInterface
{
    /**
     * Loads this module.
     *
     * @template T of \Alaress\Module\Module
     * @param ContainerInterface $di
     * @param class-string<T>    $className
     * @param string|null        $path
     * @return T
     */
    protected function loadModule(ContainerInterface $di, string $className, ?string $path)
    {
        $module = new $className($di);
        $module->init($path);
        return $module;
    }
}
