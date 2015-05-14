<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Offline_Timetable implements External_TimetableInterface
{
    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0)
    {
        // Noop
    }

    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        // Noop
    }

    public function getDayName($dayNumber)
    {
        // Noop
    }


    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0)
    {
        // Noop
    }

    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        // Noop
    }


    public function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date)
    {
        // noop
    }


    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        // Throw exception to let the user know we cannot fetch it for them
        throw new Schoolbox\External\Exception("Timetable is unavailable.");
    }

    public function getRoomTimetable($roomCode)
    {
        // Noop
    }

}
