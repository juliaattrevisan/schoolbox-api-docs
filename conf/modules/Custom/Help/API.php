<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Help;

use Config\ModuleLoader;
use Custom\Help\CourseParticipation;
use Psr\Container\ContainerInterface;

class API extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, CourseParticipation\Module::class, '/api/course-participation');
    }
}
