<?php
/**
 * help.schoolbox.com.au
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Help\Module;

class Help implements ModuleConfigInterface {

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel) {
        $namespace = 'help';

        $module = new Module($kernel->getDependencyInjector());
        $module->init("/$namespace");
    }
}
