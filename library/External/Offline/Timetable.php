<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\DebugBar\Instance as DebugBar;

class External_Offline_Timetable implements External_TimetableInterface
{
    public function getTimetableDefinition($campusId, $day = 0)
    {
        // Noop
    }

    public function getDayNumber($campusId, $date = 0)
    {
        // Noop
    }

    public function getPeriodNumber($campusId)
    {
        // Noop
    }

    public function getDayTimetable($externalUserId, $userId, $campusId, $dayNumber)
    {
        // Noop
    }

    public function getFullTimetable($externalUserId, $userId, $campusId)
    {
        // Throw exception to let the user know we cannot fetch it for them
        throw new Schoolbox\External\Exception("Timetable is unavailable.");
    }

    public function getDaySubjects($externalUserId, $dayNumber)
    {
        // Noop
    }

    public function getSubjectPeriod($classCode, $dayNumber)
    {
        // Noop
    }

    public function getSubjectName($classCode)
    {
        // Noop
    }

    public function getRoomTimetable($roomCode)
    {
        // Noop
    }

}
