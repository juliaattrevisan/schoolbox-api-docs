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
     * Timetable date type constant - this date is not in the sequence.
     *
     * @var int
     */
    const TIMETABLE_DATE_TYPE_NON_DAY = 10000;

    /**
     * Timetable date type constant - this day is forced to a particular day in the sequence.
     *
     * @var int
     */
    const TIMETABLE_DATE_TYPE_FIXED_DAY = 10003;

    /**
     * Timetable date type constant - the timetable restarts on this day.
     *
     * @var int
     */
    const TIMETABLE_DATE_TYPE_RESTART_DAY = 10002;

    /**
     * Timetable date type constant - this day is in the sequence, but is skipped?
     *
     * @var int
     */
    const TIMETABLE_DATE_TYPE_SKIP_DAY = 10001;

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
        $result   = $this->database->getAll("
            SELECT
                tp.Name                    AS Name,
                CAST(tp.StartTime AS TIME) AS StartTime,
                CAST(tp.EndTime   AS TIME) AS EndTime,
                td.SortOrder               AS DayNumber,
                tp.Code                    AS PeriodNumber

            FROM       fc.Timetable       t
            INNER JOIN fc.TimetableDay    td ON t.ID  = td.TimetableId
            INNER JOIN fc.TimetablePeriod tp ON td.ID = tp.TimetableDayId

            WHERE t.Name       = {$this->database->smartQuote($campus->getExtTimetableCode())}
            AND   t.Enabled    = 1
            AND   td.SortOrder = {$this->database->smartQuote($day)}

            ORDER BY DayNumber ASC, StartTime ASC, EndTime ASC
        ");

        // Key result by period number
        $periods = array_combine(
            array_column($result, 'PeriodNumber'),
            $result
        );

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
            $definition['number'][]   = $period['PeriodNumber'];
            $definition['sequence'][] = 1;
        }
        return $definition;
    }

    /**
     * @inheritdoc
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        $timetableDate = $this->getTimetableDate($campus, $this->getDateObjectFromTimestamp($date));
        if (isset($timetableDate)) {
            return $timetableDate['SortOrder'];
        }
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function getDayName($dayNumber)
    {
        // Kind of requires a campus, be this method doesn't have one.
        return $this->database->getOne("
            SELECT DISTINCT td.Name
            FROM       fc.Timetable       t
            INNER JOIN fc.TimetableDay    td ON t.ID  = td.TimetableId
            WHERE t.Enabled = 1
            AND   td.SortOrder = {$this->database->quote($dayNumber)}
        ");
    }

    /**
     * @inheritdoc
     */
    public function getPeriodNumber(Campus $campus, $now = 0, $user = null)
    {
        $dateObject    = $this->getDateObjectFromTimestamp($now);
        $timetableDate = $this->getTimetableDate($campus, $dateObject);
        if (!isset($timetableDate)) {
            return [];
        }

        // SchoolEdge doesn't really have period numbers as such - use period name.
        $quotedTime      = $this->database->smartQuote($dateObject->getDatabaseTime());
        $timetablePeriod = $this->database->getRow($q="
            SELECT tp.*
            FROM fc.TimetablePeriod tp

            WHERE tp.TimetableDayID = {$this->database->smartQuote($timetableDate['TimetableDay']['ID'])}
            AND   CAST(tp.StartTime AS TIME) <= $quotedTime
            AND   CAST(tp.EndTime   AS TIME) >  $quotedTime
        ");
        if (empty($timetablePeriod)) {
            return [];
        }
        return [
            'PeriodNumber'    => $timetablePeriod['Code'],
            'PeriodNumberSeq' => $timetablePeriod['Code'],
        ];
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
                    t.Name                      AS ClassCampus,
                    ts.Code                     AS ClassCode,
                    ts.Name                     AS ClassName,
                    td.SortOrder                AS DayNumber,
                    tpe.Code                    AS PeriodNumber,
                    td.Name                     AS DayName,
                    CAST(tpe.StartTime AS TIME) AS PeriodStart,
                    CAST(tpe.EndTime   AS TIME) AS PeriodEnd,
                    tpe.Name                    AS PeriodName

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
                    t.Name                      AS ClassCampus,
                    ts.Code                     AS ClassCode,
                    ts.Name                     AS ClassName,
                    td.SortOrder                AS DayNumber,
                    tpe.Code                    AS PeriodNumber,
                    td.Name                     AS DayName,
                    CAST(tpe.StartTime AS TIME) AS PeriodStart,
                    CAST(tpe.EndTime   AS TIME) AS PeriodEnd,
                    tpe.Name                    AS PeriodName

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
                    t.Name                      AS ClassCampus,
                    ts.Code                     AS ClassCode,
                    ts.Name                     AS ClassName,
                    td.SortOrder                AS DayNumber,
                    tpe.Code                    AS PeriodNumber,
                    td.Name                     AS DayName,
                    CAST(tpe.StartTime AS TIME) AS PeriodStart,
                    CAST(tpe.EndTime   AS TIME) AS PeriodEnd,
                    tpe.Name                    AS PeriodName

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
            foreach ($timetable as $x => $timetablePeriod) {
                if ($timetablePeriod['periodNumber'] == $timetabledClass['PeriodNumber']) {
                    $classCode = $timetabledClass['ClassCode'];
                    $dayNumber = $timetabledClass['DayNumber'];
                    if (!isset($timetable[$x]['day'][$dayNumber][$classCode])) {
                        // TODO add room and staff back
                        $timetable[$x]['day'][$dayNumber][$classCode] = [
                            'description'    => $timetabledClass['ClassName'],
                            'code'           => $timetabledClass['ClassCode'],
                            'campus'         => $timetabledClass['ClassCampus'],
//                            'room'           => $timetabledClass['Room'],
//                            'staffID'        => $timetabledClass['StaffID'],
//                            'staffFirstname' => $timetabledClass['StaffPreferredName'],
//                            'staffSurname'   => $timetabledClass['StaffSurname'],
//                            'staffTitle'     => $timetabledClass['StaffTitle'],
                        ];
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


    // Helper methods


    /**
     * Gets dates for a timetable
     *
     * @param Campus $campus
     * @return array
     */
    protected function getTimetableDates(Campus $campus)
    {
        // Adapted from some C# code SchoolEdge gave us
        $quotedCampus   = $this->database->smartQuote($campus->getExtTimetableCode());
        $timetable      = $this->database->getRow("
            SELECT
                CAST(t.StartDate AS DATE) AS StartDate,
                CAST(t.EndDate   AS DATE) AS EndDate
            FROM fc.Timetable t
            WHERE t.Name = {$quotedCampus}
        ");
        if (!$timetable) {
            return [];
        }

        // All timetable days
        $timetableDays  = $this->database->getAll("
            SELECT td.*
            FROM       fc.TimetableDay td
            INNER JOIN fc.Timetable    t  ON td.TimetableID    = t.ID
            WHERE t.Name = {$quotedCampus}
            ORDER BY td.SortOrder ASC
        ");

        // All dates within the timetable
        $timetableDates = $this->database->getAll("
            SELECT
                CAST(td.DateValue AS DATE) AS TimetableDate,
                td.TimetableDateTypeID,
                td.TimetableDayID
            FROM       fc.TimetableDate td
            INNER JOIN fc.Timetable     t  ON td.TimetableID    = t.ID
            WHERE t.Name = {$quotedCampus}
            ORDER BY TimetableDate ASC
        ");
        $timetableDates = array_combine(
            array_column($timetableDates, 'TimetableDate'),
            $timetableDates
        );

        $startDate = new Alaress_Date_Format($timetable['StartDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $endDate   = new Alaress_Date_Format($timetable['EndDate'], Alaress_Date_Format::DATABASE_DATE_FORMAT);

        // Fill in missing timetable dates
        for ($currentDate = clone $startDate; $currentDate->lte($endDate); $currentDate = $currentDate->addDays(1)) {
            $dateString = $currentDate->getDatabaseDate();
            if (!isset($timetableDates[$dateString])) {
                $timetableDates[$dateString] = [
                    'TimetableDate'       => $dateString,
                    'TimetableDateTypeID' => null,
                    'TimetableDayID'      => null,
                ];
            }
        }

        // Sort by key for clarity
        ksort($timetableDates);

        // Add timetable days
        $dayIndex = -1;
        foreach ($timetableDates as &$timetableDate) {
            $keepIndex = $dayIndex;
            $dayIndex++;
            if ($dayIndex > (count($timetableDays) - 1)) {
                $dayIndex = 0;
            }
            $timetableDay   = $timetableDays[$dayIndex];
            $timetableDayId = $timetableDay['ID'];

            switch ($timetableDate['TimetableDateTypeID']) {
                case self::TIMETABLE_DATE_TYPE_NON_DAY:
                    // Non-day - increment the keep index?
                    $dayIndex = $keepIndex;
                    break;

                case self::TIMETABLE_DATE_TYPE_SKIP_DAY:
                    break;

                case self::TIMETABLE_DATE_TYPE_RESTART_DAY:
                    foreach ($timetableDays as $i => $timetableDay) {
                        if ($timetableDay['ID'] == $timetableDate['TimetableDayID']) {
                            $dayIndex = $i;
                            break;
                        }
                    }
                    break;

                case self::TIMETABLE_DATE_TYPE_FIXED_DAY:
                    break;

                default:
                    $timetableDate['TimetableDayID'] = $timetableDayId;
                    break;
            }

            // Set timetable day fully
            $timetableDate['TimetableDay'] = isset($timetableDate['TimetableDayID'])
                ? $timetableDay
                : null;
            unset($timetableDate['TimetableDayID']);
        }
        unset($timetableDate);

        return $timetableDates;
    }

    /**
     * Extract date object from timestamp.
     *
     * @param  int $timestamp
     * @return Alaress_Date_Format
     */
    protected function getDateObjectFromTimestamp($timestamp)
    {
        return $timestamp
            ? new Alaress_Date_Format($timestamp, Alaress_Date_Format::TIMESTAMP)
            : Alaress_Date_Format::now();
    }

    /**
     * Fetches the timetable date for a given day
     *
     * @param Campus              $campus
     * @param Alaress_Date_Format $dateObject
     * @return array the timetable date, or null if it doesn't exist
     */
    protected function getTimetableDate(Campus $campus, Alaress_Date_Format $dateObject)
    {
        $timetableDates = $this->getTimetableDates($campus);
        $key            = $dateObject->getDatabaseDate();
        return array_key_exists($key, $timetableDates)
            ? $timetableDates[$key]
            : null;
    }
}
