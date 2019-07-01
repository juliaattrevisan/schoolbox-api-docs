<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\User\Password\Change\Module;

class UsePasswordChange implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel): void
    {
        $namespace = 'passwordChange';

        $module = new Module($kernel->getDependencyInjector());
        $module->init("/{$namespace}");
    }
}
