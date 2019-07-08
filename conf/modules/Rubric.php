<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;


use Alaress\Application\Kernel;
use Schoolbox\Assessment\Rubric\Module;
use Schoolbox\Assessment\Rubric\Criteria\Module as CriteriaModule;
use Schoolbox\Assessment\Rubric\Markbook\Module as RubricMarkbookModule;

class Rubric implements ModuleConfigInterface
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
        $module->init('/rubric');

        $module = new CriteriaModule($di);
        $module->init('/rubric/criteria');

        $module = new RubricMarkbookModule($di);
        $module->init('/rubricMarkbook');
    }

}
