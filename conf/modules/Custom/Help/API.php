<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Config\Modules\Custom\Help;

use Config\ModuleLoader;
use Custom\Help\CourseParticipation;
use Alaress\Application\Kernel;

class API extends ModuleLoader
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $this->loadModule($kernel, CourseParticipation\Module::class, '/api/course-participation');
    }

}