<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Campus;

/**
 * Schooledge implementation of timetable.
 */
class External_Schooledge_Timetable extends External_Humanedge_Timetable implements External_TimetableInterface
{
    /**
     * @inheritdoc
     */
    public function getTimetableDefinition($externalUserId, Campus $campus, $day = 0)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    function getDayName($dayNumber)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getPeriodNumber(Campus $campus, $now = 0, $user = null)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getDayTimetable($externalUserId, $userId, Campus $campus, $dayNumber)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus)
    {
        return [];
    }
}
