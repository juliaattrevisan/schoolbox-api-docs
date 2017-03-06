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
        // Wat
        // Didn't we change this?
        try {
            $user = \Schoolbox\UserMapper::getInstance()->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        // Ignore campus for now. Eventually, match campi against timetable ID

        $quotedId = $this->database->smartQuote($externalUserId);
        if ($user->getRole()->isStudent()) {
            // 1st part: blocked classes
            // 2nd part: non-blocked classes
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
                    tbi.TimetablePeriodID as PeriodID,
                    tbi.Consecutive as Consecutive

                FROM       fc.TimetableSubject       ts
                -- Join onto timetable
                INNER JOIN fc.Timetable              t    ON ts.TimetableID    = t.ID
                -- Join onto student via subject choice
                INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
                INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID
                INNER JOIN fc.TimetableSubjectChoice tsc  ON tc.ID             = tsc.TimetableClassID
                INNER JOIN dbo.Student               s    ON tsc.PersonID      = s.ID
                -- Expand period
                INNER JOIN fc.TimetableBlockInstance tbi  ON tci.TimetableBlockInstanceID = tbi.ID
                INNER JOIN fc.TImetablePeriod        tp   ON tbi.TimetablePeriodID = tp.ID
                CROSS APPLY fc.PeriodExpand(tbi.TimetablePeriodID, tbi.Consecutive) tpe
                INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID = td.ID

                WHERE s.Code = {$quotedId}
                AND   t.Enabled = 1

                UNION

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

                FROM       fc.TimetableSubject       ts
                -- Join onto timetable
                INNER JOIN fc.Timetable              t    ON ts.TimetableID    = t.ID
                -- Join onto student via year level
                INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
                INNER JOIN fc.YearLevelSub           yls  ON tc.YearLevelSubID = yls.ID
                INNER JOIN fc.YearLevelSubStudent    ylss ON yls.ID            = ylss.YearLevelSubID
                INNER JOIN dbo.Student               s    ON ylss.PersonID     = s.ID
                -- Expand period
                INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID
                INNER JOIN fc.TImetablePeriod        tp   ON tci.TimetablePeriodID = tp.ID
                CROSS APPLY fc.PeriodExpand(tci.TimetablePeriodID, tci.Consecutive) tpe
                INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID = td.ID

                WHERE s.Code = {$quotedId}
                AND   tci.TimetableBlockInstanceID IS NULL
                AND   tci.TimetablePeriodID IS NOT NULL
                AND   t.Enabled = 1

                ORDER BY DayNumber asc, PeriodStart asc
            ";

        } elseif ($user->getRole()->isStaff()) {
            // Teacher query
            // Still requires a room
            // I'm guessing we smoosh the staff and student queries together somehow?
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
                    tbi.TimetablePeriodID as PeriodID,
                    tbi.Consecutive as Consecutive

                FROM       fc.TimetableSubject       ts
                -- Join onto timetable
                INNER JOIN fc.Timetable              t    ON ts.TimetableID    = t.ID
                -- Join onto staff
                INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
                INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID
                INNER JOIN fc.TimetableBlockInstance tbi  ON tci.TimetableBlockInstanceID = tbi.ID
                -- Base staff
                LEFT  JOIN fc.TimetableClassStaff tcs        ON tc.ID        = tcs.TimetableClassId
                LEFT  JOIN dbo.Staff              staff_base ON tcs.PersonID = staff_base.ID
                -- Override staff
                LEFT  JOIN fc.TimetableClassInstanceStaff tcis              ON tci.ID        = tcis.TimetableClassInstanceId
                LEFT  JOIN dbo.Staff                      staff_replacement ON tcis.PersonID = staff_replacement.ID
                -- Expand period
                INNER JOIN fc.TimetablePeriod        tp   ON tbi.TimetablePeriodID = tp.ID
                CROSS APPLY fc.PeriodExpand(tbi.TimetablePeriodID, tbi.Consecutive) tpe
                INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID = td.ID

                WHERE (
                    (staff_base.Code = {$quotedId} AND tcis.TimetableClassStaffID IS NULL)
                    OR
                    staff_replacement.Code = {$quotedId}
                )
                AND t.Enabled = 1

                ORDER BY DayNumber ASC, PeriodStart ASC
            ";

        } else {
            return [];
        }

        return [];
    }
}
