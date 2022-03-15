<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Schoolbox\Intellischool\Module;
use Schoolbox\Intellischool\Component\Dashboard\Module as DashboardModule;

/**
 * Module configuration for Intellischool student and class analytics.
 */
class IntellischoolAnalytics extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $this->loadModule($kernel, Module::class, "/modules/analytics");
        $this->loadModule($kernel, DashboardModule::class, 'intellischoolDashboard');
    }
}
