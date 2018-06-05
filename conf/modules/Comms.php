<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Config\Modules;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Schoolbox\Comms\Config;
use Schoolbox\Comms\News;

class Comms extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'comms';

        $this->loadModule($kernel, Config\Module::class, "/{$namespace}/config");
        $this->loadModule($kernel, News\Module::class, "/{$namespace}/news");
    }
}
