<?php

/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Calendar\Event\Type\Timetable\Renderer;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Calendar\Event\Type\RendererBase;
use Calendar\Event\Type\Timetable;
use Schoolbox\User;

abstract class Base extends RendererBase
{
    /** @var Timetable\Event */
    protected Timetable\Event $event;


    /**
     * Constructor
     *
     * @param Authorizer      $authorizer
     * @param Generator       $routeGenerator
     * @param Timetable\Event $event
     * @param User            $user
     * @param User            $viewer
     */
    public function __construct(Authorizer $authorizer, Generator $routeGenerator, Timetable\Event $event, User $user, User $viewer)
    {
        parent::__construct($authorizer, $routeGenerator, $user, $viewer);
        $this->event = $event;
    }


    /**
     * @return Timetable\Event
     */
    protected function getEvent(): Timetable\Event
    {
        return $this->event;
    }
}
