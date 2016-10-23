<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;


use Alaress\Application\Kernel;
use Schoolbox\Assessment;

class Course implements ModuleConfigInterface
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $courseModule = new Assessment\Course\Module($kernel->getDependencyInjector());
        $courseModule->init('/course');

        $importModule = new Assessment\Course\Import\Module($kernel->getDependencyInjector());
        $importModule->init('/course/import');
    }

} 
