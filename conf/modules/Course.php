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
            $courseModule->init('/course');

            $importModule = new Assessment\Course\Import\Module($di);
            $importModule->init('/course/import');

            $atlasModule = new Assessment\Course\Import\Atlas\Module($di);
            $atlasModule->init('/course/import/atlas');
        }
    }
}
