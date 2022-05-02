<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Assessment\Rubric\Manager\Module as ManagerModule;
use Schoolbox\Assessment\Rubric\Markbook\Module as RubricMarkbookModule;
use Schoolbox\Assessment\Rubric\Module;

class Rubric implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $module = new Module($di);
        $module->registerModule('/rubric');
//        $module->init('/rubric');

        $module = new ManagerModule($di);
        $module->registerModule('/rubric/manager');
//        $module->init('/rubric/manager');

        $module = new RubricMarkbookModule($di);
        $module->registerModule('/rubricMarkbook');
//        $module->init('/rubricMarkbook');
    }
}
