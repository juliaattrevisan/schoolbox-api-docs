<?php

/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\EOTC\Renderer;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Calendar\Event\Type\RendererBase;
use Calendar\Event\Type\EOTC;
use Schoolbox\User;

abstract class Base extends RendererBase
{
    /** @var EOTC\Event */
    protected $event;

    /**
     * Constructor
     *
     * @param Authorizer $authorizer
     * @param Generator  $routeGenerator
     * @param EOTC\Event $event
     * @param User       $user
     * @param User       $viewer
     */
    public function __construct(Authorizer $authorizer, Generator $routeGenerator, EOTC\Event $event, User $user, User $viewer)
    {
        parent::__construct($authorizer, $routeGenerator, $user, $viewer);
        $this->event = $event;
    }


    /**
     * @return EOTC\Event
     */
    protected function getEvent(): EOTC\Event
    {
        return $this->event;
    }
}
