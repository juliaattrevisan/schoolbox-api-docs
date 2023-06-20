<?php

/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Calendar\Event\Type\Day\Renderer;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Calendar\Event\Type\Day;
use Calendar\Event\Type\RendererBase;
use Schoolbox\User;

abstract class Base extends RendererBase
{
    /** @var Day\Event */
    protected $event;

    /**
     * @var \Calendar
     */
    private $calHandler;

    /** @var string */
    public $returnStr;

    /**
     * Constructor
     *
     * @param Authorizer $authorizer
     * @param Generator  $routeGenerator
     * @param Day\Event  $event
     * @param User       $user
     * @param User       $viewer
     */
    public function __construct(Authorizer $authorizer, Generator $routeGenerator, Day\Event $event, User $user, User $viewer)
    {
        parent::__construct($authorizer, $routeGenerator, $user, $viewer);

        // TODO would rather not create this like this, but it's better than every renderer depending on it
        $this->calHandler = new \Calendar();
        $this->event      = $event;
    }


    /**
     * Returns the current attendance status for the viewer of a given attendance event
     *
     * @return int|null One of the `Event\Attendance\Model::STATUS_*` constants,
     *                  or null if the current user hasn't responded.
     */
    public function attendanceStatus(): ?int
    {
        if ($attendance = $this->calHandler->getAttendanceByEventAndUser($this->event->getEventId(), $this->viewer->getId())) {
            return (int)$attendance['status'];
        }

        return null;
    }


    /**
     * @return Day\Event
     */
    protected function getEvent(): Day\Event
    {
        return $this->event;
    }

    /**
     * Sets return string
     * @param string $returnStr
     */
    public function setReturnStr(string $returnStr): void
    {
        $this->returnStr = $returnStr;
    }
}
