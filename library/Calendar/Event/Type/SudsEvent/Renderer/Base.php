<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\SudsEvent\Renderer;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Calendar\Event\Type\SudsEvent\Event;
use Calendar\Event\Type\RendererBase;
use Schoolbox\User;

abstract class Base extends RendererBase
{
    /**
     * @var Event
     */
    protected Event $event;


    /**
     * Constructor
     *
     * @param Authorizer $authorizer
     * @param Generator  $routeGenerator
     * @param Event      $event
     * @param User       $user
     * @param User       $viewer
     */
    public function __construct(Authorizer $authorizer, Generator $routeGenerator, Event $event, User $user, User $viewer)
    {
        parent::__construct($authorizer, $routeGenerator, $user, $viewer);
        $this->event = $event;
    }
}
