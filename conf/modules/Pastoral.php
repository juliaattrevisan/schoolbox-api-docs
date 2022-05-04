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
use Psr\Container\ContainerInterface;
use Schoolbox\Pastoral\Module as PastoralModule;
use Schoolbox\Pastoral\ReportBuilder\Module as PastoralReportBuilderModule;
use Schoolbox\Pastoral\Management\Module as PastoralManagementModule;
use Schoolbox\Pastoral\Audience;

class Pastoral extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        if ($di->get('config')->get('pastoral_enabled')) {
            $this->loadModule($di, PastoralModule::class, "/pastoral");
            $this->loadModule($di, PastoralReportBuilderModule::class, "/pastoral/report");
            $this->loadModule($di, PastoralManagementModule::class, "/pastoral/management/groups");
            $this->loadModule($di, Audience\Module::class, "/pastoral/management/audience");
        }
    }
}
