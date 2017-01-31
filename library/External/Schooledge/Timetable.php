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
     * @var MSSQL
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
    }

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
        // Use fc.Timetable.ShortName as the campus' name
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
        // Teacher query
        $query = "
            SELECT DISTINCT
                ts.Code as ClassCode,
                ts.Name as ClassName,
                td.Name as DayNumber,
                tp.Name as PeriodNumber,
                dbor.Code as Room,
                tbi.Consecutive as PeriodSpan,
                dbos.Code as StaffID,
                dbos.FirstName AS StaffPreferredName,
                dbos.LastName AS StaffSurname,
                dbos.Title As StaffTitle

            FROM fc.TimetableSubject ts

            -- Class instance
            INNER JOIN fc.TimetableClass         tc  ON ts.ID = tc.TimetableSubjectID
            INNER JOIN fc.TimetableClassInstance tci ON tc.ID = tci.TimetableClassID
            INNER JOIN fc.TimetableBlockInstance tbi ON tci.TimetableBlockInstanceID = tbi.ID
            INNER JOIN fc.TimetablePeriod        tp  ON tbi.TimetablePeriodID = tp.ID
            INNER JOIN fc.TimetableDay           td  ON tp.TimetableDayID = td.ID

            -- Optional room
            LEFT JOIN fc.TimetableClassRoom         tcr  ON tc.ID  = tcr.TimetableClassId
            LEFT JOIN fc.TimetableClassInstanceRoom tcir ON tci.ID = tcir.TimetableClassInstanceId
            LEFT JOIN fc.Room                       r    ON COALESCE(tcir.RoomID, tcr.RoomID) = r.ID
            LEFT JOIN dbo.Room                      dbor ON r.ID = dbor.ID

            -- Staff - either instance or class
            LEFT  JOIN fc.TimetableClassStaff         tcs  ON tc.ID = tcs.TimetableClassId
            LEFT  JOIN fc.TimetableClassInstanceStaff tcis ON tci.ID = tcis.TimetableClassInstanceId
            INNER JOIN dbo.Staff                      dbos ON COALESCE(tcis.PersonID, tcs.PersonID) = dbos.ID

            WHERE dbos.Code = {$this->database->smartQuote($externalUserId)}
        ";
        return [];
    }
}
