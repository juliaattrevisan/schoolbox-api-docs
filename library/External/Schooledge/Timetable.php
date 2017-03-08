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
        // TODO add actual query
        $query = 'SELECT * FROM fc.Timetable WHERE 1 = 0';

        // Mostly copied from getFullTimetable
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus);

        // Build the timetable
        $timetable = [];
        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            $timetable[$x]['class']          = [];
        }
        foreach ($this->database->getAll($query) as $timetabledClass) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($timetablePeriod['periodNumber'] == $timetabledClass['PeriodNumber']) {
                    $classCode = $timetabledClass['ClassCode'];

                    // Add for both base and replacement staff
                    $room = isset($timetabledClass['ReplacementRoom'])
                        ? $timetabledClass['ReplacementRoom']
                        : $timetabledClass['BaseRoom'];
                    if ($timetabledClass['UseBaseStaff']) {
                        $timetable[$x]['class'][$classCode.'-'.$timetabledClass['BaseStaffID']] = [
                            'description'    => $timetabledClass['ClassName'],
                            'code'           => $timetabledClass['ClassCode'],
                            'campus'         => $timetabledClass['ClassCampus'],
                            'room'           => $room,
                            'staffID'        => $timetabledClass['BaseStaffID'],
                            'staffFirstname' => $timetabledClass['BaseStaffPreferredName'],
                            'staffSurname'   => $timetabledClass['BaseStaffSurname'],
                            'staffTitle'     => $timetabledClass['BaseStaffTitle'],
                        ];
                    }
                    if ($timetabledClass['UseReplacementStaff']) {
                        $timetable[$x]['class'][$classCode.'-'.$timetabledClass['ReplacementStaffID']] = [
                            'description'    => $timetabledClass['ClassName'],
                            'code'           => $timetabledClass['ClassCode'],
                            'campus'         => $timetabledClass['ClassCampus'],
                            'room'           => $room,
                            'staffID'        => $timetabledClass['ReplacementStaffID'],
                            'staffFirstname' => $timetabledClass['ReplacementStaffPreferredName'],
                            'staffSurname'   => $timetabledClass['ReplacementStaffSurname'],
                            'staffTitle'     => $timetabledClass['ReplacementStaffTitle'],
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
    public function getTimetableForDate($externalUserId, $userId, Campus $campus, Alaress_Date_Format $date)
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

        // Sub-parts common to all queries
        $classSelect = "
            t.Name                      AS ClassCampus,
            ts.Code                     AS ClassCode,
            ts.Name                     AS ClassName,
            td.SortOrder                AS DayNumber,
            tpe.Code                    AS PeriodNumber,
            td.Name                     AS DayName,
            CAST(tpe.StartTime AS TIME) AS PeriodStart,
            CAST(tpe.EndTime   AS TIME) AS PeriodEnd,
            tpe.Name                    AS PeriodName
        ";
        $baseStaffSelect = "
            CASE WHEN tcis.TimetableClassStaffID IS NULL
                THEN 1
                ELSE 0
            END                         AS UseBaseStaff,
            staff_base.Code             AS BaseStaffID,
            staff_base.FirstName        AS BaseStaffPreferredName,
            staff_base.LastName         AS BaseStaffSurname,
            staff_base.Title            AS BaseStaffTitle
        ";
        $replacementStaffSelect = "
            CASE WHEN staff_replacement.Code IS NOT NULL
                THEN 1
                ELSE 0
            END                         AS UseReplacementStaff,
            staff_replacement.Code      AS ReplacementStaffID,
            staff_replacement.FirstName AS ReplacementStaffPreferredName,
            staff_replacement.LastName  AS ReplacementStaffSurname,
            staff_replacement.Title     AS ReplacementStaffTitle
        ";
        $baseRoomSelect = "
            CASE WHEN tcir.TimetableClassRoomID IS NULL
                THEN 1
                ELSE 0
            END                         AS UseBaseRoom,
            room_base.Code              AS BaseRoom
        ";
        $replacementRoomSelect = "
            CASE WHEN room_replacement.Code IS NOT NULL
                THEN 1
                ELSE 0
            END                         AS UseReplacementRoom,
            room_replacement.Code       AS ReplacementRoom
        ";
        $timetableJoin = "INNER JOIN fc.Timetable t ON ts.TimetableID = t.ID";
        $baseStaffJoin = "
            LEFT  JOIN fc.TimetableClassStaff tcs        ON tc.ID        = tcs.TimetableClassId
            LEFT  JOIN dbo.Staff              staff_base ON tcs.PersonID = staff_base.ID
        ";
        $replacementStaffJoin = "
            LEFT  JOIN fc.TimetableClassInstanceStaff tcis              ON tci.ID        = tcis.TimetableClassInstanceId
            LEFT  JOIN dbo.Staff                      staff_replacement ON tcis.PersonID = staff_replacement.ID
        ";
        $baseRoomJoin = "
            LEFT  JOIN fc.TimetableClassRoom tcr       ON tc.ID      = tcr.TimetableClassId
            LEFT  JOIN fc.Room               tcrr      ON tcr.RoomID = tcrr.ID
            LEFT  JOIN dbo.Room              room_base ON tcrr.ID    = room_base.ID
        ";
        $replacementRoomJoin = "
            LEFT  JOIN fc.TimetableClassInstanceRoom tcir             ON tci.ID      = tcir.TimetableClassInstanceId AND tcir.RoomSpecified = 1
            LEFT  JOIN fc.Room                       tcirr            ON tcir.RoomID = tcirr.ID
            LEFT  JOIN dbo.Room                      room_replacement ON tcirr.ID    = room_replacement.ID
        ";
        $timetableFilter = "t.Name = {$quotedCampus} AND t.Enabled = 1";

        if ($user->getRole()->isStudent()) {
            // 1st part: blocked classes
            // 2nd part: non-blocked classes
            $query = "
                SELECT
                    $classSelect,
                    $baseStaffSelect,
                    $replacementStaffSelect,
                    $baseRoomSelect,
                    $replacementRoomSelect

                FROM       fc.TimetableSubject       ts
                -- Join onto student via subject choice
                INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
                INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID
                INNER JOIN fc.TimetableSubjectChoice tsc  ON tc.ID             = tsc.TimetableClassID
                INNER JOIN dbo.Student               s    ON tsc.PersonID      = s.ID
                -- Expand period
                INNER JOIN fc.TimetableBlockInstance tbi  ON tci.TimetableBlockInstanceID = tbi.ID
                INNER JOIN fc.TImetablePeriod        tp   ON tbi.TimetablePeriodID        = tp.ID
                CROSS APPLY fc.PeriodExpand(tbi.TimetablePeriodID, tbi.Consecutive) tpe
                INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID           = td.ID
                $baseStaffJoin
                $replacementStaffJoin
                $baseRoomJoin
                $replacementRoomJoin
                $timetableJoin

                WHERE s.Code = {$quotedId}
                AND   $timetableFilter

                UNION

                SELECT
                    $classSelect,
                    $baseStaffSelect,
                    $replacementStaffSelect,
                    $baseRoomSelect,
                    $replacementRoomSelect

                FROM       fc.TimetableSubject       ts
                -- Join onto student via year level
                INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
                INNER JOIN fc.YearLevelSub           yls  ON tc.YearLevelSubID = yls.ID
                INNER JOIN fc.YearLevelSubStudent    ylss ON yls.ID            = ylss.YearLevelSubID
                INNER JOIN dbo.Student               s    ON ylss.PersonID     = s.ID
                -- Expand period
                INNER JOIN fc.TimetableClassInstance tci  ON tc.ID                 = tci.TimetableClassID
                INNER JOIN fc.TImetablePeriod        tp   ON tci.TimetablePeriodID = tp.ID
                CROSS APPLY fc.PeriodExpand(tci.TimetablePeriodID, tci.Consecutive) tpe
                INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID    = td.ID
                $baseStaffJoin
                $replacementStaffJoin
                $baseRoomJoin
                $replacementRoomJoin
                $timetableJoin

                WHERE s.Code = {$quotedId}
                AND   tci.TimetableBlockInstanceID IS NULL
                AND   tci.TimetablePeriodID IS NOT NULL
                AND   $timetableFilter

                ORDER BY DayNumber asc, PeriodStart asc
            ";

        } elseif ($user->getRole()->isStaff()) {
            // Teacher query
            // Still requires a room
            // I'm guessing we smoosh the staff and student queries together somehow?
            $query = "
                SELECT
                    $classSelect,
                    $baseStaffSelect,
                    $replacementStaffSelect,
                    $baseRoomSelect,
                    $replacementRoomSelect

                FROM       fc.TimetableSubject       ts
                -- Join onto staff
                INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
                INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID
                -- Expand period
                INNER JOIN fc.TimetableBlockInstance tbi  ON tci.TimetableBlockInstanceID = tbi.ID
                INNER JOIN fc.TimetablePeriod        tp   ON tbi.TimetablePeriodID        = tp.ID
                CROSS APPLY fc.PeriodExpand(tbi.TimetablePeriodID, tbi.Consecutive) tpe
                INNER JOIN fc.TimetableDay           td   ON tpe.TimetableDayID           = td.ID
                $baseStaffJoin
                $replacementStaffJoin
                $baseRoomJoin
                $replacementRoomJoin
                $timetableJoin

                WHERE (
                    (staff_base.Code = {$quotedId} AND tcis.TimetableClassStaffID IS NULL)
                    OR
                    staff_replacement.Code = {$quotedId}
                )
                AND $timetableFilter

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
                        // Add for both base and replacement staff
                        $room = isset($timetabledClass['ReplacementRoom'])
                            ? $timetabledClass['ReplacementRoom']
                            : $timetabledClass['BaseRoom'];
                        if ($timetabledClass['UseBaseStaff']) {
                            $timetable[$x]['day'][$dayNumber][$classCode.'-'.$timetabledClass['BaseStaffID']] = [
                                'description'    => $timetabledClass['ClassName'],
                                'code'           => $timetabledClass['ClassCode'],
                                'campus'         => $timetabledClass['ClassCampus'],
                                'room'           => $room,
                                'staffID'        => $timetabledClass['BaseStaffID'],
                                'staffFirstname' => $timetabledClass['BaseStaffPreferredName'],
                                'staffSurname'   => $timetabledClass['BaseStaffSurname'],
                                'staffTitle'     => $timetabledClass['BaseStaffTitle'],
                            ];
                        }
                        if ($timetabledClass['UseReplacementStaff']) {
                            $timetable[$x]['day'][$dayNumber][$classCode.'-'.$timetabledClass['ReplacementStaffID']] = [
                                'description'    => $timetabledClass['ClassName'],
                                'code'           => $timetabledClass['ClassCode'],
                                'campus'         => $timetabledClass['ClassCampus'],
                                'room'           => $room,
                                'staffID'        => $timetabledClass['ReplacementStaffID'],
                                'staffFirstname' => $timetabledClass['ReplacementStaffPreferredName'],
                                'staffSurname'   => $timetabledClass['ReplacementStaffSurname'],
                                'staffTitle'     => $timetabledClass['ReplacementStaffTitle'],
                            ];
                        }
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
