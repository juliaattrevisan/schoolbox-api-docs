<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC;

use Alaress_Date_Format;
use Schoolbox\Calendar;
use Schoolbox\User;

/**
 * Class Event
 *
 * This is a subclass of core schoolbox events
 *
 * @package EOTC
 */
class Event extends Calendar\Event
{

    /**
     * Metadata for this event
     *
     * An instance of EventMetadata
     *
     * @var EventMetadata
     */
    protected $metadata;

    /**
     * A User object
     *
     * @var User
     */
    protected $teacherInCharge;

    /**
     * Publish Date
     *
     * @var Alaress_Date_Format|null
     */
    protected $publishDate;

    /**
     * Hook method
     *
     * Initialise collections all over the place
     */
    protected function setup()
    {
        $this->setMetadata(new EventMetadata());
    }

    /**
     * TODO: move this. it doesn't belong here.
     *
     * Ensure that the user hasn't modified the URL in an untoward manner.
     * If they have, or it's somehow absent, default to week view.
     *
     * Really, the redirect should be passed as a base-64 encoded paramter
     * (or similar) rather than having to implicitly "guess" where to go next
     *
     * @param string $returnView
     * @param string $returnDate
     * @param int $groupId
     * @param int $userId
     *
     * @return string
     * @internal param $returnTo
     */
    public function setupRedirect($returnView, $returnDate = "", $groupId = null, $userId = null): string
    {
        if ($returnView === 'dashboard') {
            return '/';
        } else if ($returnView === 'upcomingEventAttendance') {
            return '/calendar/attendance/upcomingEvents/';
        } else if ($returnView === 'homepage') {
            return '/homepage/' . $groupId;
        } else if ($returnView === 'assessmentCalendar') {
            return '/learning/assessment/calendar/';
        } else {
            if ($returnView === 'calendar') {
                $return = "/calendar/$returnDate";

                if ($userId) {
                    $return .= '/' . $userId;
                }
                return $return;
            }
        }
        //Assuming the complete url has been passed over
        if (strlen(urldecode($returnView)) > 0) {
            return urldecode($returnView);
        }
        // Otherwise - guess using Back to the future!
        return '';
    }

    /* Getters/Setters
     */

    /**
     * Set metadata
     *
     * @param EventMetadata $metadata
     * @return self
     */
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Get metadata
     *
     * @return EventMetadata|null
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Set teacher in charge
     *
     * @param User $teacherInCharge
     * @return self
     */
    public function setTeacherInCharge($teacherInCharge)
    {
        $this->teacherInCharge = $teacherInCharge;
        return $this;
    }

    /**
     * Get teacher in charge
     *
     * @return User
     */
    public function getTeacherInCharge()
    {
        return $this->teacherInCharge;
    }

    /**
     * Set publish date
     *
     * @param mixed $publishDate
     */
    public function setPublishDate($publishDate)
    {
        $this->publishDate = $publishDate;
    }

    /**
     * Get publish date
     *
     * @return mixed
     */
    public function getPublishDate()
    {
        return $this->publishDate;
    }
}
