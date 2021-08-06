<?php

/**
 * Configuration for the Dio-specific Achievement Reports.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Dsfg;

use Alaress\Application\Kernel;
use Custom\Dsfg\AchievementReport\Module;
use Config\Modules\ModuleConfigInterface;

class AchievementReport implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();
        $module = new Module($di);
        $module->init('/modules/custom/diocesan/achievementreport');

    }
}
