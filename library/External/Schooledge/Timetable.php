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
    public function getDayName($dayNumber)
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
                td.SortOrder as DayNumber,
                td.Name as DayName,
                tp.StartTime as PeriodStart,
                tp.EndTime as PeriodEnd,
                tp.Name as PeriodName,
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

            ORDER BY DayNumber ASC, PeriodStart ASC;
        ";

        // Student query - non-blocked
        // Still requires a room and staff
        $query = "
            SELECT
                t.ID as TimetableID,
                ts.Code as ClassCode,
                ts.Name as ClassName,
                td.SortOrder as DayNumber,
                td.Name as DayName,
                tpe.StartTime as PeriodStart,
                tpe.EndTime as PeriodEnd,
                tpe.Name as PeriodName,
                tci.TimetablePeriodID as PeriodID,
                tci.Consecutive as Consecutive

            FROM fc.TimetableSubject ts

            INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
            INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID
            INNER JOIN fc.YearLevelSub           yls  ON tc.YearLevelSubID = yls.ID
            INNER JOIN fc.YearLevelSubStudent    ylss ON yls.ID            = ylss.YearLevelSubID
            INNER JOIN dbo.Student               s    ON ylss.PersonID     = s.ID
            INNER JOIN fc.Timetable              t    ON ts.TimetableID    = t.ID
            INNER JOIN fc.TImetablePeriod        tp   ON tci.TimetablePeriodID = tp.ID
            -- CROSS APPLY: an INNER JOIN onto a table-level function
            CROSS APPLY fc.PeriodExpand(tci.TimetablePeriodID, tci.Consecutive) tpe
            INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID = td.ID

            WHERE s.Code = {$this->database->smartQuote($externalUserId)}
            AND   tci.TimetableBlockInstanceID IS NULL
            AND   tci.TimetablePeriodID IS NOT NULL
            AND   t.Enabled = 1

            ORDER BY DayNumber asc, PeriodStart asc
        ";

        // Student query - core
        $query = "
            SELECT DISTINCT
                ts.Code as ClassCode,
                ts.Name as ClassName,
                td.SortOrder as DayNumber,
                td.Name as DayName,
                tp.StartTime as PeriodStart,
                tp.EndTime as PeriodEnd,
                tp.Name as PeriodName,
                dbor.Code as Room,
                tbi.Consecutive as PeriodSpan,
                dbos.Code as StaffID,
                dbos.FirstName AS StaffPreferredName,
                dbos.LastName AS StaffSurname,
                dbos.Title As StaffTitle

            FROM fc.TimetableSubject ts

            -- Class instance
            INNER JOIN fc.TimetableClass             tc    ON ts.ID = tc.TimetableSubjectID
            INNER JOIN fc.YearLevelSub               yls   ON tc.YearLevelSubID = yls.ID
            INNER JOIN fc.TimetableBlockSubYearLevel tbsyl ON yls.ID = tbsyl.TimetableSubYearLevelID
            INNER JOIN fc.TimetableBlock             tb    ON tbsyl.TimetableBlockId = tb.ID
            INNER JOIN fc.TimetableBlockInstance     tbi   ON tb.ID = tbi.TimetableBlockID
            INNER JOIN fc.TimetablePeriod            tp    ON tbi.TimetablePeriodID = tp.ID
            INNER JOIN fc.TimetableDay               td    ON tp.TimetableDayID = td.ID

            -- Optional room
            LEFT JOIN fc.TimetableClassRoom tcr  ON tc.ID  = tcr.TimetableClassId
            LEFT JOIN fc.Room               r    ON tcr.RoomID = r.ID
            LEFT JOIN dbo.Room              dbor ON r.ID = dbor.ID

            -- Staff
            LEFT  JOIN fc.TimetableClassStaff tcs  ON tc.ID = tcs.TimetableClassId
            INNER JOIN dbo.Staff              dbos ON tcs.PersonID = dbos.ID

            -- Student
            INNER JOIN fc.YearLevelSubStudent ylss ON yls.ID = ylss.YearLevelSubID
            INNER JOIN dbo.Student            dboc ON ylss.PersonID = dboc.ID

            WHERE dboc.Code = {$this->database->smartQuote($externalUserId)}

            ORDER BY DayNumber ASC, PeriodStart ASC;
        ";
        return [];
    }
}
