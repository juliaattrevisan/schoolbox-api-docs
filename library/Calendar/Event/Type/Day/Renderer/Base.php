<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type\Day\Renderer;

use Calendar\Event\Type\Day;
use \Calendar\Event\Type\RendererBase;
use Schoolbox\Calendar\Event;
use Schoolbox\User;
use SitePage;

abstract class Base extends RendererBase
{

    /** @var Day\Event */
    private $event;

    /**
     * @param SitePage  $page
     * @param Day\Event $event
     * @param User      $user
     * @param \Group    $groupHandler
     * @param \Calendar $calHandler
     * @param User      $viewer
     */
    public function __construct(SitePage $page, Day\Event $event, User $user, \Group $groupHandler, \Calendar $calHandler, User $viewer)
    {
        parent::__construct($page, $user, $groupHandler, $calHandler, $viewer);
        $this->event = $event;
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
     * @return string
     */
    public function attendanceStatus()
    {
        if ($attendance = $this->getCalendarHandler()->getAttendanceByEventAndUser($this->event->getEventId(), $this->getViewer()->getId())) {
            if ($attendance['status'] == Event::ATTENDANCE_STATUS_PENDING) {
                return 'Pending';
            } else if ($attendance['status'] == Event::ATTENDANCE_STATUS_APPROVED) {
                return 'Approved';
            } else if ($attendance['status'] == Event::ATTENDANCE_STATUS_REJECTED) {
                return 'Rejected';
            }
        }

        return '';
    }


    /**
     * @return Day\Event
     */
    protected function getEvent(): Day\Event
    {
        return $this->event;
    }
} 
