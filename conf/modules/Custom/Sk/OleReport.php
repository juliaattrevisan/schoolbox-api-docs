<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Sk;

use Custom\Sk\OleReport\Module as OleReportModule;
use Config\Modules\ModuleConfigInterface;
use Psr\Container\ContainerInterface;

/**
 * Configuration for the SaintKentigern-specific OLE report.
 *
 * @package Config\Modules\Custom\Sk
 */
class OleReport implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $module = new OleReportModule($di);
        $module->registerModule('/modules/custom/stk/oleReport');
//        $module->init('/modules/custom/stk/oleReport');
    }
}
