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
use Aura\Router\Router;
use Calendar\Event\Type\RendererBase;
use Calendar\Event\Type\Timetable;
use Calendar\Event\Type\Timetable\Event;
use Schoolbox\User;

abstract class Base extends RendererBase
{

    /** @var Timetable\Event */
    private $event;


    /**
     * Constructor
     *
     * @param Authorizer $authorizer
     * @param Router     $router
     * @param Event      $event
     * @param User       $user
     * @param User       $viewer
     */
    public function __construct(Authorizer $authorizer, Router $router, Timetable\Event $event, User $user, User $viewer)
    {
        parent::__construct($authorizer, $router, $user, $viewer);
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
