<?php

use External\IOfflineConnector;
use Schoolbox\Campus;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
*/

class External_Offline_Timetable implements External_TimetableInterface, IOfflineConnector
{
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {
        return [];
    }

    public function getDayNumber(Campus $campus, $date = 0)
    {
        return 0;
    }

    public function getDayName($dayNumber): string
    {
        return '';
    }


    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array
    {
        return [];
    }

    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber): array
    {
        return [];
    }


    public function getTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
    {
        return [];
    }


    /**
     * @param string $externalUserId
     * @param int    $userId
     * @param Campus $campus
     * @return array
     * @throws \Schoolbox\External\Exception
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
    {
        // Throw exception to let the user know we cannot fetch it for them
        throw new Schoolbox\External\Exception("Timetable is unavailable.");
    }

    public function getRoomTimetable(array $roomCodes): array
    {
        return [];
    }
}
