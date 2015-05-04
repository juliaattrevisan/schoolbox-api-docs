<?php
/**
 * Configuration for the timetable module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Timetable\Module;

class Timetable implements ModuleConfigInterface
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
        $module = new Module($kernel->getDependencyInjector());
        $module->setExternalDb(new \External_Timetable());
        $module->init('/timetable');
    }
}
