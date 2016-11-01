<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\News\Component\Dashboard\Module as DashboardModule;
use Schoolbox\News\Component\Homepage\Module as HomepageModule;

class News implements ModuleConfigInterface
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

        $homepageModule = new HomepageModule($kernel->getDependencyInjector());
        $homepageModule->init("/$namespace");

        $dashboardModule = new DashboardModule($kernel->getDependencyInjector());
        $dashboardModule->init("/$namespace");
    }
}