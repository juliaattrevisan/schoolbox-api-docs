<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\SHAPS\Module;

class SHAPS implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'shaps';

        $module = new Module($kernel->getDependencyInjector());
        $module->init("/$namespace");
    }
}