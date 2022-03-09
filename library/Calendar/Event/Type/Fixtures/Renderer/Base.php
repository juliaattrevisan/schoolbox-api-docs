<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Fixtures\Renderer;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use Calendar\Event\Type\Fixtures\Event;
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
     * @param Router     $router
     * @param Event $event
     * @param User       $user
     * @param User       $viewer
     */
    public function __construct(Authorizer $authorizer, Router $router, Event $event, User $user, User $viewer)
    {
        parent::__construct($authorizer, $router, $user, $viewer);
        $this->event = $event;
    }
}
