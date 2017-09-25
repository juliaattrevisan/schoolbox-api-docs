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
use Config\ModuleLoader;
use Schoolbox;

class API extends ModuleLoader {

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $this->loadModule($kernel, Schoolbox\API\Module::class, '/api');
        $this->loadModule($kernel, Schoolbox\API\Help\CourseParticipationModule::class, '/api/course-participation');
    }

}