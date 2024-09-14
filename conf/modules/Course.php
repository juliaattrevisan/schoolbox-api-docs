<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Assessment;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

class Course implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        /** @var IConfigLoader $config */
        $config = $di->get('config');

        if ($config->get(ConfigMapper::NAME_LMS_ENABLED)) {
            $courseModule = new Assessment\Course\Module($di);
            $courseModule->registerModule('/course');

            $planModule = new Assessment\Course\Plan\Module($di);
            $planModule->registerModule('/course/plan');

            $importModule = new Assessment\Course\Import\Module($di);
            $importModule->registerModule('/course/import');

            $exportModule = new Assessment\Course\Export\Module($di);
            $exportModule->registerModule('/course/export');
        }
    }
}
