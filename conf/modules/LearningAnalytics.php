<?php

/**
 * LearningAnalytics
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\LearningAnalytics\Module as LearningAnalyticsModule;

class LearningAnalytics extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        /** @var IConfigLoader $config */
        $config = $kernel->getDependencyInjector()->get('config');

        if ($config->get(ConfigMapper::NAME_LMS_ENABLED)) {
            $this->loadModule($kernel, LearningAnalyticsModule::class, "/learning/analytics");
        }
    }
}
