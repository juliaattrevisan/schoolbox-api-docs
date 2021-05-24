<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use External\IOfflineConnector;
use Schoolbox\Campus;

/**
 * Class External_Offline_Timetable
 */
class External_Offline_Timetable implements External_TimetableInterface, IOfflineConnector
{

    /**
     * @inheritDoc
     */
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        return 0;
    }


    /**
     * @inheritDoc
     */
    public function getDayName($dayNumber): string
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, ?Alaress_Date_Format $date = null): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
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
        throw new Schoolbox\External\Exception('Timetable is unavailable.');
    }


    /**
     * @inheritDoc
     */
    public function getRoomTimetable(array $roomCodes): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function findCycleLookup(): array
    {
        return [];
    }
}
