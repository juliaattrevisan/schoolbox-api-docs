<?php

/**
 *
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Qrcode\Module;

class Qrcode implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $module = new Module($kernel->getDependencyInjector());
        $module->init('/qrcode');
    }
}
