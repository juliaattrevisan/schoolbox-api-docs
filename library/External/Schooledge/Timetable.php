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
        if (!$day) {
            $day = $this->getDayNumber($campus);
        }
        if (!$day) {
            $day = 1;
        }
        $dayCount = $this->database->getOne("
            SELECT COUNT(DISTINCT td.ID)
            FROM       fc.Timetable       t
            INNER JOIN fc.TimetableDay    td ON t.ID  = td.TimetableId
            WHERE t.Name    = {$this->database->smartQuote($campus->getExtTimetableCode())}
            AND   t.Enabled = 1
        ");
        $periods  = [];
        $result   = $this->database->getAll("
            SELECT
                tp.Name                    AS Name,
                CAST(tp.StartTime AS TIME) AS StartTime,
                CAST(tp.EndTime   AS TIME) AS EndTime,
                td.SortOrder               AS DayNumber

            FROM       fc.Timetable       t
            INNER JOIN fc.TimetableDay    td ON t.ID  = td.TimetableId
            INNER JOIN fc.TimetablePeriod tp ON td.ID = tp.TimetableDayId

            WHERE t.Name       = {$this->database->smartQuote($campus->getExtTimetableCode())}
            AND   t.Enabled    = 1
            AND   td.SortOrder = {$this->database->smartQuote($day)}

            ORDER BY DayNumber ASC, StartTime ASC, EndTime ASC
        ");

        // Figure out period number
        foreach ($result as $i => $period) {
            $periodNumber = $i+1;
            $period['PeriodNumber'] = $periodNumber;
            $periods[$periodNumber] = $period;
        }

        // This is all the same as HumanEdge
        $definition = [
            'desc'        => [],
            'start'       => [],
            'end'         => [],
            'number'      => [],
            'sequence'    => [],
            'periodCount' => count($periods),
            'dayCount'    => $dayCount,
        ];
        foreach ($periods as $period) {
            $startTime = new Alaress_Date_Format($period['StartTime'], 'H:i:s.u');
            $endTime   = new Alaress_Date_Format($period['EndTime'], 'H:i:s.u');
            $definition['desc'][]     = $period['Name'];
            $definition['start'][]    = $startTime->getUsingFormat('H:i');
            $definition['end'][]      = $endTime->getUsingFormat('H:i');
            $definition['number'][]   = intval($period['PeriodNumber']);
            $definition['sequence'][] = 1;
        }
        return $definition;
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
    public function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date)
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

        $quotedId     = $this->database->smartQuote($externalUserId);
        $quotedCampus = $this->database->smartQuote($campus->getExtTimetableCode());
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
                    CAST(tpe.StartTime AS TIME) AS PeriodStart,
                    CAST(tpe.EndTime   AS TIME) AS PeriodEnd,
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
                AND   t.Name = {$quotedCampus}
                AND   t.Enabled = 1

                UNION

                SELECT
                    t.ID as TimetableID,
                    ts.Code as ClassCode,
                    ts.Name as ClassName,
                    td.SortOrder as DayNumber,
                    td.Name as DayName,
                    CAST(tpe.StartTime AS TIME) AS PeriodStart,
                    CAST(tpe.EndTime   AS TIME) AS PeriodEnd,
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
                AND   t.Name = {$quotedCampus}
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
                    CAST(tpe.StartTime AS TIME) AS PeriodStart,
                    CAST(tpe.EndTime   AS TIME) AS PeriodEnd,
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
                AND t.Name = {$quotedCampus}
                AND t.Enabled = 1

                ORDER BY DayNumber ASC, PeriodStart ASC
            ";

        } else {
            return [];
        }

        // Copied from HumanEdge
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus);

        // Build the timetable
        $timetable = [];
        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
            // Fill in each day with blanks
            $timetable[$x]['day']            = array_fill(1, $timetabledef['dayCount'], []);
        }
        foreach ($this->database->getAll($query) as $timetabledClass) {
//            // Account for classes which extend multiple periods
//            $periodSpan = range(
//                $timetabledClass['PeriodNumber'],
//                $timetabledClass['PeriodNumber'] + $timetabledClass['PeriodSpan'] - 1
//            );
            $startTime = (new Alaress_Date_Format($timetabledClass['PeriodStart'], 'H:i:s.u'))->getUsingFormat('H:i');
            $endTime   = (new Alaress_Date_Format($timetabledClass['PeriodEnd'], 'H:i:s.u'))->getUsingFormat('H:i');

            foreach ($timetable as $x => $timetablePeriod) {
                // SchoolEdge has no actual period numbers?
                if ($timetablePeriod['periodStart'] == $startTime && $timetablePeriod['periodEnd'] == $endTime) {
                    $classCode = $timetabledClass['ClassCode'];
                    $dayNumber = $timetabledClass['DayNumber'];
                    if (!isset($timetable[$x]['day'][$dayNumber][$classCode])) {
                        $timetable[$x]['day'][$dayNumber][$classCode] = array(
                            'description'    => $timetabledClass['ClassName'],
                            'code'           => $timetabledClass['ClassCode'],
//                            'campus'         => $timetabledClass['ClassCampus'],
//                            'room'           => $timetabledClass['Room'],
//                            'staffID'        => $timetabledClass['StaffID'],
//                            'staffFirstname' => $timetabledClass['StaffPreferredName'],
//                            'staffSurname'   => $timetabledClass['StaffSurname'],
//                            'staffTitle'     => $timetabledClass['StaffTitle'],
                        );
                    }
                }
            }
        }
        return $timetable;
    }

    /**
     * @inheritdoc
     */
    public function getRoomTimetable($roomCodes)
    {
        return [];
    }
}
