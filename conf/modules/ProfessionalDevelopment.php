<?php

/**
 * Professional Development
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\ProfessionalDevelopment\Module;

class ProfessionalDevelopment implements ModuleConfigInterface
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'professionaldevelopment';

        $module = new Module($kernel->getDependencyInjector());
        $module->init("/$namespace");
    }
}
