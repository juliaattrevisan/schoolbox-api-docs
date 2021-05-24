<?php

/**
 * Configuration for the pastoral module.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\Pastoral\Module as PastoralModule;
use Schoolbox\Pastoral\ReportBuilder\Module as PastoralReportBuilderModule;
use Schoolbox\Pastoral\Management\Module as PastoralManagementModule;
use Schoolbox\Pastoral\Audience;

class Pastoral extends ModuleLoader
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function configure($kernel): void
    {
        $di = $kernel->getDependencyInjector();

        if ($di->get('config')->get('pastoral_enabled')) {
            $this->loadModule($kernel, PastoralModule::class, "/pastoral");
            $this->loadModule($kernel, PastoralReportBuilderModule::class, "/pastoral/report");
            $this->loadModule($kernel, PastoralManagementModule::class, "/pastoral/management/groups");
            $this->loadModule($kernel, Audience\Module::class, "/pastoral/management/audience");
        }
    }
}
