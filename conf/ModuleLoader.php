<?php

/**
 * @author      Slamian Dloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config;

use Alaress\Module\Module;
use Config\Modules\ModuleConfigInterface;
use Psr\Container\ContainerInterface;
use Schoolbox\Module\RestfulModule;

abstract class ModuleLoader implements ModuleConfigInterface
{
    /**
     * Loads this module.
     *
     * @template T of Module
     * @param ContainerInterface $di
     * @param class-string<T>    $className
     * @param string|null        $path
     * @return T
     */
    protected function loadModule(ContainerInterface $di, string $className, ?string $path): Module
    {
        /** @var Module $module */
        $module = new $className($di, null);
        $module->registerModule($path);
        $module->init($path);
        // @phpstan-ignore-next-line: this should be recognized
        return $module;
    }

    /**
     * Loads this RESTful module, with additional context.
     *
     * @template T of RestfulModule
     * @param ContainerInterface $di
     * @param class-string<T>    $className
     * @param string|null        $path
     * @param RestfulModule      $context
     * @return RestfulModule
     */
    protected function loadModuleWithContext(
        ContainerInterface $di,
        string $className,
        ?string $path,
        RestfulModule $context
    ): RestfulModule {
        /** @var RestfulModule $module */
        $module = new $className($di, $context);
        $module->registerModuleWithContext($path, $context);
        $module->init($path);
        return $module;
    }
}
