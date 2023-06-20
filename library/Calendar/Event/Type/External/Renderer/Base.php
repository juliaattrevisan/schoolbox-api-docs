<?php

/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\External\Renderer;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Calendar\Event\Type\External;
use Calendar\Event\Type\RendererBase;
use Schoolbox\User;

abstract class Base extends RendererBase
{

    /** @var External\Event */
    protected $event;


    /**
     * Constructor
     *
     * @param Authorizer     $authorizer
     * @param Generator      $routeGenerator
     * @param External\Event $event
     * @param User           $user
     * @param User           $viewer
     */
    public function __construct(Authorizer $authorizer, Generator $routeGenerator, External\Event $event, User $user, User $viewer)
    {
        parent::__construct($authorizer, $routeGenerator, $user, $viewer);
        $this->event = $event;
    }


    /**
     * @return External\Event
     */
    protected function getEvent(): External\Event
    {
        return $this->event;
    }
}
