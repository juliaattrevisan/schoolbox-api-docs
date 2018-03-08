<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type\Day\Renderer;

use \Calendar\Event\Type\RendererBase;
use Schoolbox\Calendar\Event;

abstract class Base extends RendererBase
{

    /**
     * If the Publish Date hasn't been reached yet, only show the events to Superusers
     * in a different way so they can still edit/click them. Otherwise, they don't appear
     * at all for everyone else
     *
     * @return bool
     */
    public function isVisible()
    {
        $isFutureEvent   = ($this->getEvent()->getPublishDate()->getUnixTimestamp() >= time());
        $isAlwaysVisible = ($this->getViewer()->isSuperuser() || $this->getEvent()->isOwner() || $this->getEvent()->isEditable());
        if ($isFutureEvent && !($isAlwaysVisible)) {
            return false;
        }
        return true;
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
        if ($this->getEvent()->getPublishDate() instanceof \Alaress_Date_NullDate) {
            return false;
        }

        return $this->getEvent()->getPublishDate()->gt(\Alaress_Date_Format::now());
    }


    /**
     * Returns the current attendance status for the viewer of a given attendance event
     *
     * @return string
     */
    public function attendanceStatus()
    {
        if ($attendance = $this->getCalendarHandler()->getAttendanceByEventAndUser($this->getEvent()->getEventId(), $this->getViewer()->getId())) {
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

} 