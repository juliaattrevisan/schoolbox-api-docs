<?php
/**
 * LearningAnalytics
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\LearningAnalytics\Module as LearningAnalyticsModule;

class LearningAnalytics extends ModuleLoader implements ModuleConfigInterface {
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel) {
        $this->loadModule($kernel, LearningAnalyticsModule::class, "/learning/analytics");
    }
}
