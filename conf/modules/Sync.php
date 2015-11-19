<?php
/**
 * Sync (UPS)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;


use Alaress\Application\Kernel;

class Sync implements ModuleConfigInterface {

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel) {
        $di = $kernel->getDependencyInjector();
        $module = new \Schoolbox\Sync\Module($di);
        $module->init('/adminv2/sync');
    }
}