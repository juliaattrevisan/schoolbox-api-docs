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
use Psr\Container\ContainerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\LearningAnalytics\Module as LearningAnalyticsModule;

class LearningAnalytics extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        /** @var IConfigLoader $config */
        $config = $di->get('config');

        if ($config->get(ConfigMapper::NAME_LMS_ENABLED)) {
            $this->loadModule($di, LearningAnalyticsModule::class, "/learning/analytics");
        }
    }
}
