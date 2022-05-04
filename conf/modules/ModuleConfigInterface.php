<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

interface ModuleConfigInterface
{
    /**
     * Run configuration for the given module.
     *
     * @param ContainerInterface $di The dependency injection container which
     *                               this config will use to construct its
     *                               modules.
     * @throws NotFoundExceptionInterface  if a dependency is not in `$di`
     * @throws ContainerExceptionInterface if there's another issue with `$di`
     * @return void
     */
    public function configure(ContainerInterface $di): void;
}
