<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Stk;

use Alaress\Application\Kernel;
use Custom\Stk\OleReport\Module as OleReportModule;
use Config\Modules\ModuleConfigInterface;

/**
 * Configuration for the SaintKentigern-specific OLE report.
 *
 * @package Config\Modules\Custom\Stk
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
        if ($di->get('config')->get('site_school_code') === 'stk') {
            $module = new OleReportModule($di);
            $module->init('/modules/custom/stk/oleReport');
        }
    }
}
