<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Sk;

use Alaress\Application\Kernel;
use Custom\Sk\OleReport\Module as OleReportModule;
use Config\Modules\ModuleConfigInterface;

/**
 * Configuration for the SaintKentigern-specific OLE report.
 *
 * @package Config\Modules\Custom\Sk
 */
class OleReport implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     *
     * @return void
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        $module = new OleReportModule($di);
        $module->init('/modules/custom/stk/oleReport');
    }
}
