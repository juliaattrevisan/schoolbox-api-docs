<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\API\Help;

use Alaress\Module\Module;
use Aura\Di\ContainerInterface;


class CourseParticipationModule extends Module
{
    /** @var string */
    protected $basename = 'helpCourseParticipation';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $this->register('GET', '/{schoolCode:\w+}/courses', 'getCourseList');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(CourseParticipationController::class)
        );

    }

}