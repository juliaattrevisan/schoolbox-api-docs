<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Canvas\Module;

class Canvas implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel): void
    {
        $namespace = 'canvas';

        $module = new Module($kernel->getDependencyInjector());
        $module->init("/{$namespace}");
    }
}
