<?php

/**
 * Configuration for the curriculum mapping module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Curriculum\Api\Module as ApiModule;
use Schoolbox\Curriculum\Api\Usage\Module as ApiUsageModule;
use Schoolbox\Curriculum\Api\Usage\Folder\Module as ApiUsageFolderModule;
use Schoolbox\Curriculum\Reports\Module as ReportsModule;
use Schoolbox\Curriculum\Module;

class Curriculum implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $module = new Module($di);
        $module->registerModule('/curriculum');

        if ($di->get('config')->get(ConfigMapper::NAME_CURRICULUM_COVERAGE_REPORT_ENABLED)) {
            $reportsModule = new ReportsModule($di);
            $reportsModule->registerModule('/curriculum/reports');
        }

        $apiModule = new ApiModule($di);
        $apiModule->registerModule('/api/curriculum');

        $apiUsageModule = new ApiUsageModule($di);
        $apiUsageModule->registerModule('/api/curriculum/usage');

        $apiUsageFolderModule = new ApiUsageFolderModule($di);
        $apiUsageFolderModule->registerModule('/api/curriculum/usage');
    }
}
