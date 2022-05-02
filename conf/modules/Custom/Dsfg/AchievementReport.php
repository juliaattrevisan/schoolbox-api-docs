<?php

/**
 * Configuration for the Dio-specific Achievement Reports.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Dsfg;

use Custom\Dsfg\AchievementReport\Module;
use Config\Modules\ModuleConfigInterface;
use Psr\Container\ContainerInterface;

class AchievementReport implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $module = new Module($di);
        $module->init('/modules/custom/diocesan/achievementreport');
    }
}
