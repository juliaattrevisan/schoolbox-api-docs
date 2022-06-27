<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Intellischool\Module;
use Schoolbox\Intellischool\Component\Dashboard\Module as DashboardModule;
use Schoolbox\Intellischool\Component\Homepage\Module as HomepageModule;

/**
 * Module configuration for Intellischool student and class analytics.
 */
class IntellischoolAnalytics extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, Module::class, "/modules/analytics");
        $this->loadModule($di, DashboardModule::class, 'intellischoolDashboard');
        $this->loadModule($di, HomepageModule::class, 'intellischoolHomepage');
    }
}
