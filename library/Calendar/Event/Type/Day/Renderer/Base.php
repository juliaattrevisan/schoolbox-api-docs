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
use Aura\Router\Router;
use Calendar\Event\Type\Day;
use Calendar\Event\Type\RendererBase;
use Schoolbox\Calendar\Event;
use Schoolbox\User;

abstract class Base extends RendererBase
{

    /** @var Day\Event */
    private $event;

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
     * @param Router     $router
     * @param Day\Event  $event
     * @param User       $user
     * @param User       $viewer
     */
    public function __construct(Authorizer $authorizer, Router $router, Day\Event $event, User $user, User $viewer)
    {
        parent::__construct($authorizer, $router, $user, $viewer);

        // TODO would rather not create this like this, but it's better than every renderer depending on it
        $this->calHandler = new \Calendar();
        $this->event      = $event;
    }


    /**
     * If an event has not been published yet, but is still visible (see isVisible) then it should be translucent
     *
     * @return mixed
     */
    public function isTranslucent()
    {

        /* TODO: This is a temporary fix and should be repaired when ADN merges into ADF
         */
        if ($this->event->getPublishDate() instanceof \Alaress_Date_NullDate) {
            return false;
        }

        return $this->event->getPublishDate()->gt(\Alaress_Date_Format::now());
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
