<?php

/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Calendar\Event\Type\Submission\Renderer;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Calendar\Event\Type\RendererBase;
use Calendar\Event\Type\Submission;
use Schoolbox\User;

abstract class Base extends RendererBase
{

    /** @var Submission\Event */
    protected $event;


    /**
     * Constructor
     *
     * @param Authorizer       $authorizer
     * @param Generator        $routeGenerator
     * @param Submission\Event $event
     * @param User             $user
     * @param User             $viewer
     */
    public function __construct(Authorizer $authorizer, Generator $routeGenerator, Submission\Event $event, User $user, User $viewer)
    {
        parent::__construct($authorizer, $routeGenerator, $user, $viewer);
        $this->event = $event;
    }


    /**
     * Helper function to determine if person viewing assessment event can edit it, this should probably be on the
     * event itself but its not so this will help
     *
     * @return bool
     */
    public function isEditable()
    {
        return $this->viewer->getRole()->isStaff();
    }


    /**
     * @return Submission\Event
     */
    protected function getEvent(): Submission\Event
    {
        return $this->event;
    }
}
