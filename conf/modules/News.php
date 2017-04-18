<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\News\Component\Dashboard\Module as DashboardModule;
use Schoolbox\News\Component\Homepage\Module as HomepageModule;

class News extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'news';

        $this->loadModule($kernel, HomepageModule::class,     "/$namespace");
        $this->loadModule($kernel, DashboardModule::class,    "/$namespace");
    }
}
