<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Paperly\Component\Dashboard\Module as DashboardModule;
use Schoolbox\Paperly\Module;

/**
 * Module configuration for Paperly student and class analytics.
 */
class Paperly extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'paperly';

        $module = new Module($di);
        $module->registerModule("/$namespace");

        $this->loadModule($di, DashboardModule::class, 'paperlyDashboard');

        $agentModule = new \Schoolbox\Paperly\Agent\Module($di);
        $agentModule->registerModule("/$namespace/agent");
    }
}
